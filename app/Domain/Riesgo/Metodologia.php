<?php

declare(strict_types=1);

namespace App\Domain\Riesgo;

use App\Domain\Riesgo\Excepciones\MetodologiaIncoherente;
use Illuminate\Support\Carbon;

/**
 * Con qué mide los riesgos una organización: las dos escalas y las dos líneas.
 *
 * **No vive en `config/`, y ahí está la diferencia con `config/obsolescencia.php`.**
 * El fin de soporte de Ubuntu es un hecho del mundo, igual para todos los
 * clientes; los criterios de aceptación de riesgo los decide la dirección de cada
 * organización y el auditor pide el papel firmado (ISO 27001, 6.1.2 a) y 6.1.3 f).
 * Y hay un segundo motivo que pesa más: en `config/` un despliegue cambiaría la
 * escala **retroactivamente para todo el histórico** y sin dejar constancia. Por
 * eso cada valoración se lleva su escala congelada dentro, que es el mismo
 * razonamiento que hay detrás de `instantanea` en los documentos.
 *
 * Inmutable y sin identidad: la fila de la organización y la de fábrica producen
 * el mismo tipo, y `esDeFabrica` es lo único que las distingue. Eso es lo que
 * permite que **no haya que materializar una fila** para que la herramienta
 * funcione desde el primer día — el mismo trato que `TextosDeFabrica` da a la
 * narrativa de los documentos.
 */
final readonly class Metodologia
{
    public function __construct(
        public string $nombre,
        public ?string $referencia,
        public EscalaRiesgo $probabilidad,
        public EscalaRiesgo $impacto,
        public int $umbralAceptacion,
        public int $umbralCritico,
        public int $periodicidadRevisionMeses,
        public bool $esDeFabrica = false,
        public ?string $aprobadaPor = null,
        public ?Carbon $aprobadaEn = null,
    ) {
        $maximo = $this->probabilidad->maximo() * $this->impacto->maximo();

        if ($umbralAceptacion < 2) {
            throw MetodologiaIncoherente::umbralBajoMinimo('de aceptación', $umbralAceptacion);
        }

        if ($umbralCritico < $umbralAceptacion) {
            throw MetodologiaIncoherente::umbralesCruzados($umbralAceptacion, $umbralCritico);
        }

        if ($umbralAceptacion > $maximo) {
            throw MetodologiaIncoherente::umbralInalcanzable('de aceptación', $umbralAceptacion, $maximo);
        }

        if ($umbralCritico > $maximo) {
            throw MetodologiaIncoherente::umbralInalcanzable('crítico', $umbralCritico, $maximo);
        }

        if ($periodicidadRevisionMeses < 1 || $periodicidadRevisionMeses > 60) {
            throw new MetodologiaIncoherente(sprintf(
                'La periodicidad de revisión es de %d meses. Fuera de 1 a 60 no es una periodicidad, '
                .'es no revisar.',
                $periodicidadRevisionMeses,
            ));
        }
    }

    /** El riesgo más alto que permiten las dos escalas. El techo de la matriz. */
    public function riesgoMaximo(): int
    {
        return $this->probabilidad->maximo() * $this->impacto->maximo();
    }

    /**
     * Si la dirección la ha aprobado por escrito.
     *
     * Mientras no lo esté, los documentos que se apoyen en ella tienen que
     * **declararlo como limitación**, igual que la SoA declara hoy que no hay
     * flujo de aprobación. Un análisis de riesgos medido con una escala que nadie
     * ha aprobado no es un hallazgo si se dice; lo es si se calla.
     */
    public function estaAprobada(): bool
    {
        return $this->aprobadaPor !== null && $this->aprobadaEn !== null;
    }

    /**
     * Si es la de fábrica y sin aprobar, que es el estado de partida de toda
     * organización y el que hay que avisar en pantalla y en papel.
     */
    public function esProvisional(): bool
    {
        return $this->esDeFabrica || ! $this->estaAprobada();
    }

    public function equivale(self $otra): bool
    {
        return $this->nombre === $otra->nombre
            && $this->referencia === $otra->referencia
            && $this->umbralAceptacion === $otra->umbralAceptacion
            && $this->umbralCritico === $otra->umbralCritico
            && $this->periodicidadRevisionMeses === $otra->periodicidadRevisionMeses
            && $this->probabilidad->equivale($otra->probabilidad)
            && $this->impacto->equivale($otra->impacto);
    }

    /**
     * Lo que se congela en `riesgo_valoraciones.escala`.
     *
     * Sin esto, cambiar la escala en enero convierte todas las valoraciones
     * anteriores en cifras sin unidades y «histórico comparable» deja de serlo.
     * Van las dos escalas y los dos umbrales, que es lo que hace falta para
     * volver a leer un número de hace ocho meses; no van la aprobación ni la
     * periodicidad, que son de la metodología y no de la medición.
     *
     * @return array{nombre: string, referencia: string|null, probabilidad: list<array{valor: int, etiqueta: string, descripcion: string|null}>, impacto: list<array{valor: int, etiqueta: string, descripcion: string|null}>, umbral_aceptacion: int, umbral_critico: int}
     */
    public function aArray(): array
    {
        return [
            'nombre' => $this->nombre,
            'referencia' => $this->referencia,
            'probabilidad' => $this->probabilidad->aArray(),
            'impacto' => $this->impacto->aArray(),
            'umbral_aceptacion' => $this->umbralAceptacion,
            'umbral_critico' => $this->umbralCritico,
        ];
    }

    /**
     * La vuelta de `aArray()`: una escala congelada, leída para interpretar una
     * valoración vieja.
     *
     * @param  array<string, mixed>  $congelada
     */
    public static function desdeArray(array $congelada): self
    {
        /** @var list<array{valor?: mixed, etiqueta?: mixed, descripcion?: mixed}> $probabilidad */
        $probabilidad = $congelada['probabilidad'] ?? [];
        /** @var list<array{valor?: mixed, etiqueta?: mixed, descripcion?: mixed}> $impacto */
        $impacto = $congelada['impacto'] ?? [];

        return new self(
            nombre: (string) ($congelada['nombre'] ?? ''),
            referencia: isset($congelada['referencia']) ? (string) $congelada['referencia'] : null,
            probabilidad: EscalaRiesgo::desdeArray($probabilidad, 'escala de probabilidad'),
            impacto: EscalaRiesgo::desdeArray($impacto, 'escala de impacto'),
            umbralAceptacion: (int) ($congelada['umbral_aceptacion'] ?? 0),
            umbralCritico: (int) ($congelada['umbral_critico'] ?? 0),
            // No viaja en la instantánea: no hace falta para releer un número.
            periodicidadRevisionMeses: 12,
        );
    }
}
