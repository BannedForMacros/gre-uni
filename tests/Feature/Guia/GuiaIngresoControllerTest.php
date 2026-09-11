<?php

namespace Tests\Feature\Guia;

use App\Models\GuiaEstado;
use App\Models\GuiaIngreso;
use App\Models\GuiaIngresoDetalle;
use App\Models\Parametro;
use App\Models\Serie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pruebas de GuiaIngresoController.
 *
 * POR QUE EXISTEN
 *   Los bugs que se arreglaron a mano el 10/09 -guias "registradas en
 *   DataMarket" que el DataMart habia rechazado, cabeceras sin detalle, el
 *   buscador de proveedores dando 500 a la segunda letra- vivian todos en este
 *   controlador, y el controlador no tenia ni una prueba. Cada caso de abajo
 *   corresponde a uno de esos fallos: si alguien los reintroduce, aqui se ve.
 *
 * SIN RED
 *   Todas las llamadas a la ApiGRE van con Http::fake(), incluido un comodin
 *   '*' al final de cada lista. Sin ese comodin Laravel deja pasar a la red de
 *   verdad lo que no encaja con ningun stub, y la bateria dependeria de que
 *   alguien tuviera la ApiGRE levantada en el 8181.
 */
class GuiaIngresoControllerTest extends TestCase
{
    use RefreshDatabase;

    /** Base de la ApiGRE que se siembra en el parametro 6. */
    private const API = 'http://apigre-de-pruebas.test/GREDMK';

    /** @var \App\Models\User */
    private $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        // El controlador entero arranca con Parametro::find(6)->valor. Sin esta
        // fila cualquier metodo muere con "property valor on null" y la prueba
        // no llegaria a comprobar nada.
        $parametro = new Parametro();
        $parametro->id     = 6;
        $parametro->nombre = 'api_datos';
        $parametro->valor  = self::API;
        $parametro->activo = 1;
        $parametro->save();

        // Todas las rutas pasan por el middleware auth, y registrarAuditoria()
        // lee el empleado del usuario logueado.
        $this->usuario = User::factory()->create(['empleado_id' => 1]);
        $this->actingAs($this->usuario);
    }

    // =====================================================================
    // storeDataMart(): lo que el DataMart rechaza NO puede darse por bueno
    // =====================================================================

    /** @test */
    public function una_guia_que_el_datamart_rechaza_no_se_reporta_como_registrada()
    {
        $guia = $this->guiaGuardada();

        Http::fake([
            '*InsertGuiaDMK' => Http::response([
                'exito'    => false,
                'msgerror' => 'Documento incompleto',
            ]),
            '*' => Http::response([], 200),
        ]);

        $respuesta = $this->post(route('guiaingreso.storeDataMart'), [
            'id'           => $guia->id,
            'panel_origen' => 'index',
        ]);

        $respuesta->assertOk();
        $respuesta->assertJson(['procede' => false]);

        // El motivo del rechazo tiene que llegar al usuario: si solo se dijera
        // "no se pudo", nadie sabria que arreglar en la guia.
        $this->assertStringContainsString('Documento incompleto', $respuesta->json('msj'));

        $this->assertSame(0, (int) $guia->fresh()->enviado_datamarket);
    }

    /** @test */
    public function una_respuesta_sin_exito_de_la_api_no_se_da_por_registrada()
    {
        // Este es el caso del isset(). Un 404 o una pagina de error de IIS no
        // traen la propiedad 'exito', y antes eso pasaba por bueno: al usuario
        // se le decia "registrada en DataMarket" con la guia nunca enviada.
        $guia = $this->guiaGuardada();

        Http::fake([
            '*InsertGuiaDMK' => Http::response('<html><body>404 - File not found</body></html>', 404),
            '*'              => Http::response([], 200),
        ]);

        $respuesta = $this->post(route('guiaingreso.storeDataMart'), [
            'id'           => $guia->id,
            'panel_origen' => 'index',
        ]);

        $respuesta->assertOk();
        $respuesta->assertJson(['procede' => false]);
        $this->assertStringNotContainsString('registrada en DataMarket', $respuesta->json('msj'));
        $this->assertSame(0, (int) $guia->fresh()->enviado_datamarket);
    }

    /** @test */
    public function una_guia_aceptada_queda_marcada_y_el_listado_deja_de_ofrecer_el_reenvio()
    {
        // El marcado local faltaba SOLO en ingreso: la guia salia bien y el
        // listado seguia ofreciendo "Reenviar a DataMart" para siempre.
        $this->estadosDeGuia();

        $guia = $this->guiaGuardada();
        $this->assertTrue($this->listadoOfreceReenvio($guia));

        Http::fake([
            '*InsertGuiaDMK' => Http::response(['exito' => true]),
            '*'              => Http::response([], 200),
        ]);

        $respuesta = $this->post(route('guiaingreso.storeDataMart'), [
            'id'           => $guia->id,
            'panel_origen' => 'index',
        ]);

        $respuesta->assertOk();
        $respuesta->assertJson(['procede' => true]);
        $this->assertSame(1, (int) $guia->fresh()->enviado_datamarket);
        $this->assertFalse($this->listadoOfreceReenvio($guia));
    }

    /** @test */
    public function con_la_apigre_apagada_la_guia_no_se_marca_como_enviada()
    {
        $guia = $this->guiaGuardada();

        Http::fake([
            '*InsertGuiaDMK' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('cURL error 7: Failed to connect');
            },
            '*' => Http::response([], 200),
        ]);

        $respuesta = $this->post(route('guiaingreso.storeDataMart'), [
            'id'           => $guia->id,
            'panel_origen' => 'index',
        ]);

        $respuesta->assertOk();
        $respuesta->assertJson(['procede' => false]);
        $this->assertSame(0, (int) $guia->fresh()->enviado_datamarket);
    }

    // =====================================================================
    // store(): ninguna cabecera puede quedarse huerfana
    // =====================================================================

    /** @test */
    public function una_guia_sin_articulos_no_se_registra_ni_consume_la_serie()
    {
        Http::fake(['*' => Http::response([], 200)]);

        $serie = $this->serieLocal(1910);

        $respuesta = $this->post(route('guiaingreso.store'), $this->payloadGuia([
            'detalle' => json_encode([]),
        ]));

        $respuesta->assertOk();
        $respuesta->assertJson(['procede' => false]);

        $this->assertSame(0, GuiaIngreso::count());
        // El correlativo es lo caro: una vez avanzado, ese numero se pierde
        // para siempre y el DataMart ve un hueco en la serie.
        $this->assertSame(1910, (int) $serie->fresh()->numero);
    }

    /** @test */
    public function una_linea_incompleta_no_deja_la_cabecera_huerfana()
    {
        // Asi nacieron las guias que el DataMart rechazaba por "Documento
        // incompleto": un campo que faltaba en la linea reventaba al armar el
        // modelo, salia un 500 y la cabecera ya estaba escrita.
        Http::fake(['*' => Http::response([], 200)]);

        $this->serieLocal(1910);

        $lineaRota = $this->linea();
        unset($lineaRota['precio_publico']);

        $respuesta = $this->post(route('guiaingreso.store'), $this->payloadGuia([
            'detalle' => json_encode([$lineaRota]),
        ]));

        // 200 con mensaje, no un 500: el JS del listado espera 'procede'.
        $respuesta->assertOk();
        $respuesta->assertJson(['procede' => false]);

        $this->assertSame(0, GuiaIngreso::count());
        $this->assertSame(0, GuiaIngresoDetalle::count());
    }

    /** @test */
    public function una_guia_con_articulos_guarda_la_cabecera_y_sus_lineas()
    {
        Http::fake(['*' => Http::response([], 200)]);

        $serie = $this->serieLocal(1910);

        $respuesta = $this->post(route('guiaingreso.store'), $this->payloadGuia());

        $respuesta->assertOk();
        $respuesta->assertJson(['procede' => true]);

        $this->assertSame(1, GuiaIngreso::count());

        $guia = GuiaIngreso::first();
        $this->assertSame(1911, (int) $guia->numero);
        $this->assertSame(1, (int) $guia->guia_estado_id);
        $this->assertSame(1911, (int) $serie->fresh()->numero);

        $lineas = GuiaIngresoDetalle::where('guia_ingreso_id', $guia->id)->get();
        $this->assertCount(1, $lineas);
        $this->assertSame('34297', $lineas[0]->codarticulo);

        // El apostrofe tiene que sobrevivir: el "limpiarCaracteres" de antes se
        // llevaba por delante el nombre del articulo de forma permanente.
        $this->assertStringContainsString("L'ORANGE", $lineas[0]->descripcion);
    }

    /** @test */
    public function el_borrador_si_puede_guardarse_sin_articulos()
    {
        Http::fake(['*' => Http::response([], 200)]);

        $respuesta = $this->post(route('guiaingreso.store'), $this->payloadGuia([
            'guardar_avance' => 'true',
            'detalle'        => json_encode([]),
        ]));

        $respuesta->assertOk();
        $respuesta->assertJson(['procede' => true]);

        $this->assertSame(1, GuiaIngreso::count());
        $this->assertSame(4, (int) GuiaIngreso::first()->guia_estado_id);
    }

    /** @test */
    public function al_generar_la_guia_definitiva_el_avance_de_origen_deja_de_estar_activo()
    {
        // El formulario de ingreso manda el id del avance en 'id_continuar'
        // (el de salida lo llama 'id_continua'). Si el controlador lee el
        // nombre equivocado, el avance nunca se desactiva y queda duplicado en
        // el listado junto a la guia ya generada.
        Http::fake(['*' => Http::response([], 200)]);

        $this->serieLocal(1910);
        $avance = $this->guiaGuardada(['guia_estado_id' => 4, 'numero' => null, 'serie' => null]);

        $respuesta = $this->post(route('guiaingreso.store'), $this->payloadGuia([
            'id_continuar' => $avance->id,
        ]));

        $respuesta->assertOk();
        $respuesta->assertJson(['procede' => true]);
        $this->assertSame(0, (int) $avance->fresh()->activo);
    }

    // =====================================================================
    // continuar(): retomar un avance
    // =====================================================================

    /** @test */
    public function retomar_una_guia_guardada_devuelve_la_pantalla_con_su_detalle()
    {
        // /guiaingreso/continuar/{id} respondia "Method lineasParaVista does
        // not exist": retomar un avance estaba roto por completo.
        $this->fakeApiDeLaPantalla();

        $guia = $this->guiaGuardada(['guia_estado_id' => 4]);
        $this->lineaGuardada($guia);

        $respuesta = $this->get(route('guiaingreso.continuar', ['guia' => $guia->id]));

        $respuesta->assertOk();

        $lineas = $respuesta->viewData('lineasDetalle');
        $this->assertCount(1, $lineas);

        // Las claves van en camelCase porque las consume
        // public/js/gre/guia-detalle.js, no un Blade. Si se renombran, la
        // pantalla se abre vacia sin ningun error visible.
        foreach (['codArticulo', 'descripcion', 'cantidad', 'precioSinIgv', 'precioPublico',
                  'costoArticulo', 'codUnidad', 'descUnidadMedida', 'siglaUmfe',
                  'porcentajeDescuento', 'bonificacion', 'esConsignado'] as $clave) {
            $this->assertArrayHasKey($clave, $lineas[0]);
        }

        $this->assertSame('34297', $lineas[0]['codArticulo']);
        $this->assertSame(7.71, $lineas[0]['precioSinIgv']);
        $this->assertSame(3.0, $lineas[0]['cantidad']);
        $this->assertTrue($lineas[0]['esConsignado']);
    }

    // =====================================================================
    // listarProveedores(): escribir las primeras letras no puede dar 500
    // =====================================================================

    /** @test */
    public function escribir_una_sola_letra_devuelve_lista_vacia_en_vez_de_un_error()
    {
        // Buscando por razon social (tipo 3) hacen falta 3 letras. Con una o
        // dos no se entraba al if, $listItems no existia y el foreach de abajo
        // respondia 500: el usuario veia "The results could not be loaded"
        // mientras escribia las primeras letras de CUALQUIER proveedor.
        Http::fake(['*' => Http::response([], 200)]);

        $respuesta = $this->get(route('guiaingreso.listarProveedores', ['term' => 'a', 'tipo' => 3]));

        $respuesta->assertOk();
        $respuesta->assertExactJson(['items' => [], 'hayMas' => false]);

        // Ya no hay minimo de letras: una sola letra consulta a la ApiGRE.
        Http::assertSentCount(1);
    }

    /** @test */
    public function una_respuesta_inesperada_de_la_api_deja_el_buscador_vacio_sin_reventar()
    {
        Http::fake([
            '*ObtenerProveedores' => Http::response('<html>Service Unavailable</html>', 503),
            '*'                   => Http::response([], 200),
        ]);

        $respuesta = $this->get(route('guiaingreso.listarProveedores', ['term' => 'distribuidora', 'tipo' => 3]));

        $respuesta->assertOk();
        $respuesta->assertJson(['items' => []]);
    }

    /** @test */
    public function el_buscador_de_proveedores_arma_cada_opcion_con_ruc_y_razon_social()
    {
        Http::fake([
            '*ObtenerProveedores' => Http::response([
                'proveedores' => [
                    ['codProveedor' => 312, 'ruc' => '20100055237', 'nombreproveedor' => "L'OREAL PERU S.A."],
                ],
            ]),
            '*' => Http::response([], 200),
        ]);

        $respuesta = $this->get(route('guiaingreso.listarProveedores', ['term' => 'oreal', 'tipo' => 3]));

        $respuesta->assertOk();
        $respuesta->assertJson([
            'items' => [
                [
                    'id'               => 312,
                    'text'             => "[20100055237] L'OREAL PERU S.A.",
                    'proveedor_nombre' => "L'OREAL PERU S.A.",
                    'proveedor_ruc'    => '20100055237',
                ],
            ],
        ]);
    }

    // =====================================================================
    // getVendedor(): datos, no HTML
    // =====================================================================

    /** @test */
    public function el_selector_de_vendedor_recibe_datos_y_no_html()
    {
        // Devolvia un bloque de <option> con los atributos entre comillas
        // simples: un apellido con apostrofe cortaba la opcion y el vendedor se
        // quedaba sin nombre.
        Http::fake([
            '*ObtenerTrabajador*' => Http::response([
                'trabajador' => [
                    ['codTrabajador' => 51, 'apellidos' => "O'BRIEN PEREZ", 'nombres' => 'JUAN'],
                ],
            ]),
            '*' => Http::response([], 200),
        ]);

        $respuesta = $this->post(route('guiaingreso.getVendedor'), ['vendedor_codigo' => 51]);

        $respuesta->assertOk();
        $respuesta->assertJson([
            'vendedores' => [
                ['codigo' => 51, 'nombre' => "O'BRIEN PEREZ JUAN", 'etiqueta' => "[51] O'BRIEN PEREZ JUAN"],
            ],
        ]);

        // Lo importante no es solo que los datos esten, sino que no vuelva a
        // colarse HTML armado en el servidor.
        $this->assertStringNotContainsString('<option', $respuesta->getContent());
        $this->assertStringNotContainsString('<', $respuesta->json('vendedores.0.nombre'));
    }

    /** @test */
    public function un_codigo_de_vendedor_sin_resultados_devuelve_una_lista_vacia()
    {
        Http::fake([
            '*ObtenerTrabajador*' => Http::response(['trabajador' => []]),
            '*'                   => Http::response([], 200),
        ]);

        $respuesta = $this->post(route('guiaingreso.getVendedor'), ['vendedor_codigo' => 99999]);

        $respuesta->assertOk();
        $respuesta->assertExactJson(['vendedores' => []]);
    }

    // =====================================================================
    // Ayudantes
    // =====================================================================

    /** Guia ya registrada en la base, como la que ve el listado. */
    private function guiaGuardada(array $extra = []): GuiaIngreso
    {
        return GuiaIngreso::create(array_merge([
            'serie'              => '001',
            'numero'             => 1911,
            'fecha_emision'      => '2026-09-10',
            'hora_emision'       => '08:47:19',
            'vendedor_id'        => 51,
            'proveedor_id'       => 312,
            'proveedor_nombre'   => "L'OREAL PERU S.A.",
            'divisa_id'          => 1,
            'forma_pago_id'      => 1,
            'tipo_operacion_id'  => 37,
            'codalmacen'         => '1',
            'monto_descuento'    => 0,
            'importe_sin_igv'    => 23.13,
            'monto_igv'          => 4.16,
            'total_venta'        => 27.29,
            'guia_estado_id'     => 1,
            'enviado_datamarket' => 0,
            'activo'             => 1,
        ], $extra));
    }

    private function lineaGuardada(GuiaIngreso $guia): GuiaIngresoDetalle
    {
        $linea = new GuiaIngresoDetalle();
        $linea->guia_ingreso_id      = $guia->id;
        $linea->codarticulo          = '34297';
        $linea->codigo_barra         = '8436567350043';
        $linea->descripcion          = "BARRA L'ORANGE & CITRIC";
        $linea->precio               = 7.71;
        $linea->precio_publico       = 17;
        $linea->precio_sin_igv       = 7.71;
        $linea->cantidad             = 3;
        $linea->importe              = 23.13;
        $linea->porcentaje_descuento = 0;
        $linea->monto_descuento      = 0;
        $linea->cod_unidad           = 1;
        $linea->desc_unidad_medida   = 'Und';
        $linea->sigla_umfe           = 'NIU';
        $linea->costo_articulo       = 7.71;
        $linea->es_consignado        = 1;
        $linea->save();

        return $linea;
    }

    /**
     * Serie ya conocida en local, para que asignarSerie() no llame a la API.
     *
     * Se inserta con el query builder porque la tabla series NO es
     * auto_increment: con el modelo, Eloquent pide el lastInsertId despues de
     * guardar, se trae un 0 y deja el objeto apuntando a una fila que no
     * existe, asi que un fresh() posterior devuelve null.
     */
    private function serieLocal(int $numero): Serie
    {
        \Illuminate\Support\Facades\DB::table('series')->insert([
            'id'                => 1,
            'documento_tipo_id' => 9,
            'serie'             => '001',
            'numero'            => $numero,
            'activo'            => 1,
        ]);

        return Serie::find(1);
    }

    /** Estados de guia, que el listado necesita para nombrar cada fila. */
    private function estadosDeGuia(): void
    {
        \Illuminate\Support\Facades\DB::table('guia_estados')->insert([
            ['id' => 1, 'nombre' => 'Generada'],
            ['id' => 2, 'nombre' => 'Aceptada'],
            ['id' => 3, 'nombre' => 'Rechazada'],
            ['id' => 4, 'nombre' => 'Avance'],
        ]);
    }

    /** Payload equivalente al FormData que arma public/js/guias/ingreso/create.js. */
    private function payloadGuia(array $extra = []): array
    {
        return array_merge([
            'es_guia_interna'       => 1,
            'serie'                 => '001',
            'fecha_emision'         => '2026-09-10',
            'vendedor_id'           => 51,
            'vendedor_nombre'       => 'PEREZ JUAN',
            'proveedor_id'          => 312,
            'proveedor_nombre'      => "L'OREAL PERU S.A.",
            'proveedor_ruc'         => '20100055237',
            'divisa_id'             => 1,
            'divisa_nombre'         => 'SOLES',
            'forma_pago_id'         => 1,
            'forma_pago_nombre'     => 'CONTADO',
            'tipo_operacion_id'     => 37,
            'tipo_operacion_nombre' => 'COMPRA',
            'codalmacen'            => '1',
            'almacen_nombre'        => 'PRINCIPAL',
            'codestacion'           => '1',
            'base_calculo'          => 1,
            'monto_descuento'       => 0,
            'importe_sin_igv'       => 23.13,
            'monto_igv'             => 4.16,
            'total_venta'           => 27.29,
            'comentario'            => 'guia de prueba',
            'es_consignado'         => 0,
            'guardar_avance'        => 'false',
            'detalle'               => json_encode([$this->linea()]),
        ], $extra);
    }

    /** Una linea del detalle tal como la manda window.greDetalle. */
    private function linea(array $extra = []): array
    {
        return array_merge([
            'item'                 => 1,
            'codarticulo'          => '34297',
            'codigo_barra'         => '8436567350043',
            'descripcion'          => "BARRA L'ORANGE & CITRIC",
            'cantidad'             => 3,
            'precio'               => 7.71,
            'importe'              => 23.13,
            'porcentaje_descuento' => 0,
            'monto_descuento'      => 0,
            'cod_unidad'           => 1,
            'desc_unidad_medida'   => 'Und',
            'sigla_umfe'           => 'NIU',
            'costo_articulo'       => 7.71,
            'precio_publico'       => 17,
            'precio_sin_igv'       => 7.71,
            'peso_unitario'        => 0,
            'tipo_igv'             => 1,
            'bonificacion'         => 0,
            'es_consignado'        => 0,
        ], $extra);
    }

    /** Los catalogos que la pantalla de create/continuar pide al abrirse. */
    private function fakeApiDeLaPantalla(): void
    {
        Http::fake([
            '*ObtenerProveedores' => Http::response([
                'proveedores' => [
                    ['codProveedor' => 312, 'ruc' => '20100055237', 'nombreproveedor' => "L'OREAL PERU S.A."],
                ],
            ]),
            '*ObtenerFormasPago' => Http::response([
                'formasdePago' => [['codFormaPago' => 1, 'descripcion' => 'CONTADO']],
            ]),
            '*ObtenerOperacion' => Http::response([
                'operaciones' => [
                    ['tipoOperacion' => 37, 'descripcion' => 'COMPRA', 'ingresoSalida' => 'Ingreso'],
                ],
            ]),
            '*ObtenerAlmacenes' => Http::response([
                'almacenes' => [['codAlmacen' => 1, 'descripcion' => 'PRINCIPAL', 'codEstacion' => 1]],
            ]),
            '*ObtenerTrabajador*' => Http::response([
                'trabajador' => [['codTrabajador' => 51, 'apellidos' => 'PEREZ', 'nombres' => 'JUAN']],
            ]),
            '*obtenerSeriesNumerosGuia' => Http::response([
                'serienumeros' => [
                    ['numserie' => '001', 'ultimoValormarket' => 1910, 'tipodocumento' => 9],
                ],
            ]),
            '*' => Http::response([], 200),
        ]);
    }

    /** ¿El listado sigue ofreciendo "Reenviar a DataMart" para esta guia? */
    private function listadoOfreceReenvio(GuiaIngreso $guia): bool
    {
        $respuesta = $this->post(route('guiaingreso.listar'), [
            'fecha_inicio' => '2026-01-01',
            'fecha_fin'    => '2026-12-31',
        ]);

        $respuesta->assertOk();

        foreach ($respuesta->json('guias') as $fila) {
            if ((int) $fila['id'] === (int) $guia->id) {
                return (bool) $fila['mostrarGuardarDatamarket'];
            }
        }

        $this->fail("La guia {$guia->id} no aparece en el listado.");
    }
}
