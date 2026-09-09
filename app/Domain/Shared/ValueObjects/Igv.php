<?php

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

/**
 * La tasa de IGV. UNA sola fuente de verdad.
 *
 * Antes el 18% estaba escrito a mano en 14 lugares distintos, repartidos en
 * cuatro capas que no se hablaban entre si:
 *
 *   GuiaIngresoController:248     round($costo_sin_igv * 1.18, 2)
 *   GuiaIngresoController:1040    round($precioBase * 1.18, 4)
 *   GuiaSalidaController:860      $igv = 0.18;
 *   GuiaSalidaController:555      ($item->costoArticulo * (1+0.18))
 *   salida/create.js:377          importe_con_descuento / 1.18
 *   ingreso/create.js:302         round(base_afecta_num * 0.18, 2)
 *   ingreso/create.blade:336      round($item->precio / 1.18, 2)
 *   ...y el propio stored procedure del ERP
 *
 * Cambiar la tasa obligaba a tocar PHP, JavaScript, Blade y T-SQL, y bastaba
 * olvidar uno para que los totales dejaran de cuadrar.
 *
 * Ahora se configura en config/gre.php y vive aqui.
 */
final class Igv
{
    /** @var float Ej: 0.18 */
    private $tasa;

    public function __construct(float $tasa)
    {
        if ($tasa < 0 || $tasa > 1) {
            throw new InvalidArgumentException(
                "Tasa de IGV invalida: {$tasa}. Se espera una fraccion, por ejemplo 0.18."
            );
        }
        $this->tasa = $tasa;
    }

    public static function vigente(): self
    {
        return new self((float) config('gre.igv.tasa', 0.18));
    }

    public static function exonerado(): self
    {
        return new self(0.0);
    }

    public function tasa(): float
    {
        return $this->tasa;
    }

    /** Porcentaje para mostrar: 18.0 */
    public function porcentaje(): float
    {
        return round($this->tasa * 100, 2);
    }

    /** Monto de IGV sobre una base que NO lo incluye. */
    public function sobre(float $baseSinIgv, int $decimales = 2): float
    {
        return round($baseSinIgv * $this->tasa, $decimales);
    }

    /** Agrega IGV a una base que no lo incluye. */
    public function agregarA(float $baseSinIgv, int $decimales = 2): float
    {
        return round($baseSinIgv * (1 + $this->tasa), $decimales);
    }

    /**
     * Quita el IGV de un precio que ya lo incluye.
     * Es la operacion que el codigo viejo escribia como `/ 1.18` en cinco sitios.
     */
    public function quitarDe(float $montoConIgv, int $decimales = 2): float
    {
        if ($this->tasa === 0.0) {
            return round($montoConIgv, $decimales);
        }
        return round($montoConIgv / (1 + $this->tasa), $decimales);
    }
}
