<?php

declare(strict_types=1);

namespace App\Domain\Documento;

use App\Domain\Documento\Models\DocumentoLectura;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Models\User;

/**
 * Quién ha acusado recibo de una versión y a quién le falta.
 *
 * **No almacena nada**: se calcula al vuelo y se enseña, igual que
 * `CoberturaSalvaguardas`. Guardar una lista de destinatarios congelada
 * obligaría a mantenerla al día cada vez que alguien entra o sale, y una lista
 * desactualizada dice que falta por leer alguien que ya no está.
 *
 * **Los destinatarios son todos los usuarios de la organización**, y eso es una
 * simplificación declarada, no un descuido: quien tiene que conocer la política
 * son las **personas** de la organización, y el módulo de personas (§ 4.8) no
 * existe todavía. Cuando llegue, esto pasa a leer de ahí sin que el acuse en sí
 * cambie. Hasta entonces el documento lo dice por escrito en sus limitaciones,
 * que es la diferencia entre una limitación declarada y una inventada.
 *
 * **`User` no lleva el scope de organización** —no usa `PerteneceAOrganizacion`,
 * porque la autenticación tiene que poder encontrar a alguien antes de saber de
 * qué organización es—, así que aquí se acota a mano. La organización sale de la
 * versión y no del contexto: bajo RLS es la misma, y así la consulta no depende
 * de que alguien haya fijado el contexto antes.
 */
final class CoberturaAcuse
{
    /**
     * @return array{
     *     exigido: bool,
     *     total: int,
     *     acusados: int,
     *     pendientes: list<string>,
     *     lectores: list<array{nombre: string, fecha: string}>,
     * }
     */
    public function de(DocumentoVersion $version): array
    {
        $exigido = $version->documento->exigeAcuse() && $version->estaAprobada();

        $destinatarios = User::query()
            ->where('organizacion_id', $version->organizacion_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $lecturas = $version->lecturas()->with('usuario:id,name')->get();

        $hanAcusado = $lecturas->pluck('user_id')->all();

        return [
            'exigido' => $exigido,
            'total' => $destinatarios->count(),
            'acusados' => $lecturas->count(),

            'pendientes' => $destinatarios
                ->reject(static fn (User $usuario): bool => in_array($usuario->id, $hanAcusado, true))
                ->map(static fn (User $usuario): string => (string) $usuario->name)
                ->values()
                ->all(),

            'lectores' => $lecturas
                ->sortBy('acusada_en')
                // `usuario` siempre está: la clave foránea borra en cascada, así
                // que una lectura no sobrevive a la baja de quien la firmó.
                ->map(static fn (DocumentoLectura $lectura): array => [
                    'nombre' => (string) $lectura->usuario->name,
                    'fecha' => $lectura->acusada_en->format('d/m/Y'),
                ])
                ->values()
                ->all(),
        ];
    }

    /** Si ya lo ha acusado quien está mirando la pantalla. */
    public function loHaAcusado(DocumentoVersion $version, User $lector): bool
    {
        return $version->lecturas()->where('user_id', $lector->id)->exists();
    }
}
