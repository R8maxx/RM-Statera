<?php

declare(strict_types=1);

namespace App\Domain\Mejora\Enums;

/**
 * De dónde sale una oportunidad de mejora.
 *
 * Seis, y son las seis puertas por las que la mejora continua entra de verdad
 * en una organización: la auditoría que la escribe como hallazgo, la revisión por
 * la dirección que la decide, el indicador que se queda corto, **el incidente del
 * que se aprende algo**, **la prueba de continuidad que sale parcial o fallida** y
 * la persona a la que se le ocurre.
 *
 * **`Incidente` llega con el § 4.10 y su migración del `CHECK`.** La lección
 * aprendida de un incidente es la fuente clásica de una mejora —y de las que más
 * se usan—: un correo fraudulento que alguien detectó y reportó bien no incumple
 * nada, así que no abre no conformidad, y aun así deja una idea para la próxima
 * vez. Sin este caso acabaría como `Propia`, que es el «elegir el que menos mal
 * suena» que deja el campo sin significar nada.
 *
 * **`Indicador` es la que justifica que este módulo llegue después del § 4.14.**
 * Un indicador fuera de objetivo no es una no conformidad —no incumple ningún
 * requisito— y hasta aquí no tenía dónde acabar: la cifra se quedaba roja en el
 * cuadro de mando y nadie apuntaba qué se iba a hacer con ella.
 *
 * `RevisionDireccion` se declara desde hoy y **se ofrece**, a diferencia de lo que
 * pasa en `OrigenTarea`: aquí no hay a qué apuntar —no hay clave foránea a una
 * revisión— así que es una etiqueta honesta y no una trazabilidad fingida. Cuando
 * llegue el § 4.15, las salidas de la revisión crearán mejoras por su camino y
 * esta etiqueta seguirá significando lo mismo.
 *
 * **`PruebaContinuidad` es el sexto, y llega con el § 4.11.** Igual que
 * `Incidente`, **no lleva clave foránea**: la lección aprendida de una prueba que
 * salió parcial o fallida no «trata» la prueba —ésa ya quedó registrada con su
 * resultado—, así que atarla sería fingir una trazabilidad que no hay. Lo que se
 * hereda es el título, no un vínculo.
 */
enum OrigenMejora: string
{
    case Auditoria = 'auditoria';
    case RevisionDireccion = 'revision_direccion';
    case Indicador = 'indicador';
    case Incidente = 'incidente';
    case PruebaContinuidad = 'prueba_continuidad';
    case Propia = 'propia';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Auditoria => 'Hallazgo de auditoría',
            self::RevisionDireccion => 'Revisión por la dirección',
            self::Indicador => 'Indicador fuera de objetivo',
            self::Incidente => 'Lección aprendida de un incidente',
            self::PruebaContinuidad => 'Lección aprendida de una prueba de continuidad',
            self::Propia => 'Iniciativa propia',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Auditoria => 'SearchCheck',
            self::RevisionDireccion => 'Users',
            self::Indicador => 'Equal',
            self::Incidente => 'CloudLightning',
            self::PruebaContinuidad => 'Repeat',
            self::Propia => 'Lightbulb',
        };
    }

    /**
     * **Los seis se ofrecen**, a diferencia de `OrigenTarea`.
     *
     * Allí la lista existe porque una tarea marcada «hallazgo de auditoría» sin
     * auditoría detrás no es trazable; aquí ninguno de los cinco restantes promete
     * un vínculo que no exista —el único que lo tiene es `Auditoria`, y esa sí lleva
     * su `hallazgo_id`—. Se deja el método para que la pregunta tenga respuesta en
     * el mismo sitio que en los otros enums de origen.
     */
    public function disponible(): bool
    {
        return true;
    }

    /** @return list<self> */
    public static function disponibles(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $origen): bool => $origen->disponible()));
    }

    /**
     * El tono del reparto por origen, con el mismo vocabulario de tres que usa
     * `OrigenTarea::tono()`: ámbar lo reactivo, azul lo planificado, gris la
     * iniciativa propia.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Auditoria, self::Indicador, self::Incidente, self::PruebaContinuidad => 'en_progreso',
            self::RevisionDireccion => 'planificado',
            self::Propia => 'no_iniciado',
        };
    }
}
