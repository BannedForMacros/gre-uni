<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * gre:limpiar-pruebas borra guias. Es el unico comando del proyecto que
 * destruye datos del cliente sin que nadie haya pulsado nada en pantalla, asi
 * que lo que hay que demostrar no es que borre, sino que NO borra de mas:
 *
 *   - que sin --ejecutar no escribe una sola fila,
 *   - que una guia buena con un comentario parecido sobrevive,
 *   - que se lleva el detalle y lo relacionado, y no deja huerfanos,
 *   - que una marca floja lo aborta en vez de barrer la base.
 */
class GreLimpiarPruebasTest extends TestCase
{
    use RefreshDatabase;

    private const MARCA = 'PRUEBA E2E - NO VALIDA';

    protected function setUp(): void
    {
        parent::setUp();

        // Los estados solo se usan para pintar el listado, pero sin ellos el
        // comando imprimiria "desconocido #1" y la prueba no se pareceria a lo
        // que ve el operador.
        foreach ([1 => 'Generada', 2 => 'Aceptada', 3 => 'Rechazada', 4 => 'Avance'] as $id => $nombre) {
            DB::table('guia_estados')->insert(['id' => $id, 'nombre' => $nombre, 'activo' => 1]);
        }
    }

    // =================================================================
    // Ayudantes
    // =================================================================

    private function ingreso(?string $comentario, int $numero = 1): int
    {
        $id = DB::table('guia_ingresos')->insertGetId([
            'serie' => '1', 'numero' => $numero, 'fecha_emision' => '2026-09-11', 'hora_emision' => '10:00:00',
            'guia_estado_id' => 1, 'activo' => 1, 'comentario' => $comentario,
            'importe_sin_igv' => 100.00, 'monto_igv' => 18.00, 'total_venta' => 118.00, 'monto_descuento' => 0,
        ]);

        DB::table('guia_ingreso_detalles')->insert([
            'guia_ingreso_id' => $id, 'codarticulo' => 'ART1', 'descripcion' => 'ARTICULO',
            'precio' => 50.00, 'cantidad' => 2, 'importe' => 100.00,
            'porcentaje_descuento' => 0, 'monto_descuento' => 0,
        ]);

        return $id;
    }

    private function salida(?string $comentario, int $numero = 1): int
    {
        $id = DB::table('guia_salidas')->insertGetId([
            'serie' => '1', 'numero' => $numero, 'fecha_emision' => '2026-09-11', 'hora_emision' => '10:00:00',
            'guia_estado_id' => 1, 'activo' => 1, 'comentario' => $comentario,
            'importe_sin_igv' => 100.00, 'monto_igv' => 18.00, 'total_venta' => 118.00, 'monto_descuento' => 0,
        ]);

        DB::table('guia_salida_detalles')->insert([
            'guia_salida_id' => $id, 'codarticulo' => 'ART1', 'descripcion' => 'ARTICULO',
            'precio' => 50.00, 'cantidad' => 2, 'importe' => 100.00,
            'porcentaje_descuento' => 0, 'monto_descuento' => 0,
        ]);

        return $id;
    }

    private function envioFacturacion(string $tabla, int $registroId): void
    {
        DB::table('facturacion_envios')->insert([
            'tabla' => $tabla, 'registro_id' => $registroId,
            'trama_json' => '{}', 'exito' => 1, 'activo' => 1,
        ]);
    }

    private function auditoria(string $tabla, int $registroId): void
    {
        DB::table('auditorias')->insert([
            'tabla' => $tabla, 'registro_id' => (string) $registroId,
            'accion_id' => '1', 'data_json' => '{}',
        ]);
    }

    // =================================================================
    // Simulacro
    // =================================================================

    public function test_por_defecto_es_un_simulacro_y_no_borra_nada(): void
    {
        $this->ingreso(self::MARCA, 7001);
        $this->salida(self::MARCA, 8001);
        $this->ingreso('Guia buena', 7002);

        $this->artisan('gre:limpiar-pruebas')->assertExitCode(0);

        $this->assertSame(2, DB::table('guia_ingresos')->count(), 'el simulacro borro una cabecera');
        $this->assertSame(1, DB::table('guia_salidas')->count());
        $this->assertSame(2, DB::table('guia_ingreso_detalles')->count());
        $this->assertSame(1, DB::table('guia_salida_detalles')->count());
    }

    public function test_pasar_ejecutar_y_dry_run_a_la_vez_no_borra(): void
    {
        $this->ingreso(self::MARCA, 7001);

        $this->artisan('gre:limpiar-pruebas --ejecutar --dry-run --forzar')->assertExitCode(0);

        $this->assertSame(1, DB::table('guia_ingresos')->count(), 'ante una orden ambigua se escribio');
    }

    public function test_si_no_hay_guias_marcadas_informa_y_sale_cero(): void
    {
        $this->ingreso('Guia buena', 7001);

        $this->artisan('gre:limpiar-pruebas --ejecutar --forzar')
             ->expectsOutput('No hay guias de prueba en esta base. Nada que limpiar.')
             ->assertExitCode(0);

        $this->assertSame(1, DB::table('guia_ingresos')->count());
    }

    // =================================================================
    // Borrado
    // =================================================================

    public function test_ejecutar_borra_las_marcadas_con_su_detalle_y_deja_las_legitimas(): void
    {
        $pruebaIngreso = $this->ingreso(self::MARCA, 7001);
        $pruebaSalida  = $this->salida(self::MARCA, 8001);
        $buenaIngreso  = $this->ingreso('Guia buena', 7002);
        $buenaSalida   = $this->salida(null, 8002);

        $this->artisan('gre:limpiar-pruebas --ejecutar --forzar')->assertExitCode(0);

        $this->assertDatabaseMissing('guia_ingresos', ['id' => $pruebaIngreso]);
        $this->assertDatabaseMissing('guia_salidas', ['id' => $pruebaSalida]);
        $this->assertDatabaseHas('guia_ingresos', ['id' => $buenaIngreso]);
        $this->assertDatabaseHas('guia_salidas', ['id' => $buenaSalida]);

        $this->assertSame(0, DB::table('guia_ingreso_detalles')->where('guia_ingreso_id', $pruebaIngreso)->count());
        $this->assertSame(0, DB::table('guia_salida_detalles')->where('guia_salida_id', $pruebaSalida)->count());
        $this->assertSame(1, DB::table('guia_ingreso_detalles')->where('guia_ingreso_id', $buenaIngreso)->count());
        $this->assertSame(1, DB::table('guia_salida_detalles')->where('guia_salida_id', $buenaSalida)->count());
    }

    public function test_no_toca_una_guia_cuyo_comentario_solo_contiene_la_marca(): void
    {
        // Alguien reviso la guia y escribio encima. Ya no es una guia de prueba:
        // es una guia de la que alguien se hizo responsable.
        $revisada = $this->ingreso(self::MARCA . ', revisada por Juan', 7003);
        $prueba   = $this->ingreso(self::MARCA, 7001);

        $this->artisan('gre:limpiar-pruebas --ejecutar --forzar')->assertExitCode(0);

        $this->assertDatabaseHas('guia_ingresos', ['id' => $revisada]);
        $this->assertDatabaseMissing('guia_ingresos', ['id' => $prueba]);
    }

    public function test_no_deja_lineas_sin_cabecera(): void
    {
        $this->ingreso(self::MARCA, 7001);
        $this->salida(self::MARCA, 8001);
        $this->ingreso('Guia buena', 7002);

        $this->artisan('gre:limpiar-pruebas --ejecutar --forzar')
             ->expectsOutput('Verificado: no queda ninguna guia marcada ni ninguna linea sin cabecera.')
             ->assertExitCode(0);

        // La misma comprobacion que hace gre:guias-huerfanas, por si el comando
        // se mintiera a si mismo en su propia verificacion.
        $huerfanosIngreso = DB::table('guia_ingreso_detalles')
            ->whereNotIn('guia_ingreso_id', DB::table('guia_ingresos')->pluck('id'))
            ->count();
        $huerfanosSalida = DB::table('guia_salida_detalles')
            ->whereNotIn('guia_salida_id', DB::table('guia_salidas')->pluck('id'))
            ->count();

        $this->assertSame(0, $huerfanosIngreso);
        $this->assertSame(0, $huerfanosSalida);
    }

    // =================================================================
    // Tablas relacionadas
    // =================================================================

    public function test_borra_lo_relacionado_de_esas_guias_y_no_lo_de_las_buenas(): void
    {
        $prueba = $this->salida(self::MARCA, 8001);
        $buena  = $this->salida('Guia buena', 8002);

        $this->envioFacturacion('guia_salidas', $prueba);
        $this->envioFacturacion('guia_salidas', $buena);
        $this->auditoria('guia_salidas', $prueba);
        $this->auditoria('guia_salidas_datamart', $prueba);
        $this->auditoria('guia_salidas', $buena);

        $this->artisan('gre:limpiar-pruebas --ejecutar --forzar')->assertExitCode(0);

        $this->assertSame(0, DB::table('facturacion_envios')->where('registro_id', $prueba)->count());
        $this->assertSame(1, DB::table('facturacion_envios')->where('registro_id', $buena)->count());
        $this->assertSame(0, DB::table('auditorias')->where('registro_id', (string) $prueba)->count());
        $this->assertSame(1, DB::table('auditorias')->where('registro_id', (string) $buena)->count());
    }

    public function test_no_confunde_el_id_de_un_ingreso_con_el_de_una_salida(): void
    {
        // Ingreso y salida pueden tener el mismo id: son dos secuencias
        // distintas. La auditoria de una no puede irse al borrar la otra.
        $ingreso = $this->ingreso(self::MARCA, 7001);
        $salida  = $this->salida('Guia buena', 8001);

        $this->auditoria('guia_salidas', $salida);

        $this->artisan('gre:limpiar-pruebas --ejecutar --forzar')->assertExitCode(0);

        $this->assertDatabaseMissing('guia_ingresos', ['id' => $ingreso]);
        $this->assertSame(
            1,
            DB::table('auditorias')->where('tabla', 'guia_salidas')->where('registro_id', (string) $salida)->count(),
            'se borro la auditoria de una salida buena al limpiar un ingreso de prueba'
        );
    }

    public function test_conservar_auditoria_deja_el_rastro(): void
    {
        $prueba = $this->salida(self::MARCA, 8001);
        $this->auditoria('guia_salidas', $prueba);

        $this->artisan('gre:limpiar-pruebas --ejecutar --forzar --conservar-auditoria')->assertExitCode(0);

        $this->assertDatabaseMissing('guia_salidas', ['id' => $prueba]);
        $this->assertSame(1, DB::table('auditorias')->where('registro_id', (string) $prueba)->count());
    }

    // =================================================================
    // Seguridad
    // =================================================================

    public function test_se_niega_con_una_marca_demasiado_corta(): void
    {
        $this->ingreso('ok', 7001);

        $this->artisan('gre:limpiar-pruebas --marca=ok --ejecutar --forzar')->assertExitCode(1);

        $this->assertSame(1, DB::table('guia_ingresos')->count(), 'una marca corta borro guias');
    }

    public function test_una_marca_propia_tambien_funciona(): void
    {
        $prueba = $this->ingreso('CARGA MASIVA DE PRUEBA', 7001);
        $buena  = $this->ingreso(self::MARCA . 'X', 7002);

        $this->artisan('gre:limpiar-pruebas --marca="CARGA MASIVA DE PRUEBA" --ejecutar --forzar')->assertExitCode(0);

        $this->assertDatabaseMissing('guia_ingresos', ['id' => $prueba]);
        $this->assertDatabaseHas('guia_ingresos', ['id' => $buena]);
    }

    public function test_si_el_operador_dice_que_no_no_se_borra_nada(): void
    {
        $prueba = $this->ingreso(self::MARCA, 7001);

        $this->artisan('gre:limpiar-pruebas --ejecutar')
             ->expectsConfirmation('Confirmas el borrado?', 'no')
             ->assertExitCode(0);

        $this->assertDatabaseHas('guia_ingresos', ['id' => $prueba]);
    }
}
