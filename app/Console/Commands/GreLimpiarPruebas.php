<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Borra de la base de la aplicacion las guias que dejo una prueba de punta a
 * punta.
 *
 * PROBLEMA
 *   instalador/pruebas/punta-a-punta.php recorre el sistema como lo haria un
 *   usuario y, para poder recorrerlo de verdad, GUARDA GUIAS DE VERDAD:
 *   consume el correlativo de la serie, escribe cabecera y detalle, pide el
 *   PDF y las manda al DataMart. Para poder reconocerlas despues les pone
 *   siempre el mismo comentario:
 *
 *       PRUEBA E2E - NO VALIDA
 *
 *   Esas guias quedan en el listado mezcladas con las buenas. Nadie las
 *   distingue de un vistazo, suman en los totales del dia, y el que las ve no
 *   sabe si puede borrarlas. Hasta ahora la limpieza se hacia a mano, con
 *   DELETEs sueltos escritos en el momento: que es exactamente la forma de
 *   llevarse por delante una guia buena.
 *
 * QUE HACE
 *   Busca las cabeceras de ingreso y de salida cuyo `comentario` sea la marca,
 *   y borra en este orden:
 *      1. las lineas de detalle de esas guias,
 *      2. sus envios de facturacion (facturacion_envios),
 *      3. sus filas de auditoria (salvo --conservar-auditoria),
 *      4. las cabeceras.
 *
 *   Ese orden es el de las claves foraneas: primero lo que cuelga, al final la
 *   cabecera. Al reves quedarian detalles apuntando a una guia que ya no
 *   existe, que es justo lo que `gre:guias-huerfanas` viene a denunciar.
 *
 *   Se hace por ids y no con un WHERE que repita el criterio en cada tabla:
 *   las tablas relacionadas se localizan por el id de la guia que ya vimos en
 *   pantalla, no por su comentario, que ellas ni siquiera tienen.
 *
 * SEGURIDAD
 *   - Por defecto es un SIMULACRO. Sin --ejecutar no se escribe una sola fila:
 *     el comando lista lo que se llevaria y termina. Si se pasan --ejecutar y
 *     --dry-run a la vez, gana el simulacro.
 *   - Compara por IGUALDAD, nunca con LIKE. Un comentario que solo CONTENGA la
 *     marca ("PRUEBA E2E - NO VALIDA, revisada por Juan") lo escribio una
 *     persona sobre una guia que le importaba, y no se toca.
 *   - Solo trabaja sobre la conexion por defecto de la aplicacion (MySQL).
 *     NUNCA abre el SQL Server del ERP: esa base no es nuestra, tiene las
 *     guias de todo el negocio y alli la limpieza se hace aparte y mirando.
 *     Si la conexion por defecto no es MySQL, el comando se niega a correr.
 *   - Se niega con una marca vacia o demasiado corta: un criterio flojo aqui
 *     barre guias buenas.
 *   - Todo el borrado va dentro de una transaccion: o se va el conjunto entero
 *     o no se va nada.
 *   - Antes de borrar pide confirmacion, salvo --forzar.
 *   - Al terminar vuelve a contar y avisa si quedo algo marcado o algun
 *     detalle huerfano.
 *
 * USO
 *   php artisan gre:limpiar-pruebas                      (simulacro: solo informa)
 *   php artisan gre:limpiar-pruebas --ejecutar           (borra, preguntando antes)
 *   php artisan gre:limpiar-pruebas --ejecutar --forzar  (sin preguntar)
 *   php artisan gre:limpiar-pruebas --marca="OTRA MARCA" --ejecutar
 */
class GreLimpiarPruebas extends Command
{
    protected $signature = 'gre:limpiar-pruebas
                            {--marca= : Comentario exacto que marca una guia de prueba (default: la del E2E)}
                            {--ejecutar : Borra de verdad. Sin esta opcion el comando es un simulacro}
                            {--dry-run : Simulacro explicito. Ya es el comportamiento por defecto}
                            {--forzar : No pregunta antes de borrar (instalador, CI)}
                            {--conservar-auditoria : Deja las filas de auditorias de esas guias}';

    protected $description = 'Borra las guias marcadas por la prueba de punta a punta (por defecto solo informa)';

    /**
     * La marca que escribe instalador/pruebas/punta-a-punta.php en el campo
     * `comentario` de las dos guias que crea. Esta copiada literalmente de la
     * constante MARCA de aquel archivo: si alla cambia, aqui hay que cambiarla
     * (o pasar --marca).
     */
    private const MARCA_E2E = 'PRUEBA E2E - NO VALIDA';

    /**
     * Largo minimo de una marca aceptable. No es un numero magico con
     * pretensiones: es el corte por debajo del cual un criterio deja de
     * identificar una prueba y empieza a coincidir con lo que un usuario
     * escribio de verdad ("ok", "urgente", "prueba").
     */
    private const LARGO_MINIMO_MARCA = 8;

    /**
     * Todo lo que cuelga de cada tipo de guia.
     *
     *   cabecera    tabla de la guia
     *   detalle     sus lineas
     *   llave       columna del detalle que apunta a la cabecera
     *   auditoria   valores de auditorias.tabla que usan los controladores para
     *               ESTE tipo de guia. Van explicitos y separados por tipo
     *               porque auditorias.registro_id guarda el id de la guia, y el
     *               id 7 de un ingreso y el id 7 de una salida son dos guias
     *               distintas: sin filtrar tambien por `tabla` se borraria la
     *               auditoria de la otra.
     *   facturacion valores de facturacion_envios.tabla del mismo tipo
     */
    private const TABLAS = [
        'ingreso' => [
            'cabecera'    => 'guia_ingresos',
            'detalle'     => 'guia_ingreso_detalles',
            'llave'       => 'guia_ingreso_id',
            'auditoria'   => ['guia_ingresos', 'guia_ingresos_datamart'],
            'facturacion' => ['guia_ingresos'],
        ],
        'salida' => [
            'cabecera'    => 'guia_salidas',
            'detalle'     => 'guia_salida_detalles',
            'llave'       => 'guia_salida_id',
            // 'facturacion_envios' aparece como valor de auditorias.tabla
            // porque el controlador de salida audita asi el envio a la
            // facturacion, y guarda ahi el id de la GUIA, no el del envio.
            'auditoria'   => ['guia_salidas', 'guia_salidas_datamart', 'facturacion_envios'],
            'facturacion' => ['guia_salidas'],
        ],
    ];

    public function handle(): int
    {
        if (! $this->baseEsLaDeLaAplicacion()) {
            return 1;
        }

        $marca = $this->marcaPedida();

        if ($marca === null) {
            return 1;
        }

        if (! $this->tablasPresentes()) {
            return 1;
        }

        // --- 1. que hay marcado -----------------------------------------
        $plan = $this->planDeLimpieza($marca);

        if ($plan['guias'] === 0) {
            $this->info('No hay guias de prueba en esta base. Nada que limpiar.');
            $this->line("(Se buscaron cabeceras con comentario exactamente igual a \"{$marca}\".)");

            return 0;
        }

        $this->pintarGuias($plan);
        $this->pintarResumen($plan, $marca);

        // --- 2. simulacro por defecto -----------------------------------
        if (! $this->vaAEjecutar()) {
            $this->newLine();
            $this->comment('SIMULACRO: no se borro nada. Para borrar de verdad: php artisan gre:limpiar-pruebas --ejecutar');

            return 0;
        }

        // --- 3. confirmar ------------------------------------------------
        if (! $this->option('forzar')) {
            $this->newLine();
            $this->warn('Se van a BORRAR ' . $plan['guias'] . ' guias de prueba y todo lo que cuelga de ellas. No se puede deshacer.');

            if (! $this->confirm('Confirmas el borrado?', false)) {
                $this->info('Cancelado. No se borro nada.');

                return 0;
            }
        }

        // --- 4. borrar ----------------------------------------------------
        $borradas = $this->borrar($plan);

        $this->newLine();
        $this->info('Borrado terminado. Filas eliminadas por tabla:');
        foreach ($borradas as $tabla => $n) {
            $this->line(sprintf('   %-24s %d', $tabla, $n));
        }

        // --- 5. verificar --------------------------------------------------
        return $this->verificar($marca);
    }

    /**
     * Este comando solo limpia la base de la aplicacion.
     *
     * El SQL Server del ERP tambien se queda con las guias que la prueba le
     * mando, pero alli viven las guias de todo el negocio, la conexion es del
     * cliente y un DELETE nuestro no tiene por que entrar. Esa limpieza se hace
     * aparte, a mano y mirando las filas. Aqui se corta de raiz: si la conexion
     * por defecto no es MySQL, no se sigue.
     */
    private function baseEsLaDeLaAplicacion(): bool
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return true;
        }

        $this->error("La conexion por defecto es \"{$driver}\", no MySQL.");
        $this->warn('Este comando solo limpia la base de la aplicacion. El DataMart (SQL Server) se limpia aparte.');

        return false;
    }

    /**
     * @return string|null  null si la marca no sirve como criterio
     */
    private function marcaPedida()
    {
        $marca = trim((string) ($this->option('marca') ?: self::MARCA_E2E));

        if (mb_strlen($marca) < self::LARGO_MINIMO_MARCA) {
            $this->error('La marca "' . $marca . '" es demasiado corta (minimo ' . self::LARGO_MINIMO_MARCA . ' caracteres).');
            $this->warn('Una marca corta coincide con comentarios que escribio un usuario. Abortado.');

            return null;
        }

        return $marca;
    }

    private function tablasPresentes(): bool
    {
        $faltan = [];

        foreach (self::TABLAS as $t) {
            foreach ([$t['cabecera'], $t['detalle']] as $tabla) {
                if (! Schema::hasTable($tabla)) {
                    $faltan[] = $tabla;
                }
            }
        }

        if (! empty($faltan)) {
            $this->error('Faltan tablas: ' . implode(', ', $faltan) . '. Esta base no parece una instalacion GRE.');

            return false;
        }

        return true;
    }

    /**
     * Que se llevaria, contado tabla por tabla, ANTES de tocar nada.
     *
     * Se guardan los ids: a partir de aqui todo se localiza por id. Entre este
     * recuento y el borrado puede entrar una guia nueva, y el operador confirma
     * lo que vio en pantalla, no lo que haya en la base un segundo despues.
     */
    private function planDeLimpieza(string $marca): array
    {
        $plan = ['guias' => 0, 'tipos' => [], 'filas' => [], 'detalle_guias' => []];

        foreach (self::TABLAS as $tipo => $t) {
            $guias = DB::table($t['cabecera'])
                ->where('comentario', $marca)
                ->select('id', 'serie', 'numero', 'fecha_emision', 'total_venta', 'guia_estado_id', 'activo')
                ->orderBy('serie')
                ->orderBy('numero')
                ->get();

            $ids = $guias->pluck('id')->all();

            $plan['tipos'][$tipo] = $ids;
            $plan['guias'] += count($ids);

            foreach ($guias as $g) {
                $g->tipo = $tipo;
                $plan['detalle_guias'][] = $g;
            }

            $plan['filas'][$t['cabecera']] = count($ids);
            $plan['filas'][$t['detalle']] = empty($ids)
                ? 0
                : DB::table($t['detalle'])->whereIn($t['llave'], $ids)->count();
        }

        $plan['filas']['facturacion_envios'] = $this->contarFacturacion($plan['tipos']);
        $plan['filas']['auditorias'] = $this->contarAuditorias($plan['tipos']);

        return $plan;
    }

    private function contarFacturacion(array $tipos): int
    {
        if (! Schema::hasTable('facturacion_envios')) {
            return 0;
        }

        $n = 0;

        foreach ($tipos as $tipo => $ids) {
            if (empty($ids)) {
                continue;
            }

            $n += DB::table('facturacion_envios')
                ->whereIn('tabla', self::TABLAS[$tipo]['facturacion'])
                ->whereIn('registro_id', $ids)
                ->count();
        }

        return $n;
    }

    private function contarAuditorias(array $tipos): int
    {
        if (! Schema::hasTable('auditorias') || $this->option('conservar-auditoria')) {
            return 0;
        }

        $n = 0;

        foreach ($tipos as $tipo => $ids) {
            if (empty($ids)) {
                continue;
            }

            $n += DB::table('auditorias')
                ->whereIn('tabla', self::TABLAS[$tipo]['auditoria'])
                ->whereIn('registro_id', $this->comoTexto($ids))
                ->count();
        }

        return $n;
    }

    /**
     * auditorias.registro_id es varchar. Si se le pasan enteros, MySQL convierte
     * la COLUMNA a numero para comparar, lo que ademas de ser lento deja de usar
     * el indice. Se comparan textos con textos.
     *
     * @param  int[]  $ids
     * @return string[]
     */
    private function comoTexto(array $ids): array
    {
        return array_map('strval', $ids);
    }

    private function vaAEjecutar(): bool
    {
        // Si alguien pide las dos cosas, gana el simulacro: entre escribir y no
        // escribir ante una orden ambigua, no se escribe.
        return (bool) $this->option('ejecutar') && ! $this->option('dry-run');
    }

    private function pintarGuias(array $plan): void
    {
        $estados = Schema::hasTable('guia_estados')
            ? DB::table('guia_estados')->pluck('nombre', 'id')
            : collect();

        $filas = [];

        foreach ($plan['detalle_guias'] as $g) {
            $filas[] = [
                $g->tipo,
                $g->serie . '-' . $g->numero,
                $g->fecha_emision,
                $g->total_venta === null ? '(nulo)' : number_format((float) $g->total_venta, 2, '.', ''),
                $g->guia_estado_id === null ? '(sin estado)' : ($estados[$g->guia_estado_id] ?? ('desconocido #' . $g->guia_estado_id)),
                ((int) $g->activo === 1) ? 'si' : 'NO',
                $g->id,
            ];
        }

        $this->newLine();
        $this->table(['Tipo', 'Documento', 'Fecha', 'Importe', 'Estado', 'Activa', 'Id'], $filas);
    }

    private function pintarResumen(array $plan, string $marca): void
    {
        $this->warn('Guias de prueba encontradas: ' . $plan['guias'] . ' (comentario = "' . $marca . '")');
        $this->newLine();
        $this->line('Filas que se borrarian, por tabla:');

        foreach ($plan['filas'] as $tabla => $n) {
            $this->line(sprintf('   %-24s %d', $tabla, $n));
        }

        if ($this->option('conservar-auditoria')) {
            $this->line('   (auditorias: se conservan por --conservar-auditoria)');
        }

        // La proporcion importa: si las de prueba son casi todas las guias de
        // la base, o esto es un entorno de pruebas, o la marca esta mal puesta.
        $total = 0;
        foreach (self::TABLAS as $t) {
            $total += DB::table($t['cabecera'])->count();
        }

        if ($total > 0) {
            $this->newLine();
            $this->line(sprintf(
                'En esta base hay %d guias en total; %d (%.1f%%) estan marcadas como prueba.',
                $total,
                $plan['guias'],
                ($plan['guias'] / $total) * 100
            ));
        }
    }

    /**
     * El borrado, en el orden de las claves foraneas.
     *
     * @return array<string,int>  filas borradas por tabla
     */
    private function borrar(array $plan): array
    {
        $borradas = [];

        DB::transaction(function () use ($plan, &$borradas) {
            foreach (self::TABLAS as $tipo => $t) {
                $ids = $plan['tipos'][$tipo];

                if (empty($ids)) {
                    $borradas[$t['detalle']] = 0;
                    $borradas[$t['cabecera']] = 0;
                    continue;
                }

                // 1. las lineas
                $borradas[$t['detalle']] = DB::table($t['detalle'])->whereIn($t['llave'], $ids)->delete();

                // 2. los envios de facturacion de esas guias
                if (Schema::hasTable('facturacion_envios')) {
                    $borradas['facturacion_envios'] = ($borradas['facturacion_envios'] ?? 0)
                        + DB::table('facturacion_envios')
                            ->whereIn('tabla', $t['facturacion'])
                            ->whereIn('registro_id', $ids)
                            ->delete();
                }

                // 3. su rastro de auditoria, salvo que se pida conservarlo
                if (Schema::hasTable('auditorias') && ! $this->option('conservar-auditoria')) {
                    $borradas['auditorias'] = ($borradas['auditorias'] ?? 0)
                        + DB::table('auditorias')
                            ->whereIn('tabla', $t['auditoria'])
                            ->whereIn('registro_id', $this->comoTexto($ids))
                            ->delete();
                }

                // 4. y por ultimo la cabecera
                $borradas[$t['cabecera']] = DB::table($t['cabecera'])->whereIn('id', $ids)->delete();
            }
        });

        return $borradas;
    }

    /**
     * Despues de borrar: que no quede nada marcado y que no quede ningun
     * detalle apuntando a una cabecera que ya no existe.
     *
     * Se comprueba de verdad contra la base en vez de confiar en que el DELETE
     * hizo lo que decia: es la unica forma de que el operador se entere en el
     * momento, y no tres semanas despues por el DataMart.
     */
    private function verificar(string $marca): int
    {
        $problemas = [];

        foreach (self::TABLAS as $tipo => $t) {
            $quedan = DB::table($t['cabecera'])->where('comentario', $marca)->count();

            if ($quedan > 0) {
                $problemas[] = "{$t['cabecera']}: quedan {$quedan} guias con la marca";
            }

            $huerfanos = DB::table($t['detalle'])
                ->whereNotExists(function ($q) use ($t) {
                    $q->select(DB::raw(1))
                        ->from($t['cabecera'])
                        ->whereColumn($t['cabecera'] . '.id', $t['detalle'] . '.' . $t['llave']);
                })
                ->count();

            if ($huerfanos > 0) {
                $problemas[] = "{$t['detalle']}: {$huerfanos} lineas sin cabecera";
            }
        }

        $this->newLine();

        if (! empty($problemas)) {
            $this->error('La verificacion posterior encontro problemas:');
            foreach ($problemas as $p) {
                $this->line('   - ' . $p);
            }
            $this->warn('Revisa a mano antes de dar la limpieza por buena.');

            return 1;
        }

        $this->info('Verificado: no queda ninguna guia marcada ni ninguna linea sin cabecera.');

        return 0;
    }
}
