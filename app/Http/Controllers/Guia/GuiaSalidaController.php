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
        $fechaInicio = $request->post('fecha_inicio');
        $fechaFin = $request->post('fecha_fin');
        $serie = $request->post('serie');
        $numero = $request->post('numero');

        $consulta = DB::table('guia_salidas')->whereBetween('fecha_emision', [$fechaInicio, $fechaFin])->where('activo',1);

        if ($serie != '') {
            $consulta = $consulta->where('serie', $serie);
        }
        if ($numero != '') {
            $consulta = $consulta->where('numero', $numero);
        }

        $list = $consulta->get();

        $api_facturacion_consultar_estado = Parametro::find(9)->valor;
        $ruc_entidad = Parametro::find(2)->valor;

        foreach ($list as $key => $value) {
            // dd($value);
            if ($value->envio_id != null) {
                $getEnvio = FacturacionEnvio::find($value->envio_id);
                // dd($getEnvio->pdf417);
            }
            $list[$key]->estado_nombre = GuiaEstado::find($value->guia_estado_id)->nombre;

            $texto_razon_social = $value->proveedor_nombre;

            if ($value->indicar_proveedor == 0) {
                $texto_razon_social = $value->cliente_razon_social;
            }
            $list[$key]->texto_razon_social = $texto_razon_social;
            $url_pdf = route('guiasalida.pdf', ['guia' => $value->id, 'valorada' => 0]);
            $url_pdf_valorada = route('guiasalida.pdf', ['guia' => $value->id, 'valorada' => 1]);

            if ($value->envio_sunat == 1) {
                $url_pdf = route('guiasalida.pdfDecode', ['guia' => $value->id]);
            }

            if ((int)$value->guia_estado_id === 1 && (int)$value->envio_sunat === 1) {

                $serie_format = str_pad($value->serie, 3, '0', STR_PAD_LEFT);
                $body_consultar_estado = [
                    'rucremitente' => (string)$ruc_entidad,
                    'serienumero'  => "T{$serie_format}-{$value->numero}",
                ];

                try {
                    $estadoSunat = Http::post($api_facturacion_consultar_estado, $body_consultar_estado)->object();

                    if ($estadoSunat && property_exists($estadoSunat, 'estado') && $estadoSunat->estado !== null) {

                        $estadoApi = strtoupper(trim((string)$estadoSunat->estado));
                        $nuevo_estado = null;

                        // Mapa según tu tabla guia_estados
                        switch ($estadoApi) {
                            case 'A': $nuevo_estado = 2; break; // Aceptada
                            case 'B': $nuevo_estado = 3; break; // Rechazada
                            case 'O': $nuevo_estado = 5; break; // Observado
                        }

                        if (!is_null($nuevo_estado) && (int)$nuevo_estado !== (int)$value->guia_estado_id) {
                            // Actualiza en BD
                            $guia = GuiaSalida::find($value->id);
                            if ($guia) {
                                $guia->guia_estado_id = $nuevo_estado;
                                $guia->mensaje_estado_sunat = $estadoSunat->mensaje ?? null;
                                $guia->save();
                            }

                            // Refleja en el listado en memoria
                            $value->guia_estado_id = $nuevo_estado;
                            $list[$key]->estado_nombre = GuiaEstado::find($nuevo_estado)->nombre;
                        }
                    }

                } catch (\Throwable $e) {
                    // registra el error; no cortamos el flujo del listado
                    \Log::error('Error consultando estado SUNAT', [
                        'guia_id' => $value->id,
                        'mensaje' => $e->getMessage(),
                    ]);
                }
            }
            $mostrar_anular = false;

            if ($value->guia_estado_id == 1 or $value->guia_estado_id == 2 or $value->guia_estado_id == 3 or $value->guia_estado_id == 5) {
                $mostrar_anular = true;
            }

            $list[$key]->mostrar_anular = $mostrar_anular;


            $list[$key]->url_pdf = $url_pdf;
            $list[$key]->url_pdf_valorada = $url_pdf_valorada;

            $mostrarGuardarDatamarket = true;
            if ($value->enviado_datamarket == 1) {
                $mostrarGuardarDatamarket = false;
            }

            $list[$key]->mostrarGuardarDatamarket = $mostrarGuardarDatamarket;

            $verReintentoFacturador = false;
            if ($value->envio_sunat == 1) {
                if ($value->enviado_facturador == 0) {
                    if ($value->guia_estado_id == 1) {
                        $verReintentoFacturador = true;
                    }
                }
            }

            $list[$key]->verReintentoFacturador = $verReintentoFacturador;

        }
        // dd($list);
        $nro = 1;
        return view('guia.salida.tabla', compact('list', 'nro'));
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
        $listUbigeos = Http::post("{$api_datos}/ObtieneUbigeos", ['codigoUbigeo' => '', 'tipoConsulta' => 1 ])->object()->ubigeos;
        // dd($listUbigeos);

        // $listUbigeosDepartamentoPartida = $listUbigeos;
        $listUbigeosDepartamentoPartida = Http::post("{$api_datos}/ObtieneUbigeos", ['codigoUbigeo' => '', 'tipoConsulta' => 1 ])->object()->ubigeos;
        foreach ($listUbigeosDepartamentoPartida as $key => $value) {
            $selected = "";
            if (trim($value->codUbigeo) == '04') {//arequipa
                $selected = "selected";
            }
            $listUbigeosDepartamentoPartida[$key]->selected = $selected;
        }
        // dd($listUbigeosDepartamentoPartida);
        $listUbigeosProvinciaPartida = [];
        $listUbigeosDistritoPartida = [];

        $listUbigeosDepartamentoLlegada = Http::post("{$api_datos}/ObtieneUbigeos", ['codigoUbigeo' => '', 'tipoConsulta' => 1 ])->object()->ubigeos;
        foreach ($listUbigeosDepartamentoLlegada as $key => $value) {
            $selected = "";
            if (trim($value->codUbigeo) == '04') {//arequipa
                $selected = "selected";
            }
            $listUbigeosDepartamentoLlegada[$key]->selected = $selected;
        }
        $listUbigeosProvinciaLlegada = [];
        $listUbigeosDistritoLlegada = [];
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
            $procede = false;
            $msj = "Ocurrio un error al obtener cliente transferencia (API)";
            $msj_tipo = "error";
            $log = "{$e}";
            dd('no se pudo cargar datos cliente transferencia');
        }

        // dd($clienteTransferencia);


        return view('guia.salida.create', compact('listSeries','listProveedores', 'listFormasPago', 'listTipoOperacion', 'listPrecios', 'listAlmacenes', 'listArticulos', 'listClientes', 'listVendedores', 'listVehiculos', 'listChoferes', 'listUbigeosDepartamentoPartida', 'listUbigeosProvinciaPartida', 'listUbigeosDistritoPartida', 'listUbigeosDepartamentoLlegada', 'listUbigeosProvinciaLlegada', 'listUbigeosDistritoLlegada', 'listAlmacenOrigen', 'listAlmacenDestino', 'verChofer', 'verVehiculo', 'validar_stock', 'clienteTransferencia'));
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
        $listUbigeosDepartamentoPartida = Http::post("{$api_datos}/ObtieneUbigeos", ['codigoUbigeo' => '', 'tipoConsulta' => 1 ])->object()->ubigeos;

        foreach ($listUbigeosDepartamentoPartida as $key => $value) {
            $selected = '';
            if (trim($value->codUbigeo) == $guia->ubigeo_partida_departamento) {
                $selected = "selected";
            }
            $listUbigeosDepartamentoPartida[$key]->selected = $selected;
        }
        // dd($guia->ubigeo_partida_provincia);
        $listUbigeosProvinciaPartida = Http::post("{$api_datos}/ObtieneUbigeos", ['codigoUbigeo' => $guia->ubigeo_partida_departamento, 'tipoConsulta' => 2 ])->object()->ubigeos;
        // dd($listUbigeosProvinciaPartida);
        foreach ($listUbigeosProvinciaPartida as $key => $value) {
            $selected = "";
            if (trim($value->codUbigeo) == $guia->ubigeo_partida_provincia) {
                $selected = "selected";
            }
            $listUbigeosProvinciaPartida[$key]->selected = $selected;
        }
        $listUbigeosDistritoPartida = Http::post("{$api_datos}/ObtieneUbigeos", ['codigoUbigeo' => $guia->ubigeo_partida_provincia, 'tipoConsulta' => 3 ])->object()->ubigeos;
        // dd($listUbigeosDistritoPartida);
        foreach ($listUbigeosDistritoPartida as $key => $value) {
            $selected = "";
            if (trim($value->codUbigeo) == $guia->ubigeo_partida_distrito) {
                $selected = "selected";
            }
            $listUbigeosDistritoPartida[$key]->selected = $selected;
        }

        // dd($listUbigeosDepartamentoPartida);
        // ubigeos de legada
        $listUbigeosDepartamentoLlegada = Http::post("{$api_datos}/ObtieneUbigeos", ['codigoUbigeo' => '', 'tipoConsulta' => 1 ])->object()->ubigeos;

        foreach ($listUbigeosDepartamentoLlegada as $key => $value) {
            $selected = '';
            if (trim($value->codUbigeo) == $guia->ubigeo_llegada_departamento) {
                $selected = "selected";
            }
            $listUbigeosDepartamentoLlegada[$key]->selected = $selected;
        }


        $listUbigeosProvinciaLlegada = Http::post("{$api_datos}/ObtieneUbigeos", ['codigoUbigeo' => $guia->ubigeo_llegada_departamento, 'tipoConsulta' => 2 ])->object()->ubigeos;
        // dd($listUbigeosProvinciaLlegada);
        foreach ($listUbigeosProvinciaLlegada as $key => $value) {
            $selected = "";
            if (trim($value->codUbigeo) == $guia->ubigeo_llegada_provincia) {
                $selected = "selected";
            }
            $listUbigeosProvinciaLlegada[$key]->selected = $selected;
        }

        $listUbigeosDistritoLlegada = Http::post("{$api_datos}/ObtieneUbigeos", ['codigoUbigeo' => $guia->ubigeo_llegada_provincia, 'tipoConsulta' => 3 ])->object()->ubigeos;
        // dd($listUbigeosDistritoLlegada);
        foreach ($listUbigeosDistritoLlegada as $key => $value) {
            $selected = "";
            if (trim($value->codUbigeo) == $guia->ubigeo_llegada_distrito) {
                $selected = "selected";
            }
            $listUbigeosDistritoLlegada[$key]->selected = $selected;
        }


        $detalle = GuiaSalidaDetalle::where('guia_salida_id', $guia->id)->get();

        $verChofer = 'display: none';
        $verVehiculo = 'display: none';
        if ($guia->modalidad_traslado == '02') {
            $verChofer = '';
            $verVehiculo = '';
        }

        return view('guia.salida.create', compact('guia', 'detalle','listSeries','listProveedores', 'listFormasPago', 'listTipoOperacion', 'listPrecios', 'listAlmacenes', 'listArticulos', 'listClientes', 'listVendedores', 'listVehiculos', 'listChoferes', 'listTransportistas', 'listUbigeosDepartamentoPartida','listUbigeosProvinciaPartida', 'listUbigeosDistritoPartida', 'listUbigeosDepartamentoLlegada', 'listUbigeosProvinciaLlegada', 'listUbigeosDistritoLlegada', 'listAlmacenOrigen', 'listAlmacenDestino', 'verChofer', 'verVehiculo'));
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

        $valor = trim($request->get('term'));
        $tipoconsulta = $request->post('tipo');
        $codestacion = $request->get('codestacion');
        $codalmacen = $request->get('codalmacen');
        $codlistaprecio = $request->get('codlistaprecio');
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
        $api_datos = Parametro::find(6)->valor;

        $codigoUbigeo = $request->post('codUbigeo');
        $tipoConsulta = $request->post('tipo_busqueda');
        $tipo_ubigeo = $request->post('tipo_ubigeo');
        $next = false;
        $procede = true;
        $listUbigeos = Http::post("{$api_datos}/ObtieneUbigeos",
            ['codigoUbigeo' => $codigoUbigeo, 'tipoConsulta' => $tipoConsulta ]
        )->object()->ubigeos;

        $tag_id = "{$tipo_ubigeo}_departamento";
        if ($tipoConsulta == 2) {
            $next = true;
            $tag_id = "{$tipo_ubigeo}_provincia";
        }
        if ($tipoConsulta == 3) {
            $tag_id = "{$tipo_ubigeo}_distrito";
        }

        // dd($listUbigeos);
        $options = "";
        foreach ($listUbigeos as $item) {
            $codigo = trim($item->codUbigeo);
            $options .= "<option value='{$codigo}'>{$item->descripcion}</option>";
        }

        return response()->json(['options' => $options, 'tag_id' => $tag_id, 'next' => $next, 'procede' => $procede]);
    }

    public function getUbigeosPorAlmacen(Request $request)
    {
        // dd($request->post());
        $tipo = $request->post('tipo');
        $ubigeoDistrito = trim($request->post('ubigeo'));
        $direccion = trim($request->post('direccion'));

        // dd($tipo, $ubigeo);
        $ubigeoProvincia = Str::substr($ubigeoDistrito, 0,4);
        $ubigeoDepartamento = Str::substr($ubigeoProvincia, 0,2);

        // dd($ubigeoDistrito, $ubigeoProvincia, $ubigeoDepartamento);

        $api_datos = Parametro::find(6)->valor;

        $getUbigeoDepartamento = Http::post("{$api_datos}/ObtieneUbigeos", ['codigoUbigeo' => '', 'tipoConsulta' => 1 ])->object()->ubigeos;
        // dd($getUbigeoDepartamento);
        $optionsDepartamento = '';
        foreach ($getUbigeoDepartamento as $item) {
            $codUbigeo = trim($item->codUbigeo);
            $selected = "";
            if ($codUbigeo == $ubigeoDepartamento) {
                $selected = "selected";
            }
            $optionsDepartamento .= "<option value='{$codUbigeo}' {$selected}>{$item->descripcion}</option>";
        }

        $getUbigeoProvincia = Http::post("{$api_datos}/ObtieneUbigeos", ['codigoUbigeo' => "{$ubigeoDepartamento}", 'tipoConsulta' => 2 ])->object()->ubigeos;
        // dd($getUbigeoProvincia);
        $optionsProvincia = '';
        foreach ($getUbigeoProvincia as $item) {
            $codUbigeo = trim($item->codUbigeo);
            $selected = "";
            if ($codUbigeo == $ubigeoProvincia) {
                $selected = "selected";
            }
            $optionsProvincia .= "<option value='{$codUbigeo}' {$selected}>{$item->descripcion}</option>";
        }

        $getUbigeoDistrito = Http::post("{$api_datos}/ObtieneUbigeos", ['codigoUbigeo' => "{$ubigeoProvincia}", 'tipoConsulta' => 3 ])->object()->ubigeos;
        // dd($getUbigeoDistrito);
        $optionsDistrito = '';
        foreach ($getUbigeoDistrito as $item) {
            $codUbigeo = trim($item->codUbigeo);
            $selected = "";
            if ($codUbigeo == $ubigeoDistrito) {
                $selected = "selected";
            }
            $optionsDistrito .= "<option value='{$codUbigeo}' {$selected}>{$item->descripcion}</option>";
        }


        // dd($optionsDepartamento, $optionsProvincia, $optionsDistrito);
        return response()->json(['optionsDepartamento' => $optionsDepartamento, 'optionsProvincia' => $optionsProvincia, 'optionsDistrito' => $optionsDistrito, 'tipo' => $tipo, 'direccion' => $direccion]);

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

    public function agregarItem(Request $request)
    {
        // ... (todo tu código inicial para obtener variables no cambia)
        $api_datos = Parametro::find(6)->valor;
        $producto_id = $request->post('producto_id');
        $codigo_barra = $request->post('codigo_barra');
        $cod_plu = $request->post('cod_plu');
        $descripcion = $request->post('descripcion');
        $precio_publico = $request->post('precio_publico');
        $precio_sin_igv = $request->post('precio_sin_igv');
        $peso = $request->post('peso') ?? 0;
        $cod_unidad = $request->post('cod_unidad') ?? 9;
        $desc_unidad_medida = $request->post('desc_unidad_medida') ?? '';
        $sigla_umfe = $request->post('sigla_umfe') ?? '';
        $stock = $request->post('stock') ?? 0;
        $costo_articulo = number_format($request->post('costo_articulo'),2) ?? 0;
        $igv = 0.18;
        $costo_con_igv = number_format($costo_articulo * (1 + $igv), 2);
        $cantidad = 1;
        $base_clalculo = $request->post('base_calculo');
        $afecto = $request->post('afecto');
        $items = json_decode($request->post('items'));
        $procede = true;
        $msj = "Datos obtenidos";
        $msj_tipo = "success";
        $log = "";
        $tr = "";

        if (count($items) > 0) {
            foreach ($items as $item) {
                if ($procede == true) {
                    if ($item->producto_id == $producto_id) {
                        $procede = false;
                        $msj = "<b>No puede repetir el producto</b>";
                        $msj_tipo = "error";
                    }
                }
            }
        }

        if ($procede == true) {

            $unidad = $desc_unidad_medida;
            $inputCantidad = "<input type='number' class='form-control form-control-sm input_cantidad_tr' name='cantidad' value='{$cantidad}'></input>";
            $celda_descuento_html = <<<HTML
            <td class='align-middle'>
                <input type="number" class="form-control form-control-sm valor_descuento_tr" value="0">
            </td>
            HTML;
            $span_precio = $precio_publico;
            $importe = $cantidad * $precio_publico;
            $importe_sin_igv = $cantidad * $precio_sin_igv;
            $span_precio_sin_igv = $precio_sin_igv;

            if ($base_clalculo == 1) {
                $span_precio = $precio_sin_igv;
                $importe = $cantidad * $precio_sin_igv;
            }

            $tr = "
            <tr
                data-producto_id = '{$producto_id}'
                data-precio_unitario = {$precio_publico}
                data-precio_publico = {$precio_publico}
                data-precio_sin_igv='{$precio_sin_igv}'
                data-descripcion = '{$descripcion}'
                data-codigo = '{$cod_plu}'
                data-codigo_barra = '{$codigo_barra}'
                data-peso = '{$peso}'
                data-cod_unidad = '{$cod_unidad}'
                data-desc_unidad_medida = '{$desc_unidad_medida}'
                data-sigla_umfe = '{$sigla_umfe}'
                data-stock = '{$stock}'
                data-costo_articulo = '{$costo_articulo}'
                data-costo_con_igv = '{$costo_con_igv}'
                data-afecto = '{$afecto}'
            >
                <td class='align-middle'>{$codigo_barra}</td>
                <td class='align-middle'>{$producto_id}</td>
                <td class='align-middle'>{$cod_plu}</td>
                <td class='align-middle'>{$descripcion}</td>
                <td class='align-middle'>
                    <span name='span_precio'>{$span_precio}</span>
                    <span name='span_precio_sin_igv' hidden>{$span_precio_sin_igv}</span>
                </td>
                <td class='align-middle'>{$inputCantidad}</td>
                <td class='align-middle'>{$unidad}</td>
                <td class='align-middle'>{$stock}</td>
                <td class='align-middle'>
                    <span name='span_importe'>{$importe}</span>
                    <span name='span_importe_sin_igv' hidden>{$importe_sin_igv}</span>
                </td>

                {$celda_descuento_html}

                <td class='align-middle' hidden>{$costo_articulo}</td>
                <td class='align-middle text-center'>
                    <button class='btn btn-danger btn-sm delete_item'><i class='fa fa-times-circle'></i></button>
                </td>
            </tr>
        ";
        }

        return response()->json(['procede' => $procede, 'msj' => $msj, 'msj_tipo' => $msj_tipo, 'log' => $log, 'tr' => $tr]);
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
                dd($e);
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
    // 4. PASO 2: ACTUALIZAR SQL SERVER **ANTES DE ARMAR EL BODY**
    // ============================================================
    if (!empty($articulosConsignados) && \App\Support\ConfiguracionEmpresa::usaConsignados()) {
        Log::info("PASO PREVIO: Actualizando consignados en SQL Server SALIDA", $articulosConsignados);
        
        $actualizacionExitosa = $this->actualizarConsignadosDirecto($articulosConsignados, 1);
        
        if (!$actualizacionExitosa) {
            return response()->json([
                'procede' => false,
                'msj' => 'Error crítico: No se pudieron actualizar los productos consignados en SQL Server',
                'msj_tipo' => 'error',
                'log' => 'Fallo en actualizarConsignadosDirecto() para Guía de Salida'
            ]);
        }
        
        Log::info("✓ Consignados actualizados correctamente en SQL Server (Salida)");
    }

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
        
        if (isset($storeRemoto->exito) && $storeRemoto->exito == false) {
            $procede = false;
            $msj_tipo = "error";
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




    private function actualizarConsignadosDirecto($codArticulos, $valorConsignado = 1)
    {
        // Si no hay artículos, no hacemos nada
        if (empty($codArticulos)) {
            return false;
        }

        try {
            // Usamos la conexión directa 'sqlsrv' configurada anteriormente
            DB::connection('sqlsrv')
                ->table('MaestroArticulo')
                ->whereIn('CodArticulo', $codArticulos) // whereIn es optimo para arrays
                ->update(['consignacion' => $valorConsignado]);

            Log::info("SQLSERVER: Actualizados artículos " . json_encode($codArticulos) . " a consignación: $valorConsignado");
            return true;

        } catch (\Exception $e) {
            Log::error("SQLSERVER Error actualizando consignados: " . $e->getMessage());
            return false;
        }
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
                    dd('No se pudo obtener PDF');
                }
                // dd($getPdf);
                if ($getPdf->success == true) {
                    $storePdf = FacturacionEnvio::find($getEnvioConPdf->id);
                    $storePdf->pdf = $getPdf->data;
                    try {
                        $storePdf->save();

                    } catch (Exception $e) {
                        //throw $th;
                        dd($e);
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
        // dd($request->post());
        $id = $request->post('id');
        $indicar_proveedor = $request->post('indicar_proveedor');
        $base_calculo = $request->post('base_calculo');


        $tipoconsulta = 2; //por codigo producto
        $codestacion = 1;
        $codalmacen = 1;
        $codlistaprecio = 1;


        $detalle = GuiaSalidaDetalle::where('guia_salida_id', $id)->get();
        // dd($detalle);

        $tabla = "";

        $api_datos = Parametro::find(6)->valor;

        foreach (($detalle ?? []) as $key => $item) {
            // dd($item);
            $afecto = 1;
            $valor = $item->codarticulo;
            $getArticulo = Http::post("{$api_datos}/ObtenerArticulo",
                ['valor' => $valor, 'tipoconsulta' => $tipoconsulta, 'codestacion' => $codestacion, 'codalmacen' => $codalmacen, 'codlistaprecio' => $codlistaprecio]
            )->object()->articulos;

            $getArticulo = $getArticulo[0];

            $detalle[$key]->stock = $getArticulo->stock ?? 0;

            $precioPublico = $getArticulo->precioPublico;
            $precioSinIGV = $getArticulo->precioSinIGV;
            // dd($getArticulo);
            if ($indicar_proveedor == true) {
                // dd($precioPublico);
                $precioSinIGV = $getArticulo->costoArticulo;
                $precioPublico = number_format(($getArticulo->costoArticulo * (1+0.18)),2);
                $precioPublico = number_format($precioPublico,2);
                $precioSinIGV = number_format($precioSinIGV,2);
            }

            if ($getArticulo->tipoIgv != 1) {
                $afecto = 0;

                $precioPublico = number_format($precioSinIGV,2);
                $precioSinIGV = number_format($precioSinIGV,2);

            }
            // dd($precioPublico);

            $detalle[$key]->afecto = $afecto;
            $detalle[$key]->precio_publico = $precioPublico;
            $detalle[$key]->precio_sin_igv = $precioSinIGV;
            $detalle[$key]->peso = $getArticulo->peso ?? 0;

            // dd($getArticulo);
        }

        // dd($detalle);

        foreach (($detalle ?? []) as $item) {
            $peso = $item->peso_unitario;
            $igv = 0.18; // Definir el porcentaje del IGV

            $unidad = "UNI";

            $inputCantidad = "<input type='number' class='form-control form-control-sm input_cantidad_tr' name='cantidad' value='{$item->cantidad}'></input>";

            $inputPorcentajeDescuento = "<input class='form-control form-control-sm input_porcentaje_descuento_tr' name='porcentaje_descuento' value='{$item->porcentaje_descuento}'></input>";

            $inputDescuento = "<input type='hidden' name='monto_descuento' value='{$item->monto_descuento}'></input>";

            $stock = 0;
            $costo_articulo = $item->costo_articulo ?? 0;
            $costo_con_igv = number_format($costo_articulo * (1 + $igv), 2);

            $importe_sin_igv = $item->cantidad * $item->precio_sin_igv;
            $span_precio_sin_igv = $item->precio_sin_igv;
            $span_precio = $item->precio_publico;
            $importe = $item->cantidad * $item->precio_publico;

            if ($base_calculo == 1) {
                $span_precio = $item->precio_sin_igv;
                $importe = $item->cantidad * $item->precio_sin_igv;
            }


            $tabla .= "
                <tr
                    data-producto_id = '{$item->codarticulo}'
                    data-precio_unitario = {$item->precio_publico}
                    data-precio_publico = {$item->precio_publico}
                    data-precio_sin_igv='{$item->precio_sin_igv}'
                    data-descripcion = '{$item->descripcion}'
                    data-codigo = '{$item->codarticulo}'
                    data-codigo_barra = '{$item->codigo_barra}'
                    data-peso = '{$peso}'
                    data-cod_unidad = '{$item->cod_unidad}'
                    data-desc_unidad_medida = '{$item->desc_unidad_medida}'
                    data-sigla_umfe = '{$item->sigla_umfe}'
                    data-stock = '{$stock}'
                    data-costo_articulo = '{$costo_articulo}'
                    data-costo_con_igv = '{$costo_con_igv}'
                    data-afecto = '{$item->afecto}'

                >
                    <td class='align-middle'>{$item->codigo_barra}</td>
                    <td class='align-middle'>{$item->codarticulo}</td>
                    <td class='align-middle'>{$item->codarticulo}</td>
                    <td class='align-middle'>{$item->descripcion}</td>
                    <td class='align-middle'>
                        <span name='span_precio'>{$span_precio}</span>
                        <span name='span_precio_sin_igv' hidden>{$span_precio_sin_igv}</span>
                    </td>
                    <td class='align-middle'>{$inputCantidad}</td>
                    <td class='align-middle'>{$unidad}</td>
                    <td class='align-middle'>{$stock}</td>
                    <td class='align-middle'>
                        <span name='span_importe'>{$importe}</span>
                        <span name='span_importe_sin_igv' hidden>{$importe_sin_igv}</span>
                    </td>
                    <td class='align-middle'>{$inputPorcentajeDescuento} {$inputDescuento}</td>
                    <td class='align-middle' hidden>{$costo_articulo}</td>
                    <td class='align-middle text-center'>
                        <button class='btn btn-danger btn-sm delete_item'><i class='fa fa-times-circle'></i></button>
                    </td>
                </tr>
            ";

        }

        return response()->json(['tabla' => $tabla]);
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
            dd($e);
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
