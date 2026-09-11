<?php

namespace Tests\Feature\Guia;

use App\Models\GuiaIngreso;
use App\Models\GuiaIngresoDetalle;
use App\Models\GuiaSalida;
use App\Models\GuiaSalidaDetalle;
use App\Models\Parametro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Que la ruta del PDF genere un PDF de verdad.
 *
 * Esto existe por un fallo concreto: la plantilla de ingreso se rehizo copiando
 * la de salida, que numera las lineas con un $nro que SU controlador define. El
 * de ingreso no lo define, asi que la pantalla moria con "Undefined variable
 * $nro". Y no se vio al revisarla porque se comprobo pintando la vista con un
 * array de datos hecho a mano, que traia ese $nro: la plantilla se verifico
 * contra datos que el controlador nunca le pasa.
 *
 * De ahi la regla de esta clase: se pide la RUTA, no la vista. Lo unico que
 * demuestra que una pantalla funciona es pedirsela al mismo sitio que se la
 * pide el usuario.
 */
class PdfRutaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([2 => '20518623339', 3 => 'GZ IMPORT SAC', 4 => 'CAL.2 DE MAYO 505', 5 => '555-1234', 6 => 'http://apigre.local/GREDMK', 10 => 'false'] as $id => $valor) {
            $p = new Parametro();
            $p->id = $id; $p->nombre = 'p' . $id; $p->valor = $valor; $p->activo = 1;
            $p->save();
        }

        Http::fake(['*' => Http::response([])]);
    }

    private function usuario(): User
    {
        return User::factory()->create();
    }

    private function ingresoConDetalle(): GuiaIngreso
    {
        $g = new GuiaIngreso();
        $g->serie = '1'; $g->numero = 6001;
        $g->fecha_emision = '2026-03-04'; $g->hora_emision = '11:22:33';
        $g->guia_estado_id = 1; $g->activo = 1;
        $g->total_venta = 45.49; $g->monto_igv = 6.94;
        $g->importe_sin_igv = 38.55; $g->monto_descuento = 0;
        $g->proveedor_nombre = 'DISTRIBUIDORA ANDINA SAC'; $g->proveedor_ruc = '20445566778';
        $g->tipo_operacion_nombre = 'INGRESO POR COMPRA'; $g->almacen_nombre = 'LIVINGSTONE';
        $g->forma_pago_nombre = 'EFECTIVO'; $g->vendedor_nombre = 'demo';
        $g->save();

        // DOS lineas a proposito: con una sola, un contador roto puede pasar
        // desapercibido.
        foreach ([[34297, 3, 23.13], [34298, 2, 15.42]] as $i => [$cod, $cantidad, $importe]) {
            $d = new GuiaIngresoDetalle();
            $d->guia_ingreso_id = $g->id;
            $d->codarticulo = $cod; $d->descripcion = "ARTICULO {$cod}";
            $d->cantidad = $cantidad; $d->precio = 7.71; $d->importe = $importe;
            $d->porcentaje_descuento = 0; $d->monto_descuento = 0;
            $d->precio_publico = 9.10; $d->precio_sin_igv = 7.71;
            $d->costo_articulo = 5; $d->costo_total = 10;
            $d->desc_unidad_medida = 'UND'; $d->codigo_barra = '84365673500' . $i;
            $d->save();
        }

        return $g;
    }

    private function salidaConDetalle(): GuiaSalida
    {
        $g = new GuiaSalida();
        $g->serie = '1'; $g->numero = 6002;
        $g->fecha_emision = '2026-03-04'; $g->hora_emision = '11:22:33';
        $g->fecha_inicio_traslado = '2026-03-06';
        $g->guia_estado_id = 1; $g->activo = 1;
        $g->total_venta = 45.49; $g->monto_igv = 6.94;
        $g->importe_sin_igv = 38.55; $g->monto_descuento = 0;
        $g->cliente_razon_social = 'DISTRIBUIDORA ANDINA SAC';
        $g->cliente_nro_documento = '20445566778';
        $g->indicar_proveedor = 0;
        $g->peso_bruto_total = 12.96;
        $g->save();

        foreach ([[34297, 3, 23.13], [34298, 2, 15.42]] as $i => [$cod, $cantidad, $importe]) {
            $d = new GuiaSalidaDetalle();
            $d->guia_salida_id = $g->id;
            $d->codarticulo = $cod; $d->descripcion = "ARTICULO {$cod}";
            $d->cantidad = $cantidad; $d->precio = 7.71; $d->importe = $importe;
            $d->porcentaje_descuento = 0; $d->monto_descuento = 0;
            $d->precio_publico = 9.10; $d->precio_sin_igv = 7.71;
            $d->costo_articulo = 5; $d->costo_total = 10;
            $d->desc_unidad_medida = 'UND'; $d->codigo_barra = '84365673500' . $i;
            $d->save();
        }

        return $g;
    }

    /** Un PDF de verdad empieza por %PDF y no viene vacio. */
    private function esUnPdf($respuesta): void
    {
        $respuesta->assertOk();

        $contenido = $respuesta->getContent();

        $this->assertStringStartsWith('%PDF', $contenido, 'La respuesta no es un PDF');
        $this->assertGreaterThan(1000, strlen($contenido), 'El PDF salio sospechosamente vacio');
    }

    // -----------------------------------------------------------------

    public function test_el_pdf_de_una_guia_de_ingreso_se_genera(): void
    {
        $g = $this->ingresoConDetalle();

        $this->esUnPdf($this->actingAs($this->usuario())->get("/guiaingreso/pdf/{$g->id}/0"));
    }

    public function test_el_pdf_valorado_de_una_guia_de_ingreso_se_genera(): void
    {
        // La version valorada anade columnas: es otra rama de la plantilla.
        $g = $this->ingresoConDetalle();

        $this->esUnPdf($this->actingAs($this->usuario())->get("/guiaingreso/pdf/{$g->id}/1"));
    }

    public function test_el_pdf_de_una_guia_de_salida_se_genera(): void
    {
        $g = $this->salidaConDetalle();

        $this->esUnPdf($this->actingAs($this->usuario())->get("/guiasalida/pdf/{$g->id}/0"));
    }

    public function test_el_pdf_valorado_de_una_guia_de_salida_se_genera(): void
    {
        $g = $this->salidaConDetalle();

        $this->esUnPdf($this->actingAs($this->usuario())->get("/guiasalida/pdf/{$g->id}/1"));
    }

    public function test_una_guia_sin_lineas_tampoco_revienta(): void
    {
        // El listado deja pedir el PDF de cualquier fila, y hay guias antiguas
        // sin detalle -las que nacieron cuando la cabecera se escribia antes
        // que las lineas-.
        $g = new GuiaIngreso();
        $g->serie = '1'; $g->numero = 6003;
        $g->fecha_emision = '2026-03-04'; $g->hora_emision = '11:22:33';
        $g->guia_estado_id = 1; $g->activo = 1;
        $g->total_venta = 0; $g->monto_igv = 0; $g->importe_sin_igv = 0; $g->monto_descuento = 0;
        $g->save();

        $this->esUnPdf($this->actingAs($this->usuario())->get("/guiaingreso/pdf/{$g->id}/0"));
    }
}
