<?php

namespace Database\Seeders;

use App\Models\GuiaEstado;
use Illuminate\Database\Seeder;

class GuiaEstadoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $list = array(
            0 => array('id' => '1', 'nombre' => 'Generada'),
            1 => array('id' => '2', 'nombre' => 'Aceptada'),
            2 => array('id' => '3', 'nombre' => 'Rechazada'),
            3 => array('id' => '4', 'nombre' => 'Avance'),
        );

        foreach ($list as $item) {
            GuiaEstado::create($item);
        }
    }
}
