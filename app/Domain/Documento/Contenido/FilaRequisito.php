<?php

declare(strict_types=1);

namespace App\Domain\Documento\Contenido;

/**
 * Una fila de la tabla de una declaración de aplicabilidad.
 *
 * Es un objeto y no un array asociativo por dos motivos concretos. Uno,
 * Larastan: `instantanea` casteada a `array` es `array<mixed>` y cada acceso es
 * `mixed`, así que la forma tiene que vivir en algún sitio tipado. Y dos, los
 * tests afirman sobre esto y no sobre el HTML, que es lo que hace que probar el
 * contenido no dependa de cómo esté maquetado.
 *
 * Sirve para las dos declaraciones y por eso tiene campos que sólo llena una:
 * `justificacionInclusion` es de ISO —donde la aplicabilidad es una decisión que
 * hay que justificar— y `exigencia`, `origenExigencia` y `dimensionModuladora`
 * son del ENS —donde es un cálculo que hay que poder rastrear—.
 */
final readonly class FilaRequisito
{
    /**
     * @param  string  $grupo  El epígrafe bajo el que se agrupa: «A.5 Controles organizativos», «op.acc».
     * @param  list<string>  $evidencias  Título y fecha de cada prueba, ya formateados.
     * @param  list<string>  $correspondencias  Códigos del otro marco que cubren lo mismo.
     */
    public function __construct(
        public string $grupo,
        public string $codigo,
        public string $titulo,
        public bool $aplica,
        public string $estado,
        public string $estadoEtiqueta,
        public string $estadoTono,
        public ?string $justificacion = null,
        public ?string $justificacionInclusion = null,
        public ?string $exigencia = null,
        public ?string $origenExigencia = null,
        public ?string $dimensionModuladora = null,
        public ?string $madurez = null,
        public ?int $madurezValor = null,
        public ?string $responsable = null,
        public array $evidencias = [],
        public array $correspondencias = [],
    ) {}

    /**
     * Rehidrata una fila desde la instantánea de una versión emitida.
     *
     * Es lo que permite que el `.docx` de la v3 diga lo que decía la v3, y no lo
     * que diga hoy la base de datos. Sin esto, dos ficheros de la misma versión
     * contarían cosas distintas y uno de ellos llevaría dentro la huella del
     * otro.
     *
     * Lo que llega es un jsonb: se estrecha cada campo en vez de confiar.
     *
     * @param  array<string, mixed>  $fila
     */
    public static function desdeArray(array $fila): self
    {
        $texto = static fn (string $clave): ?string => is_string($fila[$clave] ?? null)
            ? (string) $fila[$clave]
            : null;

        $lista = static function (string $clave) use ($fila): array {
            $valor = $fila[$clave] ?? [];

            return is_array($valor)
                ? array_values(array_filter($valor, 'is_string'))
                : [];
        };

        return new self(
            grupo: $texto('grupo') ?? '',
            codigo: $texto('codigo') ?? '',
            titulo: $texto('titulo') ?? '',
            aplica: (bool) ($fila['aplica'] ?? false),
            estado: $texto('estado') ?? '',
            estadoEtiqueta: $texto('estadoEtiqueta') ?? '',
            estadoTono: $texto('estadoTono') ?? '',
            justificacion: $texto('justificacion'),
            justificacionInclusion: $texto('justificacionInclusion'),
            exigencia: $texto('exigencia'),
            origenExigencia: $texto('origenExigencia'),
            dimensionModuladora: $texto('dimensionModuladora'),
            madurez: $texto('madurez'),
            madurezValor: is_numeric($fila['madurezValor'] ?? null) ? (int) $fila['madurezValor'] : null,
            responsable: $texto('responsable'),
            evidencias: $lista('evidencias'),
            correspondencias: $lista('correspondencias'),
        );
    }

    public function tieneEvidencia(): bool
    {
        return $this->evidencias !== [];
    }

    /**
     * Lo que se pinta en la columna de evidencia cuando no hay ninguna.
     *
     * Se dice explícitamente en lugar de dejar la celda vacía: una celda en
     * blanco se lee como un descuido de maquetación y ésta es justamente la
     * pregunta que separa «lo tenemos hecho» de «lo podemos demostrar».
     */
    public function evidenciaODefecto(): string
    {
        return $this->tieneEvidencia()
            ? implode('; ', $this->evidencias)
            : 'Sin evidencia registrada';
    }
}
