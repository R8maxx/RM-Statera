<?php

declare(strict_types=1);

namespace App\Domain\Persona;

use App\Domain\Persona\Enums\RolEns;
use App\Domain\Persona\Models\DesignacionRol;
use App\Domain\Persona\Models\Persona;
use App\Domain\Sistema\Models\Sistema;
use App\Http\Resources\Panel\Indicador;
use App\Http\Resources\Panel\ResumenPersonasPanel;

/**
 * Lo que pide atención en el registro de personas.
 *
 * **Cada indicador cuenta con el mismo scope que usa su filtro de la tabla**, y la
 * clave del indicador es la del filtro: eso es lo que garantiza que pulsar la
 * cifra enseñe exactamente esa cifra.
 *
 * Igual que sus hermanos, vive en el dominio y no en `Http/Resources`: son
 * preguntas del negocio, no de una pantalla.
 */
final readonly class RegistroPersonas
{
    public function total(): int
    {
        return Persona::query()->count();
    }

    /**
     * Lo que va mal de verdad.
     *
     * **Una sola, y es la salida sin cerrar.** Alguien que se fue con la checklist
     * de baja a medias es un acceso que puede seguir vivo, y ése es el hallazgo
     * clásico de `mp.per.*` — el hermano exacto del equipo retirado sin constancia
     * de borrado.
     *
     * **No estar formado no va en rojo.** Es la distancia que queda, y el quinto
     * principio del producto: un indicador que castiga por apuntar lo que falta
     * enseña a no apuntarlo.
     *
     * @return list<Indicador>
     */
    public function alertas(): array
    {
        return [
            $this->indicador(
                'baja_sin_cerrar',
                'Salidas sin cerrar',
                'conBajaSinCerrar',
                'caducada',
                'Personas dadas de baja con pasos de la checklist de salida sin marcar: accesos que pueden seguir vivos.',
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
                'activas',
                'En plantilla',
                'activas',
                'implantado',
                'Personas sin fecha de baja.',
            ),
            $this->indicador(
                'sin_formacion',
                'Sin formación reciente',
                'sinFormacionReciente',
                'en_progreso',
                'Activas sin ninguna asistencia en los últimos doce meses (mp.per.3 y mp.per.4).',
            ),
            $this->indicador(
                'sin_acuerdo',
                'Sin acuerdo vigente',
                'sinAcuerdoVigente',
                'planificado',
                'Activas sin acuerdo de confidencialidad en vigor (mp.per.2).',
            ),
        ];
    }

    /**
     * Los roles ENS que ningún sistema tiene designados hoy.
     *
     * **Es la cifra del 5.3**, y la que hasta este módulo se declaraba como
     * limitación en el PDF de la DdA. Se cuenta sobre los sistemas activos porque
     * es donde el rol tiene que existir: exigírselo a un sistema archivado pondría
     * un techo inalcanzable, que es el mismo argumento de `controlesResueltos()`.
     *
     * @return array{designados: int, exigibles: int, faltan: list<array{sistema: string, rol: string}>}
     */
    public function cobertura(): array
    {
        $sistemas = Sistema::query()->orderBy('codigo')->get();

        $vigentes = DesignacionRol::query()
            ->vigentes()
            ->get()
            ->groupBy(fn (DesignacionRol $designacion): string => $designacion->sistema_id.'|'.$designacion->rol->value);

        $faltan = [];
        $designados = 0;

        foreach ($sistemas as $sistema) {
            foreach (RolEns::cases() as $rol) {
                if ($vigentes->has($sistema->id.'|'.$rol->value)) {
                    $designados++;

                    continue;
                }

                $faltan[] = ['sistema' => $sistema->codigo, 'rol' => $rol->etiqueta()];
            }
        }

        return [
            'designados' => $designados,
            'exigibles' => $sistemas->count() * count(RolEns::cases()),
            'faltan' => $faltan,
        ];
    }

    /** El registro, tal y como lo lee el panel (§ 4.14). */
    public function paraElPanel(): ResumenPersonasPanel
    {
        $cobertura = $this->cobertura();

        return new ResumenPersonasPanel(
            total: $this->total(),
            activas: Persona::query()->activas()->count(),
            sinFormacion: Persona::query()->sinFormacionReciente()->count(),
            sinAcuerdo: Persona::query()->sinAcuerdoVigente()->count(),
            bajaSinCerrar: Persona::query()->conBajaSinCerrar()->count(),
            rolesDesignados: $cobertura['designados'],
            rolesExigibles: $cobertura['exigibles'],
        );
    }

    /**
     * La clave del indicador **es** la del filtro, y el scope **es** el tercer
     * argumento de su `Filtro::porScope()`.
     */
    private function indicador(string $clave, string $etiqueta, string $scope, string $tono, ?string $ayuda = null): Indicador
    {
        return new Indicador(
            clave: $clave,
            etiqueta: $etiqueta,
            valor: Persona::query()->{$scope}()->count(),
            tono: $tono,
            filtro: "filter[{$clave}]=1",
            base: '/personas',
            ayuda: $ayuda,
        );
    }
}
