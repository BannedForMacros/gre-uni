<?php

namespace Database\Seeders;

use App\Models\AuditoriaAccion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AuditoriaAccionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // delete() y no truncate(): en MySQL TRUNCATE confirma en silencio la
        // transaccion abierta (rompia gre:inicializar dentro de las pruebas) y
        // ademas falla si alguna auditoria ya apunta a estas acciones.
        DB::table('auditoria_acciones')->delete();

        $list = array(
            0 => array('id' => '1', 'nombre' => 'insertar'),
            1 => array('id' => '2', 'nombre' => 'actualizar'),
            2 => array('id' => '3', 'nombre' => 'anular'),
            3 => array('id' => '4', 'nombre' => 'eliminar'),
        );

        foreach ($list as $item) {
            AuditoriaAccion::create($item);
        }
    }
}
