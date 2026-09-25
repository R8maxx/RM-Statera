<?php

declare(strict_types=1);

namespace App\Domain\Proveedor;

use App\Domain\Proveedor\Enums\EstadoProveedor;
use App\Domain\Proveedor\Models\Proveedor;
use App\Http\Resources\Panel\Indicador;

/**
 * Las cifras de proveedores que suben al panel y al informe de estado (§ 4.9).
 *
 * **Dos rojos, y los dos caducan solos**: una reevaluación que se pasó de fecha
 * y una certificación caducada de un proveedor con el que se sigue trabajando.
 * Son lo mismo que una evidencia caducada: la prueba de que el tercero cumple ya
 * no vale, y nadie tiene que tocar nada para que ocurra.
 *
 * Lo que no ha empezado —el que nunca se evaluó, el condicionado— es trabajo
 * pendiente y no alarma.
 */
final readonly class RegistroProveedores
{
    public function total(): int
    {
        return Proveedor::query()->where('estado', '<>', EstadoProveedor::Retirado->value)->count();
    }

    /** @return list<Indicador> */
    public function alertas(): array
    {
        return [
            $this->indicador(
                'reevaluacion_vencida',
                'Reevaluación vencida',
                'reevaluacionVencida',
                'caducada',
                'Proveedores cuya última evaluación ya no cubre el plazo que fija la organización para su criticidad.',
            ),
            $this->indicador(
                'certificacion_caducada',
                'Certificación caducada',
                'conCertificacionCaducada',
                'caducada',
                'Proveedores con los que se sigue trabajando y que tienen un certificado registrado ya caducado.',
            ),
        ];
    }

    /** @return list<Indicador> */
    public function pendientes(): array
    {
        return [
            $this->indicador(
                'sin_evaluar',
                'Sin evaluar',
                'sinEvaluar',
                'no_iniciado',
                'Nunca se ha comprobado su contrato. Sin evaluación no hay fecha de reevaluación.',
            ),
            $this->indicador(
                'condicionados',
                'Condicionados',
                'condicionados',
                'en_progreso',
                'La última evaluación fue apta con condiciones: hay algo pendiente de resolver.',
            ),
        ];
    }

    private function indicador(string $clave, string $etiqueta, string $scope, string $tono, string $ayuda): Indicador
    {
        return new Indicador(
            clave: $clave,
            etiqueta: $etiqueta,
            valor: Proveedor::query()->{$scope}()->count(),
            tono: $tono,
            filtro: "filter[{$clave}]=1",
            base: '/proveedores',
            ayuda: $ayuda,
        );
    }
}
