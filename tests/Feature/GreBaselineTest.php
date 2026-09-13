<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * gre:baseline es lo que permite que un cliente que YA tiene el sistema pueda
 * recibir actualizaciones. Su base nacio de un dump importado, no de correr
 * migraciones, asi que su tabla `migrations` esta vacia o a medias y
 * `artisan migrate` aborta con "Table 'users' already exists".
 *
 * Lo que importa de este comando, y lo que fija esta prueba:
 *
 *   - Marca como aplicado el esquema que el cliente ya tiene, SIN ejecutar una
 *     sola sentencia DDL y sin tocar un solo dato.
 *   - Deja pendientes las migraciones nuevas, que si tienen que ejecutarse.
 *   - Es idempotente: correrlo dos veces no cambia nada.
 *   - Si el esquema no es del GRE, se niega y no escribe.
 *   - --dry-run no escribe.
 *
 * NO se usa RefreshDatabase a proposito. Estas pruebas BORRAN Y CREAN TABLAS
 * para imitar la base de un cliente viejo, y en MySQL el DDL confirma la
 * transaccion en curso: el rollback de RefreshDatabase no devolveria el
 * esquema a su sitio y contaminaria las pruebas siguientes. Cada prueba parte
 * de una base recien migrada.
 */
class GreBaselineTest extends TestCase
{
    /**
     * El mismo corte que usa el comando (CORTE_DEFAULT). Si alla cambia, aqui
     * falla, que es justo lo que debe pasar: el corte es el contrato.
     */
    private const CORTE = '2026_01_01_000000';

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh', ['--force' => true])->run();

        // Esta clase puede dejar el esquema a medias (borra tablas a proposito).
        // Se marca la base como no migrada para que la siguiente clase que use
        // RefreshDatabase la vuelva a construir en vez de heredar el destrozo.
        RefreshDatabaseState::$migrated = false;
    }

    // ---------------------------------------------------------------- ayudas

    /** Los nombres de migracion tal como los lee el comando: ordenados. */
    private function archivosDeMigracion(): array
    {
        $archivos = array_map(
            fn ($f) => basename($f, '.php'),
            glob(database_path('migrations/*.php'))
        );
        sort($archivos);

        return $archivos;
    }

    private function registradas(): array
    {
        return DB::table('migrations')->orderBy('migration')->pluck('migration')->all();
    }

    /** Lo que un cliente viejo ya tiene en su base. */
    private function anterioresAlCorte(): array
    {
        return array_values(array_filter(
            $this->archivosDeMigracion(),
            fn ($m) => strcmp($m, self::CORTE) < 0
        ));
    }

    /** Lo que todavia no tiene y hay que ejecutarle de verdad. */
    private function desdeElCorte(): array
    {
        return array_values(array_filter(
            $this->archivosDeMigracion(),
            fn ($m) => strcmp($m, self::CORTE) >= 0
        ));
    }

    /**
     * Deja la base como la de un cliente que viene del sistema anterior:
     * el esquema ANTERIOR al corte, y la tabla `migrations` como la dejo la
     * importacion de un dump.
     *
     * Se deshacen las migraciones posteriores al corte porque un cliente viejo
     * no tiene esas columnas. Sin este paso la prueba seria mentira: el
     * `migrate` final no tendria nada real que aplicar.
     */
    private function baseDeClienteViejo(): void
    {
        $this->artisan('migrate:rollback', [
            '--force' => true,
            '--step'  => count($this->desdeElCorte()),
        ])->run();

        DB::table('migrations')->delete();
    }

    // --------------------------------------------------------------- pruebas

    public function test_base_importada_sin_migraciones_marca_el_esquema_viejo_y_deja_pendiente_lo_nuevo(): void
    {
        $this->baseDeClienteViejo();

        // Un dato del cliente, para comprobar que el baseline no lo toca.
        $guia = DB::table('guia_ingresos')->insertGetId([
            'serie' => '001', 'numero' => 7, 'activo' => 1,
        ]);

        $this->assertSame(0, DB::table('migrations')->count(), 'la base de partida debe imitar un dump importado');
        $this->assertFalse(Schema::hasColumn('guia_salidas', 'fecha_inicio_traslado'));

        $this->artisan('gre:baseline')->assertExitCode(0);

        // Se marco exactamente el esquema anterior al corte, ni una mas.
        $this->assertSame($this->anterioresAlCorte(), $this->registradas());

        // Lo nuevo NO se marca: tiene que ejecutarse de verdad despues.
        foreach ($this->desdeElCorte() as $pendiente) {
            $this->assertNotContains($pendiente, $this->registradas(), "{$pendiente} no debia marcarse");
        }

        // Ni una sola sentencia DDL: la columna nueva sigue sin existir.
        $this->assertFalse(Schema::hasColumn('guia_salidas', 'fecha_inicio_traslado'));

        // Ni un dato tocado.
        $this->assertSame(1, DB::table('guia_ingresos')->where('id', $guia)->count());

        // Y lo que el instalador hace a continuacion ahora si entra.
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);

        $this->assertTrue(Schema::hasColumn('guia_salidas', 'fecha_inicio_traslado'));
        $this->assertSame($this->archivosDeMigracion(), $this->registradas());
        $this->assertSame(1, DB::table('guia_ingresos')->where('id', $guia)->count(), 'migrate no puede perder guias');
    }

    public function test_con_migrations_a_medias_solo_marca_las_que_faltan(): void
    {
        $this->baseDeClienteViejo();

        // Un cliente al que alguien ya le corrio migraciones alguna vez.
        $viejas = $this->anterioresAlCorte();
        $yaEstaban = array_slice($viejas, 0, 10);
        foreach ($yaEstaban as $m) {
            DB::table('migrations')->insert(['migration' => $m, 'batch' => 1]);
        }

        $this->artisan('gre:baseline')->assertExitCode(0);

        $this->assertSame($viejas, $this->registradas());

        // Las que ya estaban conservan su lote; las que agrega el baseline
        // entran en uno nuevo, para que se vea que las puso el baseline.
        $this->assertSame(1, (int) DB::table('migrations')->where('migration', $yaEstaban[0])->value('batch'));
        $this->assertSame(2, (int) DB::table('migrations')->where('migration', end($viejas))->value('batch'));
    }

    public function test_correrlo_dos_veces_no_cambia_nada(): void
    {
        $this->baseDeClienteViejo();

        $this->artisan('gre:baseline')->assertExitCode(0);
        $trasLaPrimera = DB::table('migrations')->orderBy('id')->get()->toArray();

        // La segunda vez es una actualizacion cualquiera: no debe duplicar
        // filas ni agregar un lote nuevo.
        $this->artisan('gre:baseline')
             ->expectsOutput('Nada que marcar. La base ya esta sincronizada.')
             ->assertExitCode(0);

        $this->assertEquals($trasLaPrimera, DB::table('migrations')->orderBy('id')->get()->toArray());
    }

    public function test_en_una_base_ya_migrada_no_escribe_nada(): void
    {
        // Instalacion nueva, hecha con migrate: las 40 migraciones registradas.
        $antes = $this->registradas();
        $this->assertSame($this->archivosDeMigracion(), $antes);

        $this->artisan('gre:baseline')
             ->expectsOutput('Nada que marcar. La base ya esta sincronizada.')
             ->assertExitCode(0);

        $this->assertSame($antes, $this->registradas());
    }

    public function test_un_esquema_que_no_es_del_gre_se_rechaza_sin_escribir(): void
    {
        $this->baseDeClienteViejo();

        // Otro Laravel cualquiera del cliente: tiene users, no tiene guias.
        Schema::drop('guia_ingresos');
        Schema::drop('guia_salidas');

        $this->artisan('gre:baseline')->assertExitCode(1);

        $this->assertSame(0, DB::table('migrations')->count(), 'no puede escribir sobre un esquema ajeno');
    }

    public function test_una_base_vacia_se_rechaza_y_no_crea_la_tabla_migrations(): void
    {
        $this->artisan('db:wipe', ['--force' => true])->run();

        $this->artisan('gre:baseline')->assertExitCode(1);

        // Una base vacia es una instalacion nueva: le toca migrate, no baseline.
        $this->assertFalse(Schema::hasTable('migrations'));
    }

    public function test_dry_run_no_escribe_nada(): void
    {
        $this->baseDeClienteViejo();

        $this->artisan('gre:baseline', ['--dry-run' => true])
             ->expectsOutput('Dry-run: no se escribio nada.')
             ->assertExitCode(0);

        $this->assertSame(0, DB::table('migrations')->count());
        $this->assertFalse(Schema::hasColumn('guia_salidas', 'fecha_inicio_traslado'));
    }
}
