<?php

namespace Tests\Feature\Guia;

use App\Models\GuiaIngreso;
use App\Models\GuiaSalida;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Las fechas del PDF salen de la guia, no del dia en que se imprime.
 *
 * Las plantillas leian $documento->fecha_hora_emision, un atributo que NO
 * existe: las columnas son fecha_emision y hora_emision, por separado. Eloquent
 * devuelve null para un atributo desconocido y Carbon::parse(null) da la fecha
 * actual, asi que una guia del 10 de septiembre reimpresa en diciembre salia
 * fechada en diciembre. En un documento con valor legal, y sin ningun error a
 * la vista.
 *
 * No se comprueba el PDF ya pintado: eso obliga a fabricar las veinte
 * propiedades que la plantilla necesita y la prueba se rompe cada vez que se
 * toca el diseno. Se comprueba lo que causo el fallo: que toda fecha que el PDF
 * imprime salga de una columna que existe de verdad.
 */
class PdfFechasTest extends TestCase
{
    /** Plantilla => modelo cuyos campos pinta. */
    private const PLANTILLAS = [
        'guia/ingreso/pdf.blade.php' => GuiaIngreso::class,
        'guia/salida/pdf.blade.php'  => GuiaSalida::class,
        'guia/salida/pdf2.blade.php' => GuiaSalida::class,
    ];

    public function test_toda_fecha_del_pdf_sale_de_una_columna_que_existe(): void
    {
        foreach (self::PLANTILLAS as $plantilla => $modelo) {
            $fuente = $this->sinComentarios(file_get_contents(resource_path('views/' . $plantilla)));
            $columnas = Schema::getColumnListing((new $modelo)->getTable());

            preg_match_all('/parse\(\s*(.+?)\s*\)->format/s', $fuente, $expresiones);
            $this->assertNotEmpty($expresiones[1], "No se encontro ninguna fecha en {$plantilla}");

            foreach ($expresiones[1] as $expresion) {
                preg_match_all('/\$documento->([a-z_]+)/', $expresion, $campos);

                foreach ($campos[1] as $campo) {
                    $this->assertContains($campo, $columnas,
                        "{$plantilla} imprime una fecha desde \$documento->{$campo}, que no es una "
                        . "columna de la tabla. Eloquent devuelve null y Carbon::parse(null) imprime "
                        . "la fecha de HOY.");
                }
            }
        }
    }

    public function test_ninguna_plantilla_usa_el_campo_que_no_existe(): void
    {
        foreach (array_keys(self::PLANTILLAS) as $plantilla) {
            $fuente = $this->sinComentarios(file_get_contents(resource_path('views/' . $plantilla)));

            $this->assertStringNotContainsString('fecha_hora_emision', $fuente,
                "{$plantilla} volvio a usar fecha_hora_emision, que no existe.");
        }
    }

    public function test_el_logo_del_pdf_sale_de_la_configuracion_de_cada_empresa(): void
    {
        foreach (array_keys(self::PLANTILLAS) as $plantilla) {
            $fuente = $this->sinComentarios(file_get_contents(resource_path('views/' . $plantilla)));

            // Con la ruta fija, una instalacion que no suba su logo imprimiria
            // las guias con la marca de otro cliente.
            $this->assertStringNotContainsString("url('img/logo.png')", $fuente,
                "{$plantilla} volvio a apuntar al logo fijo.");

            $this->assertStringContainsString('Empresa::logoPath', $fuente,
                "{$plantilla} deberia pedir el logo a la configuracion de la empresa.");
        }
    }

    /** Los comentarios de Blade nombran los campos para explicar por que se fueron. */
    private function sinComentarios(string $fuente): string
    {
        return preg_replace('/\{\{--.*?--\}\}/s', '', $fuente);
    }
}
