<?php

namespace App\Http\Controllers\Guia;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\GuiaEstado;
use App\Models\GuiaIngreso;
use App\Models\GuiaIngresoDetalle;
use App\Models\Parametro;
use App\Models\Serie;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use App\Domain\Shared\ValueObjects\Igv;
use App\Http\Requests\Guia\AgregarItemRequest;
use Illuminate\Support\Facades\DB;
use Luecano\NumeroALetras\NumeroALetras;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;


class GuiaIngresoController extends Controller
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
        return view('guia.ingreso.index');
    }
    public function listar(Request $request)
    {
        // Devolvia una vista parcial (HTML) que el JavaScript inyectaba con
        // $('#resultados').html(). Eso ataba el diseno de la tabla al backend y
        // obligaba a re-inicializar DataTables a mano en cada busqueda. Ahora
        // devuelve datos y la vista los pinta.
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin    = $request->input('fecha_fin');
        $serie       = $request->input('serie');
        $numero      = $request->input('numero');

        $consulta = DB::table('guia_ingresos')
            ->whereBetween('fecha_emision', [$fechaInicio, $fechaFin])
            ->where('activo', 1);

        if ($serie !== null && $serie !== '') {
            $consulta = $consulta->where('serie', $serie);
        }
        if ($numero !== null && $numero !== '') {
            $consulta = $consulta->where('numero', $numero);
        }

        $list = $consulta->orderBy('fecha_emision', 'desc')->orderBy('id', 'desc')->get();

        // Los estados se traen de una vez. Antes se hacia GuiaEstado::find()
        // dentro del bucle: una consulta por fila.
        $estados = GuiaEstado::pluck('nombre', 'id');

        $guias = $list->map(function ($g) use ($estados) {
            return [
                'id'            => $g->id,
                'documento'     => $g->serie . '-' . $g->numero,
                'serie'         => $g->serie,
                'numero'        => (int) $g->numero,
                'razonSocial'   => $g->proveedor_nombre,
                'fechaEmision'  => $g->fecha_emision,
                'totalVenta'    => (float) $g->total_venta,
                'guiaEstadoId'  => (int) $g->guia_estado_id,
                'estadoNombre'  => $estados[$g->guia_estado_id] ?? '',

                'mostrarEliminar'          => (int) $g->guia_estado_id === 1,
                'mostrarGuardarDatamarket' => (int) $g->enviado_datamarket !== 1,
                'mostrarContinuar'         => (int) $g->guia_estado_id === 4,

                'urlPdf'         => route('guiaingreso.pdf', ['guia' => $g->id, 'valorada' => 0]),
                'urlPdfValorada' => route('guiaingreso.pdf', ['guia' => $g->id, 'valorada' => 1]),
                'urlContinuar'   => route('guiaingreso.continuar', ['guia' => $g->id]),
            ];
        })->values();

        return response()->json(['procede' => true, 'guias' => $guias]);
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $api_datos = Parametro::find(6)->valor;
        // dd($api_datos);

        $listProveedores = [];
        $listFormasPago = Http::get("{$api_datos}/ObtenerFormasPago")->object()->formasdePago;
        $listTipoOperacion = Http::get("{$api_datos}/ObtenerOperacion")->object()->operaciones;
        $listAlmacenes = Http::get("{$api_datos}/ObtenerAlmacenes")->object()->almacenes;
        // dd($listAlmacenes);
        // $listArticulos = Http::post(route('simulacion.ObtenerArticulos'), [])->object();
        $listArticulos = [];
        // dd($listArticulos);
        // $listVendedores = Http::get("{$api_datos}/ObtenerTrabajador?CodigoTrabajador=-1")->object()->trabajador;
        $listVendedores = [];

        // foreach ($listVendedores as $key => $value) {
        //     $listVendedores[$key]->selected = '';
        // }
        // $getVendedor = $listVendedores[0];

        $listSeries = Http::get("{$api_datos}/obtenerSeriesNumerosGuia")->object()->serienumeros;

        $lineasDetalle = [];

        return view('guia.ingreso.create', compact('listProveedores', 'listFormasPago', 'listTipoOperacion', 'listAlmacenes', 'listArticulos', 'listVendedores', 'listSeries', 'lineasDetalle'));
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

    public function continuar(GuiaIngreso $guia)
    {
        $listProveedores = [];
        $api_datos = Parametro::find(6)->valor;
        // dd($guia);
        // dd($listProveedores);
        if ($guia->proveedor_id != null) {
            $listProveedores = Http::post("{$api_datos}/ObtenerProveedores", ['valor' => $guia->proveedor_id, 'tipo' => 1])->object()->proveedores;
        }

        $listFormasPago = Http::get("{$api_datos}/ObtenerFormasPago")->object()->formasdePago;
        $listTipoOperacion = Http::get("{$api_datos}/ObtenerOperacion")->object()->operaciones;
        // dd($listTipoOperacion);
        foreach ($listTipoOperacion as $key => $value) {
            $selected = "";
            if ($guia->tipo_operacion_id == $value->tipoOperacion) {
                $selected = "selected";
            }
            $listTipoOperacion[$key]->selected = $selected;
        }
        $listAlmacenes = Http::get("{$api_datos}/ObtenerAlmacenes")->object()->almacenes;
        // dd($listAlmacenes);
        foreach ($listAlmacenes as $key => $value) {
            $selected = "";
            if ($guia->codalmacen == $value->codAlmacen) {
                $selected = "selected";
            }
            $listAlmacenes[$key]->selected = $selected;
        }
        // dd($listAlmacenes);
        // $listArticulos = Http::post(route('simulacion.ObtenerArticulos'), [])->object();
        $listArticulos = [];
        // dd($listArticulos);
        $listVendedores = Http::get("{$api_datos}/ObtenerTrabajador?CodigoTrabajador={$guia->vendedor_id}")->object()->trabajador;
        // dd($listVendedores);

        foreach ($listVendedores as $key => $value) {
            $selected = "";
            if ($value->codTrabajador == $guia->vendedor_id) {
                $selected = "selected";
            }

            $listVendedores[$key]->selected = $selected;
        }

        $detalle = GuiaIngresoDetalle::where('guia_ingreso_id', $guia->id)->get();
        // dd($detalle);
        
        $lineasDetalle = $this->lineasParaVista($detalle);

        return view('guia.ingreso.create', compact('guia','listProveedores', 'listFormasPago', 'listTipoOperacion', 'listAlmacenes', 'listArticulos', 'listVendedores', 'detalle', 'lineasDetalle'));
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

public function agregarItem(AgregarItemRequest $request)
    {
        // Antes este metodo devolvia 40 lineas de HTML concatenado, con los
        // atributos entre comillas simples. Una descripcion con apostrofe
        // ("L'OREAL") truncaba la fila y el articulo perdia su descripcion sin
        // ningun aviso. Ahora devuelve datos; el HTML lo arma la vista.
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

        // Se conserva la regla previa: precio_visual, y si no llega se cae al
        // costo del articulo y luego al precio publico.
        $precioBase = (float) ($datos['precio_visual']
            ?? $datos['costo_articulo']
            ?? $datos['precio_publico']
            ?? 0);

        $igv     = Igv::vigente();
        $tipoIgv = (int) ($datos['tipo_igv'] ?? 1);

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
                'codUnidad'           => (int) ($datos['cod_unidad'] ?? 9),
                'descUnidadMedida'    => $datos['desc_unidad_medida'] ?? '',
                'siglaUmfe'           => $datos['sigla_umfe'] ?? '',
                'tipoIgv'             => $tipoIgv,
                'afectoIgv'           => $tipoIgv === 1,
                'porcentajeDescuento' => 0,
                'bonificacion'        => false,
                'esConsignado'        => false,
            ],
            'igv' => ['tasa' => $igv->tasa(), 'porcentaje' => $igv->porcentaje()],
        ]);
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

    // Aqui estaba formBusquedaArticulo(), que devolvia el <input> o el <select>
    // del buscador como string para que el JS lo pusiera con innerHTML. El
    // buscador ya se pinta en el Blade y cambia de modo con x-model: el
    // endpoint se quedo sin consumidores y su ruta tambien se elimino.

    public function listarArticulos(Request $request)
    {
        $api_datos = Parametro::find(6)->valor;

        // La ruta es GET, pero esto leia $request->post('tipo'), que en un GET
        // siempre es null. El tipo de consulta nunca llegaba a la API y la
        // busqueda por nombre no devolvia nada. input() lee query y body.
        $valor          = trim((string) $request->input('term', ''));
        $tipoconsulta   = (int) $request->input('tipo_busqueda_articulo', $request->input('tipo', 1));
        $codestacion    = $request->input('codestacion', 1);
        $codalmacen     = $request->input('codalmacen', 1);
        $codlistaprecio = $request->input('codlistaprecio', 1);

        // Por codigo de barras basta con pocos caracteres; por nombre se exige
        // algo mas para no traer media base en cada tecla.
        $maximo = ($tipoconsulta === 4) ? 2 : 0;
        // dd($request->all());
        $listArticulos = [];

        if (strlen($valor) > $maximo) {
            try {
                $listArticulos = Http::post("{$api_datos}/ObtenerArticulo", [
                    'valor'          => $valor,
                    'tipoconsulta'   => $tipoconsulta,
                    'codestacion'    => $codestacion,
                    'codalmacen'     => $codalmacen,
                    'codlistaprecio' => $codlistaprecio,
                ])->object()->articulos ?? [];
            } catch (Exception $e) {
                // Antes una caida de la ApiGRE dejaba $listArticulos sin
                // definir y el foreach de abajo reventaba con un 500.
                return response()->json([
                    'items' => [],
                    'error' => 'No se pudo consultar el catalogo de articulos.',
                ], 502);
            }
        }

        // dd($listArticulos);
        $items = array();
        foreach ($listArticulos as $item) {
            $stock = $item->stock ?? 0;
            $items[] = (object) array('id' => $item->codArticulo, 'text' => "[{$item->codBarra}] {$item->nombreArticulo}", 'codigo_barra' => $item->codBarra, 'descripcion' => $item->nombreArticulo, 'precio_publico' => $item->precioPublico, 'precio_sin_igv' => $item->precioSinIGV, 'peso' => $item->peso ?? 0, 'cod_unidad' => $item->codUnidad, 'desc_unidad_medida' => $item->descUnidadMedida ?? '', 'sigla_umfe' => $item->siglaUMFE ?? '', 'costo_articulo' => $item->costoArticulo, 'tipo_igv' => $item->tipoIgv ?? 0, );
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

    $procede = true;
    $msj = "Articulo encontrado";
    $msj_tipo = "success";
    $log = "";
    $getArticulo = null;

    try {
        $responseArticulos = Http::post("{$api_datos}/ObtenerArticulo", [
            'valor' => $valor, 
            'tipoconsulta' => 1, 
            'codestacion' => $codestacion, 
            'codalmacen' => $codalmacen, 
            'codlistaprecio' => $codlistaprecio
        ])->object()->articulos;
        
    } catch (Exception $e) {
        $procede = false;
        $msj = "Ocurrió un problema al buscar";
        $msj_tipo = "error";
        $log = "{$e}";
        $responseArticulos = [];
    }
    
    if ($procede == true) {
        if (count($responseArticulos) == 0) {
            $procede = false;
            $msj = "Artículo no encontrado";
            $msj_tipo = "error";
        } else {
            // Obtener el primer artículo del array
            $articuloRaw = $responseArticulos[0];
            
            // 🔥 MAPEAR EL OBJETO CON LOS MISMOS NOMBRES QUE USA listarArticulos()
            $getArticulo = (object) [
                'codArticulo'       => $articuloRaw->codArticulo,
                'codBarra'          => $articuloRaw->codBarra,
                'nombreArticulo'    => $articuloRaw->nombreArticulo,
                'precioPublico'     => $articuloRaw->precioPublico,
                'precioSinIGV'      => $articuloRaw->precioSinIGV,
                'peso'              => $articuloRaw->peso ?? 0,
                'codUnidad'         => $articuloRaw->codUnidad,
                'descUnidadMedida'  => $articuloRaw->descUnidadMedida ?? '',
                'siglaUMFE'         => $articuloRaw->siglaUMFE ?? '',
                'costo_articulo'    => $articuloRaw->costoArticulo,  // ✅ Conversión de camelCase a snake_case
                'tipo_igv'          => $articuloRaw->tipoIgv ?? 0,   // ✅ Conversión de camelCase a snake_case
                'afecto'            => $articuloRaw->afecto ?? 1,    // Por si lo necesitas
            ];
        }
    }

    return response()->json([
        'procede'      => $procede, 
        'msj'          => $msj, 
        'msj_tipo'     => $msj_tipo, 
        'log'          => $log, 
        'getArticulo'  => $getArticulo
    ]);
}

    // Aqui vivia store2(), una copia vieja de store() que insertaba en la
    // ApiGRE y en local sin transaccion y con la serie cableada a 1. No la
    // llamaba nadie: no tenia ruta (Route::resource solo publica store), y no
    // aparecia en ninguna vista ni en public/js. Se elimina para que nadie la
    // tome por el camino bueno al buscar "store" en este archivo.

    public function modalStore(Request $request)
    {
        return view('guia.ingreso.modal-store');
    }

    public function store(Request $request)
    {
        // dd($request->post());

        $api_datos = Parametro::find(6)->valor;
        $id = "";
        $es_guia_interna = $request->post('es_guia_interna');

        $datos = $request->post();
        // El formulario de INGRESO manda el id del avance en 'id_continuar';
        // el de salida lo llama 'id_continua'. Aqui se leia el nombre de
        // salida, asi que al generar la guia definitiva el avance de origen no
        // se desactivaba nunca: quedaban las dos filas activas en el listado y
        // el usuario podia seguir retomando un borrador ya emitido.
        // Se aceptan los dos nombres para no romper nada que ya mande el otro.
        $id_continuar = $request->post('id_continuar', $request->post('id_continua'));
        $datos['guardar_avance'] = ($datos['guardar_avance'] == 'true') ? true : false ;
        $guardar_avance = $datos['guardar_avance'];

        $datos['indicar_proveedor'] = ($datos['indicar_proveedor'] ?? '' == 'on') ? true : false ;
        

        $procede = true;
        $msj_tipo = "success";
        $log = "";
        $datos['guia_estado_id'] = 1; //registrado-emitida
        
        $url_redirect = route('guiasalida.index');

        // Una guia GENERADA tiene que tener lineas.
        //
        // Sin esto la cabecera se creaba igual, con su total y consumiendo el
        // correlativo de la serie, y el detalle quedaba vacio. La guia se veia
        // normal en el listado y el DataMart la rechazaba despues con
        // "Documento incompleto", sin que nadie supiera de donde salio.
        //
        // El borrador (guardar_avance) si puede ir sin lineas: para eso es.
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
            if ($es_guia_interna == 1) {
                // dd($es_guia_interna);
                
                $asignarSerie = $this->asignarSerie($datos['serie']);
                // dd($asignarSerie);
                $procede = $asignarSerie->procede;
            }
        }

        if ($guardar_avance == true) {
            $msj = "Avance de guia registrada";
            $datos['guia_estado_id'] = 4;//estado avance
        }

        // validacion antes del store
        if ($procede == true) {
            // dd($request->post());
            // $datos['fecha_emision'] = date('Y-m-d');
            $datos['fecha_emision'] = $request->post('fecha_emision');
            $datos['hora_emision'] = date('H:i:s');

            if ($id_continuar != null) {
                // dd('desactivamos el activo anterior');
                $guia_avance = GuiaIngreso::find($id_continuar);
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
                if ($es_guia_interna == 0) {
                    $datos['serie_id'] = null;
                    $datos['serie'] = $datos['serie_externa'];
                    
                }
                if ($es_guia_interna == 1) {
                    
                    $datos['serie_id'] = $asignarSerie->serieAsignada->id;
                    $datos['numero'] = intval($asignarSerie->serieAsignada->numero) +1; 
                }
            }

        }

        //registro en store
        //
        // Cabecera, correlativo de serie y detalle son UNA operacion. Antes se
        // guardaban por separado y sin transaccion: si una linea fallaba a la
        // mitad, la cabecera y el correlativo ya estaban escritos y quedaba una
        // guia incompleta imposible de distinguir de una buena.
        $store = null;
        if ($procede == true) {
            DB::beginTransaction();

            Log::info('STORE LOCAL - Intentando crear Cabecera GuiaIngreso:', $datos);
            try {
                $store = GuiaIngreso::create($datos);
            } catch (Exception $e) {
                // dd($e);
                $procede = false;
                $msj = "No se pudo registrar en Nube";
                $msj_tipo = "success";
                $log = "{$e}";
            }

        }

        // actualizar serie nube
        //
        // Solo cuando la guia se GENERA. El borrador no pasa por asignarSerie()
        // -no consume correlativo a proposito-, asi que $asignarSerie ni
        // existe: guardar un avance de una guia interna moria con "Undefined
        // variable $asignarSerie" y el usuario perdia el trabajo con un 500.
        if ($procede == true && $guardar_avance == false) {
            if ($es_guia_interna == 1) {
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
        }

        //registrar detalle
        if ($procede == true) {
            $detalle = $lineas;
            $id = $store->id;
            foreach ($detalle as $item) {
                
                if ($procede == true) {
                    // El try envuelve la linea ENTERA, no solo el save().
                    //
                    // Un campo que falta en la linea -paso con precio_publico-
                    // revienta al ARMAR el modelo, antes de guardarlo, y eso
                    // es un Error de PHP, no una Exception: se escapaba de
                    // aqui y salia un 500. Ahora se convierte en un mensaje y
                    // la transaccion deshace la cabecera, para que no vuelva a
                    // quedar una guia sin detalle.
                    try {
                        $guiaDetalle = new GuiaIngresoDetalle();
                        $guiaDetalle->guia_ingreso_id = $store->id;
                        $guiaDetalle->codarticulo = $item->codarticulo;
                        $guiaDetalle->precio = $item->precio;
                        $guiaDetalle->cantidad = $item->cantidad;
                        $guiaDetalle->importe = $item->importe;
                        $guiaDetalle->porcentaje_descuento = $item->porcentaje_descuento;
                        $guiaDetalle->monto_descuento = $item->monto_descuento;

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

                        // <--- NUEVO: Guardar el flag consignado en el detalle
                        // Solo si el cliente usa consignados (la columna es_consignado puede
                        // no existir en clientes que no la usan). Apagado por defecto.
                        if (\App\Support\ConfiguracionEmpresa::usaConsignados()) {
                            $guiaDetalle->es_consignado = $item->es_consignado ?? 0;
                        }

                        // --- AGREGA ESTO JUSTO ANTES DEL SAVE() ---
                        Log::info("STORE LOCAL - Guardando item detalle [{$item->codarticulo}]:", $guiaDetalle->toArray());
                        // ------------------------------------------
                        // Throwable, no Exception: un dato que falta en la linea
                        // -paso una vez con precio_publico- lanza un Error, que no
                        // es una Exception y se escapaba de aqui. El resultado era
                        // un 500 con la cabecera ya escrita y sin detalle: asi
                        // nacieron las guias que el DataMart rechaza por
                        // "Documento incompleto". Ahora se convierte en un mensaje
                        // y la transaccion deshace la cabecera.
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

        // Cierre de la transaccion abierta en el registro de la cabecera.
        if ($store !== null) {
            if ($procede == true) {
                DB::commit();
            } else {
                DB::rollBack();
                $id = "";
            }
        }

        // La auditoria se escribe FUERA de la transaccion y solo si hay guia.
        // Antes esta linea leia $store->id sin comprobar nada: si la cabecera
        // fallaba, $store no existia y el error real quedaba tapado por un
        // error 500 de PHP.
        if ($store !== null && $procede == true) {
            $this->registrarAuditoria($store->id, 1, 'guia_ingresos', json_encode($datos), strip_tags($msj));
        }

        return response()->json(['procede' => $procede, 'msj' => $msj, 'msj_tipo' => $msj_tipo, 'log' => $log, 'id' => $id]);
    }
    
    /**
     * Antes borraba las comillas y los apostrofes de la descripcion:
     *
     *     L'OREAL SHAMPOO  ->  LOREAL SHAMPOO
     *
     * Era el parche para el bug de concatenacion de HTML, cuando el <tr> se
     * armaba con atributos entre comillas simples y un apostrofe truncaba la
     * fila. Ese HTML ya no existe, pero el parche seguia destruyendo el nombre
     * del articulo de forma permanente en la base.
     *
     * Ahora solo se normalizan los espacios y se quitan los caracteres de
     * control, que si rompen el XML que se manda al DataMart.
     */
    function limpiarCaracteres($cadena)
    {
        $limpia = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $cadena);
        return trim(preg_replace('/\s+/u', ' ', $limpia));
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
        $api_datos = Parametro::find(6)->valor;
        $panel_origen = $request->post('panel_origen');
        $id = $request->post('id');

        $guia = GuiaIngreso::find($id);
        
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

        $procede = true;
        $msj = "<b><i class='fa fa-check-double'></i>Guia Nº: {$guia->serie}-{$guia->numero} registrada en DataMarket</b>";
        $msj_tipo = "success";
        $log = "";

        $detalle = GuiaIngresoDetalle::where('guia_ingreso_id', $guia->id)->get();
        
        $articulosConsignados = [];
        $body_detalle = [];
        $maestrosActualizados = 0; // filas del MaestroArticulo actualizadas (para el SweetAlert)

        // ============================================================
        // PASO 1: IDENTIFICAR artículos consignados
        // ============================================================
        foreach ($detalle as $item) {
            if (($item->es_consignado ?? 0) == 1) {
                $articulosConsignados[] = $item->codarticulo;
            }
        }

        // ============================================================
        // PASO 2: (MOVIDO) La actualización de MaestroArticulo se hace
        // AHORA DESPUÉS de la API (ver PASO 5.5). Antes corría aquí, ANTES
        // del SP InsertarGuiasOdooDmk, y el SP pisaba 'consignacion' por
        // timing — el mismo problema que ya se resolvió para el detalle.
        // ============================================================

        // ============================================================
        // PASO 3: ARMAR BODY DETALLE (CON CORRECCIÓN DE IGV Y 4 DECIMALES)
        // ============================================================
        // Tu base de datos SQL Server soporta 5 decimales en Precio y 7 en Importe.
        // Usaremos 4 decimales para máxima precisión matemática.
        foreach ($detalle as $item) {
            
            // Convertimos a float para asegurar que sea número
            $precioBase = floatval($item->precio);
            $importeBase = floatval($item->importe);

            // LÓGICA MATEMÁTICA:
            // 1. Multiplicamos por 1.18 para agregar el IGV (DataMarket lo espera así).
            // 2. Redondeamos a 4 decimales para evitar pérdida de centavos en la división inversa.
            // Ejemplo: 30.47 * 1.18 = 35.9546
            $precioConIgv = round($precioBase * 1.18, 4);
            $importeConIgv = round($importeBase * 1.18, 4);

            $body_detalle[] = array(
                "anioGuia" => $anio,
                "cantidad" => $item->cantidad,
                "codArticulo" => $item->codarticulo,
                "estadoProceso" => "0",
                
                // Enviamos "35.9546"
                "importeDetalle" => number_format($importeConIgv, 4, '.', ''),
                
                "item" => 1, 
                "numSerie" => $guia->serie,
                "numeroGuia" => $guia->numero,
                
                // Enviamos "35.9546"
                "precio" => number_format($precioConIgv, 4, '.', ''),
                
                "tipoGuia" => "N",
                "unidadMedida" => $item->cod_unidad ?? 1,

                // El flag viaja CON la guia. Antes no iba en el cuerpo y el
                // DataMart lo copiaba de MaestroArticulo.consignacion, que es
                // lo que obligaba a marcar el maestro por conexion directa
                // antes del stored procedure.
                "esConsignado" => (($item->es_consignado ?? 0) == 1) ? 1 : 0,
                "tipoIgv"      => $item->tipo_igv ?? 1,
            );
        }

        // ============================================================
        // PASO 4: Armar body completo
        // ============================================================
        $body = [
            "anioGuiaRemision" => $anio,
            "breveteChofer" => null,
            "codAlmacen" => $guia->codalmacen,
            "codAlmacenDestino" => null,
            "codAlmacenOrigen" => null,
            "codCliente" => null,
            "codEstacion" => $guia->codestacion,
            "codListaPrecio" => null,
            "codProveedor" => $guia->proveedor_id ?? 0, 
            "codtrabajador" => $guia->vendedor_id,
            "comentario" => $guia->comentario,
            "descuento" => $guia->monto_descuento,
            
            "detalle" => $body_detalle, // <--- Aquí va el array corregido
            
            "direccionllegada" => null,
            "direccionpartida" => null,
            "dnichofer" => null,
            "estadoProceso" => "0",
            "fechaEmision" => $guia->fecha_emision,
            "formapago" => $guia->forma_pago_id,
            "igv" => $guia->monto_igv,
            "modalidadTransporte" => "18",
            "nombreTransportista" => null,
            "nombrechofer" => null,
            "numSerie" => $guia->serie,
            "seriefactura" => $guia->pedido_serie,
            "numeroFactura" => $guia->pedido_numero,
            "numeroGuia" => $guia->numero,
            "placavehiculo" => null,
            "rucTransportista" => null,
            "tipoGuia" => "N",
            "tipoOperacion" => $guia->tipo_operacion_id,
            "tipomonda" => $guia->divisa_id, 
            "totalVenta" => $guia->total_venta,
            "ubigeollegada" => null,
            "ubigeopartida" => null,
            "valorVenta" => $guia->importe_sin_igv,
        ];

        Log::info('DATAMARKET - Payload a enviar (Body completo):', $body);

        // ============================================================
        // PASO 5: Enviar a la API
        // ============================================================
        try {
            $storeRemoto = Http::post("{$api_datos}/InsertGuiaDMK", $body)->object();

            // Un 404 o un HTML de error devuelven algo SIN la propiedad exito,
            // y con isset() eso pasaba por bueno: al usuario se le decia
            // "registrada en DataMarket" con la guia nunca enviada. Aqui el
            // exito tiene que ser afirmado explicitamente.
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

        // El PASO 5.5 ya no existe. Marcaba MaestroArticulo.consignacion y
        // parchaba DetalleGuiaRemision.esconsignado por conexion DIRECTA a
        // SQL Server, despues de llamar al stored procedure. De ahi salia el
        // problema de orden: segun el controller corria antes o despues, y el
        // resultado cambiaba. Ahora el flag viaja dentro del cuerpo de la guia
        // y lo resuelve el DataMart en la misma transaccion del insert.


        // ============================================================
        // PASO 6: Dejar constancia local del envio
        //
        // Esto faltaba SOLO en ingreso -salida si lo hacia-, asi que la guia
        // se enviaba bien y seguia marcada como no enviada: el listado ofrecia
        // "Reenviar a DataMart" para siempre y no habia forma de distinguir la
        // que ya salio de la que no.
        // ============================================================
        if ($procede == true) {
            try {
                $guia->enviado_datamarket = 1;
                $guia->save();
            } catch (Exception $e) {
                $procede = false;
                $msj = "Se envio a DataMarket pero fallo al actualizar el estado local.";
                $msj_tipo = "error";
                $log = "Error Local DB: " . $e->getMessage();
            }
        }

        if ($procede == false && $panel_origen != 'index') {
            $msj = "{$msj} <br> <button class='btn btn-success btn-sm' id='btnReintentarDataMart' data-id='{$id}' ><i class='fa-regular fa-paper-plane'></i> Reintentar</button>";
        }

        if (method_exists($this, 'registrarAuditoria')) {
            $this->registrarAuditoria($guia->id, 1, 'guia_ingresos_datamart', json_encode($body), strip_tags($msj));
        }

        return response()->json([
            'procede' => $procede,
            'msj' => $msj,
            'msj_tipo' => $msj_tipo,
            'log' => $log,
            'consignados_enviados' => count($articulosConsignados),
            'maestros_actualizados' => $maestrosActualizados
        ]);
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    public function pdf(GuiaIngreso $guia, $valorada)
    {

        // dd($guia);
        $data = array();
        $ruc_entidad = Parametro::find(2)->valor;
        $nombreEntidad = Parametro::find(3)->valor;
        $direccion_entidad = Parametro::find(4)->valor;
        // El 4 es la direccion; los telefonos son el 5. Con el 4 la cabecera
        // del PDF imprimia la direccion donde dice "Telf:", y cuando la
        // direccion estaba vacia el telefono salia vacio tambien. Venia asi
        // desde el proyecto anterior.
        $telefonos = optional(Parametro::find(5))->valor;
        $cabecera = (object) array(
            'nombre_entidad' => $nombreEntidad,
            'direccion_entidad' => $direccion_entidad,
            'telefono_entidad' => $telefonos,
            'ruc_entidad' => $ruc_entidad,
        );
        $data['cabecera'] = $cabecera;

        $data['documento'] = $guia;
        // dd($guia);


        $formatter = new NumeroALetras();
        $texto_moneda = 'soles';
        // dd($guia->total_venta);
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

        $detalle = GuiaIngresoDetalle::where('guia_ingreso_id', $guia->id)->get();
        // dd($detalle);
        $data['valorada'] = $valorada;
        $data['detalle'] = $detalle;


        $pdf = Pdf::loadView('guia.ingreso.pdf', $data);
        // $('formato', $data);
        $pdf->setPaper('A4', 'portrait');
        $font = $pdf->getFontMetrics()->get_font("helvetica", "bold");
        // $pdf->getCanvas()->page_text(520, 810, "Pag. {PAGE_NUM} de {PAGE_COUNT}", $font, 10, array(0, 0, 0));
        return $pdf->stream();
    }
    
    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function eliminar(Request $request)
    {
        /*
         * TRES DEFECTOS QUE TENIA ESTE METODO
         *
         * 1. $body se construia dentro del if ($procede == true) pero se usaba
         *    despues, fuera de ese if, en registrarAuditoria(). Si el guardado
         *    local fallaba, $body no existia: aviso de variable indefinida y
         *    una fila de auditoria con "null" como payload.
         *
         * 2. Ponia activo = 0 y, si el DataMart rechazaba la eliminacion,
         *    restauraba guia_estado_id pero NO activo. La guia quedaba invisible
         *    en el listado (activo = 0) pero viva en el DataMart: desaparecia de
         *    la pantalla sin haberse eliminado de verdad.
         *
         * 3. En ese mismo camino de error dejaba msj_tipo = "", asi que el aviso
         *    salia sin icono y sin color de error.
         */
        $id   = $request->input('id');
        $guia = GuiaIngreso::find($id);

        // Los fallos de negocio se responden con 200 y procede=false, que es la
        // convencion del resto de la aplicacion y la que entiende Gre.request.
        // Antes esta accion devolvia 404, 500 y 502: el mismo tipo de fallo
        // contado de dos maneras distintas segun la pantalla.
        if (! $guia) {
            return response()->json([
                'procede'  => false,
                'msj'      => 'La guia ya no existe.',
                'msj_tipo' => 'error',
            ]);
        }

        $documento = $guia->serie . '-' . $guia->numero;

        // Se recuerda el estado previo para poder revertir si el DataMart falla.
        $activoPrevio = $guia->activo;
        $estadoPrevio = $guia->guia_estado_id;

        try {
            $guia->activo = 0;
            $guia->save();
        } catch (Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage());

            return response()->json([
                'procede'  => false,
                'msj'      => "No se pudo eliminar la guia {$documento}.",
                'msj_tipo' => 'error',
            ]);
        }

        $this->registrarAuditoria($guia->id, 4, 'guia_ingresos', json_encode($guia), "Guia {$documento} eliminada");

        $anio = Carbon::parse($guia->fecha_emision)->year;

        $body = [
            'anioGuiaRemision' => $anio,
            'codProveedor'     => $guia->proveedor_id,
            'numSerie'         => $guia->serie,
            'numeroGuia'       => $guia->numero,
        ];

        try {
            $api_datos = Parametro::find(6)->valor;

            /*
             * Se MIRA la respuesta. Antes se llamaba y se tiraba el resultado:
             * si el DataMart contestaba que no habia podido borrarla, o si la
             * ApiGRE devolvia un 500 -que Http::post no convierte en excepcion-,
             * la pantalla decia igualmente "eliminada" y la guia quedaba
             * marcada como borrada aqui y viva alla. Mismo fallo que tenia
             * storeDataMart.
             */
            $respuesta = Http::post("{$api_datos}/EliminaGuiaDMK", $body)->object();

            if (! is_object($respuesta) || ! isset($respuesta->exito) || $respuesta->exito == false) {
                $motivo = (is_object($respuesta) && ! empty($respuesta->msgerror))
                    ? $respuesta->msgerror
                    : 'la ApiGRE no respondio como se esperaba';

                throw new Exception($motivo);
            }

        } catch (\Throwable $e) {
            Log::error(__METHOD__ . ' (DataMart): ' . $e->getMessage());

            // Se revierte TODO, no solo el estado: dejarla con activo = 0 la
            // ocultaba del listado aunque siguiera existiendo en el DataMart.
            $guia->activo         = $activoPrevio;
            $guia->guia_estado_id = $estadoPrevio;
            $guia->save();

            return response()->json([
                'procede'  => false,
                'msj'      => "No se pudo eliminar la guia {$documento} en el DataMart. No se elimino nada.",
                'msj_tipo' => 'error',
            ]);
        }

        $this->registrarAuditoria($guia->id, 4, 'guia_ingresos_datamart', json_encode($body), "Guia {$documento} eliminada en DataMart");

        return response()->json([
            'procede'  => true,
            'msj'      => "Guia de Ingreso {$documento} eliminada",
            'msj_tipo' => 'success',
        ]);
    }

    public function modalOtrasGuias(Request $request)
    {
        // dd('hola ');
        return view('guia.ingreso.modal_otras_guias');
    }


    public function buscarOtrasGuias(Request $request)
    {
        $activo = $request->post('activo');
        $serie = $request->post('serie');
        $numero = $request->post('numero');

        $consulta = GuiaIngreso::where('activo', $activo);
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
        
        return view('guia.ingreso.tabla_otras_guias', compact('list'));
    }

    public function cargarOtraGuia(Request $request)
    {
        $detalle = GuiaIngresoDetalle::where('guia_ingreso_id', $request->post('id'))->get();
        $igv     = Igv::vigente();

        return response()->json([
            'procede' => true,
            'lineas'  => $this->lineasParaVista($detalle),
            'igv'     => ['tasa' => $igv->tasa(), 'porcentaje' => $igv->porcentaje()],
        ]);
    }

    /**
     * El detalle guardado, con la forma que espera el componente de la vista.
     *
     * Este metodo FALTABA en ingreso. Se llama desde continuar() y desde
     * cargarOtraGuia(), pero solo se habia escrito en GuiaSalidaController, asi
     * que /guiaingreso/continuar/{id} respondia
     * "Method lineasParaVista does not exist": retomar una guia guardada como
     * avance estaba roto por completo.
     *
     * Las claves van en camelCase porque las consume public/js/gre/guia-detalle.js,
     * no un Blade: son las mismas que devuelve el buscador de articulos, para
     * que una linea recuperada y una recien buscada sean indistinguibles.
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
                // A diferencia de salida, el detalle de ingreso SI tiene
                // columna bonificacion, y hay que respetarla: una linea
                // bonificada no suma al total.
                'bonificacion'        => (bool) ($item->bonificacion ?? false),
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

}
