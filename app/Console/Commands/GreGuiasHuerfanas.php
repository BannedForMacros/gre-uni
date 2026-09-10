<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encuentra (y opcionalmente borra) las guias que quedaron sin detalle.
 *
 * PROBLEMA
 *   El store() viejo escribia la cabecera y el correlativo de la serie y
 *   DESPUES recorria el detalle, todo sin transaccion. Si una linea reventaba a
 *   mitad, la cabecera ya estaba grabada: quedaba una guia con su numero y su
 *   importe pero con CERO lineas. En el listado no se distingue de una buena, y
 *   el DataMart la rechaza mas tarde con "Documento incompleto".
 *
 *   El store() de hoy ya envuelve todo en una transaccion, pero eso solo evita
 *   guias nuevas: las que ya se escribieron siguen ahi. En las instalaciones que
 *   llevan anos con el codigo viejo nadie sabe cuantas hay ni cuales son.
 *
 * POR QUE UN COMANDO Y NO UNA MIGRACION
 *   Una migracion borraria filas sola, en el deploy de un cliente, sin que nadie
 *   mire lo que se va. Aqui la primera pasada solo informa; borrar exige pedirlo
 *   y confirmarlo a mano.
 *
 * LAS DE AVANCE NO CUENTAN
 *   Una guia en estado Avance es un borrador a medio llenar: no tener lineas
 *   todavia es lo normal, para eso existe. Si entraran en la lista, la primera
 *   purga de un cliente se llevaria por delante el trabajo sin terminar de sus
 *   usuarios.
 *
 * USO
 *   php artisan gre:guias-huerfanas                    (solo informa)
 *   php artisan gre:guias-huerfanas --tipo=salida
 *   php artisan gre:guias-huerfanas --purgar           (borra, preguntando antes)
 */
class GreGuiasHuerfanas extends Command
{
    protected $signature = 'gre:guias-huerfanas
                            {--tipo= : Limita a "ingreso" o "salida" (por defecto, las dos)}
                            {--purgar : Borra las cabeceras encontradas, tras confirmar}';

    protected $description = 'Lista las guias sin lineas de detalle y, si se pide, las borra';

    /**
     * Estado de borrador. No se da por supuesto: la fila 4 de guia_estados se
     * llama "Avance", y es el valor que ponen los dos controladores cuando el
     * usuario pulsa guardar avance ($datos['guia_estado_id'] = 4).
     */
    private const ESTADO_AVANCE = 4;

    /** [tabla de cabecera, tabla de detalle, columna que las une]. */
    private const TABLAS = [
        'ingreso' => ['guia_ingresos', 'guia_ingreso_detalles', 'guia_ingreso_id'],
        'salida'  => ['guia_salidas',  'guia_salida_detalles',  'guia_salida_id'],
    ];

    public function handle(): int
    {
        $tipos = $this->tiposPedidos();

        if ($tipos === null) {
            return 1;
        }

        $huerfanas = [];

        foreach ($tipos as $tipo) {
            list($cabecera, $detalle, $llave) = self::TABLAS[$tipo];

            if (! Schema::hasTable($cabecera) || ! Schema::hasTable($detalle)) {
                $this->error("Falta la tabla {$cabecera} o {$detalle}. Esta base no parece una instalacion GRE.");

                return 1;
            }

            foreach ($this->buscar($cabecera, $detalle, $llave) as $fila) {
                $fila->tipo = $tipo;
                $huerfanas[] = $fila;
            }
        }

        if (empty($huerfanas)) {
            $this->info('No hay guias huerfanas. Todas las guias tienen al menos una linea de detalle.');
            $this->line('(Las guias en estado Avance quedan fuera a proposito: son borradores.)');

            return 0;
        }

        $this->pintarTabla($huerfanas);

        if (! $this->option('purgar')) {
            $this->newLine();
            $this->comment('Solo se ha informado; no se borro nada. Para borrarlas: php artisan gre:guias-huerfanas --purgar');

            return 0;
        }

        return $this->purgar($huerfanas);
    }

    /**
     * @return string[]|null  null si el --tipo no es valido
     */
    private function tiposPedidos()
    {
        $tipo = (string) ($this->option('tipo') ?: '');

        if ($tipo === '') {
            return array_keys(self::TABLAS);
        }

        $tipo = strtolower($tipo);

        if (! isset(self::TABLAS[$tipo])) {
            $this->error("Tipo \"{$tipo}\" desconocido. Usa --tipo=ingreso o --tipo=salida, o no pases --tipo para ver las dos.");

            return null;
        }

        return [$tipo];
    }

    /**
     * Cabeceras sin una sola linea de detalle.
     *
     * NOT EXISTS y no un LEFT JOIN con IS NULL porque el join multiplicaria las
     * filas si algun dia el detalle deja de ser 1:N limpio, y aqui se cuentan
     * cabeceras, no lineas.
     *
     * El estado se compara con IS NULL delante porque en bases viejas hay
     * cabeceras con guia_estado_id nulo: en SQL, `guia_estado_id <> 4` las
     * descartaria en silencio, y esas tambien son huerfanas.
     */
    private function buscar(string $cabecera, string $detalle, string $llave)
    {
        return DB::table($cabecera)
            ->select(
                'id', 'serie', 'numero', 'fecha_emision', 'total_venta',
                'guia_estado_id', 'enviado_datamarket', 'activo'
            )
            ->whereNotExists(function ($q) use ($detalle, $cabecera, $llave) {
                $q->select(DB::raw(1))
                    ->from($detalle)
                    ->whereColumn($detalle . '.' . $llave, $cabecera . '.id');
            })
            ->where(function ($q) {
                $q->whereNull('guia_estado_id')
                    ->orWhere('guia_estado_id', '<>', self::ESTADO_AVANCE);
            })
            ->orderBy('serie')
            ->orderBy('numero')
            ->get();
    }

    private function pintarTabla(array $huerfanas): void
    {
        $estados = Schema::hasTable('guia_estados')
            ? DB::table('guia_estados')->pluck('nombre', 'id')
            : collect();

        $filas = [];

        foreach ($huerfanas as $g) {
            $filas[] = [
                $g->tipo,
                $g->serie . '-' . $g->numero,
                $g->fecha_emision,
                // Un importe nulo no se pinta como 0.00: que la cabecera se
                // grabara sin total es parte de lo que hay que ver aqui.
                $g->total_venta === null ? '(nulo)' : number_format((float) $g->total_venta, 2, '.', ''),
                $this->nombreEstado($estados, $g->guia_estado_id),
                ((int) $g->enviado_datamarket === 1) ? 'SI' : 'no',
                ((int) $g->activo === 1) ? 'si' : 'NO',
                $g->id,
            ];
        }

        $this->newLine();
        $this->table(
            ['Tipo', 'Documento', 'Fecha', 'Importe', 'Estado', 'DataMart', 'Activa', 'Id'],
            $filas
        );

        $this->warn('Guias sin ninguna linea de detalle: ' . count($huerfanas));
    }

    /**
     * Nombre del estado tal cual lo vera el operador.
     *
     * En bases viejas aparecen estados nulos o ids que ya no estan en
     * guia_estados; se muestran como lo que son en vez de dejar la celda vacia,
     * porque un estado desconocido es en si mismo una pista de que paso.
     */
    private function nombreEstado($estados, $id): string
    {
        if ($id === null) {
            return '(sin estado)';
        }

        return $estados[$id] ?? ('desconocido #' . $id);
    }

    /**
     * Borra las cabeceras listadas.
     *
     * Se borra por id y no por un WHERE que repita el criterio: entre el listado
     * y la confirmacion puede haber entrado una guia nueva, y el operador dijo
     * que si a lo que vio en pantalla, no a lo que haya ahora.
     *
     * No se tocan las filas de auditoria de esas guias: son el registro de lo
     * que paso y siguen siendo la unica pista de por que existieron.
     */
    private function purgar(array $huerfanas): int
    {
        $this->newLine();
        $this->warn('Se van a BORRAR ' . count($huerfanas) . ' cabeceras de guia. La operacion no se puede deshacer.');

        $enviadas = 0;
        foreach ($huerfanas as $g) {
            if ((int) $g->enviado_datamarket === 1) {
                $enviadas++;
            }
        }

        if ($enviadas > 0) {
            $cuantas = $enviadas === 1 ? '1 de ellas figura' : "{$enviadas} de ellas figuran";
            $this->warn("Atencion: {$cuantas} como enviada al DataMart. Borrarlas aqui no las quita de alla.");
        }

        if (! $this->confirm('Confirmas el borrado?', false)) {
            $this->info('Cancelado. No se borro nada.');

            return 0;
        }

        $porTipo = [];
        foreach ($huerfanas as $g) {
            $porTipo[$g->tipo][] = $g->id;
        }

        $borradas = 0;

        DB::transaction(function () use ($porTipo, &$borradas) {
            foreach ($porTipo as $tipo => $ids) {
                list($cabecera) = self::TABLAS[$tipo];
                $borradas += DB::table($cabecera)->whereIn('id', $ids)->delete();
            }
        });

        $this->newLine();
        $this->info("Borradas {$borradas} guias huerfanas.");

        return 0;
    }
}
