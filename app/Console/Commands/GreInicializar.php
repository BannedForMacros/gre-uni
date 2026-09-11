<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\AuditoriaAccionSeeder;
use Database\Seeders\GuiaEstadoSeeder;
use Database\Seeders\ParametroSeeder;
use Database\Seeders\PerfilSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Datos base de una instalacion, y el primer administrador.
 *
 * PROBLEMA
 *   DatabaseSeeder solo siembra parametros y estados. Sin perfiles ni
 *   acciones de auditoria la primera guia falla, y sin un usuario nadie puede
 *   entrar: el tecnico tenia que crearlo a mano en la base. Ademas Perfil,
 *   GuiaEstado y AuditoriaAccion usan create(): correrlos dos veces revienta
 *   por clave duplicada, asi que no se podian meter en un instalador que se
 *   ejecuta mas de una vez.
 *
 * QUE HACE
 *   - Parametros: siempre (su seeder ya es idempotente).
 *   - Perfiles, estados y acciones: solo si la tabla esta vacia.
 *   - Administrador: solo si no existe ningun usuario. La clave se imprime una
 *     vez como ADMIN_CLAVE=... para que el instalador la entregue.
 *
 * Idempotente: correrlo dos veces no duplica filas ni cambia la clave.
 */
class GreInicializar extends Command
{
    protected $signature = 'gre:inicializar
                            {--usuario=admin : Usuario del primer administrador}
                            {--clave= : Clave del administrador (si no se indica, se genera)}';

    protected $description = 'Siembra los datos base que falten y crea el primer administrador (idempotente)';

    /** Tabla => seeder que la llena. Solo se siembran si estan vacias. */
    private const CATALOGOS = [
        'perfiles'           => PerfilSeeder::class,
        'guia_estados'       => GuiaEstadoSeeder::class,
        'auditoria_acciones' => AuditoriaAccionSeeder::class,
    ];

    public function handle(): int
    {
        $this->call('db:seed', ['--class' => ParametroSeeder::class, '--force' => true]);

        foreach (self::CATALOGOS as $tabla => $seeder) {
            if (DB::table($tabla)->count() > 0) {
                $this->line("{$tabla}: ya tiene datos, no se toca");
                continue;
            }
            $this->call('db:seed', ['--class' => $seeder, '--force' => true]);
            $this->info("{$tabla}: sembrada");
        }

        if (User::count() > 0) {
            $this->line('usuarios: ya existen, no se crea administrador');
            return 0;
        }

        $clave = $this->option('clave') ?: Str::random(14);

        $admin = new User();
        $admin->name      = 'Administrador';
        $admin->username  = $this->option('usuario');
        $admin->email     = null;
        $admin->password  = Hash::make($clave);
        $admin->perfil_id = 1;
        $admin->save();

        $this->info("ADMIN_USUARIO={$admin->username}");
        $this->info("ADMIN_CLAVE={$clave}");

        return 0;
    }
}
