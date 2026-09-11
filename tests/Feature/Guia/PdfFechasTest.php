<?php

namespace Tests\Feature\Guia;

use App\Models\GuiaIngreso;
use App\Models\GuiaSalida;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * DEFECTO CONOCIDO Y ACEPTADO: el PDF imprime la fecha de HOY.
 *
 * Las plantillas leen $documento->fecha_hora_emision, un atributo que NO
 * existe: las columnas son fecha_emision y hora_emision, por separado. Eloquent
 * devuelve null para un atributo desconocido y Carbon::parse(null) da la fecha
 * actual, asi que una guia del 10 de septiembre reimpresa en diciembre sale
 * fechada en diciembre. Comprobado sobre la guia 1-1916: emitida el 2026-09-10,
 * el PDF ponia 2026-09-11.
 *
 * Se corrigio y se volvio a dejar como estaba POR DECISION EXPRESA: el PDF
 * tiene que salir exactamente igual que en la version anterior, de la que estos
 * archivos son copia byte a byte.
 *
 * Esta prueba NO comprueba que el comportamiento sea correcto -no lo es-. Deja
 * escrito cual es, para que:
 *   - nadie lo descubra otra vez desde cero,
 *   - y si alguien lo arregla, esta prueba falle y lea este comentario antes de
 *     tocarlo, en vez de creer que rompio algo.
 *
 * Para arreglarlo, en las tres plantillas:
 *   $documento->fecha_hora_emision
 *   -> trim($documento->fecha_emision . ' ' . $documento->hora_emision)
 * y en "Fecha Inicio" de guia de salida:
 *   -> $documento->fecha_inicio_traslado ?? $documento->fecha_emision
 */
class PdfFechasTest extends TestCase
{
    /** Plantilla => modelo cuyos campos pinta. */
    private const PLANTILLAS = [
        'guia/ingreso/pdf.blade.php' => GuiaIngreso::class,
        'guia/salida/pdf.blade.php'  => GuiaSalida::class,
        'guia/salida/pdf2.blade.php' => GuiaSalida::class,
    ];

    public function test_fecha_hora_emision_no_es_una_columna_de_ninguna_de_las_dos_tablas(): void
    {
        foreach (array_unique(array_values(self::PLANTILLAS)) as $modelo) {
            $tabla = (new $modelo)->getTable();

            $this->assertNotContains('fecha_hora_emision', Schema::getColumnListing($tabla),
                "Si {$tabla} ya tiene la columna fecha_hora_emision, el defecto descrito en "
                . "esta clase dejo de existir y hay que revisar estas pruebas.");

            $this->assertContains('fecha_emision', Schema::getColumnListing($tabla));
            $this->assertContains('hora_emision', Schema::getColumnListing($tabla));
        }
    }

    public function test_las_plantillas_siguen_igual_que_en_la_version_anterior(): void
    {
        foreach (array_keys(self::PLANTILLAS) as $plantilla) {
            $fuente = file_get_contents(resource_path('views/' . $plantilla));

            // Se deja constancia de que el campo inexistente sigue ahi a
            // proposito. Si alguien lo cambia, esta prueba falla y le manda a
            // leer el porque antes de dar por bueno el cambio.
            $this->assertStringContainsString('fecha_hora_emision', $fuente,
                "{$plantilla} ya no usa fecha_hora_emision. Si es un arreglo deliberado, "
                . "actualiza esta prueba; ver el comentario de la clase.");
        }
    }
}
