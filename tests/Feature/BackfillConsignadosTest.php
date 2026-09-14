<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La migracion 2026_09_09_100100 agrega es_consignado a las CABECERAS y, acto
 * seguido, rellena el valor mirando las lineas: si alguna linea de la guia es
 * consignada, la cabecera tambien lo es.
 *
 * POR QUE EXISTE ESTA PRUEBA
 *
 * Ese relleno se escribio primero como:
 *
 *     UPDATE cabeceras c SET c.es_consignado = 1
 *      WHERE EXISTS (SELECT 1 FROM detalles d
 *                     WHERE d.fk = c.id AND d.es_consignado = 1)
 *
 * Funciona, pero obliga a recorrer los detalles UNA VEZ POR CABECERA, y las
 * tablas de detalle de los clientes no tienen indice sobre la clave foranea:
 * solo la primaria. Medido sobre el respaldo real de un cliente, con 18.272
 * cabeceras y 301.424 detalles: 27 minutos y 40 segundos. Reescrito como un
 * JOIN contra la lista de guias consignadas, que recorre los detalles una sola
 * vez: 537 milisegundos sobre esos mismos datos.
 *
 * Lo que fija esta prueba es que la version rapida marca EXACTAMENTE las
 * mismas guias que la lenta: ni una de mas, ni una de menos. Si alguien vuelve
 * a tocar esa consulta, esto lo sujeta.
 */
class BackfillConsignadosTest extends TestCase
{
    // Aqui si conviene: esta prueba solo inserta filas, no toca el esquema, asi
    // que la transaccion revierte limpio y cada caso parte de una base vacia.
    use RefreshDatabase;

    private function migracion()
    {
        return require database_path('migrations/2026_09_09_100100_add_es_consignado_to_cabeceras.php');
    }

    private function detalle(string $tabla, string $fk, int $cabecera, int $consignado): void
    {
        // Cada tabla de detalle tiene SOLO su propia clave foranea.
        DB::table($tabla)->insert([
            $fk                     => $cabecera,
            'codarticulo'           => 'ART-1',
            'descripcion'           => 'Articulo de prueba',
            'precio'                => 10,
            'cantidad'              => 1,
            'importe'               => 10,
            'porcentaje_descuento'  => 0,
            'monto_descuento'       => 0,
            'es_consignado'         => $consignado,
        ]);
    }

    /** @test */
    public function marca_la_cabecera_cuando_alguna_linea_es_consignada(): void
    {
        $conConsignado = DB::table('guia_salidas')->insertGetId(['es_consignado' => 0]);
        $sinConsignado = DB::table('guia_salidas')->insertGetId(['es_consignado' => 0]);

        // La primera guia lleva dos lineas y solo una es consignada: basta con esa.
        $this->detalle('guia_salida_detalles', 'guia_salida_id', $conConsignado, 0);
        $this->detalle('guia_salida_detalles', 'guia_salida_id', $conConsignado, 1);
        $this->detalle('guia_salida_detalles', 'guia_salida_id', $sinConsignado, 0);

        $this->migracion()->up();

        $this->assertSame(1, (int) DB::table('guia_salidas')->where('id', $conConsignado)->value('es_consignado'),
            'La guia con una linea consignada debia quedar marcada');
        $this->assertSame(0, (int) DB::table('guia_salidas')->where('id', $sinConsignado)->value('es_consignado'),
            'La guia sin lineas consignadas NO debia tocarse');
    }

    /** @test */
    public function hace_lo_mismo_con_las_guias_de_ingreso(): void
    {
        $conConsignado = DB::table('guia_ingresos')->insertGetId(['es_consignado' => 0]);
        $sinConsignado = DB::table('guia_ingresos')->insertGetId(['es_consignado' => 0]);

        $this->detalle('guia_ingreso_detalles', 'guia_ingreso_id', $conConsignado, 1);
        $this->detalle('guia_ingreso_detalles', 'guia_ingreso_id', $sinConsignado, 0);

        $this->migracion()->up();

        $this->assertSame(1, (int) DB::table('guia_ingresos')->where('id', $conConsignado)->value('es_consignado'));
        $this->assertSame(0, (int) DB::table('guia_ingresos')->where('id', $sinConsignado)->value('es_consignado'));
    }

    /** @test */
    public function marca_exactamente_las_mismas_guias_que_la_consulta_lenta(): void
    {
        // Varias guias, unas consignadas y otras no, mezcladas.
        $esperadas = [];
        for ($i = 0; $i < 12; $i++) {
            $consignada = ($i % 3 === 0);
            $id = DB::table('guia_salidas')->insertGetId(['es_consignado' => 0]);
            $this->detalle('guia_salida_detalles', 'guia_salida_id', $id, $consignada ? 1 : 0);
            if ($consignada) {
                $esperadas[] = $id;
            }
        }

        // La consulta LENTA, la original, sobre los mismos datos.
        DB::statement("
            UPDATE guia_salidas c SET c.es_consignado = 1
             WHERE EXISTS (SELECT 1 FROM guia_salida_detalles d
                            WHERE d.guia_salida_id = c.id AND d.es_consignado = 1)
        ");
        $conLaLenta = DB::table('guia_salidas')->where('es_consignado', 1)->orderBy('id')->pluck('id')->all();

        // Se deshace y se aplica la migracion, que lleva la consulta RAPIDA.
        DB::table('guia_salidas')->update(['es_consignado' => 0]);
        $this->migracion()->up();
        $conLaRapida = DB::table('guia_salidas')->where('es_consignado', 1)->orderBy('id')->pluck('id')->all();

        $this->assertSame($conLaLenta, $conLaRapida, 'Las dos consultas deben marcar las mismas guias');
        $this->assertSame($esperadas, $conLaRapida, 'Y deben ser exactamente las guias con linea consignada');
    }

    /** @test */
    public function es_idempotente(): void
    {
        $id = DB::table('guia_salidas')->insertGetId(['es_consignado' => 0]);
        $this->detalle('guia_salida_detalles', 'guia_salida_id', $id, 1);

        $this->migracion()->up();
        $this->migracion()->up();

        $this->assertSame(1, (int) DB::table('guia_salidas')->where('id', $id)->value('es_consignado'));
        $this->assertSame(1, DB::table('guia_salidas')->where('es_consignado', 1)->count());
    }
}
