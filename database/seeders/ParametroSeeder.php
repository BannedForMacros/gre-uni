<?php

namespace Database\Seeders;

use App\Models\Parametro;
use Illuminate\Database\Seeder;

/**
 * Parametros de la instalacion.
 *
 * DOS COSAS QUE ESTABAN MAL
 *
 * 1. Sembraba los ids 1 al 8, pero el codigo usa hasta el 10:
 *      Parametro::find(9)->valor   -> GuiaSalidaController::index()
 *      Parametro::find(10)->valor  -> validar_stock
 *    En una instalacion limpia esos find() devolvian null y la pantalla moria
 *    con "Attempt to read property valor on null".
 *
 * 2. Traia la credencial de facturacion y el RUC de un cliente escritos en el
 *    archivo. Ahora salen del .env; despues se administran desde
 *    Configuraciones > Configuracion de Empresa.
 */
class ParametroSeeder extends Seeder
{
    public function run(): void
    {
        $parametros = [
            1  => ['credencial',                       env('GRE_FACTURACION_CREDENCIAL', '')],
            2  => ['ruc_entiedad',                     env('GRE_RUC', '')],
            3  => ['razon_social_entidad',             env('GRE_RAZON_SOCIAL', '')],
            4  => ['direccion_entiedad',               env('GRE_DIRECCION', '')],
            5  => ['telefonos',                        env('GRE_TELEFONOS', '-')],
            // La ruta se arma aqui, no se escribe en el .env: los
            // controladores llaman a {$api_datos}/InsertGuiaDMK y compania,
            // que viven bajo /GREDMK. Cuando esto salia directo del .env, una
            // instalacion limpia se quedaba apuntando a /api/v1 y no
            // funcionaba nada.
            6  => ['api_datos',                        config('gre.api.legacy')],
            7  => ['api_facturacion',                  env('GRE_FACTURACION_URL', '')],
            8  => ['api_facturacion_consultas',        env('GRE_FACTURACION_CONSULTAS_URL', '')],
            9  => ['api_facturacion_consultar_estado', env('GRE_FACTURACION_ESTADO_URL', '')],
            10 => ['validar_stock',                    env('GRE_VALIDAR_STOCK', 'false')],
        ];

        foreach ($parametros as $id => [$nombre, $valor]) {
            // La tabla no es auto_increment: el id se asigna a mano.
            // updateOrCreate para poder re-sembrar sin duplicar ni pisar lo que
            // el cliente ya configuro desde la pantalla.
            $p = Parametro::find($id);

            if (! $p) {
                $p = new Parametro();
                $p->id     = $id;
                $p->nombre = $nombre;
                $p->valor  = (string) $valor;
                $p->activo = 1;
                $p->save();
            }
        }
    }
}
