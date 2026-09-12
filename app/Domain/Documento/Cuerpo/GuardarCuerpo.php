<?php

declare(strict_types=1);

namespace App\Domain\Documento\Cuerpo;

use App\Domain\Documento\Models\DocumentoCuerpo;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Guarda lo que alguien ha escrito en el editor.
 *
 * Hace tres cosas, y las tres importan:
 *
 * 1. **Sanea** contra el esquema. La ruta se puede llamar sin pasar por el
 *    editor.
 * 2. **Recalcula la procedencia** de cada bloque calculado comparándolo con
 *    `generado`, que es la línea base que produjo Statera. El cliente no decide
 *    si algo está editado: se comprueba. Un editor que se declarara «no editado»
 *    a sí mismo dejaría el documento mintiendo en su propia portada.
 * 3. **Sella `editado_en`**, que es lo que hace que el documento entregado
 *    declare que se ha tocado a mano.
 */
final readonly class GuardarCuerpo
{
    public function __construct(private SanearCuerpo $sanear) {}

    /**
     * @param  array<string, mixed>  $cuerpo  lo que llega del editor
     * @return bool si se guardó
     */
    public function __invoke(DocumentoCuerpo $fila, array $cuerpo, ?User $autor = null): bool
    {
        $saneado = ($this->sanear)($cuerpo);

        if ($saneado === null) {
            return false;
        }

        $marcado = $this->marcarProcedencia($saneado, $this->bloquesGenerados($fila->generado));

        return DB::transaction(function () use ($fila, $marcado, $autor): bool {
            $fila->fill([
                'cuerpo' => $marcado,
                'editado_en' => now(),
                'editado_por_id' => $autor?->id,
            ]);

            return $fila->save();
        });
    }

    /**
     * Los bloques calculados de la línea base, indexados por su generador.
     *
     * @param  array<string, mixed>  $generado
     * @return array<string, string> fuente => huella del contenido original
     */
    private function bloquesGenerados(array $generado): array
    {
        $bloques = [];

        $this->recorrer($generado, static function (array $nodo, string $fuente) use (&$bloques): void {
            $bloques[$fuente] = self::huella($nodo);
        });

        return $bloques;
    }

    /**
     * Marca `editado` en los bloques que ya no son los que Statera generó.
     *
     * @param  array<string, mixed>  $nodo
     * @param  array<string, string>  $base
     * @return array<string, mixed>
     */
    private function marcarProcedencia(array $nodo, array $base): array
    {
        $fuente = $nodo['attrs']['fuente'] ?? null;

        if (is_string($fuente) && EsquemaCuerpo::esFuente($fuente)) {
            $original = $base[$fuente] ?? null;
            $editado = $original !== null && $original !== self::huella($nodo);

            if ($editado) {
                $nodo['attrs']['editado'] = true;
            } else {
                unset($nodo['attrs']['editado']);
            }

            if ($nodo['attrs'] === []) {
                unset($nodo['attrs']);
            }

            return $nodo;
        }

        $hijos = $nodo['content'] ?? [];

        if (! is_array($hijos) || $hijos === []) {
            return $nodo;
        }

        $nodo['content'] = array_values(array_map(
            fn (mixed $hijo): mixed => is_array($hijo) ? $this->marcarProcedencia($hijo, $base) : $hijo,
            $hijos,
        ));

        return $nodo;
    }

    /**
     * Los bloques calculados de un cuerpo, uno por uno.
     *
     * @param  array<string, mixed>  $nodo
     * @param  callable(array<string, mixed>, string): void  $hacer
     */
    private function recorrer(array $nodo, callable $hacer): void
    {
        $fuente = $nodo['attrs']['fuente'] ?? null;

        if (is_string($fuente) && EsquemaCuerpo::esFuente($fuente)) {
            $hacer($nodo, $fuente);

            return;
        }

        $hijos = $nodo['content'] ?? [];

        if (! is_array($hijos)) {
            return;
        }

        foreach ($hijos as $hijo) {
            if (is_array($hijo)) {
                $this->recorrer($hijo, $hacer);
            }
        }
    }

    /**
     * La huella del contenido de un bloque, sin su procedencia.
     *
     * Se quitan `editado` y `huella` antes de mirar: si entraran, un bloque
     * marcado como editado saldría distinto de sí mismo y nunca podría volver a
     * estar al día.
     *
     * **Y se ordenan las claves**, que es lo que costó encontrar. `jsonb` de
     * PostgreSQL **no conserva el orden de las claves** —las guarda ordenadas
     * por longitud y luego por bytes—, así que la línea base vuelve de la base
     * con `{"text":…,"type":…}` mientras el editor manda `{"type":…,"text":…}`.
     * Sin normalizar, dos bloques idénticos dan huellas distintas y el primer
     * guardado marcaría **todos** los apartados calculados como editados a mano,
     * en un documento donde no se ha tocado nada. El documento entregado lo
     * diría en sus limitaciones, y sería mentira.
     *
     * @param  array<string, mixed>  $nodo
     */
    public static function huella(array $nodo): string
    {
        return hash('sha256', (string) json_encode(self::canonico(self::sinProcedencia($nodo))));
    }

    /**
     * El mismo contenido con las claves en un orden estable.
     *
     * Las listas conservan su orden —ahí el orden **es** contenido: cambiar dos
     * párrafos de sitio cambia el documento—; los objetos se ordenan.
     *
     * @param  array<array-key, mixed>  $valor
     * @return array<array-key, mixed>
     */
    private static function canonico(array $valor): array
    {
        $esLista = array_is_list($valor);

        $canonico = array_map(
            static fn (mixed $hijo): mixed => is_array($hijo) ? self::canonico($hijo) : $hijo,
            $valor,
        );

        if (! $esLista) {
            ksort($canonico);
        }

        return $canonico;
    }

    /**
     * @param  array<string, mixed>  $nodo
     * @return array<string, mixed>
     */
    private static function sinProcedencia(array $nodo): array
    {
        unset($nodo['attrs']['editado'], $nodo['attrs']['huella']);

        if (($nodo['attrs'] ?? null) === []) {
            unset($nodo['attrs']);
        }

        $hijos = $nodo['content'] ?? [];

        if (is_array($hijos) && $hijos !== []) {
            $nodo['content'] = array_values(array_map(
                static fn (mixed $hijo): mixed => is_array($hijo) ? self::sinProcedencia($hijo) : $hijo,
                $hijos,
            ));
        }

        return $nodo;
    }

    /**
     * Qué bloques calculados están tocados a mano, por su nombre de generador.
     *
     * Es lo que se imprime en las limitaciones del documento: decir «se ha
     * editado» sin decir **qué** no le sirve de nada a quien lo audita.
     *
     * @param  array<string, mixed>  $cuerpo
     * @return list<string>
     */
    public static function bloquesEditados(array $cuerpo): array
    {
        $editados = [];

        $recorrer = static function (array $nodo) use (&$recorrer, &$editados): void {
            $fuente = $nodo['attrs']['fuente'] ?? null;

            if (is_string($fuente) && EsquemaCuerpo::esFuente($fuente)) {
                if (($nodo['attrs']['editado'] ?? false) === true) {
                    $editados[] = $fuente;
                }

                return;
            }

            $hijos = $nodo['content'] ?? [];

            if (is_array($hijos)) {
                foreach ($hijos as $hijo) {
                    if (is_array($hijo)) {
                        $recorrer($hijo);
                    }
                }
            }
        };

        $recorrer($cuerpo);

        return $editados;
    }
}
