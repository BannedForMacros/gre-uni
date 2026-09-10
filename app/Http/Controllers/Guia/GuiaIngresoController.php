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
        $fechaInicio = $request->post('fecha_inicio');
        $fechaFin = $request->post('fecha_fin');
        $serie = $request->post('serie');
        $numero = $request->post('numero');

        $consulta = DB::table('guia_ingresos')->whereBetween('fecha_emision', [$fechaInicio, $fechaFin])->where('activo', 1);
        
        if ($serie != '') {
            $consulta = $consulta->where('serie', $serie);
        }
        if ($numero != '') {
            $consulta = $consulta->where('numero', $numero);
        }
        
        $list = $consulta->get();
        // dd($list);

        foreach ($list as $key => $value) {
            $list[$key]->estado_nombre = GuiaEstado::find($value->guia_estado_id)->nombre;
            $mostrar_eliminar = false;
            if ($value->guia_estado_id == 1) {
                $mostrar_eliminar = true;
                
            }
            
            $list[$key]->mostrar_eliminar = $mostrar_eliminar;
            
            $mostrarGuardarDatamarket = true;
            if ($value->enviado_datamarket == 1) {
                $mostrarGuardarDatamarket = false;
            }
            
            $list[$key]->mostrarGuardarDatamarket = $mostrarGuardarDatamarket;

        }
        // dd($list);
        return view('guia.ingreso.tabla', compact('list'));
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

        return view('guia.ingreso.create', compact('listProveedores', 'listFormasPago', 'listTipoOperacion', 'listAlmacenes', 'listArticulos', 'listVendedores', 'listSeries'));
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
        
        return view('guia.ingreso.create', compact('guia','listProveedores', 'listFormasPago', 'listTipoOperacion', 'listAlmacenes', 'listArticulos', 'listVendedores', 'detalle'));
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

            $items[] = (object) array('id' => $item->codProveedor, 'text' => "[{$item->ruc}] {$item->nombreproveedor}", 'proveedor_nombre' => $item->nombreproveedor, 'proveedor_ruc' => $item->ruc);
        }

        return response()->json(['items' => $items]);
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

        $valor = trim($request->get('term'));
        $tipoconsulta = $request->post('tipo');
        $codestacion = $request->get('codestacion');
        $codalmacen = $request->get('codalmacen');
        $codlistaprecio = $request->get('codlistaprecio');
        $maximo = 0;
        if ($tipoconsulta == 4) {
            $maximo = 2;
        }
        // dd($request->all());
        if (strlen($valor) > $maximo) {
            $listArticulos = Http::post("{$api_datos}/ObtenerArticulo", 
                ['valor' => $valor, 'tipoconsulta' => $tipoconsulta, 'codestacion' => $codestacion, 'codalmacen' => $codalmacen, 'codlistaprecio' => $codlistaprecio]
            )->object()->articulos;
            
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

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store2(Request $request)
    {
        $api_datos = Parametro::find(6)->valor;

        $datos = $request->post();
        $id_continuar = $request->post('id_continuar');
        // dd($request->post());
        if ($id_continuar == '') {
            unset($datos['id']);
        }
        // dd($datos);
        $detalle = json_decode($request->post('detalle'));
        $guardar_avance = ($datos['guardar_avance'] == 'true') ? true : false ;
        unset($datos['detalle']);
        // dd($datos);
        $procede = true;
        $msj = "Guia de Ingreso registrada";
        $msj_tipo = "success";
        $log = "";
        $datos['fecha_emision'] = date('Y-m-d');
        $datos['hora_emision'] = date('H:i');
        $url_redirect = route('guiaingreso.index');
        $datos['serie']= 1;
        $guia_estado_id = 1;
        if ($guardar_avance == true) {
            $guia_estado_id = 4;
        }
        $datos['guia_estado_id'] = $guia_estado_id;
        
        // $getLast = GuiaSalida::orderBy('id', 'desc')->first();
        $listSeries = Http::get("{$api_datos}/obtenerSeriesNumerosGuia")->object()->serienumeros;
        // dd($listSeries);
        foreach ($listSeries as $item) {
            if ($item->numserie == $datos['serie']) {
                $numero = intval($item->ultimoValormarket) + 1;
                $serie = $item->numserie;
            }
        }

        // if ($getLast != null) {
        //     $numero = intval($getLast->numero)+1;
        // }
        $datos['numero'] = $numero;
        $datos['serie'] = $serie;
        // dd([$numero, $serie]);
        $anio_actual = date('Y');


        // dd($body);

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

        if ($procede == true) {
            
            if ($guardar_avance == false) {

                foreach ($detalle as $item) {
                    $body_detalle[] = array(
                        "anioGuia" => $anio_actual,
                        "cantidad" => $item->cantidad,
                        "codArticulo" => $item->codarticulo,
                        "estadoProceso" => "0",
                        "importeDetalle" => $item->importe,
                        "item" => 1,
                        "numSerie" => $datos['serie'],
                        "numeroGuia" => $datos['numero'],
                        "precio" => $item->precio,
                        "tipoGuia" => "N",
                        "unidadMedida" => 1
                    );
                }
                $body = [
                    "anioGuiaRemision" => $anio_actual,
                    "breveteChofer" => null,
                    "codAlmacen" => $datos['codalmacen'],
                    "codAlmacenDestino" => null,
                    "codAlmacenOrigen" => null,
                    "codCliente" => null,
                    "codEstacion" => $datos['codestacion'],
                    "codListaPrecio" => null,
                    "codProveedor" => $datos['proveedor_id'],
                    "codtrabajador" => $datos['vendedor_id'],
                    "comentario" => $datos['comentario'],
                    "descuento" => $datos['monto_descuento'],
                    "detalle" => $body_detalle,
                    "direccionllegada" =>null,
                    "direccionpartida" => null,
                    "dnichofer" => null,
                    "estadoProceso" => "0",
                    "fechaEmision" => $datos['fecha_emision'],
                    "formapago" => $datos['forma_pago_id'],
                    "igv" => $datos['monto_igv'],
                    "modalidadTransporte" => "18",
                    "nombreTransportista" => null,
                    "nombrechofer" => null,
                    "numSerie" => $datos['serie'],
                    "seriefactura" => $datos['pedido_serie'],
                    "numeroFactura" => 159,
                    "numeroGuia" => $datos['numero'],
                    "placavehiculo" => null,
                    "rucTransportista" => null,
                    "tipoGuia" => "N", //N->ingreso; A->Salida
                    "tipoOperacion" => $datos['tipo_operacion_id'],
                    "tipomonda" => 1,
                    "totalVenta" => $datos['total_venta'],
                    "ubigeollegada" => null,
                    "ubigeopartida" => null,
                    "valorVenta" => $datos['importe_sin_igv']
                ];


                try {
                    $storeRemoto = Http::post("{$api_datos}/InsertGuiaDMK", $body)->object();
                    // dd($storeRemoto);
                    if ($storeRemoto->exito == false) {
                        $procede = false;
                        $msj = "No se pudo completar : {$storeRemoto->msgerror}";
                    }
                } catch (Exception $e) {
                    //throw $th;
                    dd($e);
                    $procede = false;
                    $msj = "No se pudo registrar remotamente";
                    $msj_tipo = "error";
                    $log = "{$e}";
                }
                
            }
        }

        if ($guardar_avance == true) {
            $datos['numero'] = null;
            $datos['serie'] = null;
        }

        
        if ($procede == true) {

            try {
                $guia = GuiaIngreso::create($datos);
            } catch (Exception $e) {
                //throw $th;
                dd($e);
                $procede = false;
                $msj = "No se pudo registrar la Guia de Salida";
                $msj_tipo = "error";
                $log = "{$e}";
            }
            
        }

        // dd($guia);
        if ($procede == true) {
            foreach ($detalle as $item) {

                if ($procede == true) {
                    $guiaDetalle = new GuiaIngresoDetalle();
                    $guiaDetalle->guia_ingreso_id = $guia->id;
                    $guiaDetalle->codarticulo = $item->codarticulo;
                    $guiaDetalle->precio = $item->precio;
                    $guiaDetalle->cantidad = $item->cantidad;
                    $guiaDetalle->importe = $item->importe;
                    $guiaDetalle->porcentaje_descuento = $item->porcentaje_descuento;
                    $guiaDetalle->monto_descuento = $item->monto_descuento;
                    $guiaDetalle->descripcion = $item->descripcion;
                    $guiaDetalle->precio_publico = $item->precio_publico;
                    $guiaDetalle->precio_sin_igv = $item->precio_sin_igv;
                    $guiaDetalle->codigo_barra = $item->codigo_barra;

                    try {
                        $guiaDetalle->save();
                    } catch (Exception $e) {
                        //throw $th;
                        dd($e);
                        $procede = false;
                        $msj = "No se pudo registrar el detalle";
                        $msj_tipo = "error";
                        $log = "{$e}";
                    }
                }
            }
        }

        return response()->json(['procede' => $procede, 'msj' => $msj, 'msj_tipo' => $msj_tipo, 'log' => $log, 'url_redirect' => $url_redirect]);
    }

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
        if ($procede == true) {
            // --- AGREGA ESTO AQUÍ ---
            Log::info('STORE LOCAL - Intentando crear Cabecera GuiaIngreso:', $datos);
            // ------------------------
            // dd($datos);
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
        if ($procede == true) {
            if ($es_guia_interna == 1) {
                $updateSerie = Serie::find($asignarSerie->serieAsignada->id);
                // dd($datos);
                $updateSerie->numero = $datos['numero'];

                try {
                    $updateSerie->save();
                    
                } catch (Exception $e) {
                    //throw $th;
                    dd($e);
                    $procede = true;
                    $msj = "No se pudo actualizar serie de Nube";
                    $msj_tipo = "error";
                    $log = "{$e}";
                }
                
            }
        }

        //registrar detalle
        if ($procede == true) {
            $detalle = json_decode($request->post('detalle'));
            $id = $store->id;
            foreach ($detalle as $item) {
                
                if ($procede == true) {
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

        $this->registrarAuditoria($store->id, 1, 'guia_ingresos', json_encode($datos), strip_tags($msj));

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

            if (isset($storeRemoto->exito) && $storeRemoto->exito == false) {
                $procede = false;
                $msgErrorRemoto = $storeRemoto->msgerror ?? 'Error desconocido en remoto';
                $msj = "No se pudo completar : {$msgErrorRemoto}";
            }

        } catch (Exception $e) {
            $procede = false;
            $msj = "{$msj} <b>No se pudo registrar en DATAMART (Error de Conexión API)</b>";
            $msj_tipo = "error";
            $log = "Error API: " . $e->getMessage();
            Log::error($log);
        }

        // ============================================================
        // PASO 5.5: Re-aplicar consignados DESPUÉS de la API (Laravel manda)
        // El SP InsertarGuiasOdooDmk pisa los flags de consignación por timing,
        // así que actualizamos MaestroArticulo y DetalleGuiaRemision AQUÍ,
        // después de que el SP ya insertó la guía.
        //
        // NOTA: Este bloque toca columnas de SQL Server (MaestroArticulo.consignacion
        // y DetalleGuiaRemision.esconsignado) que solo existen en clientes que usan
        // consignados. Por eso está protegido por el flag \App\Support\ConfiguracionEmpresa::usaConsignados()
        // (CONSIGNADOS_ENABLED en .env). Apagado por defecto -> no toca SQL Server.
        // ============================================================
        if ($procede == true && \App\Support\ConfiguracionEmpresa::usaConsignados()) {

            // (a) MaestroArticulo: ahora SÍ después del SP, para que no lo pise
            if (!empty($articulosConsignados)) {
                Log::info("PASO 5.5(a): Re-actualizando consignados en MaestroArticulo (post-API)", $articulosConsignados);
                $resMaestro = $this->actualizarConsignadosDirecto($articulosConsignados, 1);
                if ($resMaestro === false) {
                    Log::warning("DataMart Ingreso {$guia->serie}-{$guia->numero}: MaestroArticulo NO se actualizó (error de conexión/driver).");
                } else {
                    $maestrosActualizados = $resMaestro; // nº de filas realmente actualizadas
                }
            }

            // (b) DetalleGuiaRemision: sincronizar esconsignado (como ya estaba)
            $resDetalle = $this->sincronizarConsignadoEnDetalle(
                $anio,
                $guia->serie,
                $guia->numero,
                'N', // N = Ingreso
                $detalle
            );
            if (!$resDetalle['ok']) {
                Log::warning("DataMart Ingreso {$guia->serie}-{$guia->numero}: esconsignado no se sincronizó. " . json_encode($resDetalle));
            }
        }

        // ============================================================
        // PASO 6: Actualizar estado local
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
        $telefonos = Parametro::find(4)->valor;
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
        // dd($request->post());
        $id = $request->post('id');
        
        $guia = GuiaIngreso::find($id);
        
        $guia->activo = 0;

        // dd($guia);

        $procede = true;
        $msj = "Guia de Ingreso {$guia->serie}-{$guia->numero} Eliminada";
        $msj_tipo = "success";
        $log = "";

        try {
            $guia->save();

        } catch (Exception $e) {
            //throw $th;
            $procede = false;
            $msj = "No se pudo eliminar la Guia";
            $msj_tipo = "error";
            $log = "{$e}";
        }

        // auditoria eliminar local
        $this->registrarAuditoria($guia->id, 4, 'guia_ingresos', json_encode($guia), strip_tags($msj));


        if ($procede == true) {
            $api_datos = Parametro::find(6)->valor;

            $fecha = Carbon::parse($guia->fecha_emision);
            $anio = $fecha->year;

            $cod_proveedor = $guia->proveedor_id;
            // if ($cod_proveedor == null) {
            //     $cod_proveedor = $guia->cliente_id;
            // }

            $body = [
                "anioGuiaRemision" => $anio,
                "codProveedor" => $cod_proveedor,
                "numSerie" => $guia->serie,
                "numeroGuia" => $guia->numero
            ];
            // dd($body);
            try {
                $anularRemoto = Http::post("{$api_datos}/EliminaGuiaDMK", $body)->object();

            } catch (Exception $e) {
                $procede = false;
                $msj = "No se pudo completar eliminar en DataMark";
                $msj_tipo = "";
                $log = "{$e}";
            }
            // dd($anularRemoto);
        }

        $this->registrarAuditoria($guia->id, 4, 'guia_ingresos_datamart', json_encode($body), strip_tags($msj));

        if ($procede == false) {
            $guia->guia_estado_id = 1;
            $guia->save();
        }


        return response()->json(['procede' => $procede, 'msj' => $msj, 'msj_tipo' => $msj_tipo, 'log' => $log]);
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
        // Segunda copia del mismo constructor de <tr>, con el mismo bug del
        // apostrofe. Ahora devuelve las lineas como datos, en el mismo formato
        // que agregarItem, para que la vista las pinte igual.
        $id = $request->post('id');

        $detalle = GuiaIngresoDetalle::where('guia_ingreso_id', $id)->get();
        $igv     = Igv::vigente();

        $lineas = $detalle->map(function ($item) {
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
                'codUnidad'           => (int) ($item->cod_unidad ?? 9),
                'descUnidadMedida'    => $item->desc_unidad_medida ?? '',
                'siglaUmfe'           => $item->sigla_umfe ?? '',
                'tipoIgv'             => 1,
                'afectoIgv'           => true,
                'porcentajeDescuento' => (float) ($item->porcentaje_descuento ?? 0),
                'bonificacion'        => (bool) ($item->bonificacion ?? false),
                'esConsignado'        => (bool) ($item->es_consignado ?? false),
            ];
        })->values();

        return response()->json([
            'procede' => true,
            'lineas'  => $lineas,
            'igv'     => ['tasa' => $igv->tasa(), 'porcentaje' => $igv->porcentaje()],
        ]);
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


    private function actualizarConsignadosDirecto($codArticulos, $valorConsignado = 1)
    {
        // Si no hay artículos, no hacemos nada
        if (empty($codArticulos)) {
            return false;
        }

        try {
            $conn = DB::connection('sqlsrv');
            $dbName = $conn->getDatabaseName();

            // Diagnóstico: ¿cuántos de esos CodArticulo existen realmente en el maestro?
            $existentes = $conn->table('MaestroArticulo')
                ->whereIn('CodArticulo', $codArticulos)
                ->count();

            Log::info("SQLSERVER[maestro] BD='{$dbName}' | a actualizar=" . json_encode($codArticulos)
                . " | encontrados en MaestroArticulo={$existentes}/" . count($codArticulos)
                . " | valor consignacion={$valorConsignado}");

            // update() devuelve el número de filas afectadas
            $afectados = $conn->table('MaestroArticulo')
                ->whereIn('CodArticulo', $codArticulos) // whereIn es optimo para arrays
                ->update(['consignacion' => $valorConsignado]);

            if ($afectados === 0) {
                // El UPDATE corrió sin error pero NO tocó ninguna fila:
                // esto es lo que hace que "no se actualice" sin lanzar excepción.
                Log::warning("SQLSERVER[maestro] ⚠️ UPDATE afectó 0 filas. Los CodArticulo "
                    . json_encode($codArticulos) . " no coinciden con MaestroArticulo (¿tipo/formato/base distinta?).");
            } else {
                Log::info("SQLSERVER[maestro] ✓ Consignación actualizada: filas_afectadas={$afectados}");
            }

            return $afectados; // nº de filas del maestro realmente actualizadas

        } catch (\Exception $e) {
            Log::error("SQLSERVER[maestro] Error actualizando consignados: " . $e->getMessage());
            return false;
        }
    }


    /**
     * Sincroniza el flag esconsignado en DetalleGuiaRemision (SQL Server)
     * según el flag es_consignado de cada ítem en MySQL.
     *
     * Se llama DESPUÉS del POST a /InsertGuiaDMK, así Laravel queda como fuente
     * de verdad y no depende de que el SP propague consignacion -> esconsignado
     * (cosa que falla por timing, porque el SP solo inserta una vez por guía).
     */
    private function sincronizarConsignadoEnDetalle($anio, $serie, $numero, $tipoGuia, $detalle)
    {
        try {
            $codConsignados = [];
            $codNoConsignados = [];

            foreach ($detalle as $item) {
                $cod = trim((string)$item->codarticulo);
                if ($cod === '') {
                    continue;
                }
                if (($item->es_consignado ?? 0) == 1) {
                    $codConsignados[] = $cod;
                } else {
                    $codNoConsignados[] = $cod;
                }
            }

            $codConsignados = array_values(array_unique($codConsignados));
            $codNoConsignados = array_values(array_unique($codNoConsignados));

            $afectados1 = 0;
            $afectados0 = 0;

            if (!empty($codConsignados)) {
                $afectados1 = DB::connection('sqlsrv')
                    ->table('db_travel.dbo.DetalleGuiaRemision')
                    ->where('AnioGuiaRemision', $anio)
                    ->where('NumSerie', $serie)
                    ->where('NumeroGuia', $numero)
                    ->where('TipoGuia', $tipoGuia)
                    ->whereIn('CodArticulo', $codConsignados)
                    ->update(['esconsignado' => 1]);
            }

            if (!empty($codNoConsignados)) {
                $afectados0 = DB::connection('sqlsrv')
                    ->table('db_travel.dbo.DetalleGuiaRemision')
                    ->where('AnioGuiaRemision', $anio)
                    ->where('NumSerie', $serie)
                    ->where('NumeroGuia', $numero)
                    ->where('TipoGuia', $tipoGuia)
                    ->whereIn('CodArticulo', $codNoConsignados)
                    ->update(['esconsignado' => 0]);
            }

            Log::info("DetalleGuiaRemision esconsignado sincronizado: guia={$serie}-{$numero} tipo={$tipoGuia} anio={$anio}, consignados_afectados={$afectados1}, no_consignados_afectados={$afectados0}");

            return [
                'ok' => true,
                'consignados' => $afectados1,
                'no_consignados' => $afectados0,
            ];

        } catch (\Exception $e) {
            Log::error("Error sincronizando esconsignado en DetalleGuiaRemision: " . $e->getMessage());
            return ['ok' => false, 'msj' => $e->getMessage()];
        }
    }

}
