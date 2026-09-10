<?php

namespace App\Http\Controllers\Guia;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\FacturacionEnvio;
use App\Models\GuiaEstado;
use App\Models\GuiaSalida;
use App\Models\GuiaSalidaDetalle;
use App\Models\Parametro;
use App\Models\Serie;
use App\Models\User;
use Exception;
use Faker\Provider\UserAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Domain\Shared\ValueObjects\Igv;
use App\Http\Requests\Guia\AgregarItemRequest;
use Illuminate\Support\Facades\DB;
use Luecano\NumeroALetras\NumeroALetras;
use Illuminate\Support\Str;
use Normalizer;
use Illuminate\Support\Facades\Log;


class GuiaSalidaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // $list = GuiaSalida::where('activo',1)->get();
        // dd($list);
        return view('guia.salida.index');
    }

    public function listar(Request $request)
    {
        // Igual que en Ingreso: devolvia HTML dentro de una vista parcial.
        // Ahora devuelve datos.
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin    = $request->input('fecha_fin');
        $serie       = $request->input('serie');
        $numero      = $request->input('numero');

        $consulta = DB::table('guia_salidas')
            ->whereBetween('fecha_emision', [$fechaInicio, $fechaFin])
            ->where('activo', 1);

        if ($serie !== null && $serie !== '') {
            $consulta = $consulta->where('serie', $serie);
        }
        if ($numero !== null && $numero !== '') {
            $consulta = $consulta->where('numero', $numero);
        }

        $list = $consulta->orderBy('fecha_emision', 'desc')->orderBy('id', 'desc')->get();

        // Una sola consulta para los estados. Antes era GuiaEstado::find()
        // dentro del bucle, una por fila.
        $estados = GuiaEstado::pluck('nombre', 'id');

        // El estado en SUNAT ya no se consulta aqui: la tabla se pinta con lo
        // que hay en la base y el navegador pide el refresco aparte, contra
        // guiasalida.estadosSunat. Ver el comentario de ese metodo.
        $guias = $list->map(function ($g) use ($estados) {
            return $this->filaListado($g, $estados);
        })->values();

        return response()->json(['procede' => true, 'guias' => $guias]);
    }

    /**
     * Arma una fila del listado.
     *
     * Vive aparte porque el refresco de estados devuelve exactamente la misma
     * forma: cuando una guia pasa de Generada a Aceptada no solo cambia el
     * texto del estado, tambien cambian los botones (deja de ofrecerse
     * "Reenviar al facturador"). Si el refresco devolviera solo el estado, esas
     * reglas habria que repetirlas en JavaScript y se desincronizarian.
     */
    private function filaListado($g, $estados): array
    {
        $razonSocial = ((int) $g->indicar_proveedor === 0)
            ? $g->cliente_razon_social
            : $g->proveedor_nombre;

        $urlPdf = ((int) $g->envio_sunat === 1)
            ? route('guiasalida.pdfDecode', ['guia' => $g->id])
            : route('guiasalida.pdf', ['guia' => $g->id, 'valorada' => 0]);

        $estadoId = (int) $g->guia_estado_id;

        return [
            'id'           => $g->id,
            'documento'    => $g->serie . '-' . $g->numero,
            'serie'        => $g->serie,
            'numero'       => (int) $g->numero,
            'razonSocial'  => $razonSocial,
            'fechaEmision' => $g->fecha_emision,
            'totalVenta'   => (float) $g->total_venta,
            'envioSunat'   => (int) $g->envio_sunat === 1,
            'guiaEstadoId' => $estadoId,
            'estadoNombre' => $estados[$g->guia_estado_id] ?? '',

            // Le dice al navegador cuales vale la pena preguntar al facturador:
            // solo las emitidas a SUNAT que siguen sin respuesta.
            'estadoPendiente' => $estadoId === 1 && (int) $g->envio_sunat === 1,

            'mostrarAnular'            => in_array($estadoId, [1, 2, 3, 5], true),
            'mostrarGuardarDatamarket' => (int) $g->enviado_datamarket !== 1,
            'mostrarContinuar'         => $estadoId === 4,
            'verReintentoFacturador'   => (int) $g->envio_sunat === 1
                                          && (int) $g->enviado_facturador === 0
                                          && $estadoId === 1,

            'urlPdf'         => $urlPdf,
            'urlPdfValorada' => route('guiasalida.pdf', ['guia' => $g->id, 'valorada' => 1]),
            'urlContinuar'   => route('guiasalida.continuar', ['guia' => $g->id]),
        ];
    }

    /** Segundos que se da por bueno el ultimo estado consultado al facturador. */
    private const ESTADO_SUNAT_VIGENCIA = 120;

    /** Guias que se consultan como maximo por peticion (una pagina del listado). */
    private const ESTADO_SUNAT_MAXIMO = 50;

    /** Consultas simultaneas contra el facturador. */
    private const ESTADO_SUNAT_SIMULTANEAS = 5;

    /**
     * Refresca contra el facturador el estado de las guias que el usuario tiene
     * a la vista y devuelve solo las filas que cambiaron.
     *
     * POR QUE ESTA SEPARADO DEL LISTADO
     *   Antes esto ocurria dentro de listar(), una peticion HTTP por guia y en
     *   serie: 25 guias por pagina eran 25 llamadas encadenadas antes de pintar
     *   la primera fila (medido: 3.85 s contra un servicio que responde en
     *   150 ms; con la latencia real del facturador es peor). La tabla no puede
     *   depender de que SUNAT conteste.
     *
     * POR QUE NO UNA COLA
     *   Esto se instala on-premise en el servidor del cliente: no hay Redis, ni
     *   supervisor, ni nadie que arranque `queue:work`. Un job encolado que
     *   nadie consume deja los estados congelados para siempre y sin sintoma
     *   visible. El navegador del usuario, en cambio, siempre esta ahi.
     *
     * POR QUE NO UNA SOLA LLAMADA AGRUPADA
     *   El endpoint del facturador (parametro 9) recibe un unico serienumero
     *   por peticion; no existe consulta por lote y es un servicio de terceros.
     *   Lo que si se puede es dejar de esperarlas de una en una: se lanzan en
     *   grupos simultaneos.
     *
     * Ademas se anota cuando se consulto cada guia, porque el listado se
     * recarga entero despues de cada accion (anular, reenviar) y sin esa marca
     * se repetirian las mismas consultas cada pocos segundos.
     */
    public function estadosSunat(Request $request)
    {
        $ids = collect((array) $request->input('ids', []))
            ->map(function ($id) { return (int) $id; })
            ->filter()
            ->unique()
            ->take(self::ESTADO_SUNAT_MAXIMO)
            ->values();

        if ($ids->isEmpty()) {
            return response()->json(['procede' => true, 'guias' => []]);
        }

        $urlConsulta = Parametro::find(9)->valor ?? '';
        $rucEntidad  = Parametro::find(2)->valor ?? '';

        if (trim((string) $urlConsulta) === '') {
            return response()->json(['procede' => true, 'guias' => []]);
        }

        $vigenteDesde = Carbon::now()->subSeconds(self::ESTADO_SUNAT_VIGENCIA);

        $pendientes = DB::table('guia_salidas')
            ->whereIn('id', $ids->all())
            ->where('activo', 1)
            ->where('guia_estado_id', 1)
            ->where('envio_sunat', 1)
            ->where(function ($q) use ($vigenteDesde) {
                $q->whereNull('estado_sunat_consultado_at')
                  ->orWhere('estado_sunat_consultado_at', '<', $vigenteDesde);
            })
            ->get();

        if ($pendientes->isEmpty()) {
            return response()->json(['procede' => true, 'guias' => []]);
        }

        // Codigo de SUNAT -> id en la tabla guia_estados.
        $mapa = ['A' => 2, 'B' => 3, 'O' => 5];

        $cambiadas = [];

        foreach ($pendientes->chunk(self::ESTADO_SUNAT_SIMULTANEAS) as $grupo) {
            $respuestas = $this->consultarEstadosEnLote($grupo, $urlConsulta, $rucEntidad);

            foreach ($grupo as $g) {
                $cuerpo = $respuestas[(string) $g->id] ?? null;

                // Se marca como consultada incluso cuando el facturador falla:
                // si esta caido, reintentar en cada recarga solo suma esperas.
                DB::table('guia_salidas')
                    ->where('id', $g->id)
                    ->update(['estado_sunat_consultado_at' => Carbon::now()]);

                if (! $cuerpo || ! isset($cuerpo->estado) || $cuerpo->estado === null) {
                    continue;
                }

                $codigo = strtoupper(trim((string) $cuerpo->estado));

                if (! isset($mapa[$codigo]) || $mapa[$codigo] === (int) $g->guia_estado_id) {
                    continue;
                }

                DB::table('guia_salidas')
                    ->where('id', $g->id)
                    ->update([
                        'guia_estado_id'       => $mapa[$codigo],
                        'mensaje_estado_sunat' => $cuerpo->mensaje ?? null,
                        'updated_at'           => Carbon::now(),
                    ]);

                $g->guia_estado_id = $mapa[$codigo];
                $cambiadas[] = $g;
            }
        }

        if (empty($cambiadas)) {
            return response()->json(['procede' => true, 'guias' => []]);
        }

        $estados = GuiaEstado::pluck('nombre', 'id');

        $filas = array_map(function ($g) use ($estados) {
            return $this->filaListado($g, $estados);
        }, $cambiadas);

        return response()->json(['procede' => true, 'guias' => $filas]);
    }

    /**
     * Lanza las consultas de un grupo a la vez y devuelve el cuerpo de cada una
     * indexado por id de guia.
     *
     * El timeout corto es deliberado: un facturador colgado no puede dejar al
     * navegador esperando el minuto por defecto de Guzzle.
     */
    private function consultarEstadosEnLote($grupo, string $urlConsulta, $rucEntidad): array
    {
        try {
            $respuestas = Http::pool(function ($pool) use ($grupo, $urlConsulta, $rucEntidad) {
                $peticiones = [];

                foreach ($grupo as $g) {
                    $peticiones[] = $pool->as((string) $g->id)
                        ->timeout(10)
                        ->post($urlConsulta, [
                            'rucremitente' => (string) $rucEntidad,
                            'serienumero'  => 'T' . str_pad($g->serie, 3, '0', STR_PAD_LEFT) . '-' . $g->numero,
                        ]);
                }

                return $peticiones;
            });
        } catch (\Throwable $e) {
            Log::error('Error consultando estados SUNAT en lote', ['mensaje' => $e->getMessage()]);
            return [];
        }

        $cuerpos = [];

        foreach ($respuestas as $clave => $respuesta) {
            // Http::pool no lanza: la excepcion de la peticion que fallo llega
            // en el array, en el sitio de su respuesta.
            if (! $respuesta instanceof \Illuminate\Http\Client\Response) {
                Log::error('Error consultando estado SUNAT', [
                    'guia_id' => $clave,
                    'mensaje' => $respuesta instanceof \Throwable ? $respuesta->getMessage() : 'respuesta no valida',
                ]);
                continue;
            }

            $cuerpos[(string) $clave] = $respuesta->object();
        }

        return $cuerpos;
    }

    public function getSerie(Request $request)
    {
        $api_datos = Parametro::find(6)->valor;
        $serie = $request->post('serie');
        // dd($request->post());
        $listSeries = Http::get("{$api_datos}/obtenerSeriesNumerosGuia")->object()->serienumeros;

        foreach ($listSeries as $item) {
            if ($serie == $item->numserie) {
                $getSerie = $item;
            }
        }


        // dd($getSerie);
        $serieLocal = Serie::where('serie', $getSerie->numserie)->first();
        // dd($serieLocal);

        if ($serieLocal == null) {
            $getSerie->nuevo_numero = str_pad(($getSerie->ultimoValormarket + 1), 4, "0", STR_PAD_LEFT);
        }

        if ($serieLocal != null) {
            $getSerie->nuevo_numero = str_pad(($serieLocal->numero + 1), 4, "0", STR_PAD_LEFT);

        }

        return response()->json(['getSerie' => $getSerie]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $api_datos = Parametro::find(6)->valor;

        $listProveedores = [];
        // dd(count($listProveedores));
        // $listFormasPago = Http::post(route('simulacion.ObtenerFormasPago'), [])->object();
        $listFormasPago = Http::get("{$api_datos}/ObtenerFormasPago")->object()->formasdePago;
        // $listTipoOperacion = Http::post(route('simulacion.ObtenerOperaciones'), [])->object();
        $listTipoOperacion = Http::get("{$api_datos}/ObtenerOperacion")->object()->operaciones;
        // $listTipoOperacion = [];
        // dd($listTipoOperacion);
        foreach ($listTipoOperacion as $key => $value) {
            $selected = "";
            if (trim($value->tipoOperacion) == 12) {//SALIDA POR TRANSFERENCIA DE ALMACEN
                $selected = "selected";
            }
            $listTipoOperacion[$key]->selected = $selected;
        }
        // $listAlmacenes = Http::post(route('simulacion.ObtenerAlmacenes'), [])->object();
        $listAlmacenes = Http::get("{$api_datos}/ObtenerAlmacenes")->object()->almacenes;
        $listAlmacenOrigen = Http::get("{$api_datos}/ObtenerAlmacenes")->object()->almacenes;
        $listAlmacenDestino = Http::get("{$api_datos}/ObtenerAlmacenes")->object()->almacenes;
        // dd($listAlmacenes);
        $listPrecios = Http::get("{$api_datos}/ObtenerSucursalPrecio")->object()->listasPrecio;
        // $listVendedores = Http::get("{$api_datos}/ObtenerTrabajador?CodigoTrabajador=-1")->object()->trabajador;
        $listVendedores = [];
        // dd($listVendedores);
        // $getVendedor = $listVendedores[0];
        // dd($getVendedor);
        // dd($getVendedor);
        // dd($listAlmacenes);
        // $listArticulos = Http::post(route('simulacion.ObtenerArticulos'), [])->object();
        $listArticulos = array();
        // $listClientes = Http::post(route('simulacion.ObtenerClientes'), [])->object();
        $listClientes = [];
        // dd($listClientes);
        $listVehiculos = Http::post("{$api_datos}/ObtenerVehiculo", ['valor' => '', 'tipo' => 4])->object()->vehiculos;
        // dd($listVehiculos);
        $listChoferes = Http::post("{$api_datos}/ObtenerChoferes", ['nombrechofer' => ''])->object()->choferes;
        // dd($listChoferes);

        $listSeries = Http::get("{$api_datos}/obtenerSeriesNumerosGuia")->object()->serienumeros;
        // dd($listSeries);
        // Los ubigeos ya no se precargan aqui: eran siete llamadas a la API en
        // cada apertura de la pantalla para llenar unos <select> que ahora se
        // pintan desde el componente, que pide solo el nivel que necesita.
        $verChofer = 'display: none';
        $verVehiculo = 'display: none';

        $validar_stock = Parametro::find(10)->valor;
        // dd($validar_stock);
        $clienteTransferencia = (object) array();

        $valor_cliente_transferencia = Parametro::find(2)->valor;

        try {

            $getClienteTransferencia = Http::post("{$api_datos}/obtenerCliente",
            ['valor' => $valor_cliente_transferencia, 'tipo' => 2])
            ->object()->cliente;
            $clienteTransferencia = $getClienteTransferencia[0];
        } catch (Exception $e) {
            // Antes esto era un dd(): si el catalogo de clientes venia vacio,
            // la pantalla entera moria mostrando texto crudo sobre fondo negro.
            // Un catalogo ausente no debe impedir registrar una guia.
            Log::warning('No se pudo obtener el cliente de transferencia: ' . $e->getMessage());
            $clienteTransferencia = null;
        }

        // dd($clienteTransferencia);


        $lineasDetalle = [];

        return view('guia.salida.create', compact('lineasDetalle', 'listSeries','listProveedores', 'listFormasPago', 'listTipoOperacion', 'listPrecios', 'listAlmacenes', 'listArticulos', 'listClientes', 'listVendedores', 'listVehiculos', 'listChoferes', 'listAlmacenOrigen', 'listAlmacenDestino', 'verChofer', 'verVehiculo', 'validar_stock', 'clienteTransferencia'));
    }

    public function continuar(GuiaSalida $guia)
    {
        $api_datos = Parametro::find(6)->valor;

        $listProveedores = [];
        if ($guia->proveedor_id != null) {
            $listProveedores = Http::post("{$api_datos}/ObtenerProveedores", ['valor' => $guia->proveedor_id, 'tipo' => 1])->object()->proveedores;

        }

        $listFormasPago = Http::get("{$api_datos}/ObtenerFormasPago")->object()->formasdePago;

        $listTipoOperacion = Http::get("{$api_datos}/ObtenerOperacion")->object()->operaciones;

        if (count($listTipoOperacion) > 0 ) {
            foreach ($listTipoOperacion as $key => $item) {
                $selected = "";
                if ($item->tipoOperacion == $guia->tipo_operacion_id) {
                    $selected = "selected";
                }

                $listTipoOperacion[$key]->selected = $selected;
            }
        }
        // dd($listTipoOperacion);

        $listAlmacenes = Http::get("{$api_datos}/ObtenerAlmacenes")->object()->almacenes;
        $listAlmacenOrigen = Http::get("{$api_datos}/ObtenerAlmacenes")->object()->almacenes;
        $listAlmacenDestino = Http::get("{$api_datos}/ObtenerAlmacenes")->object()->almacenes;

        foreach ($listAlmacenes as $key => $value) {
            $selected = "";
            if ($value->codAlmacen == $guia->codalmacen) {
                $selected = "selected";
            }
            $listAlmacenes[$key]->selected = $selected;
        }
        foreach ($listAlmacenOrigen as $key => $value) {
            $selected = "";
            if ($value->codAlmacen == $guia->cod_almacen_origen) {
                $selected = "selected";
            }
            $listAlmacenOrigen[$key]->selected = $selected;
        }

        foreach ($listAlmacenDestino as $key => $value) {
            $selected = "";
            if ($value->codAlmacen == $guia->cod_almacen_destino) {
                $selected = "selected";
            }
            $listAlmacenDestino[$key]->selected = $selected;
        }

        $listPrecios = Http::get("{$api_datos}/ObtenerSucursalPrecio")->object()->listasPrecio;
        $listVendedores = Http::get("{$api_datos}/ObtenerTrabajador?CodigoTrabajador=-1")->object()->trabajador;

        $listArticulos = array();

        $listClientes = Http::post("{$api_datos}/obtenerCliente", ['valor' => $guia->cliente_id, 'tipo' => 1])->object()->cliente;
        // dd($listClientes);

        foreach ($listClientes as $key => $item) {
            $tipo_documento = $item->tipoDocumentoIdentidad;
            $nro_documento = $item->dni;
            if ($tipo_documento == '') {
                $nro_documento = trim($item->rucCliente);
            }
            $listClientes[$key]->texto_cliente = "[{$nro_documento}] {$item->razonSocial}";
        }


        $listVehiculos = Http::post("{$api_datos}/ObtenerVehiculo", ['valor' => '', 'tipo' => 4])->object()->vehiculos;
        $listChoferes = Http::post("{$api_datos}/ObtenerChoferes", ['nombrechofer' => ''])->object()->choferes;

        $listSeries = Http::get("{$api_datos}/obtenerSeriesNumerosGuia")->object()->serienumeros;
        foreach ($listSeries as $key => $item) {
            $selected = "";
            if ($item->numserie == $guia->serie ) {
                $selected = "selected";
            }
            $listSeries[$key]->selected = $selected;
        }
        // dd($listSeries);

        foreach ($listVendedores as $key => $value) {
            $selected = "";
            if ($value->codTrabajador == $guia->vendedor_id) {
                $selected = "selected";
            }

            $listVendedores[$key]->selected = $selected;
        }


        $listTransportistas = Http::post("{$api_datos}/ObtenerTransportista", ['valor' => $guia->transportista_ruc, 'tipo' => 3])->object()->transportistas;
        foreach ($listTransportistas as $key => $value) {
            $listTransportistas[$key]->texto_transportista = "[{$value->rucTransportista}] {$value->nombreTransportista}";
        }
        // dd($listTransportistas);

        // dd($guia);

        // ubigeos de partida
        // Los ubigeos guardados de la guia se le pasan al componente en
        // `ubigeoInicial` (ver el x-data de la vista) y el rehidrata la cascada
        // pidiendo solo los dos niveles que hacen falta. Antes eran seis
        // llamadas a la API aqui, para llenar <select> que ya no existen.

        $lineasDetalle = $this->lineasParaVista($detalle);

        return view('guia.salida.create', compact('lineasDetalle', 'guia', 'detalle','listSeries','listProveedores', 'listFormasPago', 'listTipoOperacion', 'listPrecios', 'listAlmacenes', 'listArticulos', 'listClientes', 'listVendedores', 'listVehiculos', 'listChoferes', 'listTransportistas','listAlmacenOrigen', 'listAlmacenDestino', 'verChofer', 'verVehiculo'));
    }

    public function getVendedor(Request $request)
    {
        // Esto devolvia un bloque de <option> que el JS insertaba con .html().
        // Los atributos iban entre comillas simples, asi que un apellido con
        // apostrofe ("O'BRIEN") cortaba la opcion y el vendedor se quedaba sin
        // nombre; y como el nombre viajaba en un data-*, el resto del codigo
        // tenia que volver a leerlo del DOM. Ahora devuelve datos.
        $api_datos = Parametro::find(6)->valor;

        $vendedor_codigo = $request->post('vendedor_codigo');

        $trabajadores = Http::get("{$api_datos}/ObtenerTrabajador?CodigoTrabajador={$vendedor_codigo}")->object()->trabajador ?? [];

        return response()->json([
            'vendedores' => \App\Support\VendedorVista::lista($trabajadores),
        ]);
    }

    // Aqui estaba formBusquedaArticulo(), que devolvia el <input> o el <select>
    // del buscador como string para que el JS lo pusiera con innerHTML. El
    // buscador ya se pinta en el Blade y cambia de modo con x-model: el
    // endpoint se quedo sin consumidores y su ruta tambien se elimino.

    public function listarArticulos(Request $request)
    {
        $api_datos = Parametro::find(6)->valor;

        // La ruta es GET; post('tipo') siempre devolvia null, asi que el tipo
        // de consulta nunca llegaba a la API. Mismo bug que en Guia de Ingreso.
        $valor          = trim((string) $request->input('term', ''));
        $tipoconsulta   = (int) $request->input('tipo_busqueda_articulo', $request->input('tipo', 1));
        $codestacion    = $request->input('codestacion', 1);
        $codalmacen     = $request->input('codalmacen', 1);
        $codlistaprecio = $request->input('codlistaprecio', 1);
        // $indicar_proveedor = $request->get('indicar_proveedor');
        $indicar_proveedor = ($request->get('indicar_proveedor') == 'true') ? true : false;
        $maximo = 0;

        // dd($indicar_proveedor);

        if ($tipoconsulta == 4) {
            $maximo = 2;
        }

        if (strlen($valor) > $maximo) {
            $listArticulos = Http::post("{$api_datos}/ObtenerArticulo",
                ['valor' => $valor, 'tipoconsulta' => $tipoconsulta, 'codestacion' => $codestacion, 'codalmacen' => $codalmacen, 'codlistaprecio' => $codlistaprecio]
            )->object()->articulos;

        }

        // dd($listArticulos);
        $items = array();
        foreach (($listArticulos ?? []) as $item) {
            $afecto = 1;
            $stock = $item->stock ?? 0;
            $precioPublico = $item->precioPublico;
            $precioSinIGV = $item->precioSinIGV;
            if ($indicar_proveedor == true) {
                $precioSinIGV = $item->costoArticulo;
                $precioPublico = number_format(($item->costoArticulo * (1+0.18)),2);
                $precioPublico = number_format($precioPublico,2);
                $precioSinIGV = number_format($precioSinIGV,2);
            }
            if ($item->tipoIgv != 1) {
                $afecto = 0;

                $precioPublico = number_format($precioSinIGV,2);
                $precioSinIGV = number_format($precioSinIGV,2);

            }

            $items[] = (object) array(

                'id' => $item->codArticulo,
                'text' => "[{$item->codBarra}] {$item->nombreArticulo} - unidad:({$item->descUnidadMedida}) - stock {$stock}  ",
                'codigo_barra' => $item->codBarra,
                'descripcion' => $item->nombreArticulo,
                'precio_publico' => $precioPublico,
                'precio_sin_igv' => $precioSinIGV,
                'peso' => $item->peso ?? 0,
                'cod_unidad' => $item->codUnidad,
                'desc_unidad_medida' => $item->descUnidadMedida ?? '',
                'sigla_umfe' => $item->siglaUMFE ?? '',
                'stock' => $item->stock ?? 0,
                'costo_articulo' => $item->costoArticulo,
                'afecto' => $afecto
            );

        }

        return response()->json(['items' => $items]);
    }

    public function buscarArticuloBarra(Request $request)
    {
        $api_datos = Parametro::find(6)->valor;

        $valor = trim($request->get('producto_valor'));
        $tipoconsulta = $request->post('tipo');
        $codestacion = $request->get('codestacion');
        $codalmacen = $request->get('codalmacen');
        $codlistaprecio = $request->get('codlistaprecio');
        $indicar_proveedor = ($request->get('indicar_proveedor') == 'true') ? true : false;
        // dd($indicar_proveedor);

        $procede = true;
        $msj = "Articulo encontrado";
        $msj_tipo = "success";
        $log = "";

        try {
            $getArticulo = Http::post("{$api_datos}/ObtenerArticulo",
            ['valor' => $valor, 'tipoconsulta' => 1, 'codestacion' => $codestacion, 'codalmacen' => $codalmacen, 'codlistaprecio' => $codlistaprecio])->object()->articulos;

        } catch (Exception $e) {
            $procede = false;
            $msj = "Ocurrio un problema al buscar";
            $msj_tipo = "error";
            $log = "{$e}";
        }

        if ($procede == true) {
            // dd(count($getArticulo));
            if (count($getArticulo) == 0) {
                $procede = false;
                $msj = "Arcitulo no encontrado";
                $msj_tipo = "error";

            }else{
                $getArticulo = $getArticulo[0];
                // dd($getArticulo);
                $getArticulo->costo_articulo = $getArticulo->costoArticulo ?? 0;
                if ($indicar_proveedor == true) {
                    // dd('validamos');
                    $precioSinIGV = $getArticulo->costoArticulo;
                    $precioPublico = number_format(($getArticulo->costoArticulo * (1+0.18)),2);
                    $getArticulo->precioPublico = number_format($precioPublico,2);
                    $getArticulo->precioSinIGV = number_format($precioSinIGV,2);
                }
                $afecto = 1;
                if ($getArticulo->tipoIgv != 1) {
                    $afecto = 0;
                    $getArticulo->precioPublico = number_format($getArticulo->precioSinIGV,2);
                    $getArticulo->precioSinIGV = number_format($getArticulo->precioSinIGV,2);
                }

                $getArticulo->afecto = $afecto;
                // dd($getArticulo);
            }

        }
        // $getArticulo = $listArticulos;

        // return response()->json(['getArticulo' => $getArticulo, '']);
        return response()->json(['procede' => $procede, 'msj' => $msj, 'msj_tipo' => $msj_tipo, 'log' => $log, 'getArticulo' => $getArticulo]);
    }

    public function listarProveedores(Request $request)
    {
        $api_datos = Parametro::find(6)->valor;

        $valor = trim($request->get('term'));
        $tipo  = $request->get('tipo'); //busqueda por razon social
        $maximo = ($tipo == 3) ? 2 : 0;

        // $listItems arranca vacio A PROPOSITO.
        //
        // Antes solo se definia dentro del if. Buscando por razon social hacen
        // falta 3 letras, asi que con una o dos el if no entraba, la variable
        // no existia y el foreach de abajo reventaba: el buscador respondia
        // 500 y el usuario veia "The results could not be loaded" mientras
        // escribia las primeras letras de CUALQUIER proveedor.
        $listItems = [];

        if (strlen($valor) > $maximo) {
            try {
                $respuesta = Http::post("{$api_datos}/ObtenerProveedores",
                    ['valor' => $valor, 'tipo' => $tipo]
                )->object();

                // La ApiGRE puede responder algo sin 'proveedores' (un error,
                // un HTML). Encadenar ->proveedores a ciegas era otro 500.
                if (is_object($respuesta) && isset($respuesta->proveedores) && is_array($respuesta->proveedores)) {
                    $listItems = $respuesta->proveedores;
                }
            } catch (\Throwable $e) {
                Log::error(__METHOD__ . ": " . $e->getMessage());
                return response()->json(['items' => [], 'error' => 'No se pudo consultar los proveedores.']);
            }
        }

        $items = array();
        foreach ($listItems as $item) {

            $items[] = (object) array('id' => $item->codProveedor, 'text' => "[{$item->ruc}] {$item->nombreproveedor}", 'proveedor_nombre' => $item->nombreproveedor, 'proveedor_ruc' => $item->ruc);
        }

        return response()->json(['items' => $items]);
    }

    public function listarClientes(Request $request)
    {
        $api_datos = Parametro::find(6)->valor;

        $valor = trim($request->get('term'));
        $tipo = $request->get('tipo_busqueda_cliente');//busqueda por razon social

        // Vacio A PROPOSITO: antes solo se definia dentro del if, y con una o
        // dos letras el foreach de abajo reventaba con "Undefined variable".
        // El buscador respondia 500 mientras el usuario escribia las primeras
        // letras de cualquier cliente. Mismo fallo que tenia el de proveedores.
        $listClientes = [];

        if (strlen($valor) > 2) {
            try {
                $respuesta = Http::post("{$api_datos}/obtenerCliente",
                    ['valor' => $valor, 'tipo' => $tipo]
                )->object();

                if (is_object($respuesta) && isset($respuesta->cliente) && is_array($respuesta->cliente)) {
                    $listClientes = $respuesta->cliente;
                }
            } catch (\Throwable $e) {
                Log::error(__METHOD__ . ": " . $e->getMessage());
                return response()->json(['items' => [], 'error' => 'No se pudo consultar los clientes.']);
            }
        }

        $items = array();
        foreach ($listClientes as $item) {
            $tipo_documento = $item->tipoDocumentoIdentidad;
            $nro_documento = $item->dni;
            $documento_tipo_nombre = 'DNI';
            if ($tipo_documento == '') {
                $documento_tipo_nombre = 'RUC';
                $nro_documento = trim($item->rucCliente);
            }
            $items[] = (object) array('id' => $item->codCliente, 'text' => "[{$nro_documento}] {$item->razonSocial}", 'direccion' => $item->direccion, 'razon_social' => $item->razonSocial, 'nro_documento' => $nro_documento, 'documento_tipo_nombre' => $documento_tipo_nombre );
        }

        return response()->json(['items' => $items]);
    }

    public function listarTransportistas(Request $request)
    {
        $api_datos = Parametro::find(6)->valor;

        $valor = trim($request->get('term'));
        $tipo = 1;//busqueda por nombre

        // Vacio A PROPOSITO, igual que en clientes y proveedores: encadenar
        // ->transportistas a ciegas sobre la respuesta de la API convertia
        // cualquier fallo del DataMart en un 500 del buscador.
        $listItems = [];

        try {
            $respuesta = Http::post("{$api_datos}/ObtenerTransportista",
                ['valor' => $valor, 'tipo' => $tipo]
            )->object();

            if (is_object($respuesta) && isset($respuesta->transportistas) && is_array($respuesta->transportistas)) {
                $listItems = $respuesta->transportistas;
            }
        } catch (\Throwable $e) {
            Log::error(__METHOD__ . ": " . $e->getMessage());
            return response()->json(['items' => [], 'error' => 'No se pudo consultar los transportistas.']);
        }

        $items = array();
        foreach ($listItems as $item) {
            $items[] = (object) array('id' => $item->codTransportista, 'text' => "[{$item->rucTransportista}] {$item->nombreTransportista}", 'transportista_direccion' => $item->direccionTransportista, 'ruc' => $item->rucTransportista, 'nombre' => $item->nombreTransportista );
        }

        return response()->json(['items' => $items]);
    }

    public function listarUbigeos(Request $request)
    {
        // Devolvia <option> concatenados dentro del JSON, asi que un cambio de
        // marcado obligaba a tocar PHP. Ahora devuelve datos y la vista los
        // pinta con x-for.
        $codigoUbigeo = (string) $request->input('codUbigeo', '');
        $tipoConsulta = (int) $request->input('tipo_busqueda', 1);

        return response()->json([
            'procede'      => true,
            'tipoConsulta' => $tipoConsulta,
            'ubigeos'      => $this->ubigeosDesdeApi($codigoUbigeo, $tipoConsulta),
        ]);
    }

    /**
     * Consulta ubigeos a la ApiGRE y normaliza la forma.
     *
     * El codigo viene con relleno de espacios porque en el DataMart es char(n);
     * sin el trim las comparaciones contra el ubigeo guardado nunca coinciden.
     * Si la API falla, se devuelve lista vacia: un ubigeo ausente no puede
     * impedir registrar la guia.
     */
    private function ubigeosDesdeApi(string $codigoPadre, int $tipoConsulta): array
    {
        $api_datos = Parametro::find(6)->valor;

        try {
            $lista = Http::post("{$api_datos}/ObtieneUbigeos", [
                'codigoUbigeo' => $codigoPadre,
                'tipoConsulta' => $tipoConsulta,
            ])->object()->ubigeos ?? [];
        } catch (Exception $e) {
            Log::warning('No se pudieron obtener ubigeos: ' . $e->getMessage());
            return [];
        }

        return collect($lista)->map(function ($item) {
            return [
                'codUbigeo'   => trim((string) ($item->codUbigeo ?? '')),
                'descripcion' => trim((string) ($item->descripcion ?? '')),
            ];
        })->values()->all();
    }

    public function getUbigeosPorAlmacen(Request $request)
    {
        // Armaba tres bloques de <option> con el "selected" cocido adentro.
        // Ahora devuelve las tres listas y cual queda elegido en cada nivel;
        // el marcado lo decide la vista.
        $tipo           = (int) $request->input('tipo', 1);
        $ubigeoDistrito = trim((string) $request->input('ubigeo', ''));
        $direccion      = trim((string) $request->input('direccion', ''));

        // El ubigeo peruano es jerarquico: DDPPDD. La provincia son los 4
        // primeros digitos y el departamento los 2 primeros.
        $ubigeoProvincia    = Str::substr($ubigeoDistrito, 0, 4);
        $ubigeoDepartamento = Str::substr($ubigeoProvincia, 0, 2);

        return response()->json([
            'tipo'          => $tipo,
            'direccion'     => $direccion,
            'departamentos' => $this->ubigeosDesdeApi('', 1),
            'provincias'    => $ubigeoDepartamento !== '' ? $this->ubigeosDesdeApi($ubigeoDepartamento, 2) : [],
            'distritos'     => $ubigeoProvincia !== ''    ? $this->ubigeosDesdeApi($ubigeoProvincia, 3)    : [],
            'seleccion'     => [
                'departamento' => $ubigeoDepartamento,
                'provincia'    => $ubigeoProvincia,
                'distrito'     => $ubigeoDistrito,
            ],
        ]);
    }

    public function getModalidadTraslado(Request $request)
    {
        // dd($request->post());
        $entidad_ruc = Parametro::find(2)->valor;
        // $entidad_ruc = '20117332714';
        $transportista_ruc = $request->post('transportista_ruc');

        $modalidad_traslado = '01';//publico
        $verChofer = false;

        if ($entidad_ruc == $transportista_ruc) {
            $modalidad_traslado = '02';//privado
            $verChofer = true;
        }

        return response()->json(['modalidad_traslado' => $modalidad_traslado, 'verChofer' => $verChofer]);
    }

// Reemplaza tu función actual con esta en tu GuiaSalidaController.php

    public function agregarItem(AgregarItemRequest $request)
    {
        // Mismo cambio que en Guia de Ingreso: devolvia HTML concatenado con
        // los atributos entre comillas simples, asi que una descripcion con
        // apostrofe truncaba la fila. Ahora devuelve datos.
        //
        // Ademas tenia el IGV escrito a mano ($igv = 0.18) para calcular el
        // costo con IGV, que era el septimo lugar donde vivia esa constante.
        $datos = $request->validated();

        $yaEnDetalle = collect(json_decode($request->input('items', '[]')) ?: [])
            ->contains(function ($item) use ($datos) {
                $cod = $item->codArticulo ?? $item->producto_id ?? null;
                return (string) $cod === (string) $datos['producto_id'];
            });

        if ($yaEnDetalle) {
            return response()->json([
                'procede'  => false,
                'msj'      => 'Ese articulo ya esta en el detalle.',
                'msj_tipo' => 'error',
            ], 422);
        }

        $igv       = Igv::vigente();
        $tipoIgv   = (int) ($datos['tipo_igv'] ?? 1);
        $afectoIgv = $request->has('afecto') ? (bool) $request->input('afecto') : ($tipoIgv === 1);

        // En salida el precio de referencia es el de venta, no el costo.
        $precioBase = (float) ($datos['precio_sin_igv']
            ?? $datos['precio_publico']
            ?? $datos['costo_articulo']
            ?? 0);

        $stock = (float) $request->input('stock', 0);

        return response()->json([
            'procede'  => true,
            'msj'      => 'Articulo agregado',
            'msj_tipo' => 'success',
            'linea'    => [
                'codArticulo'         => $datos['producto_id'],
                'codigoBarra'         => $datos['codigo_barra'] ?? '',
                'codPlu'              => $datos['cod_plu'] ?? '',
                'descripcion'         => $datos['descripcion'],
                'cantidad'            => 1,
                'precioSinIgv'        => round($precioBase, 2),
                'precioPublico'       => (float) ($datos['precio_publico'] ?? 0),
                'costoArticulo'       => round((float) ($datos['costo_articulo'] ?? 0), 2),
                'peso'                => (float) ($datos['peso'] ?? 0),
                'stock'               => $stock,
                'codUnidad'           => (int) ($datos['cod_unidad'] ?? 9),
                'descUnidadMedida'    => $datos['desc_unidad_medida'] ?? '',
                'siglaUmfe'           => $datos['sigla_umfe'] ?? '',
                'tipoIgv'             => $tipoIgv,
                'afectoIgv'           => $afectoIgv,
                'porcentajeDescuento' => 0,
                'bonificacion'        => false,
                'esConsignado'        => false,
            ],
            'igv'           => ['tasa' => $igv->tasa(), 'porcentaje' => $igv->porcentaje()],
            'validarStock'  => (Parametro::find(10)->valor ?? 'false') === 'true',
        ]);
    }
    public function modalStore(Request $request)
    {
        $envio_sunat = $request->post('envio_sunat');
        $guardar_avance = ($request->post('guardar_avance') == 'true') ? true : false ;
        // dd($guardar_avance);
        return view('guia.salida.modal-store', compact('envio_sunat', 'guardar_avance'));
    }

    public function store(Request $request)
    {
        // dd($request->post());
        $api_datos = Parametro::find(6)->valor;
        $id = "";

        $datos = $request->post();
        $id_continuar = $request->post('id_continua');
        $datos['guardar_avance'] = ($datos['guardar_avance'] == 'true') ? true : false ;
        $guardar_avance = $datos['guardar_avance'];

        $datos['indicar_proveedor'] = ($datos['indicar_proveedor'] ?? '' == 'on') ? true : false ;

        $procede = true;
        $msj_tipo = "success";
        $log = "";
        $datos['guia_estado_id'] = 1; //registrado-emitida

        $url_redirect = route('guiasalida.index');

        // Una guia GENERADA tiene que tener lineas. Ver el comentario largo en
        // GuiaIngresoController::store(): la cabecera se creaba igual, con su
        // total y quemando el correlativo, y el DataMart la rechazaba despues
        // con "Documento incompleto". El borrador si puede ir vacio.
        $lineas = json_decode($request->post('detalle'));
        if (! is_array($lineas)) { $lineas = []; }

        if ($guardar_avance == false && count($lineas) === 0) {
            return response()->json([
                'procede'  => false,
                'msj'      => 'La guia no tiene articulos. Agregue al menos uno antes de generarla.',
                'msj_tipo' => 'error',
                'log'      => '',
                'id'       => '',
            ]);
        }

        // asignamos existencia de serie en BD
        if ($guardar_avance == false) {
            $msj = "Guia registrada";
            $asignarSerie = $this->asignarSerie($datos['serie']);
            // dd($asignarSerie);

            $procede = $asignarSerie->procede;
        }
        if ($guardar_avance == true) {
            $msj = "Avance de guia registrada";
            $datos['guia_estado_id'] = 4;//estado avance
        }

        // se inicia registro
        if ($procede == false ) {
            $msj = $asignarSerie->msj;
            $msj_tipo = $asignarSerie->msj_tipo;
            $log = $asignarSerie->log;
        }

        // validacion antes del store
        if ($procede == true) {


            // $datos['fecha_emision'] = date('Y-m-d');
            $datos['fecha_emision'] = $request->post('fecha_emision');
            $datos['hora_emision'] = date('H:i:s');
            $datos['fecha_inicio_traslado'] = $request->post('fecha_inicio_traslado') ?: null;

            if ($datos['indicar_proveedor'] == true) {
                $proveedor_id = ($datos['proveedor_id'] ?? null) ? $datos['proveedor_id'] : null ;
            }

            if ($datos['tipo_operacion_id'] != 12) {//diferente a trasnsferencia
                $datos['cod_almacen_origen'] = null;
                $datos['almacen_origen_nombre'] = null;
                $datos['cod_almacen_destino'] = null;
                $datos['almacen_destino_nombre'] = null;
                $datos['codigo_anexo_partida'] = null;
                $datos['codigo_anexo_llegada'] = null;
            }

            if ($datos['tipo_operacion_id'] == 12) {//transferencia
                $datos['codalmacen'] = null;
                $datos['almacen_nombre'] = null;
                $valor_cliente_transferencia = Parametro::find(2)->valor;

                // try {

                //     $getClientePorRuc = Http::post("{$api_datos}/obtenerCliente",
                //     ['valor' => $valor_cliente_transferencia, 'tipo' => 2])
                //         ->object()->cliente;

                // } catch (Exception $e) {
                //     $procede = false;
                //     $msj = "Ocurrio un error al obtener cliente transferencia (API)";
                //     $msj_tipo = "error";
                //     $log = "{$e}";

                // }
                if ($procede == true) {
                    // $getClientePorRuc = $getClientePorRuc[0];
                    $datos['cliente_id'] = trim($request->post('cliente_transf_id'));
                    $datos['cliente_razon_social'] = trim($request->post('cliente_transf_razon_social'));
                    $datos['cliente_nro_documento'] = trim($request->post('cliente_transf_nro_documento'));
                    $datos['cliente_documento_tipo_nombre'] = 'RUC';
                    $datos['cliente_direccion'] = trim($request->post('cliente_transf_direccion'));

                }

            }

            if ($datos['modalidad_traslado'] == '01') {//publico
                $datos['vehiculo_id'] = null;
                $datos['chofer_id'] = null;
                $datos['brevete'] = null;
                $datos['chofer_dni'] = null;
                $datos['chofer_brevete'] = null;
                $datos['chofer_nombre'] = null;
                $datos['vehiculo_placa'] = null;
                $datos['vehiculo_marca'] = null;
            }

            if ($datos['vehiculo_placa'] != null) {
                $placa_vehiculo = str_replace(' ', '', $datos['vehiculo_placa']);
                $placa_vehiculo_format = substr(str_replace('-', '', $placa_vehiculo), 0, 8);
                $datos['vehiculo_placa'] = $placa_vehiculo_format;

            }

            if ($id_continuar != null) {
                // dd('desactivamos el activo anterior');
                $guia_avance = GuiaSalida::find($id_continuar);
                $guia_avance->activo = 0;
                try {
                    $guia_avance->save();
                } catch (Exception $e) {
                    //throw $th;
                    $procede = false;
                    $msj = "No se pudo limpiar la guia guardada";
                    $msj_tipo = "error";
                    $log = "{$e}";
                }
            }


            if ($guardar_avance == false) {
                // dd($asignarSerie);
                $datos['serie_id'] = $asignarSerie->serieAsignada->id;
                $datos['numero'] = intval($asignarSerie->serieAsignada->numero) +1;
            }
        }

        //registro en store
        //
        // Cabecera, correlativo de serie y detalle son UNA operacion: si una
        // linea falla a la mitad, no puede quedar una cabecera escrita.
        $store = null;
        if ($procede == true) {
            DB::beginTransaction();

            Log::info('Datos a guardar en [guia_salidas] (local):', $datos);

            try {
                $store = GuiaSalida::create($datos);
            } catch (Exception $e) {
                // dd($e);
                $procede = false;
                $msj = "No se pudo registrar en Nube";
                $msj_tipo = "success";
                $log = "{$e}";
            }

        }

        // auditoria store
        $obsevracion_auditoria = $msj;

        // actualizar serie nube
        //
        // Solo cuando la guia se GENERA. El borrador no pasa por asignarSerie()
        // -no gasta correlativo a proposito-, asi que $asignarSerie ni existe:
        // guardar un avance moria con "Undefined variable $asignarSerie" y el
        // usuario perdia todo lo tecleado con un 500. Mismo fallo que ya se
        // corrigio en Guia de Ingreso.
        if ($procede == true && $guardar_avance == false) {
            $updateSerie = Serie::find($asignarSerie->serieAsignada->id);
            // dd($datos);
            $updateSerie->numero = $datos['numero'];

            try {
                $updateSerie->save();

            } catch (Exception $e) {
                //throw $th;
                Log::error(__METHOD__ . ": " . $e->getMessage());
                $procede = true;
                $msj = "No se pudo actualizar serie de Nube";
                $msj_tipo = "error";
                $log = "{$e}";
            }
        }

        //registrar detalle
        if ($procede == true) {
            $detalle = $lineas;
            $id = $store->id;

            Log::info('Datos a guardar en [guia_salida_detalles] (local):', $detalle);

            foreach ($detalle as $item) {

                if ($procede == true) {
                    // El try envuelve la linea ENTERA, no solo el save(): un
                    // campo que falta revienta al ARMAR el modelo, y eso es un
                    // Error de PHP, no una Exception. Antes salia un 500 y la
                    // cabecera quedaba escrita sin detalle.
                    try {
                        $guiaDetalle = new GuiaSalidaDetalle();
                        $guiaDetalle->guia_salida_id = $store->id;
                        $guiaDetalle->codarticulo = $item->codarticulo;
                        $guiaDetalle->precio = $item->precio;
                        $guiaDetalle->cantidad = floatval($item->cantidad);
                        $guiaDetalle->importe = $item->importe;
                        $guiaDetalle->costo_articulo = $item->costo_articulo ?? 0;
                        $costo_total = $item->costo_articulo * $item->cantidad;
                        $guiaDetalle->costo_total = number_format($costo_total, 2);
                        $guiaDetalle->porcentaje_descuento = $item->porcentaje_descuento;
                        $guiaDetalle->monto_descuento = $item->monto_descuento;
                        $guiaDetalle->peso_unitario = $item->peso;
                        $guiaDetalle->peso_total = floatval($item->peso) * floatval($item->cantidad);


                        $nombreArticulo = $item->descripcion;
                        // $nombreArticuloSinComillas = str_replace('"', '', $nombreArticulo);
                        $nombreArticuloSinComillas = $this->limpiarCaracteres($nombreArticulo);

                        $nombreArticuloLimpio = json_decode('"' . $nombreArticuloSinComillas . '"');

                        // $guiaDetalle->descripcion = $item->descripcion;
                        $guiaDetalle->descripcion = $nombreArticuloLimpio;
                        $guiaDetalle->precio_publico = $item->precio_publico;
                        $guiaDetalle->precio_sin_igv = $item->precio_sin_igv;
                        $guiaDetalle->codigo_barra = $item->codigo_barra;

                        $guiaDetalle->cod_unidad = $item->cod_unidad;
                        $guiaDetalle->desc_unidad_medida = $item->desc_unidad_medida;
                        $guiaDetalle->sigla_umfe = $item->sigla_umfe;

                        $costo_total = ($item->costo_articulo ?? 0) * $item->cantidad;
                        $guiaDetalle->costo_articulo = $item->costo_articulo ?? 0;
                        $guiaDetalle->costo_total = $costo_total;
                        // Solo si la empresa usa consignados: la columna puede no
                        // existir en clientes que no la tienen.
                        if (\App\Support\ConfiguracionEmpresa::usaConsignados()) {
                            $guiaDetalle->es_consignado = $item->es_consignado ?? 0;
                        }

                        $guiaDetalle->save();
                    } catch (\Throwable $e) {
                        $procede = false;
                        $msj = "No se pudo registrar el detalle de la guia.";
                        $msj_tipo = "error";
                        $log = "{$e}";
                        Log::error(__METHOD__ . ": " . $e->getMessage());
                    }
                }

            }
        }

        if ($procede == true) {
            // El borrador no tiene numero -no gasta correlativo-, y leer
            // $datos['numero'] ahi tumbaba la peticion entera con un 500.
            $msj = ($guardar_avance == true)
                ? "<b>Avance de guia guardado</b>"
                : "<b>Guia de Salida registrada Nº: {$datos['serie']}-{$datos['numero']}</b>";

            if ($guardar_avance == false && $datos['envio_sunat'] == 0) {
                $link = route('guiasalida.pdf', ['guia' => $store, 'valorada' => 0]);
                $msj = "{$msj} <a class='btn btn-sm btn-success' href='{$link}' target='_blank'><i class='fa fa-external-link'></i> Ver</a>";
            }
        }

        if ($procede == false) {
            $data_guardar_avance  = ($guardar_avance == true) ? 'true' : 'false' ;
            $msj = "{$msj} <br> <button class='btn btn-success btn-sm' data-guardar_avance= '{$data_guardar_avance}' id='btnReintentar'><i class='fa-regular fa-paper-plane'></i> Reintentar</button>";
        }

        // Cierre de la transaccion abierta en el registro de la cabecera.
        if ($store !== null) {
            if ($procede == true) {
                DB::commit();
            } else {
                DB::rollBack();
                $id = "";
            }
        }

        // Fuera de la transaccion y solo si hay guia: antes esta linea leia
        // $store->id sin comprobar, y una cabecera fallida daba error 500 en
        // vez del mensaje real.
        if ($store !== null && $procede == true) {
            $this->registrarAuditoria($store->id, 1, 'guia_salidas', json_encode($datos), $obsevracion_auditoria);
        }

        return response()->json(['procede' => $procede, 'msj' => $msj, 'msj_tipo' => $msj_tipo, 'log' => $log, 'id' => $id]);

    }

    function limpiarCaracteres($cadena)
    {
        $caracteresEspeciales = ['"', "'"];
        return str_replace($caracteresEspeciales, '', $cadena);
    }

    public function asignarSerie($serie_busqueda)
    {
        $api_datos = Parametro::find(6)->valor;

        $getSerieLocal = Serie::where('serie', $serie_busqueda)->first();
        $procede = true;
        $msj = "Serie asignada";
        $msj_tipo = "success";
        $log = "";
        $serieAsignada = "";

        if ($getSerieLocal == null) {

            $serie = null;
            $numero = null;

            try {
                $listSeries = Http::get("{$api_datos}/obtenerSeriesNumerosGuia")->object()->serienumeros;
                // dd($listSeries);
            } catch (Exception $e) {
                //throw $th;
                // dd($e);
                $procede = false;
                $msj = "Ocurrio un problema para obtener el Nº Serie (api)";
                $msj_tipo = "error";
                $log = "{$e}";
            }

            if ($procede == true) {
                // dd($listSeries);
                foreach ($listSeries as $item) {
                    if ($item->numserie == $serie_busqueda) {
                        $numero = $item->ultimoValormarket;
                        $serie = $item->numserie;
                        $documento_tipo_id = $item->tipodocumento;
                    }
                }
                $procede = false;
                // dd($numero);
                // dd([$serie, $numero]);
                if ($serie != null) {
                    // dd($serie);
                    $procede = true;
                    $id = 1;
                    $lastSerie = Serie::orderBy('id', 'desc')->first();
                    // dd($lastSerie);

                    if ($lastSerie != null) {
                        // dd('generamos serie');
                        $id = intval($lastSerie->id) + 1;
                    }
                    // dd($id);
                    $nueva_serie = new Serie();
                    $nueva_serie->id = $id;
                    $nueva_serie->serie = $serie;
                    $nueva_serie->documento_tipo_id = $documento_tipo_id;
                    $nueva_serie->numero = $numero;

                    try {
                        $nueva_serie->save();
                        // dd($nueva_serie);
                        $getSerieLocal = Serie::find($id);
                    } catch (Exception $e) {
                        //throw $th;
                        // dd($e);
                        $procede = false;
                        $msj = "No se pudo generar serie";
                        $msj_tipo = "error";
                        $log = "{$e}";
                    }
                }

            }

        }

        if ($procede == true) {
            $serieAsignada = $getSerieLocal;
            // dd($serieAsignada);
        }

        return (object)['procede' => $procede, 'msj' => $msj, 'msj_tipo' => $msj_tipo, 'log' => $log, 'serieAsignada' => $serieAsignada];

    }


public function storeDataMart(Request $request)
{
    // ============================================================
    // 1. OBTENER PARÁMETROS INICIALES
    // ============================================================
    $api_datos = Parametro::find(6)->valor;
    $panel_origen = $request->post('panel_origen');
    $id = $request->post('id');

    $guia = GuiaSalida::find($id);
    
    if (!$guia) {
        return response()->json([
            'procede' => false, 
            'msj' => 'Guía no encontrada', 
            'msj_tipo' => 'error', 
            'log' => 'ID invalido'
        ]);
    }

    $fecha = Carbon::parse($guia->fecha_emision);
    $anio = $fecha->year;

    // Variables de control
    $procede = true;
    $msj = "<b><i class='fa fa-check-double'></i>Guia Nº: {$guia->serie}-{$guia->numero} registrada en DataMart</b>";
    $msj_tipo = "success";
    $log = "";

    // ============================================================
    // 2. OBTENER DETALLE
    // ============================================================
    $detalle = GuiaSalidaDetalle::where('guia_salida_id', $guia->id)->get();
    
    $articulosConsignados = [];
    $body_detalle = [];

    // ============================================================
    // 3. PASO 1: IDENTIFICAR ARTÍCULOS CONSIGNADOS **PRIMERO**
    // ============================================================
    foreach ($detalle as $item) {
        if (($item->es_consignado ?? 0) == 1) {
            $articulosConsignados[] = $item->codarticulo;
        }
    }

    // ============================================================
    // El "PASO 2" ya no existe. Marcaba MaestroArticulo.consignacion por
    // conexion DIRECTA a SQL Server antes de llamar al stored procedure, y si
    // fallaba abortaba la guia entera con "Error critico". Ahora el flag viaja
    // dentro del cuerpo y lo resuelve el DataMart en la misma transaccion.
    //
    // Ademas Ingreso lo hacia DESPUES del procedimiento y Salida ANTES: el
    // mismo dato con dos comportamientos distintos segun la pantalla.

    // ============================================================
    // 5. PASO 3: AHORA SÍ ARMAR EL BODY_DETALLE
    // ============================================================
    foreach ($detalle as $item) {
        $body_detalle[] = array(
            "anioGuia" => $anio,
            "cantidad" => $item->cantidad,
            "codArticulo" => $item->codarticulo,
            "estadoProceso" => "0",
            "importeDetalle" => $item->importe,
            "item" => 1,
            "numSerie" => $guia->serie,
            "numeroGuia" => $guia->numero,
            "precio" => $item->precio,
            "tipoGuia" => "A", // A = Salida
            "unidadMedida" => $item->cod_unidad ?? 1,
            "descuento" => $item->monto_descuento ?? 0,

            // El flag viaja CON la guia, igual que en Ingreso.
            "esConsignado" => (($item->es_consignado ?? 0) == 1) ? 1 : 0,
            "tipoIgv"      => $item->tipo_igv ?? 1,
        );
    }

    // ============================================================
    // 6. PASO 4: ARMAR BODY COMPLETO
    // ============================================================
    $body = [
        "anioGuiaRemision" => $anio,
        "breveteChofer" => $guia->brevete,
        "codAlmacen" => $guia->codalmacen,
        "codAlmacenOrigen" => $guia->cod_almacen_origen,
        "codAlmacenDestino" => $guia->cod_almacen_destino,
        "codCliente" => $guia->cliente_id,
        "codEstacion" => $guia->codestacion,
        "codListaPrecio" => $guia->codlistaprecio,
        "codProveedor" => $guia->proveedor_id ?? 0,
        "codtrabajador" => $guia->vendedor_id,
        "comentario" => $guia->comentario,
        "descuento" => $guia->monto_descuento,
        "detalle" => $body_detalle,
        "direccionllegada" => $guia->direccion_llegada,
        "direccionpartida" => $guia->direccion_partida,
        "dnichofer" => $guia->chofer_dni,
        "esproveedor" => $guia->indicar_proveedor,
        "estadoProceso" => "0",
        "fechaEmision" => $guia->fecha_emision,
        "formapago" => $guia->forma_pago_id,
        "igv" => $guia->monto_igv,
        "modalidadTransporte" => "18",
        "nombreTransportista" => $guia->transportista_nombre,
        "nombrechofer" => $guia->transportista_nombre,
        "numSerie" => $guia->serie,
        "seriefactura" => $guia->pedido_serie,
        "numeroFactura" => '',
        "numeroGuia" => $guia->numero,
        "placavehiculo" => $guia->vehiculo_placa,
        "rucTransportista" => $guia->transportista_ruc,
        "tipoGuia" => "A", // N->ingreso; A->Salida
        "tipoOperacion" => $guia->tipo_operacion_id,
        "tipomonda" => 1,
        "totalVenta" => $guia->total_venta,
        "ubigeollegada" => $guia->ubigeo_llegada,
        "ubigeopartida" => $guia->ubigeo_partida,
        "valorVenta" => $guia->importe_sin_igv,
    ];

    Log::info('DATAMARKET SALIDA - Payload a enviar (Body completo):', $body);

    // ============================================================
    // 7. PASO 5: ENVIAR A LA API
    // ============================================================
    try {
        $storeRemoto = Http::post("{$api_datos}/InsertGuiaDMK", $body)->object();
        
        // Un 404 o un HTML de error llegan sin la propiedad exito, y con
        // isset() eso pasaba por bueno: se informaba "registrada en DataMart"
        // con la guia nunca enviada.
        if (! is_object($storeRemoto) || ! isset($storeRemoto->exito)) {
            $procede = false;
            $msj = "La ApiGRE no respondio como se esperaba. La guia NO se registro en el DataMart.";
            $msj_tipo = "error";
            $log = "Respuesta inesperada de {$api_datos}/InsertGuiaDMK";
        } elseif ($storeRemoto->exito == false) {
            $procede = false;
            $msgErrorRemoto = $storeRemoto->msgerror ?? 'Error desconocido en remoto';
            $msj = "No se pudo completar : {$msgErrorRemoto}";
            $msj_tipo = "error";
        }
    } catch (Exception $e) {
        $procede = false;
        $msj = "{$msj} <b>No se pudo registrar en DATAMART (Error de Conexión API)</b>";
        $msj_tipo = "error";
        $log = "Error API: " . $e->getMessage();
        Log::error($log);
    }

    // ============================================================
    // 8. PASO 6: ACTUALIZAR ESTADO LOCAL
    // ============================================================
    if ($procede == true) {
        try {
            $guia->enviado_datamarket = 1;
            $guia->save();
        } catch (Exception $e) {
            $procede = false;
            $msj = "Se envió a DataMarket pero falló al actualizar el estado local.";
            $msj_tipo = "error";
            $log = "Error Local DB: " . $e->getMessage();
        }
    }

    // ============================================================
    // 9. CONFIGURAR BOTÓN DE REINTENTO SI FALLÓ
    // ============================================================
    if ($procede == false) {
        if ($panel_origen != 'index') {
            $li_btn = "";
            if ($guia->envio_sunat == 1) {
                $li_btn = "
                <button type='button' class='btn btn-dark dropdown-toggle dropdown-toggle-split' data-bs-toggle='dropdown' aria-expanded='false'>
                    <span class='visually-hidden'>Toggle Dropdown</span>
                </button>
                <ul class='dropdown-menu'>
                    <li><a class='dropdown-item' style='cursor: pointer' id='btnReintentarFacturar' data-id='{$id}'><i class='fa fa-download'></i> <b>Continuar Sunat</b></a></li>
                </ul>";
            }
            $msj = "{$msj}
            <div class='btn-group float-end'>
                <button class='btn btn-success btn-sm' id='btnReintentarDataMart' data-id='{$id}'><i class='fa-regular fa-paper-plane'></i> Reintentar</button>
                {$li_btn}
            </div>";
        }
    }

    // ============================================================
    // 10. REGISTRAR AUDITORÍA
    // ============================================================
    if (method_exists($this, 'registrarAuditoria')) {
        $this->registrarAuditoria($guia->id, 1, 'guia_salidas_datamart', json_encode($body), strip_tags($msj));
    }

    return response()->json([
        'procede' => $procede, 
        'msj' => $msj, 
        'msj_tipo' => $msj_tipo, 
        'log' => $log
    ]);
}
    
    public function facturacionElectronica(Request $request)
    {
        $panel_origen = $request->post('panel_origen');

        $api_facturacion = Parametro::find(7)->valor;

        $id = $request->post('id');
        $guia = GuiaSalida::find($id);

        $ruc_emisor = Parametro::find(2)->valor;
        $razon_social_emisor = Parametro::find(3)->valor;
        // dd($guia);

        $cliente_documento_tipo = 6;
        if ($guia->cliente_documento_tipo_nombre == 'DNI') {
            $cliente_documento_tipo = 1;
        }

        $detalle = GuiaSalidaDetalle::where('guia_salida_id', $guia->id)->get();
        // dd($detalle);
        $nro = 1;
        foreach ($detalle as $item) {
            // $nombre_articulo_format = $item->descripcion;
            // $nombre_articulo_format = json_encode(utf8_encode($item->descripcion), JSON_UNESCAPED_UNICODE);;



            // Ejemplo de uso
            // $descripcion = "fanny pi\u00f1a en rodajas x 567gr.";
            $descripcionLimpia = $this->limpiarCaracteresEspeciales($item->descripcion);

            // Convertir la cadena a formato JSON
            // $descripcionJson = json_encode($descripcionLimpia, JSON_UNESCAPED_UNICODE);
            // $nombre_articulo_format = json_encode($descripcionLimpia, JSON_UNESCAPED_UNICODE);
            $nombre_articulo_format =$descripcionLimpia;

            // dd($descripcionJson);

            $body_detalle[] = array(
                'Correlativo' => $nro++,
                "CodigoItem" => "{$item->codarticulo}",
                "Descripcion" => "{$nombre_articulo_format}  |  {$item->codigo_barra}",
                // "UnidadMedida" => "NIU",
                "UnidadMedida" => "{$item->sigla_umfe}",
                "Cantidad" => $item->cantidad,
                "LineaReferencia" => 1
            );
        }

        // [
        //     [
        //         "Correlativo" => 1,
        //         "CodigoItem" => "ENTREGA DE EQUIPO",
        //         "Descripcion" => "ENTREGA DE EQUIPO",
        //         "UnidadMedida" => "NIU",
        //         "Cantidad" => 1,
        //         "LineaReferencia" => 1
        //     ]
        // ]
        $serie_format = str_pad($guia->serie, 3, '0', STR_PAD_LEFT);

        $destinatario = array(
            'NroDocumento' => $guia->cliente_nro_documento,
            "TipoDocumento" => "{$cliente_documento_tipo}",
            "NombreRazonSocial" => $this->limpiarCaracteresEspeciales($guia->cliente_razon_social)
        );

        if ($guia->indicar_proveedor == 1) {

            $destinatario = array(
                'NroDocumento' => $guia->proveedor_ruc ?? '',
                "TipoDocumento" => 6,
                "NombreRazonSocial" => $this->limpiarCaracteresEspeciales($guia->proveedor_nombre ?? '')
            );
        }

        $body = [
            // "IdDocumento" => "T001-00000070",
            "IdDocumento" => "T{$serie_format}-{$guia->numero}",
            "FechaEmision" => "{$guia->fecha_emision}",
            "HoraEmision" => "{$guia->hora_emision}",
            "TipoDocumento" => "09",
            "Glosa" => $guia->comentario,
            "Remitente" => [
                "NroDocumento" => $ruc_emisor,
                "TipoDocumento" => "6",
                "NombreRazonSocial" => $this->limpiarCaracteresEspeciales($razon_social_emisor)
            ],
            "Destinatario" => $destinatario,
            // "Destinatario" => [
            //     "NroDocumento" => $guia->cliente_nro_documento,
            //     // "TipoDocumento" => "6",
            //     "TipoDocumento" => "{$cliente_documento_tipo}",
            //     // "NombreRazonSocial" => "Luis Ordoñez Villacorta"
            //     "NombreRazonSocial" => $this->limpiarCaracteresEspeciales($guia->cliente_razon_social)
            // ],
            "Proveedor" => [
                "NroDocumento" =>  $guia->proveedor_ruc ?? '',
                "TipoDocumento" => 6,
                "NombreRazonSocial" => $this->limpiarCaracteresEspeciales($guia->proveedor_nombre ?? '')
            ],
            "DocumentoRelacionado" => [
                // "descripcion" => "Factura",
                "descripcion" => "",
                // "nrorucemisor" => "20117332714",
                "nrorucemisor" => "",
                "NroDocumento" => "",
                // "TipoDocumento" => "01"
                "TipoDocumento" => ""
            ],
            // "CodigoMotivoTraslado" => "01",
            "CodigoMotivoTraslado" => "{$guia->motivo_traslado_id}",
            // "DescripcionMotivoTraslado" => "VENTA",
            "DescripcionMotivoTraslado" => $this->limpiarCaracteresEspeciales($guia->descripcion_motivo_traslado),
            // "PesoBrutoTotal" => 1,
            "PesoBrutoTotal" => $guia->peso_bruto_total,
            "UnidadPesobrutototal" => "KGM",
            // "UnidadPesobrutototal" => "",
            "NroPallets" => 0,
            "ModalidadTraslado" => $guia->modalidad_traslado,
            // "FechaInicioTraslado" => "2023-06-02",
            "FechaInicioTraslado" => $guia->fecha_inicio_traslado,
            "RucTransportista" => "{$guia->transportista_ruc}",
            "RazonSocialTransportista" => $this->limpiarCaracteresEspeciales("{$guia->transportista_nombre}"),
            "NroPlacaVehiculo" => $guia->vehiculo_placa,
            "NroDocumentoConductor" => "{$guia->chofer_dni}",
            "NombresdelConductor" => $this->limpiarCaracteresEspeciales("{$guia->chofer_nombre}"),
            "NrolicenciaConductor" => "{$guia->chofer_brevete}",
            "DireccionPartida" => [
                "Ubigeo" => "{$guia->ubigeo_partida}",
                "DireccionCompleta" => $this->limpiarCaracteresEspeciales("{$guia->direccion_partida}"),
                "codigoanexo" => "{$guia->codigo_anexo_partida}"
            ],
            "DireccionLlegada" => [
                "Ubigeo" => "{$guia->ubigeo_llegada}",
                "DireccionCompleta" => $this->limpiarCaracteresEspeciales("{$guia->direccion_llegada}"),
                "codigoanexo" => "{$guia->codigo_anexo_llegada}"
            ],
            "NumeroContenedor" => "",
            "Nropresintocontenedor" => "",
            "CodigoPuerto" => "",
            "VehiculoM1L" => 0,
            "BienesATransportar" => $body_detalle
        ];

        // dd(json_encode($body));
        // dd($body);

        $url_button = route('guiasalida.pdfDecode', ['guia'=> $guia->id]);

        $procede = true;
        $msj = "Guia electronica emitida <br><code>Debe esperar a que SUNAT apruebe el envio</code>  <br><a class='btn btn-success' href='{$url_button}' target='_blank'><i class='fa fa-external-link'></i> ver</a>";
        $msj_tipo = "";
        $log = "";

        $credencial = Parametro::find(1)->valor;
        try {
            $send = Http::withHeaders(['Credencial' => $credencial])
                        ->asJson() // Asegurarse de que se envíe como JSON
                        // ->put("{$api_facturacion}", $body)->object();
                        ->put("{$api_facturacion}", $body)->object();
            if ($send == null) {
                $procede = false;
                $msj = "No se obtuvo respuesta del facturador";
                $msj_tipo = "error";
            }
            if ($send != null) {
                // dd($send->Exito);
                if ($send->Exito == false) {
                    $procede = false;
                    $msj = "Ocurrio un error en el facturador: {$send->MensajeError}";
                    $msj_tipo = "error";
                }
            }
            // dd($send);
            try {
                $send->CodigoHash;

            } catch (Exception $e) {
                // dd($e);
                $procede = false;
                $msj = "Ocurrio un error con el envio API Guia";
                $msj_tipo = "error";
                $log = "{$e}";
            }
            // dd($send->CodigoHash);

        } catch (Exception $e) {
            //throw $th;
            // dd($e);
            $procede = false;
            $msj = "Ocurrio un error en el envio a sunat";
            $msj_tipo = "error";
            $log = "{$e}";
        }

        if ($procede == true) {

            $store = new FacturacionEnvio();
            $store->tabla = 'guia_salidas';
            $store->registro_id = $id;
            $store->trama_json = json_encode($body);
            $store->codigo_hash = $send->CodigoHash;
            $store->codigo_qr = $send->CodigoQr;
            $store->pdf417 = $send->pdf417;
            $store->exito = $send->Exito;
            $store->mensaje_error = $send->MensajeError;
            $store->pila = $send->Pila;
            try {
                // dd($store);
                $store->save();
            } catch (Exception $e) {
                //throw $th;
                // dd($e);
                $procede = false;
                $msj = "Ocurrio un error al guardar la respuesta del envio";
                $msj_tipo = "error";
                $log = "{$e}";
            }
        }

        if ($procede == true) {//store auditoria
            $this->registrarAuditoria($guia->id, 1, 'facturacion_envios', json_encode($body), strip_tags($msj));
        }

        if ($procede == true) {//actulizamos el id del envio en la tabla original

            $guia->envio_id = $store->id;
            $guia->enviado_facturador = 1;

            try {

                $guia->save();

            } catch (Exception $e) {
                //throw $th;
                // dd($e);
                $procede = false;
                $msj = "Ocurrio un error al actualizar el envio en la guia";
                $msj_tipo = "error";
                $log = "{$e}";
            }
        }

        if ($procede == true) {//obtener PDF y XML
            // dd('pdf');
            $api_facturacion_consultas = Parametro::find(8)->valor;
            $serie_format = str_pad($guia->serie, 3, "0", STR_PAD_LEFT);
            $bodyConsulta = array(
                'token' => $credencial,
                'serie' => "T{$serie_format}-{$guia->numero}",
                'tipodocumentoconsulta' => '09',
                'fecha' => $guia->fecha_emision,
                'tipodocumentorespuesta' => 'PDF'
            );
            // dd($bodyConsulta);
            try {
                $getPdf = Http::withHeaders(['Credencial' => $credencial])->post($api_facturacion_consultas, $bodyConsulta)->object();
                if ($getPdf->success == true) {
                    $storePdf = FacturacionEnvio::find($store->id);
                    $storePdf->pdf = $getPdf->data;
                    try {
                        $storePdf->save();

                    } catch (Exception $e) {
                        //throw $th;
                        // dd($e);

                    }
                }
                // dd($getPdf);
            } catch (Exception $e) {
                //throw $th;
                // dd($e);
                // $procede = false;
                // $msj = "";
            }
            // dd($getPdf);
            // dd($getPdf->data);

        }

        if ($procede == false) {
            if ($panel_origen != 'index') {
                $msj = "{$msj} <br> <button class='btn btn-success btn-sm' id='btnReintentarFacturar' data-id='{$id}'> <i class='fa-regular fa-paper-plane'></i> Reintentar Facturar</button>";
            }
        }



        return response()->json(['procede' => $procede, 'msj' => $msj, 'msj_tipo' => $msj_tipo, 'log' => $log]);
    }

    function limpiarCaracteresEspeciales($texto) {
        // Normalizar el texto para tratar caracteres acentuados
        $textoNormalizado = Normalizer::normalize($texto, Normalizer::FORM_D);

        // Reemplazar caracteres especiales
        $textoLimpio = preg_replace('/[^a-zA-Z0-9 ]/u', '', $textoNormalizado);

        return $textoLimpio;
    }

    public function pdf(GuiaSalida $guia, $valorada)
    {

        // dd($guia);
        // dd($valorada);
        $data = array();
        $ruc_entidad = Parametro::find(2)->valor;
        $nombreEntidad = Parametro::find(3)->valor;
        $direccion_entidad = Parametro::find(4)->valor;
        $telefonos = Parametro::find(4)->valor;
        $cabecera = (object) array(
            'nombre_entidad' => $nombreEntidad,
            'direccion_entidad' => $direccion_entidad,
            'telefono_entidad' => $telefonos,
            'ruc_entidad' => $ruc_entidad,
        );
        $data['cabecera'] = $cabecera;
        $texto_modalidad_traslado = "TRANSPORTE PUBLICO";
        if ($guia->modalidad_traslado == '02') {
            $texto_modalidad_traslado = "TRANSPORTE PRIVADO";
        }
        $guia->texto_modalidad_traslado = $texto_modalidad_traslado;
        $data['documento'] = $guia;
        // dd($guia);

        $formatter = new NumeroALetras();
        $texto_moneda = 'soles';
        $total_letras = $formatter->toInvoice($guia->total_venta, 2, $texto_moneda);
        $total_letras = Str::upper($total_letras);

        $data['guia'] = (object) array(
            'texto_moneda' => $texto_moneda,
            'concepto' => '-',
            'monto' => '0.00',
            'total_letras' => $total_letras,
            'nombre_cajero' => 'demo',
            'total_venta_gravada' => $guia->importe_sin_igv,
            'monto_descuento' => $guia->monto_descuento,
            'total_igv' => $guia->monto_igv,
            'total' => $guia->total_venta,
        );

        $detalle = GuiaSalidaDetalle::where('guia_salida_id', $guia->id)->get();
        // dd($detalle);
        $data['detalle'] = $detalle;
        $data['valorada'] = $valorada;
        $data['nro'] = 1;

        $peso_total = 0;
        // foreach ($detalle as $item) {
        //     if ($item->peso_total != null) {
        //         $peso_total = $peso_total + $item->peso_total;
        //     }
        // }
        $data['documento']->peso_total = $guia->peso_bruto_total;
        // dd($data);
        $pdf = Pdf::loadView('guia.salida.pdf', $data);
        // $('formato', $data);
        $pdf->setPaper('A4', 'portrait');
        $font = $pdf->getFontMetrics()->get_font("helvetica", "bold");
        // $pdf->getCanvas()->page_text(520, 810, "Pag. {PAGE_NUM} de {PAGE_COUNT}", $font, 10, array(0, 0, 0));
        return $pdf->stream();
    }

    public function pdfDecode(GuiaSalida $guia)
    {
        // dd($guia);
        // $getEnvioConPdf = FacturacionEnvio::where('tabla', 'guia_salidas')->where('registro_id', $guia->id)->whereNotNull('pdf')->first();
        $getEnvioConPdf = FacturacionEnvio::where('tabla', 'guia_salidas')->where('registro_id', $guia->id)->where('activo', 1)->first();
        // dd($getEnvioConPdf->pdf);
        // DB::table('users')->whereNotNull()
        // $pdfData = 'JVBERi0xLjQKJcfs...'; // Base64 encoded PDF data

        if ($getEnvioConPdf != null) {

            if ($getEnvioConPdf->pdf != null) {
                $pdfData = $getEnvioConPdf->pdf; // Base64 encoded PDF data
                $pdfDataDecoded = base64_decode($pdfData);
                return response($pdfDataDecoded)->header('Content-Type', 'application/pdf');
            }

            if ($getEnvioConPdf->pdf == null) {
                // dd('verificamos pdf');
                $credencial = Parametro::find(1)->valor;
                $api_facturacion_consultas = Parametro::find(8)->valor;
                $serie_format = str_pad($guia->serie, 3, "0", STR_PAD_LEFT);
                $bodyConsulta = array(
                    'token' => $credencial,
                    'serie' => "T{$serie_format}-{$guia->numero}",
                    'tipodocumentoconsulta' => '09',
                    'fecha' => $guia->fecha_emision,
                    'tipodocumentorespuesta' => 'PDF'
                );
                // dd($bodyConsulta);

                $procede = true;
                try {

                    $getPdf = Http::withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'Credencial' => $credencial,
                        ])->post($api_facturacion_consultas, $bodyConsulta)->object();
                } catch (Exception $e) {
                    //throw $th;
                    Log::warning('No se pudo obtener el PDF del comprobante: ' . $e->getMessage());
                }
                // dd($getPdf);
                if ($getPdf->success == true) {
                    $storePdf = FacturacionEnvio::find($getEnvioConPdf->id);
                    $storePdf->pdf = $getPdf->data;
                    try {
                        $storePdf->save();

                    } catch (Exception $e) {
                        //throw $th;
                        Log::error(__METHOD__ . ": " . $e->getMessage());
                        $procede = false;
                    }
                }


                if ($procede == true) {
                    $getEnvioConPdf = FacturacionEnvio::where('tabla', 'guia_salidas')->where('registro_id', $guia->id)->whereNotNull('pdf')->first();
                    $pdfData = $getEnvioConPdf->pdf; // Base64 encoded PDF data
                    $pdfDataDecoded = base64_decode($pdfData);
                    return response($pdfDataDecoded)->header('Content-Type', 'application/pdf');
                }
                if ($procede == false) {
                    return "No se pudo obtener el PDF";

                }

            }
        }

        if ($getEnvioConPdf != null) {
            return ('No existe envio de este comprobante');
        }


    }

    public function anular(Request $request)
    {
        // dd($request->post());
        $id = $request->post('id');

        $guia = GuiaSalida::find($id);

        $guia->guia_estado_id = 0;

        // dd($guia);

        $procede = true;
        $msj = "Guia anulada <br><code>Si la guia fue enviada a sunat tambien debe anularse en la Web Oficial</code>";
        $msj_tipo = "success";
        $log = "";

        try {
            $guia->save();

        } catch (Exception $e) {
            //throw $th;
            $procede = false;
            $msj = "No se pudo anular la Guia";
            $msj_tipo = "error";
            $log = "{$e}";
        }

        if ($procede == true) {//auditoria local
            $this->registrarAuditoria($guia->id, 3, 'guia_salidas', json_encode($guia), strip_tags($msj));
        }

        if ($procede == true) {
            $api_datos = Parametro::find(6)->valor;

            $fecha = Carbon::parse($guia->fecha_emision);
            $anio = $fecha->year;

            $cod_proveedor = $guia->proveedor_id;
            if ($cod_proveedor == null) {
                $cod_proveedor = $guia->cliente_id;
            }

            $body = [
                "anioGuiaRemision" => $anio,
                "codProveedor" => $cod_proveedor,
                "numSerie" => $guia->serie,
                "numeroGuia" => $guia->numero
            ];
            // dd($body);
            try {
                $anularRemoto = Http::post("{$api_datos}/AnulaGuiaDMK", $body)->object();
            } catch (Exception $e) {
                $procede = false;
                $msj = "No se pudo completar anulacion en DataMark";
                $msj_tipo = "";
                $log = "{$e}";
            }
            // dd($anularRemoto);
        }

        if ($procede == false) {
            $guia->guia_estado_id = 1;
            $guia->save();
        }

        $this->registrarAuditoria($guia->id, 3, 'guia_salidas_datamart', json_encode($body), strip_tags($msj));


        return response()->json(['procede' => $procede, 'msj' => $msj, 'msj_tipo' => $msj_tipo, 'log' => $log]);
    }

    public function modalOtrasGuias(Request $request)
    {
        // dd('hola ');
        return view('guia\salida\modal_otras_guias');
    }

    public function buscarOtrasGuias(Request $request)
    {
        $estado_id = $request->post('estado_id');
        $serie = $request->post('serie');
        $numero = $request->post('numero');

        $consulta = GuiaSalida::where('guia_estado_id', $estado_id);
        if ($serie != '') {
            $consulta = $consulta->where('serie', $serie);
        }
        if ($numero != '') {
            $consulta = $consulta->where('numero', $numero);
        }

        $list = $consulta->get();

        foreach ($list as $key => $item) {
            $list[$key]->estado_nombre = GuiaEstado::find($item->guia_estado_id)->nombre;
        }
        // dd($list);

        return view('guia.salida.tabla_otras_guias', compact('list'));
    }

    public function cargarOtraGuia(Request $request)
    {
        // Segunda copia del constructor de <tr>, con el mismo bug del apostrofe.
        // Devuelve las lineas como datos, en el mismo formato que agregarItem.
        $detalle = GuiaSalidaDetalle::where('guia_salida_id', $request->post('id'))->get();
        $igv     = Igv::vigente();

        return response()->json([
            'procede' => true,
            'lineas'  => $this->lineasParaVista($detalle),
            'igv'     => ['tasa' => $igv->tasa(), 'porcentaje' => $igv->porcentaje()],
        ]);
    }

    /**
     * Convierte el detalle guardado al formato que consume el componente.
     * Un solo mapeo, compartido por continuar() y cargarOtraGuia().
     */
    private function lineasParaVista($detalle): array
    {
        return collect($detalle)->map(function ($item) {
            return [
                'codArticulo'         => $item->codarticulo,
                'codigoBarra'         => $item->codigo_barra ?? '',
                'codPlu'              => $item->codarticulo,
                'descripcion'         => $item->descripcion,
                'cantidad'            => (float) $item->cantidad,
                'precioSinIgv'        => (float) $item->precio,
                'precioPublico'       => (float) ($item->precio_publico ?? 0),
                'costoArticulo'       => (float) ($item->costo_articulo ?? 0),
                'peso'                => (float) ($item->peso_unitario ?? 0),
                'stock'               => 0,
                'codUnidad'           => (int) ($item->cod_unidad ?? 9),
                'descUnidadMedida'    => $item->desc_unidad_medida ?? '',
                'siglaUmfe'           => $item->sigla_umfe ?? '',
                'tipoIgv'             => 1,
                'afectoIgv'           => true,
                'porcentajeDescuento' => (float) ($item->porcentaje_descuento ?? 0),
                'bonificacion'        => false,
                'esConsignado'        => (bool) ($item->es_consignado ?? false),
            ];
        })->values()->all();
    }

    public function registrarAuditoria($registro_id, $accion_id, $tabla, $data_json, $observaciones=null)
    {
        $procede = true;
        $msj = "Auditoria registrada";
        $msj_tipo = "success";
        $log = "";

        $empleado_id = User::find(Auth::id())->empleado_id;

        try {
            $auditoria = new Auditoria();
            $auditoria->registro_id = $registro_id;
            $auditoria->accion_id = $accion_id;
            $auditoria->tabla = $tabla;
            $auditoria->data_json = $data_json;
            $auditoria->observaciones = $observaciones;
            $auditoria->empleado_id = $empleado_id;

            $auditoria->save();
        } catch (Exception $e) {
            //throw $th;
            $procede = false;
            $msj = "No se pudo registrar la auditoria";
            $msj_tipo = "error";
            $log = "{$e}";
        }

        return (object) array ('procede' => $procede, 'msj' => $msj, 'msj_tipo' => $msj_tipo, 'log' => $log);

    }

    public function validarMesAbierto(Request $request)
    {
        // dd($request->post());
        $fecha_emision = $request->post('fecha_emision');

        $fecha = Carbon::parse($fecha_emision);

        $mes = $fecha->format('m');
        $anio = $fecha->format('Y');

        $procede = true;
        $msj = "Mes abierto";
        $msj_tipo = "error";
        $log = "";

        try {
            $api_datos = Parametro::find(6)->valor;

            $response = Http::post("{$api_datos}/ValidaMesAbierto",
                ['anio' => $anio, 'mes' => $mes]
            )->object();
            // dd($response->exito);
        } catch (Exception $e) {
            //throw $th;
            Log::error(__METHOD__ . ": " . $e->getMessage());
            $procede = false;
            $msj = "Error al obtener mes abierto";
            $log = "{$e}";
        }

        if ($procede == true) {
            if (($response->exito ?? false) != true) {
                $procede = false;
                $msj = "Mes no esta abierto para emision";
                $msj_tipo = "error";
                // $log = "";
            }
        }

        return response()->json(['procede' => $procede, 'msj' => $msj, 'msj_tipo' => $msj_tipo, 'log' => $log]);
    }

}
