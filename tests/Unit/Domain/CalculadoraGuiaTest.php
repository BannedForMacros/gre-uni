<?php

namespace Tests\Unit\Domain;

use App\Domain\Guia\Services\CalculadoraGuia;
use App\Domain\Guia\ValueObjects\LineaGuia;
use App\Domain\Shared\ValueObjects\Igv;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CalculadoraGuiaTest extends TestCase
{
    private function calc(float $tasa = 0.18): CalculadoraGuia
    {
        return new CalculadoraGuia(new Igv($tasa));
    }

    /** @test */
    public function calcula_los_totales_de_una_guia_simple(): void
    {
        $t = $this->calc()->totales([
            new LineaGuia(1, '40978', 'ART A', 2, 50.00, 9),
            new LineaGuia(2, '40011', 'ART B', 1, 10.00, 9),
        ]);

        $this->assertSame(110.00, $t->valorVenta());
        $this->assertSame(19.80,  $t->igv());
        $this->assertSame(129.80, $t->total());
    }

    /** @test */
    public function los_articulos_inafectos_no_pagan_igv(): void
    {
        $t = $this->calc()->totales([
            new LineaGuia(1, '40978', 'GRAVADO',  1, 100.00, 9, true),
            new LineaGuia(2, '40011', 'INAFECTO', 1, 100.00, 9, false),
        ]);

        $this->assertSame(200.00, $t->valorVenta());
        $this->assertSame(18.00,  $t->igv(), 'solo la linea gravada paga IGV');
        $this->assertSame(100.00, $t->inafecto());
    }

    /** @test */
    public function aplica_el_descuento_de_linea_antes_del_igv(): void
    {
        $t = $this->calc()->totales([
            new LineaGuia(1, '40978', 'ART', 2, 50.00, 9, true, 10.00),
        ]);

        $this->assertSame(90.00, $t->valorVenta());
        $this->assertSame(16.20, $t->igv());
    }

    /** @test */
    public function cambiar_la_tasa_no_requiere_tocar_codigo(): void
    {
        $linea = [new LineaGuia(1, '40978', 'ART', 1, 100.00, 9)];

        $this->assertSame(18.00, $this->calc(0.18)->totales($linea)->igv());
        $this->assertSame(10.00, $this->calc(0.10)->totales($linea)->igv());
        $this->assertSame(0.0,   $this->calc(0.00)->totales($linea)->igv());
    }

    /** @test */
    public function rechaza_cantidad_cero_que_es_lo_que_revienta_el_stored_procedure(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La cantidad debe ser mayor que cero');

        new LineaGuia(1, '40978', 'ART', 0, 50.00, 9);
    }

    /** @test */
    public function rechaza_item_no_correlativo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new LineaGuia(0, '40978', 'ART', 1, 50.00, 9);
    }

    /** @test */
    public function quitar_igv_es_la_inversa_de_agregarlo(): void
    {
        $igv = new Igv(0.18);
        $this->assertSame(118.00, $igv->agregarA(100.00));
        $this->assertSame(100.00, $igv->quitarDe(118.00));
    }

    /** @test */
    public function rechaza_una_tasa_de_igv_imposible(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Igv(1.5);
    }
}
