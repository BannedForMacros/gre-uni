<?php

namespace App\Domain\Guia\ValueObjects;

use App\Domain\Shared\ValueObjects\Igv;
use InvalidArgumentException;

/**
 * Una linea del detalle de la guia.
 *
 * Invariantes que el codigo viejo no garantizaba:
 *   - cantidad > 0        (el SP del ERP dividia entre cero y tumbaba la guia)
 *   - precio  >= 0
 *   - item correlativo    (antes se mandaba "item" => 1 en TODAS las lineas)
 *   - el precio SIEMPRE se guarda sin IGV, en un solo formato
 */
final class LineaGuia
{
    private $item;
    private $codArticulo;
    private $descripcion;
    private $cantidad;
    private $precioSinIgv;
    private $descuento;
    private $unidadMedida;
    private $afectoIgv;
    private $esConsignado;

    public function __construct(
        int $item,
        string $codArticulo,
        string $descripcion,
        float $cantidad,
        float $precioSinIgv,
        int $unidadMedida,
        bool $afectoIgv = true,
        float $descuento = 0.0,
        bool $esConsignado = false
    ) {
        if ($item < 1) {
            throw new InvalidArgumentException("El item debe ser correlativo desde 1, se recibio {$item}.");
        }
        if (trim($codArticulo) === '') {
            throw new InvalidArgumentException('El codigo de articulo no puede estar vacio.');
        }
        if ($cantidad <= 0) {
            throw new InvalidArgumentException(
                "La cantidad debe ser mayor que cero (articulo {$codArticulo}, cantidad {$cantidad})."
            );
        }
        if ($precioSinIgv < 0) {
            throw new InvalidArgumentException("El precio no puede ser negativo (articulo {$codArticulo}).");
        }
        if ($descuento < 0) {
            throw new InvalidArgumentException("El descuento no puede ser negativo (articulo {$codArticulo}).");
        }

        $this->item         = $item;
        $this->codArticulo  = trim($codArticulo);
        $this->descripcion  = $descripcion;
        $this->cantidad     = $cantidad;
        $this->precioSinIgv = $precioSinIgv;
        $this->unidadMedida = $unidadMedida;
        $this->afectoIgv    = $afectoIgv;
        $this->descuento    = $descuento;
        $this->esConsignado = $esConsignado;
    }

    public function item(): int             { return $this->item; }
    public function codArticulo(): string   { return $this->codArticulo; }
    public function descripcion(): string   { return $this->descripcion; }
    public function cantidad(): float       { return $this->cantidad; }
    public function precioSinIgv(): float   { return $this->precioSinIgv; }
    public function unidadMedida(): int     { return $this->unidadMedida; }
    public function afectoIgv(): bool       { return $this->afectoIgv; }
    public function descuento(): float      { return $this->descuento; }
    public function esConsignado(): bool    { return $this->esConsignado; }

    /** Base imponible de la linea, ya con el descuento aplicado. */
    public function valorVenta(): float
    {
        return round(($this->cantidad * $this->precioSinIgv) - $this->descuento, 2);
    }

    /** IGV de la linea. Cero si el articulo es inafecto o exonerado. */
    public function igv(Igv $igv): float
    {
        return $this->afectoIgv ? $igv->sobre($this->valorVenta()) : 0.0;
    }

    public function total(Igv $igv): float
    {
        return round($this->valorVenta() + $this->igv($igv), 2);
    }
}
