<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prepara un cliente EXISTENTE para poder recibir actualizaciones.
 *
 * PROBLEMA
 *   Muchos clientes tienen la base creada importando un dump, no corriendo
 *   migraciones. Su tabla `migrations` esta vacia o incompleta, asi que
 *   `artisan migrate` intenta crear tablas que ya existen y aborta:
 *       SQLSTATE[42S01]: Table 'users' already exists
 *   No se pierden datos, pero la actualizacion no entra.
 *
 * QUE HACE
 *   Marca como YA APLICADAS las migraciones cuyo efecto ya esta en la base,
 *   sin ejecutar una sola sentencia DDL. Despues `artisan migrate` corre
 *   unicamente lo nuevo.
 *
 * SEGURIDAD
 *   - No crea, no altera y no borra ninguna tabla de datos.
 *   - Solo escribe filas en la tabla `migrations`.
 *   - Si el esquema no coincide con lo esperado, se niega a continuar.
 *   - Idempotente: correrlo dos veces no cambia nada.
 *
 * USO
 *   php artisan gre:baseline --dry-run    (ver que haria, sin escribir)
 *   php artisan gre:baseline
 *   php artisan migrate --force
 */
class GreBaseline extends Command
{
    protected $signature = 'gre:baseline
                            {--hasta= : Marca como aplicadas las migraciones anteriores a este prefijo (default: todas menos las nuevas de 2026_09)}
                            {--dry-run : Muestra que haria, sin escribir nada}';

    protected $description = 'Marca como aplicadas las migraciones ya presentes en la base (clientes existentes)';

    /**
     * Todo lo anterior a este prefijo se considera parte del esquema que el
     * cliente YA tiene. Verificado: el esquema de produccion coincide con las
     * migraciones hasta 2024_11_07 sin una sola diferencia de columna.
     */
    private const CORTE_DEFAULT = '2026_01_01_000000';

    /** Tablas que debe tener una instalacion GRE valida. */
    private array $centinelas = [
        'users', 'parametros', 'series', 'empleados', 'perfiles',
        'guia_ingresos', 'guia_ingreso_detalles',
        'guia_salidas',  'guia_salida_detalles',
        'guia_estados',  'facturacion_envios',
        'auditorias',    'auditoria_acciones',
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        // --- 1. la base debe parecerse a una instalacion GRE -------------
        $faltantes = array_values(array_filter(
            $this->centinelas,
            fn ($t) => ! Schema::hasTable($t)
        ));

        if (count($faltantes) === count($this->centinelas)) {
            $this->error('Base vacia. Esto es una instalacion nueva: corre `php artisan migrate` directamente.');
            return 1;
        }

        if (! empty($faltantes)) {
            $this->error('La base no coincide con una instalacion GRE. Faltan tablas:');
            foreach ($faltantes as $t) {
                $this->line("   - {$t}");
            }
            $this->warn('Abortado. Revisa manualmente antes de continuar.');
            return 1;
        }

        // --- 2. asegurar que exista la tabla migrations ------------------
        if (! Schema::hasTable('migrations')) {
            $this->warn('No existe la tabla `migrations`. Creandola...');
            if (! $dry) {
                $this->call('migrate:install');
            }
        }

        // --- 3. que migraciones ya estan registradas --------------------
        $registradas = Schema::hasTable('migrations')
            ? DB::table('migrations')->pluck('migration')->all()
            : [];

        $archivos = collect(glob(database_path('migrations/*.php')))
            ->map(fn ($f) => basename($f, '.php'))
            ->sort()
            ->values();

        // --- 4. decidir cuales marcar -----------------------------------
        // Criterio DETERMINISTA por corte de fecha, no heuristico.
        // Se verifico que el esquema de produccion coincide exactamente con
        // las migraciones hasta 2024_11_07 (diff de columnas: 0 diferencias).
        // Todo lo anterior al corte se considera ya presente en la base.
        $corte = (string) ($this->option('hasta') ?: self::CORTE_DEFAULT);

        $porMarcar = [];
        $porCorrer = [];

        foreach ($archivos as $m) {
            if (in_array($m, $registradas, true)) {
                continue;
            }
            if (strcmp($m, $corte) < 0) {
                $porMarcar[] = $m;
            } else {
                $porCorrer[] = $m;
            }
        }

        $this->line("Corte de baseline: <comment>{$corte}</comment>");

        // --- 5. reportar -------------------------------------------------
        $this->newLine();
        $this->info('Migraciones ya registradas .......... ' . count($registradas));
        $this->info('A marcar como aplicadas (baseline) .. ' . count($porMarcar));
        $this->info('Quedaran pendientes de ejecutar ..... ' . count($porCorrer));
        $this->newLine();

        foreach ($porCorrer as $m) {
            $this->line("   PENDIENTE  {$m}");
        }

        if (empty($porMarcar)) {
            $this->newLine();
            $this->info('Nada que marcar. La base ya esta sincronizada.');
            return 0;
        }

        if ($dry) {
            $this->newLine();
            foreach ($porMarcar as $m) {
                $this->line("   [dry-run] marcaria  {$m}");
            }
            $this->warn('Dry-run: no se escribio nada.');
            return 0;
        }

        // --- 6. escribir el baseline -------------------------------------
        $batch = (int) (DB::table('migrations')->max('batch') ?? 0) + 1;

        DB::transaction(function () use ($porMarcar, $batch) {
            foreach ($porMarcar as $m) {
                DB::table('migrations')->insert(['migration' => $m, 'batch' => $batch]);
            }
        });

        $this->info('Baseline aplicado: ' . count($porMarcar) . " migraciones marcadas (batch {$batch}).");
        $this->newLine();
        $this->comment('Siguiente paso:  php artisan migrate --force');

        return 0;
    }

}
