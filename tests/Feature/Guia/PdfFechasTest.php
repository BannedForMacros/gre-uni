<?php

namespace Tests\Feature\Guia;

use App\Models\GuiaIngreso;
use App\Models\GuiaSalida;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * El PDF imprimia la fecha de HOY en vez de la de la guia.
 *
 * Las plantillas leian $documento->fecha_hora_emision, un atributo que NO
 * existe: las columnas son fecha_emision y hora_emision, por separado. Eloquent
 * devuelve null para un atributo desconocido y Carbon::parse(null) da la fecha
 * actual, asi que una guia del 3 de septiembre reimpresa en diciembre salia
 * fechada en diciembre. En un documento con valor legal, y sin ningun error a
 * la vista.
 *
 * No se comprueba el PDF ya pintado: eso obliga a fabricar las veinte
 * propiedades que la plantilla necesita y la prueba se rompe cada vez que se
 * toca el diseno. Se comprueba lo que causo el fallo: que toda fecha que el
 * PDF imprime salga de una columna que existe de verdad.
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
            $fuente = file_get_contents(resource_path('views/' . $plantilla));
            $columnas = Schema::getColumnListing((new $modelo)->getTable());

            // Los campos que la plantilla mete dentro de un Carbon::parse().
            preg_match_all('/parse\(\s*(.+?)\s*\)->format/s', $fuente, $expresiones);

            $this->assertNotEmpty($expresiones[1], "No se encontro ninguna fecha en {$plantilla}");

            foreach ($expresiones[1] as $expresion) {
                preg_match_all('/\$documento->([a-z_]+)/', $expresion, $campos);

                foreach ($campos[1] as $campo) {
                    $this->assertContains(
                        $campo,
                        $columnas,
                        "{$plantilla} imprime una fecha desde \$documento->{$campo}, "
                        . "que no es una columna de la tabla. Eloquent devuelve null y "
                        . "Carbon::parse(null) imprime la fecha de HOY."
                    );
                }
            }
        }
    }

    public function test_ninguna_plantilla_vuelve_a_usar_el_campo_que_no_existia(): void
    {
        foreach (array_keys(self::PLANTILLAS) as $plantilla) {
            $fuente = file_get_contents(resource_path('views/' . $plantilla));

            // Solo se busca fuera de los comentarios de Blade, que si lo nombran
            // para explicar por que desaparecio.
            $sinComentarios = preg_replace('/\{\{--.*?--\}\}/s', '', $fuente);

            $this->assertStringNotContainsString(
                'fecha_hora_emision',
                $sinComentarios,
                "{$plantilla} volvio a usar fecha_hora_emision, que no existe."
            );
        }
    }
}
