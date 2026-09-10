<?php

namespace Tests\Feature;

use App\Models\Parametro;
use App\Models\Serie;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pruebas de GuiaSalidaController.
 *
 * POR QUE EXISTEN
 *   Los bugs que se arreglaron a mano en esta pantalla -el listado esperando a
 *   SUNAT guia por guia, la cabecera que quedaba huerfana cuando fallaba una
 *   linea del detalle, el JSON con <option> dentro, el vendedor de Salida
 *   pidiendose a la ruta de Ingreso- no los habria cazado nadie: el
 *   controlador no tenia ni una prueba. Cada uno de ellos tiene aqui la suya.
 *
 * Todo el trafico saliente va con Http::fake(): estas pruebas tienen que pasar
 * con la ApiGRE y el facturador apagados, que es como esta la maquina de
 * cualquiera que no sea quien monto el entorno.
 */
class GuiaSalidaControllerTest extends TestCase
{
    use RefreshDatabase;

    /** Base de la ApiGRE en las pruebas. Ningun test la levanta de verdad. */
    private const API = 'http://apigre.test/GREDMK';

    /** Endpoint del facturador que responde el estado en SUNAT (parametro 9). */
    private const FACTURADOR = 'http://facturador.test/consultarEstado';

    protected function setUp(): void
    {
        parent::setUp();

        $this->parametro(2, 'ruc_entiedad', '20123456789');
        $this->parametro(6, 'api_datos', self::API);
        $this->parametro(9, 'api_facturacion_consultar_estado', self::FACTURADOR);
        $this->parametro(10, 'validar_stock', 'false');

        // Los ids importan: el controlador traduce el codigo de SUNAT a estos
        // numeros (A->2, B->3, O->5).
        foreach ([1 => 'Generada', 2 => 'Aceptada', 3 => 'Rechazada', 4 => 'Avance', 5 => 'Observada'] as $id => $nombre) {
            DB::table('guia_estados')->insert(['id' => $id, 'nombre' => $nombre]);
        }

        $this->actingAs(User::factory()->create());
    }

    // ---------------------------------------------------------------- ayudas

    private function parametro(int $id, string $nombre, string $valor): void
    {
        $p = new Parametro();
        $p->id     = $id;
        $p->nombre = $nombre;
        $p->valor  = $valor;
        $p->activo = 1;
        $p->save();
    }

    /** La serie ya dada de alta en la base, con su ultimo correlativo emitido. */
    private function serie(string $serie, int $numero, int $id = 1): void
    {
        DB::table('series')->insert([
            'id'                => $id,
            'serie'             => $serie,
            'documento_tipo_id' => 9,
            'numero'            => $numero,
            'activo'            => 1,
            'created_at'        => Carbon::now(),
            'updated_at'        => Carbon::now(),
        ]);
    }

    /** Inserta una guia de salida con lo minimo que mira el listado. */
    private function guia(array $extra = []): int
    {
        return DB::table('guia_salidas')->insertGetId(array_merge([
            'serie'                => '001',
            'numero'               => 100,
            'fecha_emision'        => '2026-09-10',
            'cliente_razon_social' => 'CLIENTE DEMO SAC',
            'indicar_proveedor'    => 0,
            'total_venta'          => 118.00,
            'guia_estado_id'       => 1,
            'envio_sunat'          => 1,
            'enviado_datamarket'   => 0,
            'enviado_facturador'   => 0,
            'activo'               => 1,
            'created_at'           => Carbon::now(),
            'updated_at'           => Carbon::now(),
        ], $extra));
    }

    /**
     * El cuerpo que manda de verdad el navegador al guardar.
     *
     * Se copia de public/js/guias/salida/create.js a proposito, campos sueltos
     * incluidos ('detalle', 'guardar_avance', 'id_continua'): si la prueba
     * mandara solo columnas de la tabla, no probaria lo que pasa en produccion.
     */
    private function payload(array $extra = []): array
    {
        return array_merge([
            'serie'                 => '001',
            'fecha_emision'         => '2026-09-10',
            'fecha_inicio_traslado' => '2026-09-11',
            'guardar_avance'        => 'false',
            'id_continua'           => null,
            'detalle'               => json_encode([]),
            'tipo_operacion_id'     => 1,
            'tipo_operacion_nombre' => 'VENTA',
            'modalidad_traslado'    => '02',
            'vehiculo_placa'        => 'ABC-123',
            'vehiculo_marca'        => 'VOLVO',
            'envio_sunat'           => 0,
            'cliente_id'            => 55,
            'cliente_razon_social'  => 'CLIENTE DEMO SAC',
            'codalmacen'            => '01',
            'almacen_nombre'        => 'PRINCIPAL',
            'monto_descuento'       => 0,
            'importe_sin_igv'       => 100,
            'monto_igv'             => 18,
            'total_venta'           => 118,
            'base_calculo'          => 0,
            'comentario'            => 'prueba',
        ], $extra);
    }

    /** Una linea de detalle completa, tal como la arma guia-detalle.js. */
    private function linea(array $extra = []): array
    {
        return array_merge([
            'codarticulo'          => 'ART-001',
            'descripcion'          => 'ARTICULO DE PRUEBA',
            'precio'               => 118,
            'precio_publico'       => 118,
            'precio_sin_igv'       => 100,
            'cantidad'             => 1,
            'importe'              => 118,
            'porcentaje_descuento' => 0,
            'monto_descuento'      => 0,
            'peso'                 => 1.5,
            'codigo_barra'         => '7750000000001',
            'cod_unidad'           => 1,
            'desc_unidad_medida'   => 'UNIDAD',
            'sigla_umfe'           => 'NIU',
            'costo_articulo'       => 80,
        ], $extra);
    }

    // ------------------------------------------------------------- 1. listar

    /** @test */
    public function el_listado_no_consulta_al_facturador_ni_una_sola_vez()
    {
        Http::fake();

        foreach (range(1, 5) as $n) {
            $this->guia(['numero' => 100 + $n]);
        }

        $respuesta = $this->postJson(route('guiasalida.listar'), [
            'fecha_inicio' => '2026-09-01',
            'fecha_fin'    => '2026-09-30',
        ]);

        $respuesta->assertOk()->assertJson(['procede' => true]);
        $this->assertCount(5, $respuesta->json('guias'));

        // Esto es lo que costaba 3.85 s: una llamada por guia, en serie.
        Http::assertNothingSent();
    }

    /** @test */
    public function el_listado_senala_que_guias_siguen_esperando_respuesta_de_sunat()
    {
        Http::fake();

        $pendiente = $this->guia(['numero' => 200, 'guia_estado_id' => 1, 'envio_sunat' => 1]);
        $aceptada  = $this->guia(['numero' => 201, 'guia_estado_id' => 2, 'envio_sunat' => 1]);
        $sinEnviar = $this->guia(['numero' => 202, 'guia_estado_id' => 1, 'envio_sunat' => 0]);

        $guias = collect($this->postJson(route('guiasalida.listar'), [
            'fecha_inicio' => '2026-09-01',
            'fecha_fin'    => '2026-09-30',
        ])->assertOk()->json('guias'))->keyBy('id');

        $this->assertTrue($guias[$pendiente]['estadoPendiente']);
        $this->assertFalse($guias[$aceptada]['estadoPendiente']);
        $this->assertFalse($guias[$sinEnviar]['estadoPendiente']);

        // El nombre del estado sale de la base, no de una consulta remota.
        $this->assertSame('Generada', $guias[$pendiente]['estadoNombre']);
        $this->assertSame('Aceptada', $guias[$aceptada]['estadoNombre']);
    }

    /** @test */
    public function el_listado_deja_fuera_las_guias_anuladas_y_las_de_otras_fechas()
    {
        Http::fake();

        $dentro = $this->guia(['numero' => 300]);
        $this->guia(['numero' => 301, 'activo' => 0]);
        $this->guia(['numero' => 302, 'fecha_emision' => '2026-08-01']);

        $guias = $this->postJson(route('guiasalida.listar'), [
            'fecha_inicio' => '2026-09-01',
            'fecha_fin'    => '2026-09-30',
        ])->assertOk()->json('guias');

        $this->assertCount(1, $guias);
        $this->assertSame($dentro, $guias[0]['id']);
    }

    /** @test */
    public function el_listado_filtra_por_serie_y_numero_cuando_se_indican()
    {
        Http::fake();

        $buscada = $this->guia(['serie' => '002', 'numero' => 400]);
        $this->guia(['serie' => '002', 'numero' => 401]);
        $this->guia(['serie' => '003', 'numero' => 400]);

        $guias = $this->postJson(route('guiasalida.listar'), [
            'fecha_inicio' => '2026-09-01',
            'fecha_fin'    => '2026-09-30',
            'serie'        => '002',
            'numero'       => 400,
        ])->assertOk()->json('guias');

        $this->assertCount(1, $guias);
        $this->assertSame($buscada, $guias[0]['id']);
    }

    // ------------------------------------------------------- 2. estadosSunat

    /** @test */
    public function el_refresco_guarda_en_la_guia_el_estado_que_devuelve_el_facturador()
    {
        Http::fake([
            self::FACTURADOR => Http::response(['estado' => 'A', 'mensaje' => 'Aceptada por SUNAT']),
        ]);

        $id = $this->guia(['numero' => 500]);

        $respuesta = $this->postJson(route('guiasalida.estadosSunat'), ['ids' => [$id]]);

        $respuesta->assertOk()->assertJson(['procede' => true]);

        $fila = DB::table('guia_salidas')->find($id);
        $this->assertSame(2, (int) $fila->guia_estado_id);
        $this->assertSame('Aceptada por SUNAT', $fila->mensaje_estado_sunat);
        $this->assertNotNull($fila->estado_sunat_consultado_at);

        // Solo devuelve las que cambiaron, con la fila entera: los botones del
        // listado dependen del estado, no solo el texto.
        $this->assertCount(1, $respuesta->json('guias'));
        $this->assertSame('Aceptada', $respuesta->json('guias.0.estadoNombre'));
        $this->assertFalse($respuesta->json('guias.0.estadoPendiente'));
    }

    /** @test */
    public function una_guia_recien_consultada_no_se_vuelve_a_preguntar_al_facturador()
    {
        Http::fake([
            self::FACTURADOR => Http::response(['estado' => null, 'mensaje' => 'En proceso']),
        ]);

        $id = $this->guia(['numero' => 501]);

        $this->postJson(route('guiasalida.estadosSunat'), ['ids' => [$id]])->assertOk();
        Http::assertSentCount(1);

        // El listado se recarga entero despues de cada accion; sin la ventana
        // de vigencia esto repetiria la consulta cada pocos segundos.
        $this->postJson(route('guiasalida.estadosSunat'), ['ids' => [$id]])->assertOk();
        Http::assertSentCount(1);
    }

    /** @test */
    public function pasada_la_ventana_de_vigencia_la_guia_se_vuelve_a_consultar()
    {
        Http::fake([
            self::FACTURADOR => Http::response(['estado' => 'B', 'mensaje' => 'Rechazada']),
        ]);

        $id = $this->guia([
            'numero'                     => 502,
            'estado_sunat_consultado_at' => Carbon::now()->subMinutes(10),
        ]);

        $this->postJson(route('guiasalida.estadosSunat'), ['ids' => [$id]])->assertOk();

        Http::assertSentCount(1);
        $this->assertSame(3, (int) DB::table('guia_salidas')->find($id)->guia_estado_id);
    }

    /** @test */
    public function el_refresco_no_pregunta_por_guias_que_ya_tienen_respuesta_de_sunat()
    {
        Http::fake([self::FACTURADOR => Http::response(['estado' => 'A'])]);

        $aceptada  = $this->guia(['numero' => 503, 'guia_estado_id' => 2]);
        $sinEnviar = $this->guia(['numero' => 504, 'envio_sunat' => 0]);
        $anulada   = $this->guia(['numero' => 505, 'activo' => 0]);

        $respuesta = $this->postJson(route('guiasalida.estadosSunat'), [
            'ids' => [$aceptada, $sinEnviar, $anulada],
        ]);

        $respuesta->assertOk()->assertJson(['procede' => true, 'guias' => []]);
        Http::assertNothingSent();
    }

    /** @test */
    public function un_facturador_caido_deja_la_pantalla_en_pie_y_la_guia_sin_tocar()
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        $id = $this->guia(['numero' => 506]);

        $respuesta = $this->postJson(route('guiasalida.estadosSunat'), ['ids' => [$id]]);

        $respuesta->assertOk()->assertJson(['procede' => true, 'guias' => []]);

        $fila = DB::table('guia_salidas')->find($id);
        $this->assertSame(1, (int) $fila->guia_estado_id);

        // Se marca aunque haya fallado: reintentar en cada recarga contra un
        // servicio caido solo suma esperas.
        $this->assertNotNull($fila->estado_sunat_consultado_at);
    }

    /** @test */
    public function una_respuesta_que_no_es_json_no_cambia_el_estado_de_la_guia()
    {
        Http::fake([self::FACTURADOR => Http::response('<html>404 Not Found</html>', 404)]);

        $id = $this->guia(['numero' => 507]);

        $this->postJson(route('guiasalida.estadosSunat'), ['ids' => [$id]])
             ->assertOk()
             ->assertJson(['procede' => true, 'guias' => []]);

        $this->assertSame(1, (int) DB::table('guia_salidas')->find($id)->guia_estado_id);
    }

    /** @test */
    public function sin_url_de_facturador_configurada_el_refresco_no_intenta_nada()
    {
        Http::fake();
        DB::table('parametros')->where('id', 9)->update(['valor' => '']);

        $id = $this->guia(['numero' => 508]);

        $this->postJson(route('guiasalida.estadosSunat'), ['ids' => [$id]])
             ->assertOk()
             ->assertJson(['procede' => true, 'guias' => []]);

        Http::assertNothingSent();
    }

    /** @test */
    public function el_refresco_sin_ids_responde_vacio_sin_consultar_la_base()
    {
        Http::fake();

        $this->guia(['numero' => 509]);

        $this->postJson(route('guiasalida.estadosSunat'), ['ids' => []])
             ->assertOk()
             ->assertJson(['procede' => true, 'guias' => []]);

        Http::assertNothingSent();
    }

    // -------------------------------------------------------------- 3. store

    /** @test */
    public function generar_una_guia_sin_articulos_no_crea_nada_ni_quema_el_correlativo()
    {
        Http::fake();

        $this->serie('001', 700);

        $respuesta = $this->postJson(route('guiasalida.store'), $this->payload([
            'detalle' => json_encode([]),
        ]));

        $respuesta->assertOk()->assertJson(['procede' => false]);

        $this->assertSame(0, DB::table('guia_salidas')->count());
        $this->assertSame(0, DB::table('guia_salida_detalles')->count());

        // El correlativo es lo que no se puede perder: un hueco en la
        // numeracion hay que justificarlo ante SUNAT.
        $this->assertSame(700, (int) Serie::find(1)->numero);
    }

    /** @test */
    public function una_linea_de_detalle_incompleta_no_deja_la_cabecera_escrita()
    {
        Http::fake();

        $this->serie('001', 700);

        $incompleta = $this->linea();
        unset($incompleta['codarticulo'], $incompleta['descripcion']);

        $respuesta = $this->postJson(route('guiasalida.store'), $this->payload([
            'detalle' => json_encode([$this->linea(), $incompleta]),
        ]));

        // Lo importante es que el usuario vea un mensaje, no un 500.
        $respuesta->assertOk()->assertJson(['procede' => false]);
        $this->assertNotEmpty($respuesta->json('msj'));

        // Y que la transaccion deshaga la cabecera y la linea que si entro.
        $this->assertSame(0, DB::table('guia_salidas')->count());
        $this->assertSame(0, DB::table('guia_salida_detalles')->count());
        $this->assertSame(700, (int) Serie::find(1)->numero);
    }

    /** @test */
    public function una_guia_completa_queda_guardada_con_todas_sus_lineas()
    {
        Http::fake();

        $this->serie('001', 700);

        $respuesta = $this->postJson(route('guiasalida.store'), $this->payload([
            'detalle' => json_encode([
                $this->linea(),
                $this->linea(['codarticulo' => 'ART-002', 'cantidad' => 3]),
            ]),
        ]));

        $respuesta->assertOk()->assertJson(['procede' => true]);

        $this->assertSame(1, DB::table('guia_salidas')->count());

        $guia = DB::table('guia_salidas')->first();
        $this->assertSame('001', $guia->serie);
        $this->assertSame(701, (int) $guia->numero);
        $this->assertSame(1, (int) $guia->guia_estado_id);

        $lineas = DB::table('guia_salida_detalles')->where('guia_salida_id', $guia->id)->get();
        $this->assertCount(2, $lineas);
        $this->assertEqualsCanonicalizing(
            ['ART-001', 'ART-002'],
            $lineas->pluck('codarticulo')->all()
        );

        // El correlativo avanza una sola vez y queda igual al de la guia.
        $this->assertSame(701, (int) Serie::find(1)->numero);
    }

    /** @test */
    public function el_borrador_se_puede_guardar_sin_una_sola_linea()
    {
        Http::fake();

        $this->serie('001', 700);

        $respuesta = $this->postJson(route('guiasalida.store'), $this->payload([
            'guardar_avance' => 'true',
            'detalle'        => json_encode([]),
        ]));

        $respuesta->assertOk()->assertJson(['procede' => true]);

        $guia = DB::table('guia_salidas')->first();
        $this->assertNotNull($guia);
        $this->assertSame(4, (int) $guia->guia_estado_id);
        $this->assertNull($guia->numero);

        // Un borrador no es un documento emitido: no puede gastar correlativo.
        $this->assertSame(700, (int) Serie::find(1)->numero);

        // Y no puede anunciarse como una guia emitida con el numero en blanco.
        $this->assertStringNotContainsString('registrada Nº', $respuesta->json('msj'));
    }

    /** @test */
    public function guardar_no_llama_a_la_apigre_para_una_serie_que_ya_esta_en_la_base()
    {
        Http::fake();

        $this->serie('001', 700);

        $this->postJson(route('guiasalida.store'), $this->payload([
            'detalle' => json_encode([$this->linea()]),
        ]))->assertOk()->assertJson(['procede' => true]);

        Http::assertNothingSent();
    }

    // --------------------------------------------------------- 4. getVendedor

    /** @test */
    public function el_buscador_de_vendedores_devuelve_datos_y_no_un_bloque_de_html()
    {
        Http::fake([
            self::API . '/ObtenerTrabajador*' => Http::response([
                'trabajador' => [
                    ['codTrabajador' => 77, 'apellidos' => "O'BRIEN LOPEZ", 'nombres' => 'JUAN'],
                ],
            ]),
        ]);

        $respuesta = $this->postJson(route('guiasalida.getVendedor'), ['vendedor_codigo' => 77]);

        $respuesta->assertOk()->assertJsonStructure([
            'vendedores' => [['codigo', 'nombre', 'etiqueta']],
        ]);

        $this->assertSame(77, $respuesta->json('vendedores.0.codigo'));
        $this->assertSame("O'BRIEN LOPEZ JUAN", $respuesta->json('vendedores.0.nombre'));
        $this->assertSame("[77] O'BRIEN LOPEZ JUAN", $respuesta->json('vendedores.0.etiqueta'));

        // El apostrofe cortaba el atributo de la <option> y el vendedor se
        // quedaba sin nombre. Aqui no hay atributos que cortar.
        $this->assertStringNotContainsString('<option', $respuesta->getContent());
        $this->assertDoesNotMatchRegularExpression('/<[a-z]/i', $respuesta->getContent());
    }

    /** @test */
    public function un_codigo_sin_vendedores_devuelve_una_lista_vacia_y_no_un_error()
    {
        Http::fake([self::API . '/ObtenerTrabajador*' => Http::response(['trabajador' => null])]);

        $this->postJson(route('guiasalida.getVendedor'), ['vendedor_codigo' => 'NO-EXISTE'])
             ->assertOk()
             ->assertExactJson(['vendedores' => []]);
    }

    /** @test */
    public function la_pantalla_de_salida_pide_los_vendedores_a_la_ruta_de_salida()
    {
        $this->fakeCatalogosDeCreate();

        $html = $this->get(route('guiasalida.create'))->assertOk()->getContent();

        // El selector de Salida apuntaba a guiaingreso/getVendedor: buscar un
        // vendedor desde Salida consultaba el endpoint de la otra pantalla.
        $this->assertStringContainsString(route('guiasalida.getVendedor'), $html);
        $this->assertStringNotContainsString(route('guiaingreso.getVendedor'), $html);
    }

    // ------------------------------------------------------ 5. storeDataMart

    /** @test */
    public function un_datamart_que_responde_exito_false_no_marca_la_guia_como_enviada()
    {
        Http::fake([
            self::API . '/InsertGuiaDMK' => Http::response([
                'exito' => false, 'msgerror' => 'Documento incompleto',
            ]),
        ]);

        $id = $this->guia(['numero' => 800]);

        $respuesta = $this->postJson(route('guiasalida.storeDataMart'), ['id' => $id, 'panel_origen' => 'index']);

        $respuesta->assertOk()->assertJson(['procede' => false]);
        $this->assertStringContainsString('Documento incompleto', $respuesta->json('msj'));
        $this->assertSame(0, (int) DB::table('guia_salidas')->find($id)->enviado_datamarket);
    }

    /** @test */
    public function una_respuesta_sin_la_propiedad_exito_se_trata_como_fallo()
    {
        // Un 404 o una pagina de error del servidor llegan asi. Con isset()
        // pasaban por buenas: se informaba "registrada en DataMart" y la guia
        // nunca habia salido.
        Http::fake([self::API . '/InsertGuiaDMK' => Http::response('<html>Not Found</html>', 404)]);

        $id = $this->guia(['numero' => 801]);

        $respuesta = $this->postJson(route('guiasalida.storeDataMart'), ['id' => $id, 'panel_origen' => 'index']);

        $respuesta->assertOk()->assertJson(['procede' => false]);
        $this->assertSame(0, (int) DB::table('guia_salidas')->find($id)->enviado_datamarket);
    }

    /** @test */
    public function un_datamart_que_responde_exito_true_marca_la_guia_como_enviada()
    {
        Http::fake([self::API . '/InsertGuiaDMK' => Http::response(['exito' => true])]);

        $id = $this->guia(['numero' => 802]);

        $this->postJson(route('guiasalida.storeDataMart'), ['id' => $id, 'panel_origen' => 'index'])
             ->assertOk()
             ->assertJson(['procede' => true]);

        $this->assertSame(1, (int) DB::table('guia_salidas')->find($id)->enviado_datamarket);
    }

    /** @test */
    public function un_id_que_no_existe_devuelve_aviso_y_no_un_error_500()
    {
        Http::fake();

        $this->postJson(route('guiasalida.storeDataMart'), ['id' => 999999, 'panel_origen' => 'index'])
             ->assertOk()
             ->assertJson(['procede' => false]);

        Http::assertNothingSent();
    }

    // ---------------------------------------------------------------- ayudas

    /**
     * create() pide una decena de catalogos a la ApiGRE. Se responden todos con
     * el mismo cuerpo, y vacios: cada llamada lee su propia propiedad, y un
     * catalogo vacio es justo el caso que antes moria en un dd() con la
     * pantalla en negro.
     */
    private function fakeCatalogosDeCreate(): void
    {
        Http::fake([
            '*' => Http::response([
                'formasdePago' => [],
                'operaciones'  => [],
                'almacenes'    => [],
                'listasPrecio' => [],
                'vehiculos'    => [],
                'choferes'     => [],
                'serienumeros' => [],
                'cliente'      => [],
            ]),
        ]);
    }
}
