<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Cuantos resultados trae un buscador de catalogo -articulos, clientes,
 * proveedores, transportistas- y si quedaron mas.
 *
 * Antes cada buscador exigia dos o tres letras antes de consultar: al abrirlo
 * no mostraba nada y el usuario no sabia si funcionaba. Se exigia porque el
 * DataMart devuelve TODO lo que coincide y eso viajaba entero hasta el
 * navegador. Medido en el DataMart de pruebas (11 sep 2026):
 *
 *   articulos por descripcion, sin texto   6.681 filas   ~0,4 s
 *   clientes por nombre, sin texto        12.199 filas   ~0,6 s
 *   articulos, "226ERS"                       13 filas   ~0,2 s
 *
 * El procedimiento es rapido aun sin texto; lo caro era el transporte. Asi que
 * se consulta desde la primera letra -o sin texto al abrir la lista- y se
 * corta aqui.
 */
final class BusquedaCatalogo
{
    /** Suficiente para ver las variantes de una familia (EP 226ERS tiene 13). */
    public const LIMITE = 20;

    public const LIMITE_MAXIMO = 50;

    public static function limite(Request $request): int
    {
        $pedido = (int) $request->input('limite', self::LIMITE);

        return max(1, min(self::LIMITE_MAXIMO, $pedido > 0 ? $pedido : self::LIMITE));
    }

    /**
     * A la ApiGRE se le pide uno de mas: si llega, hay mas resultados que los
     * mostrados y la lista lo dice. Y se recorta tambien aqui, porque una
     * ApiGRE anterior ignora el limite y devuelve todo.
     *
     * @return array{0: array, 1: bool} [resultados recortados, hayMas]
     */
    public static function recortar(array $items, int $limite): array
    {
        return [array_slice($items, 0, $limite), count($items) > $limite];
    }
}
