<?php

namespace App\Domain\Guia\Services;

final class TotalesGuia
{
    private $valorVenta;
    private $igv;
    private $total;
    private $inafecto;
    private $tasaAplicada;

    public function __construct(float $valorVenta, float $igv, float $total, float $inafecto, float $tasaAplicada)
    {
        $this->valorVenta   = $valorVenta;
        $this->igv          = $igv;
        $this->total        = $total;
        $this->inafecto     = $inafecto;
        $this->tasaAplicada = $tasaAplicada;
    }

    public function valorVenta(): float   { return $this->valorVenta; }
    public function igv(): float          { return $this->igv; }
    public function total(): float        { return $this->total; }
    public function inafecto(): float     { return $this->inafecto; }
    public function tasaAplicada(): float { return $this->tasaAplicada; }

    public function toArray(): array
    {
        return [
            'valor_venta'    => $this->valorVenta,
            'monto_igv'      => $this->igv,
            'total_venta'    => $this->total,
            'inafecto'       => $this->inafecto,
            'tasa_aplicada'  => $this->tasaAplicada,
        ];
    }
}
