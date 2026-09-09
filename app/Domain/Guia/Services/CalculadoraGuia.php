<?php

namespace App\Domain\Guia\Services;

use App\Domain\Guia\ValueObjects\LineaGuia;
use App\Domain\Shared\ValueObjects\Igv;

/**
 * Los totales de la guia. Calculados en UN solo lugar.
 *
 * Antes se calculaban tres veces sobre los mismos datos:
 *   1. en PHP, al armar el detalle
 *   2. otra vez en JavaScript, para pintar la pantalla
 *   3. y el stored procedure del ERP los volvia a tocar
 * Tres fuentes de verdad para el mismo numero, que se desincronizaban.
 */
final class CalculadoraGuia
{
    /** @var Igv */
    private $igv;

    public function __construct(Igv $igv)
    {
        $this->igv = $igv;
    }

    /** @param LineaGuia[] $lineas */
    public function totales(array $lineas, float $descuentoGlobal = 0.0): TotalesGuia
    {
        $valorVenta = 0.0;
        $montoIgv   = 0.0;
        $inafecto   = 0.0;

        foreach ($lineas as $linea) {
            $valorVenta += $linea->valorVenta();
            $montoIgv   += $linea->igv($this->igv);
            if (! $linea->afectoIgv()) {
                $inafecto += $linea->valorVenta();
            }
        }

        $valorVenta = round($valorVenta - $descuentoGlobal, 2);
        $montoIgv   = round($montoIgv, 2);

        return new TotalesGuia(
            $valorVenta,
            $montoIgv,
            round($valorVenta + $montoIgv, 2),
            round($inafecto, 2),
            $this->igv->tasa()
        );
    }
}
