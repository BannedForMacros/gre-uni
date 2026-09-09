<?php

namespace App\Support;

use App\Models\Parametro;
use Illuminate\Support\Facades\Cache;

/**
 * Configuracion de la empresa, editable desde pantalla.
 *
 * POR QUE EXISTE
 *   Antes cada opcion vivia en un sitio distinto:
 *     - consignados  -> flag CONSIGNADOS_ENABLED en el .env  (requiere acceso
 *                       al servidor y reiniciar; el cliente no puede tocarlo)
 *     - logo         -> tabla parametros
 *     - datos        -> tabla parametros, por ID numerico
 *
 *   Y el flag de consignados solo protegia Guia de Ingreso: en Guia de Salida
 *   el check aparecia SIEMPRE, incluso en clientes que no usan consignados.
 *
 *   Ahora todo se lee de la tabla `parametros` por NOMBRE (no por id, que era
 *   fragil) y se administra desde Configuracion > Empresa.
 *
 * CACHE
 *   Se cachea para no consultar en cada render. Cualquier escritura desde la
 *   pantalla invalida la cache.
 */
class ConfiguracionEmpresa
{
    private const CACHE_KEY = 'gre.configuracion.empresa';
    private const CACHE_TTL = 3600;

    /** Claves gestionadas desde la pantalla, con su valor por defecto. */
    private const DEFAULTS = [
        'usa_consignados' => '0',
        'logo_empresa'    => '',
    ];

    public static function todo(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $valores = self::DEFAULTS;

            $filas = Parametro::whereIn('nombre', array_keys(self::DEFAULTS))->get();
            foreach ($filas as $fila) {
                $valores[$fila->nombre] = (string) $fila->valor;
            }

            return $valores;
        });
    }

    public static function get(string $clave, string $default = ''): string
    {
        $todo = self::todo();
        return $todo[$clave] ?? $default;
    }

    /**
     * ¿Esta empresa usa productos consignados?
     *
     * Si esta en false, la funcionalidad queda 100% inerte:
     *   - no se muestra el check en Ingreso ni en Salida
     *   - no se guarda es_consignado en el detalle
     *   - no se envia esConsignado a la ApiGRE
     * El cliente no necesita las columnas en su SQL Server.
     */
    public static function usaConsignados(): bool
    {
        return self::get('usa_consignados', '0') === '1';
    }

    /**
     * Escribe un parametro por nombre.
     *
     * OJO: la tabla `parametros` NO es auto_increment, hay que asignar el id
     * a mano al crear una fila nueva.
     */
    public static function set(string $clave, string $valor): void
    {
        $param = Parametro::where('nombre', $clave)->first();

        if (! $param) {
            $param = new Parametro();
            $param->id     = (int) Parametro::max('id') + 1;
            $param->nombre = $clave;
        }

        // El modelo Parametro esta protegido contra asignacion masiva:
        // los atributos se asignan uno a uno a proposito.
        $param->valor  = $valor;
        $param->activo = 1;
        $param->save();

        self::olvidar();
    }

    public static function olvidar(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
