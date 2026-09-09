<?php

namespace Database\Seeders;

use App\Models\Parametro;
use Illuminate\Database\Seeder;

class ParametroSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $list = array(
            0 => array('id' => '1', 'nombre' => 'credencial', 'valor' => env('GRE_FACTURACION_CREDENCIAL', '')),
            1 => array('id' => '2', 'nombre' => 'ruc_entiedad', 'valor' => env('GRE_RUC', '')),
            2 => array('id' => '3', 'nombre' => 'razon_social_entidad', 'valor' => 'Franco Supermercado E.I.R.L.'),
            3 => array('id' => '4', 'nombre' => 'direccion_entiedad', 'valor' => env('GRE_DIRECCION', '')),
            4 => array('id' => '5', 'nombre' => 'telefonos', 'valor' => '-'),
            5 => array('id' => '6', 'nombre' => 'api_datos', 'valor' => env('GRE_API_URL', 'http://localhost:8181/api/v1')),
            6 => array('id' => '7', 'nombre' => 'api_facturacion', 'valor' => env('GRE_FACTURACION_URL', '')),
            7 => array('id' => '8', 'nombre' => 'api_facturacion_consultas', 'valor' => env('GRE_FACTURACION_CONSULTAS_URL', '')),
        );

        foreach ($list as $item) {
            Parametro::create($item);
        }
    }
}
