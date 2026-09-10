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

        $this->refrescarEstadosSunat($list, $estados);

        $guias = $list->map(function ($g) use ($estados) {
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
        })->values();

        return response()->json(['procede' => true, 'guias' => $guias]);
    }

    /**
     * Actualiza desde SUNAT el estado de las guias que siguen pendientes.
     *
     * OJO: consulta el facturador UNA VEZ POR GUIA, en serie. Con un listado de
     * cincuenta guias son cincuenta llamadas HTTP encadenadas, y por eso el
     * listado tardaba lo suficiente como para necesitar un "Cargando...".
     * Se acota a las guias que de verdad pueden cambiar de estado (emitidas y
     * enviadas a SUNAT); moverlo a un job en segundo plano queda pendiente.
     */
    private function refrescarEstadosSunat($list, $estados): void
    {
        $pendientes = $list->filter(function ($g) {
            return (int) $g->guia_estado_id === 1 && (int) $g->envio_sunat === 1;
        });

        if ($pendientes->isEmpty()) {
            return;
        }

        $urlConsulta = Parametro::find(9)->valor ?? '';
        $rucEntidad  = Parametro::find(2)->valor ?? '';

        if ($urlConsulta === '') {
            return;
        }

        // Codigo de SUNAT -> id en la tabla guia_estados.
        $mapa = ['A' => 2, 'B' => 3, 'O' => 5];

        foreach ($pendientes as $g) {
            try {
                $respuesta = Http::post($urlConsulta, [
                    'rucremitente' => (string) $rucEntidad,
                    'serienumero'  => 'T' . str_pad($g->serie, 3, '0', STR_PAD_LEFT) . '-' . $g->numero,
                ])->object();

                if (! $respuesta || ! isset($respuesta->estado) || $respuesta->estado === null) {
                    continue;
                }

                $codigo = strtoupper(trim((string) $respuesta->estado));

                if (! isset($mapa[$codigo]) || $mapa[$codigo] === (int) $g->guia_estado_id) {
                    continue;
                }

                $nuevoEstado = $mapa[$codigo];

                $guia = GuiaSalida::find($g->id);
                if ($guia) {
                    $guia->guia_estado_id = $nuevoEstado;
                    $guia->mensaje_estado_sunat = $respuesta->mensaje ?? null;
                    $guia->save();
                }

                $g->guia_estado_id = $nuevoEstado;

            } catch (\Throwable $e) {
                // Que SUNAT no responda no puede impedir ver el listado.
                Log::error('Error consultando estado SUNAT', [
                    'guia_id' => $g->id,
                    'mensaje' => $e->getMessage(),
                ]);
            }
        }
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
        // dd($request->post());
        $api_datos = Parametro::find(6)->valor;

        $vendedor_codigo = $request->post('vendedor_codigo');

        $getVendedor = Http::get("{$api_datos}/ObtenerTrabajador?CodigoTrabajador={$vendedor_codigo}")->object()->trabajador;

        // $getVendedor = $getVendedor[0];
        // dd($getVendedor);
        $options = "";
        foreach ($getVendedor as $item) {
            $options .= "<option value='{$item->codTrabajador}'

                data-vendedor_nombre = '{$item->apellidos} {$item->nombres}'
            >{$item->apellidos} {$item->nombres}</option>";
        }

        return response()->json(['options' => $options]);
    }

    public function formBusquedaArticulo(Request $request)
    {
        $tipoBusqueda = $request->post('tipo_busqueda_articulo');
        $callSelect = true;
        if ($tipoBusqueda == 1) {
            $callSelect = false;
            $form = " <input class='form-control' id='producto_valor' name='producto_valor' placeholder='Escanea un producto' autocomplete='off' autofocus>
            ";
        }else{
            $form =  " <select class='form-select select_2' name='producto_select' id='producto_select' style='width: 100%' data-placeholder='Indicar un Articulo'></select>";
        }

        return response()->json(['form' => $form, 'callSelect' => $callSelect]);
    }

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
        $tipo = $request->get('tipo');//busqueda por razon social
        // dd($request->all());
        $maximo = 0;
        if ($tipo == 3) {
            $maximo = 2;
        }

        if (strlen($valor) > $maximo) {
            $listItems = Http::post("{$api_datos}/ObtenerProveedores",
                ['valor' => $valor, 'tipo' => $tipo]
            )->object()->proveedores;

        }

        // dd($listItems);
        $items = array();
        foreach ($listItems as $item) {

            $items[] = (object) array('id' => $item->codProveedor, 'text' => "[{$item->ruc}] {$item->nombreproveedor}", 'proveedor_nombre' => $item->nombreproveedor, 'proveedor_ruc' => $item->ruc, 'proveedor_direccion' => $item->direccion ?? '');
        }

        return response()->json(['items' => $items]);
    }

    public function listarClientes(Request $request)
    {
        $api_datos = Parametro::find(6)->valor;

        $valor = trim($request->get('term'));
        $tipo = $request->get('tipo_busqueda_cliente');//busqueda por razon social
        // dd($request->all());
        if (strlen($valor) > 2) {
            $listClientes = Http::post("{$api_datos}/obtenerCliente",
                ['valor' => $valor, 'tipo' => $tipo]
            )->object()->cliente;

        }

        // dd($listClientes);
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
        // dd($request->all());
        if (strlen($valor) >= 0) {
            $listItems = Http::post("{$api_datos}/ObtenerTransportista",
                ['valor' => $valor, 'tipo' => $tipo]
            )->object()->transportistas;

        }

        // dd($listItems);
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
        if ($procede == true) {

            // =================================================================
            // AÑADE ESTA LÍNEA PARA VER LOS DATOS DE LA CABECERA
            Log::info('Datos a guardar en [guia_salidas] (local):', $datos);
            // =================================================================

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
        if ($procede == true) {
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
            $detalle = json_decode($request->post('detalle'));
            $id = $store->id;

            // =================================================================
            // AÑADE ESTA LÍNEA PARA VER LOS DATOS DEL DETALLE
            Log::info('Datos a guardar en [guia_salida_detalles] (local):', $detalle);
            // =================================================================

            foreach ($detalle as $item) {

                if ($procede == true) {
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

                    try {
                        $guiaDetalle->save();
                    } catch (Exception $e) {
                        //throw $th;
                        // dd($e);
                        $procede = false;
                        $msj = "No se pudo registrar el detalle";
                        $msj_tipo = "error";
                        $log = "{$e}";
                    }
                }

            }
        }

        if ($procede == true) {
            $msj = "<b>Guia de Salida registrada Nº: {$datos['serie']}-{$datos['numero']}</b>";
            if ($datos['envio_sunat'] == 0) {
                $link = route('guiasalida.pdf', ['guia' => $store, 'valorada' => 0]);
                $msj = "{$msj} <a class='btn btn-sm btn-success' href='{$link}' target='_blank'><i class='fa fa-external-link'></i> Ver</a>";
            }
        }

        if ($procede == false) {
            $data_guardar_avance  = ($guardar_avance == true) ? 'true' : 'false' ;
            $msj = "{$msj} <br> <button class='btn btn-success btn-sm' data-guardar_avance= '{$data_guardar_avance}' id='btnReintentar'><i class='fa-regular fa-paper-plane'></i> Reintentar</button>";
        }

        $this->registrarAuditoria($store->id, 1, 'guia_salidas', json_encode($datos), $obsevracion_auditoria);

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
