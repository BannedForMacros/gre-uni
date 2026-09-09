<?php

namespace Database\Seeders;

use App\Models\Perfil;
use Illuminate\Database\Seeder;

class PerfilSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $list = array(
            0 => array('id' => '1', 'nombre' => 'Administrador'),
            1 => array('id' => '2', 'nombre' => 'Operario'),
        );

        foreach ($list as $item) {
            Perfil::create($item);
        }
    }
}
