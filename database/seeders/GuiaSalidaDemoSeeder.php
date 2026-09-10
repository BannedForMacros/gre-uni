<?php

namespace Database\Seeders;

use App\Domain\Guia\Services\CalculadoraGuia;
use App\Domain\Guia\ValueObjects\LineaGuia;
use App\Domain\Shared\ValueObjects\Igv;
use App\Models\GuiaSalida;
use App\Models\GuiaSalidaDetalle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Guias de salida de DEMO, con cabecera y detalle.
 *
 * El listado de guias de salida nunca se habia podido ver renderizado: la unica
 * carga que habia en la tabla eran 25 cabeceras de una prueba de rendimiento,
 * todas del mismo dia, con la misma razon social y CERO lineas de detalle. Con
 * eso no se puede probar ni el orden por columna, ni el filtro rapido, ni la
 * paginacion, ni el PDF valorado, que necesita detalle para tener algo que
 * imprimir.
 *
 * NO forma parte de DatabaseSeeder a proposito. Se invoca a mano:
 *
 *     php artisan db:seed --class=GuiaSalidaDemoSeeder
 *
 * Ademas se niega a correr si la base no parece de demo (ver comprobarBaseDeDemo).
 * Sembrar guias falsas en la base de un cliente ensucia su correlativo y sus
 * reportes, y no hay forma limpia de distinguirlas despues.
 */
class GuiaSalidaDemoSeeder extends Seeder
{
    /**
     * Serie propia para lo sembrado aqui.
     *
     * No se usa la serie "1", que es la que lleva el correlativo vivo en la
     * tabla series y que incrementa asignarSerie(); ni la "900", que es la de
     * las 25 cabeceras de la prueba de rendimiento. Con una serie aparte, todo
     * lo que crea este seeder se localiza y se borra con un solo WHERE.
     */
    const SERIE = '901';

    /**
     * Articulos reales del DataMart (MaestroArticulo de db_travel_lab).
     *
     * Se copian aqui en vez de consultarlos a SQL Server porque un seeder que
     * necesita el ERP arriba para correr no sirve como dato de prueba. Los
     * codigos si son autenticos: si el listado lleva a abrir la guia, el
     * articulo existe y el buscador lo encuentra.
     *
     * [codigo, descripcion, codigo de barras, precio publico, precio sin igv,
     *  costo, peso unitario]
     */
    private function articulos(): array
    {
        return [
            'barra_lemon'  => ['34297', 'EP 226ERS ENDURANCE BAR CHOCO BITS 60G CITRIC LEMON', '8436567350043', 17.00, 14.41, 7.71, 0.0600],
            'barra_fresa'  => ['34298', 'EP 226ERS ENDURANCE BAR CHOCO BITS 60G STRAWBERRY', '8436567350067', 17.00, 14.41, 7.71, 0.0600],
            'gorro_835'    => ['87', 'GORRO 835 THINSULATE STRIPED TOQUE', null, 49.90, 42.29, 12.52, 0.1200],
            'gorro_837'    => ['88', 'GORRO 837 HEAVY KNIT STRIPED TOQUE', null, 49.00, 41.53, 12.26, 0.1300],
            'gorro_6154'   => ['89', 'GORRO 6154 BM CABLE KNIT TOQUE', null, 59.00, 50.00, 13.52, 0.1500],
            'gorro_836'    => ['90', 'GORRO 836 FLEECE STRIPED TOQUE', null, 45.00, 38.14, 9.92, 0.1100],
            'gorra_g01'    => ['91', 'GORRA ALGODON G01 COLOR OLIVE', null, 45.00, 38.14, 8.81, 0.0900],
            'gorro_1985'   => ['92', 'GORRO 1985/6/7/8 FLEECE TOQUE', null, 59.00, 50.00, 13.75, 0.1400],
        ];
    }

    /**
     * Las ocho guias.
     *
     * Fechas distintas, estados distintos y razones sociales distintas porque
     * el listado ordena por columna y filtra por documento, razon social y
     * estado: con filas iguales no se distingue si el orden funciono.
     *
     * envio_sunat solo se pone en 1 en guias que NO estan en estado 1. El
     * listado, para las que estan en estado 1 con envio_sunat 1, consulta al
     * facturador una vez por guia y reescribe guia_estado_id con lo que
     * responda: los datos sembrados se moverian solos en cada visita.
     */
    private function guias(): array
    {
        return [
            [
                'numero' => 1001, 'fecha' => '2026-09-03', 'estado' => 2, 'sunat' => 1,
                'cliente' => 'DISTRIBUIDORA ANDINA SAC', 'doc' => '20481234567', 'tipo_doc' => 'RUC',
                'dir_cliente' => 'AV. REPUBLICA DE PANAMA 3030, SAN ISIDRO',
                'partida' => ['15', '1501', '150131', 'AV. CANAVAL Y MOREYRA 480, SAN ISIDRO'],
                'llegada' => ['13', '1301', '130101', 'JR. PIZARRO 458, TRUJILLO'],
                'lineas'  => [['barra_lemon', 24], ['barra_fresa', 18], ['gorro_835', 6]],
            ],
            [
                'numero' => 1002, 'fecha' => '2026-09-04', 'estado' => 1, 'sunat' => 0,
                'cliente' => 'COMERCIAL LOS PORTALES EIRL', 'doc' => '20556677889', 'tipo_doc' => 'RUC',
                'dir_cliente' => 'AV. AVIACION 2405, SAN BORJA',
                'partida' => ['15', '1501', '150130', 'AV. AVIACION 2405, SAN BORJA'],
                'llegada' => ['04', '0401', '040101', 'CALLE MERCADERES 210, AREQUIPA'],
                'lineas'  => [['gorro_6154', 12], ['gorro_836', 9]],
            ],
            [
                'numero' => 1003, 'fecha' => '2026-09-05', 'estado' => 3, 'sunat' => 1,
                'cliente' => 'INVERSIONES EL SOL SRL', 'doc' => '20334455661', 'tipo_doc' => 'RUC',
                'dir_cliente' => 'AV. GRAU 1120, BARRANCO',
                'partida' => ['15', '1501', '150104', 'AV. GRAU 1120, BARRANCO'],
                'llegada' => ['15', '1502', '150201', 'AV. CHICLAYO 145, BARRANCA'],
                'lineas'  => [['gorra_g01', 30], ['gorro_1985', 15], ['barra_lemon', 48], ['gorro_837', 7]],
            ],
            [
                'numero' => 1004, 'fecha' => '2026-09-06', 'estado' => 4, 'sunat' => 0,
                'cliente' => 'MINIMARKET SANTA ROSA SAC', 'doc' => '20998877665', 'tipo_doc' => 'RUC',
                'dir_cliente' => 'CALLE LOS ALAMOS 780, LOS OLIVOS',
                'partida' => ['15', '1501', '150117', 'CALLE LOS ALAMOS 780, LOS OLIVOS'],
                'llegada' => ['15', '1501', '150140', 'AV. CAMINOS DEL INCA 2050, SANTIAGO DE SURCO'],
                'lineas'  => [['barra_fresa', 60], ['barra_lemon', 36]],
            ],
            [
                'numero' => 1005, 'fecha' => '2026-09-07', 'estado' => 2, 'sunat' => 1,
                'cliente' => 'TEXTILES DEL NORTE SA', 'doc' => '20112233445', 'tipo_doc' => 'RUC',
                'dir_cliente' => 'CARRETERA INDUSTRIAL KM 4, TRUJILLO',
                'partida' => ['13', '1301', '130105', 'CARRETERA INDUSTRIAL KM 4, LA ESPERANZA'],
                'llegada' => ['15', '1501', '150101', 'JR. CUZCO 890, CERCADO DE LIMA'],
                'lineas'  => [['gorro_835', 20], ['gorro_837', 20], ['gorro_836', 10]],
            ],
            [
                'numero' => 1006, 'fecha' => '2026-09-08', 'estado' => 1, 'sunat' => 0,
                'cliente' => 'BODEGA DONA CARMEN', 'doc' => '10456789012', 'tipo_doc' => 'DNI',
                'dir_cliente' => 'CALLE LAS BEGONIAS 145, ATE',
                'partida' => ['15', '1501', '150103', 'CALLE LAS BEGONIAS 145, ATE'],
                'llegada' => ['15', '1501', '150137', 'AV. LOS RUISENORES 340, SANTA ANITA'],
                'lineas'  => [['barra_lemon', 12], ['gorra_g01', 4], ['gorro_1985', 3]],
            ],
            [
                'numero' => 1007, 'fecha' => '2026-09-09', 'estado' => 3, 'sunat' => 1,
                'cliente' => 'ALMACENES DEL SUR SAC', 'doc' => '20667788990', 'tipo_doc' => 'RUC',
                'dir_cliente' => 'AV. EJERCITO 1010, YANAHUARA',
                'partida' => ['04', '0401', '040126', 'AV. EJERCITO 1010, YANAHUARA'],
                'llegada' => ['04', '0401', '040104', 'AV. AVIACION 555, CERRO COLORADO'],
                'lineas'  => [['gorro_6154', 25], ['gorro_836', 25]],
            ],
            [
                'numero' => 1008, 'fecha' => '2026-09-10', 'estado' => 4, 'sunat' => 0,
                'cliente' => 'SUPERMERCADOS UNIDOS SAC', 'doc' => '20445566778', 'tipo_doc' => 'RUC',
                'dir_cliente' => 'AV. LA MARINA 2355, SAN MIGUEL',
                'partida' => ['15', '1501', '150136', 'AV. LA MARINA 2355, SAN MIGUEL'],
                'llegada' => ['15', '1501', '150114', 'AV. JAVIER PRADO ESTE 5220, LA MOLINA'],
                'lineas'  => [['barra_fresa', 90], ['barra_lemon', 90], ['gorro_835', 12], ['gorra_g01', 8]],
            ],
        ];
    }

    public function run(): void
    {
        if (! $this->comprobarBaseDeDemo()) {
            return;
        }

        $articulos   = $this->articulos();
        $calculadora = new CalculadoraGuia(Igv::vigente());
        $creadas     = 0;
        $omitidas    = 0;

        foreach ($this->guias() as $guia) {
            // Se puede volver a correr sin duplicar: si el par serie/numero ya
            // esta, se deja como esta en vez de sembrarlo otra vez.
            $yaExiste = GuiaSalida::where('serie', self::SERIE)
                ->where('numero', $guia['numero'])
                ->exists();

            if ($yaExiste) {
                $omitidas++;
                continue;
            }

            DB::transaction(function () use ($guia, $articulos, $calculadora, &$creadas) {
                $this->crearGuia($guia, $articulos, $calculadora);
                $creadas++;
            });
        }

        $this->command->info("GuiaSalidaDemoSeeder: {$creadas} guias creadas, {$omitidas} ya existian (serie " . self::SERIE . ').');
    }

    /**
     * Crea una cabecera con su detalle, cuadrando los importes.
     *
     * Los totales NO se suman a mano: se arma una LineaGuia por item y se pasan
     * a CalculadoraGuia, que es la misma pieza que usa la pantalla. Asi el IGV
     * queda redondeado POR LINEA y luego sumado, que es la regla del sistema, y
     * el total de la cabecera coincide con la suma de los IGV de linea aunque
     * los centavos no cuadren al redondear el total de golpe.
     */
    private function crearGuia(array $guia, array $articulos, CalculadoraGuia $calculadora): void
    {
        $lineas         = [];
        $filasDetalle   = [];
        $pesoBrutoTotal = 0.0;
        $item           = 1;

        foreach ($guia['lineas'] as $linea) {
            list($clave, $cantidad) = $linea;
            list($codigo, $descripcion, $codBarra, $precioPublico, $precioSinIgv, $costo, $pesoUnitario) = $articulos[$clave];

            $lineas[] = new LineaGuia(
                $item,
                $codigo,
                $descripcion,
                (float) $cantidad,
                (float) $precioSinIgv,
                1
            );

            $importe   = round($precioSinIgv * $cantidad, 2);
            $pesoTotal = round($pesoUnitario * $cantidad, 4);
            $pesoBrutoTotal += $pesoTotal;

            $filasDetalle[] = [
                'codarticulo'          => $codigo,
                'codigo_barra'         => $codBarra,
                'descripcion'          => $descripcion,
                'precio'               => $precioSinIgv,
                'precio_publico'       => $precioPublico,
                'precio_sin_igv'       => $precioSinIgv,
                'cantidad'             => $cantidad,
                'importe'              => $importe,
                'porcentaje_descuento' => 0,
                'monto_descuento'      => 0,
                'peso_unitario'        => $pesoUnitario,
                'peso_total'           => $pesoTotal,
                'sigla_umfe'           => 'NIU',
                'desc_unidad_medida'   => 'Und',
                'cod_unidad'           => 1,
                'costo_articulo'       => $costo,
                'costo_total'          => round($costo * $cantidad, 2),
                'activo'               => 1,
                'es_consignado'        => 0,
            ];

            $item++;
        }

        $totales = $calculadora->totales($lineas);

        $cabecera = GuiaSalida::create([
            'serie'                         => self::SERIE,
            'numero'                        => $guia['numero'],
            'fecha_emision'                 => $guia['fecha'],
            'fecha_inicio_traslado'         => $guia['fecha'],
            'hora_emision'                  => '09:30:00',
            'indicar_proveedor'             => 0,
            'cliente_razon_social'          => $guia['cliente'],
            'cliente_nro_documento'         => $guia['doc'],
            'cliente_documento_tipo_nombre' => $guia['tipo_doc'],
            'cliente_direccion'             => $guia['dir_cliente'],
            'motivo_traslado_id'            => '01',
            'descripcion_motivo_traslado'   => 'VENTA',
            'modalidad_traslado'            => '01',
            'transportista_ruc'             => '20601234567',
            'transportista_nombre'          => 'TRANSPORTES RAPIDOS DEL PERU SAC',
            'transportista_direccion'       => 'AV. NICOLAS AYLLON 4500, ATE',
            'chofer_dni'                    => '44556677',
            'chofer_brevete'                => 'Q44556677',
            'chofer_nombre'                 => 'LUIS ALBERTO QUISPE MAMANI',
            'vehiculo_placa'                => 'ABC-123',
            'vehiculo_marca'                => 'HYUNDAI',
            'ubigeo_partida_departamento'   => $guia['partida'][0],
            'ubigeo_partida_provincia'      => $guia['partida'][1],
            'ubigeo_partida_distrito'       => $guia['partida'][2],
            'ubigeo_partida'                => $guia['partida'][2],
            'direccion_partida'             => $guia['partida'][3],
            'ubigeo_llegada_departamento'   => $guia['llegada'][0],
            'ubigeo_llegada_provincia'      => $guia['llegada'][1],
            'ubigeo_llegada_distrito'       => $guia['llegada'][2],
            'ubigeo_llegada'                => $guia['llegada'][2],
            'direccion_llegada'             => $guia['llegada'][3],
            'monto_descuento'               => 0,
            'importe_sin_igv'               => $totales->valorVenta(),
            'monto_igv'                     => $totales->igv(),
            'total_venta'                   => $totales->total(),
            'peso_bruto_total'              => round($pesoBrutoTotal, 2),
            'guia_estado_id'                => $guia['estado'],
            'envio_sunat'                   => $guia['sunat'],
            'comentario'                    => 'DEMO - dato de prueba',
            'activo'                        => 1,
            'enviado_datamarket'            => 0,
            'enviado_facturador'            => 0,
            'es_consignado'                 => 0,
        ]);

        foreach ($filasDetalle as $fila) {
            $fila['guia_salida_id'] = $cabecera->id;
            GuiaSalidaDetalle::create($fila);
        }
    }

    /**
     * Cortafuegos contra sembrar demo en la base de un cliente.
     *
     * Son dos condiciones independientes porque una sola se escapa: un entorno
     * puede tener APP_ENV=local apuntando por error a la base productiva, y una
     * base puede llamarse "demo" en un servidor marcado como production.
     */
    private function comprobarBaseDeDemo(): bool
    {
        $entorno = (string) app()->environment();
        $base    = (string) DB::connection()->getDatabaseName();

        if (app()->environment('production')) {
            $this->command->error('GuiaSalidaDemoSeeder ABORTADO: APP_ENV es "production". Este seeder solo genera datos de demo y no debe correr en produccion.');

            return false;
        }

        if (strpos(strtolower($base), 'demo') === false) {
            $this->command->error("GuiaSalidaDemoSeeder ABORTADO: la base \"{$base}\" no parece de demo (su nombre no contiene \"demo\"). Si de verdad quieres sembrar datos falsos aqui, renombra la base o cambia la guarda a conciencia.");

            return false;
        }

        $this->command->info("GuiaSalidaDemoSeeder: sembrando en \"{$base}\" (entorno {$entorno}).");

        return true;
    }
}
