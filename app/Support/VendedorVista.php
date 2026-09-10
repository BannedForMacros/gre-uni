<?php

namespace App\Support;

/**
 * Da forma a los trabajadores que devuelve la ApiGRE para el selector de
 * vendedor.
 *
 * POR QUE EXISTE
 *   El mismo vendedor llega por dos caminos: la lista que pinta el servidor al
 *   abrir la pantalla y la que trae getVendedor() al buscar por codigo. Cada
 *   camino armaba su propia opcion y no coincidian: la inicial mostraba
 *   "[123] PEREZ JUAN" y la buscada solo "PEREZ JUAN", asi que la misma
 *   persona se veia distinta segun como hubiera aparecido en pantalla.
 */
class VendedorVista
{
    /**
     * @param  iterable|null  $trabajadores  respuesta de ObtenerTrabajador
     * @return array
     */
    public static function lista($trabajadores)
    {
        $vendedores = array();

        foreach (($trabajadores ?: []) as $item) {
            $nombre = trim("{$item->apellidos} {$item->nombres}");

            $vendedores[] = array(
                'codigo'   => $item->codTrabajador,
                'nombre'   => $nombre,
                'etiqueta' => "[{$item->codTrabajador}] {$nombre}",
            );
        }

        return $vendedores;
    }
}
