<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Faker\Factory as Faker;
use Illuminate\Support\Carbon;

class SimulacionController extends Controller
{
    public function ObtenerProveedores(Request $request)
    {
        try {
            $data = json_decode(Storage::get('json/proveedores.json'), true);
            return response()->json($data);
        } catch (Exception $e) {
            //throw $th;
            return response()->json([]);
        }
    }
    public function ObtenerAlmacenes(Request $request)
    {
        try {
            $data = json_decode(Storage::get('json/almacenes.json'), true);
            return response()->json($data);
        } catch (Exception $e) {
            //throw $th;
            return response()->json([]);
        }
    }
    public function ObtenerChoferes(Request $request)
    {
        try {
            $data = json_decode(Storage::get('json/choferes.json'), true);
            return response()->json($data);
        } catch (Exception $e) {
            //throw $th;
            return response()->json([]);
        }
    }
    public function ObtenerOperaciones(Request $request)
    {
        try {
            $data = json_decode(Storage::get('json/operaciones.json'), true);
            return response()->json($data);
        } catch (Exception $e) {
            //throw $th;
            return response()->json([]);
        }
    }
    public function ObtenerListaPrecios(Request $request)
    {
        try {
            $data = json_decode(Storage::get('json/lista_precios.json'), true);
            return response()->json($data);
        } catch (Exception $e) {
            //throw $th;
            return response()->json([]);
        }
    }
    public function ObtenerFormasPago(Request $request)
    {
        try {
            $data = json_decode(Storage::get('json/formas_pago.json'), true);
            return response()->json($data);
        } catch (Exception $e) {
            //throw $th;
            return response()->json([]);
        }
    }
    public function ObtenerArticulos(Request $request)
    {
        try {
            $data = json_decode(Storage::get('json/articulos.json'), true);
            return response()->json($data);
        } catch (Exception $e) {
            //throw $th;
            return response()->json([]);
        }
    }
    public function ObtenerTrabajadores(Request $request)
    {
        try {
            $data = json_decode(Storage::get('json/trabajadores.json'), true);
            return response()->json($data);
        } catch (Exception $e) {
            //throw $th;
            return response()->json([]);
        }
    }
    public function ObtenerTransportistas(Request $request)
    {
        try {
            $data = json_decode(Storage::get('json/transportistas.json'), true);
            return response()->json($data);
        } catch (Exception $e) {
            //throw $th;
            return response()->json([]);
        }
    }
    public function ObtenerVehiculos(Request $request)
    {
        try {
            $data = json_decode(Storage::get('json/vehiculos.json'), true);
            return response()->json($data);
        } catch (Exception $e) {
            //throw $th;
            return response()->json([]);
        }
    }
    public function ObtenerClientes(Request $request)
    {
        try {
            $data = json_decode(Storage::get('json/clientes.json'), true);
            return response()->json($data);
        } catch (Exception $e) {
            //throw $th;
            return response()->json([]);
        }
    }
    public function ObtenerTiposCambio(Request $request)
    {
        try {
            $data = json_decode(Storage::get('json/tipo_cambio.json'), true);
            return response()->json($data);
        } catch (Exception $e) {
            //throw $th;
            return response()->json([]);
        }
    }

    public function ObtenerPedidoClientes(Request $request)
    {
        try {
            $data = json_decode(Storage::get('json/pedido_clientes.json'), true);
            return response()->json($data);
        } catch (Exception $e) {
            //throw $th;
            return response()->json([]);
        }
    }

    public function ObtenerDetalleOrden(Request $request)
    {
        try {
            $data = json_decode(Storage::get('json/detalle_orden.json'), true);
            return response()->json($data);
        } catch (Exception $e) {
            //throw $th;
            return response()->json([]);
        }
    }
    public function ObtenerSeries(Request $request)
    {
        try {
            $data = json_decode(Storage::get('json/series.json'), true);
            return response()->json($data);
        } catch (Exception $e) {
            //throw $th;
            return response()->json([]);
        }
    }

    public function generateData()
    {
        $faker = Faker::create();
        
        $products = [
            "iPhone" => 4000,
            "Samsung Galaxy" => 3200,
            "Nike Air Max" => 600,
            "Adidas Superstar" => 480,
            "Sony PlayStation" => 1600,
            "Microsoft Xbox" => 1400,
            "Canon EOS" => 2400,
            "MacBook Pro" => 6000,
            "Dell XPS" => 5200,
            "LG OLED TV" => 8000,
            "Bose QuietComfort" => 1200,
            "Gucci Guilty" => 320,
            "Chanel Coco Mademoiselle" => 480,
            "Rolex Submariner" => 40000,
            "Levi's 501" => 320,
            "Ray-Ban Wayfarer" => 600,
            "Converse Chuck Taylor" => 240,
            "L'Oréal Paris" => 40,
            "Coca-Cola" => 8,
            "Nutella" => 20
        ];
        
        $articles = [];
        
        foreach ($products as $nombreArticulo => $precioReal) {
            $precioSinIGV = round($precioReal / 1.18, 2);
            
            $article = [
                "CodArticulo" => count($articles) + 1,
                "NombreArticulo" => $nombreArticulo,
                "CuentaCompra" => 0,
                "CuentaVenta" => 0,
                "NombreCorto" => $nombreArticulo,
                "CodEstacion" => 0,
                "CodFamilia" => 0,
                "CodUnidad" => 0,
                "TipoMoneda" => $faker->randomElement(['string', 'string', 'string']),
                "PrecioPublico" => $precioReal,
                "PrecioSinIGV" => $precioSinIGV,
                "CostoArticulo" => 0,
                "ISC" => 0,
                "IGV" => 0,
                "StockMinimo" => 0,
                "StockMaximo" => 0,
                "CodBarra" => $faker->ean13(),
                "CodPlu" => $faker->ean8(),
                "Stock" => 0,
                "CodBarraAd" => $faker->ean13(),
                "FechaUSalida" => Carbon::now()->format('Y-m-d\TH:i:s.u'),
                "FechaUIngreso" => Carbon::now()->format('Y-m-d\TH:i:s.u'),
                "CostoPromedio" => 0,
                "PuntosFidelidad" => 0,
                "CostoAdicionalFidelidad" => 0,
                "StockUInventario" => 0,
                "IngresoDUInventario" => 0,
                "SalidasDUInventario" => 0,
                "Estado" => "string",
                "UsuarioCreador" => "string",
                "FechaCreacion" => Carbon::now()->format('Y-m-d\TH:i:s.u'),
                "UsuarioModificador" => "string",
                "FechaModificacion" => Carbon::now()->format('Y-m-d\TH:i:s.u'),
                "CodSubFamilia" => 0,
                "ControlStock" => "string",
                "TipoKit" => "string",
                "FechaExpiracion" => Carbon::now()->format('Y-m-d\TH:i:s.u'),
                "Procedencia" => "string",
                "Ubicacion" => 0,
                "PorcentajeUtilidad" => 0,
                "Foto" => "string",
                "TipoMonedaCosto" => "string",
                "PrecioPublico2" => 0,
                "PrecioPublico3" => 0,
                "StockM" => 0,
                "CodArtNue" => "string",
                "Inicial" => 0,
                "Talla" => "string",
                "StockInicial" => 0,
                "Comision" => 0,
                "Comentario" => "string",
                "GastosAdministrativos" => 0,
                "OtrosGastos" => 0,
                "Peso" => 0,
                "Grosor" => 0,
                "PrecioGramo" => 0,
                "CodAlmacen" => 0,
                "serie" => "string",
                "ConPromocion" => 0,
                "TipoVenta" => "string",
                "Perecible" => "string",
                "CodBarra2" => "string",
                "ControlPeso" => "string",
                "Percepcion" => "string",
                "TipoCalculoPrecio" => "string",
                "GarantiaDias" => 0,
                "canjepuntos" => 0,
                "solesCanje" => 0,
                "articanje" => 0,
                "ModaLinea" => 0,
                "PrecioTalla" => 0,
                "Marca" => "Adidas",
                "Importado" => 0,
                "Transferible" => 0,
                "CostoIgv" => 0,
                "Insumo" => 0,
                "Autor" => "string",
                "Editorial" => "string",
                "Express" => 0,
                "Carta" => 0,
                "PermitirC" => 0,
                "PermitirV" => 0,
                "CostoPactado" => 0,
                "CodProveedorPrincipal" => 0,
                "FechaAnulacion" => Carbon::now()->format('Y-m-d\TH:i:s.u'),
                "Imagen" => "string",
                "CodClasificacion" => 0,
                "CodigoSAP" => "string",
                "DescuentoPorcentaje" => 0,
                "TipoExistencia" => "string",
                "TipoIgv" => 0,
                "TouchResumen" => 0,
                "web1" => "string",
                "web2" => "string",
                "ESTADOENVIOWS" => 0,
                "CodigoSunat" => "string",
                "CodTipoEmpaque" => 0,
                "IGVCostoProv" => 0,
                "ImprimirComanda" => 0,
                "Cortesia" => 0,
                "CodCuenta" => "string",
                "CodProyecto" => 0,
                "ICBPER" => 0,
                "NotaDebito" => 0,
                "NotaCredito" => 0
            ];
        
            $article["NombreCorto"] = $article["NombreArticulo"];
        
            $articles[] = $article;
        }
        
        // Mostrar el array de artículos generado
        echo json_encode($articles, JSON_PRETTY_PRINT);
        
    }
}
