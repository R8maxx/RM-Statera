<?php

declare(strict_types=1);

namespace App\Domain\Incidente;

use App\Domain\Incidente\Enums\EstadoIncidente;
use App\Domain\Incidente\Models\Incidente;
use App\Http\Resources\Panel\Indicador;
use App\Http\Resources\Panel\Reparto;
use App\Http\Resources\Panel\ResumenIncidentesPanel;

/**
 * Lo que pide atención en el registro de incidentes.
 *
 * **Cada indicador cuenta con el mismo scope que usa su filtro de la tabla**, y
 * la clave del indicador es la del filtro: eso es lo que garantiza que pulsar la
 * cifra enseñe exactamente esa cifra.
 */
final readonly class RegistroIncidentes
{
    public function total(): int
    {
        return Incidente::query()->count();
    }

    /**
     * Lo que va mal de verdad.
     *
     * **Una sola, y es el plazo de la AEPD.** Es el único número que pone la ley
     * —72 h, artículo 33.1 del RGPD— y el único sitio del módulo donde algo está
     * incumplido ahora mismo. Ni los estados ni la peligrosidad gastan rojo: un
     * incidente crítico abierto no va mal, va siendo atendido.
     *
     * **El CCN-CERT no entra**, y es deliberado: el RD 311/2022 dice «sin
     * dilación» y no fija horas, así que no hay plazo que incumplir sin
     * inventárselo.
     *
     * @return list<Indicador>
     */
    public function alertas(): array
    {
        return [
            $this->indicador(
                'fuera_de_plazo_aepd',
                'Fuera de plazo con la AEPD',
                'fueraDePlazoAepd',
                'caducada',
                'Notificables a la AEPD, sin notificar y con las 72 h del artículo 33.1 del RGPD ya pasadas.',
            ),
        ];
    }

    /**
     * Lo que está a medias.
     *
     * @return list<Indicador>
     */
    public function pendientes(): array
    {
        return [
            $this->indicador(
                'abiertos',
                'Abiertos',
                'abiertos',
                'en_progreso',
                'Todo lo que no está cerrado, «resuelto» incluido: el servicio volvió y falta la lección aprendida.',
            ),
            $this->indicador(
                'en_plazo_aepd',
                'Pendiente de notificar a la AEPD',
                'enPlazoAepd',
                'planificado',
                'Notificables, sin notificar y todavía dentro de las 72 h. No es un incumplimiento: es trabajo urgente.',
            ),
            $this->indicador(
                'sin_leccion',
                'Resueltos sin lección aprendida',
                'sinLeccion',
                'en_revision',
                'op.exp.7 pide aprender del incidente, y es el paso que se salta todo el mundo el día que el servicio vuelve.',
            ),
        ];
    }

    /**
     * El reparto por estado, **con los cerrados dentro**.
     *
     * Como en no conformidades y objetivos, y al revés que en tareas: la pregunta
     * aquí es «de los que hemos tenido, cuántos hemos llegado a cerrar con su
     * lección», y sin `cerrado` en la barra esa cifra no se ve.
     *
     * @return list<Reparto>
     */
    public function porEstado(): array
    {
        $cuentas = Incidente::query()
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return array_map(
            static fn (EstadoIncidente $estado): Reparto => new Reparto(
                clave: $estado->value,
                etiqueta: $estado->etiqueta(),
                valor: (int) ($cuentas[$estado->value] ?? 0),
                tono: $estado->tono(),
            ),
            EstadoIncidente::cases(),
        );
    }

    public function paraElPanel(): ResumenIncidentesPanel
    {
        return new ResumenIncidentesPanel(
            total: $this->total(),
            abiertos: Incidente::query()->abiertos()->count(),
            fueraDePlazoAepd: Incidente::query()->fueraDePlazoAepd()->count(),
            enPlazoAepd: Incidente::query()->enPlazoAepd()->count(),
            sinLeccion: Incidente::query()->sinLeccion()->count(),
            porEstado: $this->porEstado(),
        );
    }

    private function indicador(string $clave, string $etiqueta, string $scope, string $tono, ?string $ayuda = null): Indicador
    {
        return new Indicador(
            clave: $clave,
            etiqueta: $etiqueta,
            valor: Incidente::query()->{$scope}()->count(),
            tono: $tono,
            filtro: "filter[{$clave}]=1",
            base: '/incidentes',
            ayuda: $ayuda,
        );
    }
}
