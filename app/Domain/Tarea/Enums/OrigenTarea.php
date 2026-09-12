<?php

declare(strict_types=1);

namespace App\Domain\Tarea\Enums;

/**
 * De dónde sale una tarea.
 *
 * **Se declara entero y hoy sólo se cablea uno.** Los cinco primeros son los de
 * § 4.7 y de ésos únicamente existe `BrechaImplantacion`: hallazgo, riesgo,
 * incidente y revisión por la dirección llegan con sus módulos (§ 4.12, § 4.3,
 * § 4.10 y § 4.15). Mismo criterio con el que se carga el Anexo II completo
 * usando el subconjunto de categoría básica: el modelo entero desde el principio
 * y los datos que haya.
 *
 * `Propia` **no está en la especificación y se añade a conciencia.** Los cinco de
 * § 4.7 dan por supuesto que toda tarea nace de otro registro, y muchas no: «pedir
 * presupuesto del antivirus» no es un hallazgo, ni un riesgo, ni un incidente.
 * Sin un valor para eso, quien apunta una tarea a mano elige el que menos mal le
 * suena y el campo deja de significar nada, que es justo lo contrario de por qué
 * existe.
 */
enum OrigenTarea: string
{
    case Hallazgo = 'hallazgo';
    case Riesgo = 'riesgo';
    case BrechaImplantacion = 'brecha_implantacion';
    case Incidente = 'incidente';
    case RevisionDireccion = 'revision_direccion';
    case Propia = 'propia';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Hallazgo => 'Hallazgo de auditoría',
            self::Riesgo => 'Tratamiento de un riesgo',
            self::BrechaImplantacion => 'Requisito pendiente',
            self::Incidente => 'Incidente',
            self::RevisionDireccion => 'Revisión por la dirección',
            self::Propia => 'Iniciativa propia',
        };
    }

    /**
     * Si hoy se puede crear una tarea con este origen.
     *
     * Los orígenes cuyo módulo no existe se declaran pero no se ofrecen: una
     * lista desplegable con cuatro opciones que no llevan a ninguna parte enseña
     * el mapa de lo que falta en lugar de dejar hacer el trabajo de hoy.
     */
    public function disponible(): bool
    {
        return $this === self::BrechaImplantacion || $this === self::Propia;
    }

    /** @return list<self> */
    public static function disponibles(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $origen): bool => $origen->disponible()));
    }
}
