<?php

declare(strict_types=1);

namespace App\Domain\Documento\Contenido;

use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Auditoria\ResultadoAuditoria;
use App\Domain\Conformidad\Enums\EstadoConformidad;
use App\Domain\Conformidad\Models\Conformidad;
use App\Domain\Conformidad\RequisitosDeDeclaracion;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Excepciones\DocumentoNoGenerable;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Documento\Narrativa\MarkdownDocumento;
use App\Domain\Documento\Narrativa\ResolverNarrativa;
use App\Domain\Obligacion\Cadencia;

/**
 * La Declaración de Conformidad del ENS: § 4.17, categoría básica.
 *
 * **Es el sexto documento calculado**, y como el acta implementa
 * `GeneradorDocumento` directamente: no imprime la tabla del Anexo II —la
 * situación medida a medida la declara la DdA, que es el documento de al lado— y
 * lo que sí imprime no sale de `implantaciones`.
 *
 * ### Se construye desde lo congelado, nunca de una consulta nueva
 *
 * Lo que se declara es **la categoría del día en que se inició la declaración** y
 * **el resultado de una autoevaluación cerrada**. Las dos cosas están congeladas:
 * la categoría en `conformidades.categoria` y la checklist en los puntos de la
 * auditoría, que un trigger blinda desde el cierre. Si el documento leyera la
 * categoría del sistema al generar, revalorarlo en octubre cambiaría la
 * declaración de marzo bajo su firma.
 *
 * Sin declaración iniciada no hay documento, y se dice: generar una declaración
 * que nadie ha empezado sería imprimir una conformidad que no se ha comprobado.
 */
final class DeclaracionConformidadEns implements GeneradorDocumento
{
    use Concerns\ArmaContenidoComun;

    public function __construct(
        private readonly ResolverNarrativa $narrativa,
        private readonly MarkdownDocumento $markdown,
        private readonly RequisitosDeDeclaracion $requisitos,
        private readonly ResultadoAuditoria $resultado,
    ) {}

    public function tipo(): TipoDocumento
    {
        return TipoDocumento::DeclaracionConformidadEns;
    }

    /**
     * @param  array<string, mixed>  $parametros
     */
    public function construir(Documento $documento, DocumentoVersion $version, array $parametros = []): ContenidoDocumento
    {
        $conformidad = $this->conformidadDe($documento);
        $conformidad->loadMissing(['auditoria.cerradaPor', 'sistema.valoraciones']);

        $auditoria = $conformidad->auditoria;
        $sistema = $conformidad->sistema;
        $portada = $this->portadaBase($documento, $version);

        return new ContenidoDocumento(
            titulo: $documento->titulo,
            subtitulo: 'Categoría '.$conformidad->categoria->etiqueta().' · '.$auditoria->codigo,

            portada: [
                ...$portada,
                'alcance' => $sistema->alcance_declarado,
                // La congelada y no la del sistema hoy: ver la cabecera.
                'categoria' => $conformidad->categoria->etiqueta(),
            ],

            // Sin cifras de implantación ni filas de requisitos: este documento no
            // cuenta medidas implantadas, cuenta lo que la autoevaluación revisó.
            resumen: [],
            filas: [],

            limitaciones: [
                ...$this->limitacionesPropias($conformidad),
                ...$this->limitacionesBase($version),
            ],
            historial: $this->historialDe($documento),
            extras: [
                'declaracion' => $this->declaracion($conformidad, $auditoria, $version, $portada),
                'resultado' => $this->resultado->para($auditoria),
            ],
            textos: TextosDocumento::desdeMarkdown(
                $this->narrativa->paraDocumento($documento),
                $this->markdown,
            ),
        );
    }

    /**
     * La conformidad que respalda este documento.
     *
     * **La que está en preparación, si la hay**: es la que se está redactando. Si
     * no, la vigente —declarada o publicada—, para que regenerar la serie después
     * de firmar siga imprimiendo lo mismo. Una retirada no respalda nada.
     */
    private function conformidadDe(Documento $documento): Conformidad
    {
        $conformidad = Conformidad::query()
            ->where('sistema_id', $documento->sistema_id)
            ->vivas()
            ->orderByRaw('CASE WHEN estado = ? THEN 0 ELSE 1 END', [EstadoConformidad::EnPreparacion->value])
            ->orderByDesc('id')
            ->first();

        if (! $conformidad instanceof Conformidad) {
            throw DocumentoNoGenerable::sinDeclaracionIniciada();
        }

        return $conformidad;
    }

    /**
     * Lo que este documento en concreto no puede afirmar.
     *
     * @return list<string>
     */
    private function limitacionesPropias(Conformidad $conformidad): array
    {
        $limitaciones = [
            'Esta declaración se apoya en una **autoevaluación** registrada en Statera. La herramienta '
            .'**no comprueba la independencia ni la competencia** de quien la realizó, ni que su alcance '
            .'coincida con el que exige la guía CCN-STIC 809: registra lo que la organización revisó, con su '
            .'checklist congelada al cerrarla.',

            'Las medidas marcadas **fuera de muestra** no se revisaron en la autoevaluación. La declaración '
            .'las cuenta aparte y **no afirma que estén conformes**: afirma que no se revisaron.',

            'El **distintivo de conformidad** no lo emite Statera. Lo publica la organización junto a esta '
            .'declaración; la herramienta registra dónde y desde cuándo, pero **no sirve ninguna página '
            .'pública** ni comprueba que el distintivo siga publicado.',

            'La **vigencia de dos años** es la que Statera aplica a la renovación, la misma de la obligación '
            .'`ens.conformidad` de su catálogo. **La cifra está pendiente de contrastar con el BOE**, igual que '
            .'el resto de periodicidades de ese catálogo.',
        ];

        /*
         * El único aviso que depende del estado de hoy, y a propósito: la
         * declaración imprime la categoría congelada, pero si el sistema ya no es
         * de esa categoría, quien la lee tiene que saberlo.
         */
        $actual = $conformidad->sistema->categoria();

        if ($actual !== null && $actual !== $conformidad->categoria) {
            array_unshift(
                $limitaciones,
                "**El sistema ya no es de categoría {$conformidad->categoria->etiqueta()}.** Esta declaración "
                .'recoge la categoría del día en que se inició; la valoración vigente lo sitúa en categoría '
                ."{$actual->etiqueta()}, y con ella la declaración deja de valer: procede la vía que le "
                .'corresponda a la categoría nueva.',
            );
        }

        return $limitaciones;
    }

    /**
     * Los datos de la declaración formal y de su ficha.
     *
     * @param  array<string, mixed>  $portada
     * @return array<string, mixed>
     */
    private function declaracion(Conformidad $conformidad, Auditoria $auditoria, DocumentoVersion $version, array $portada): array
    {
        $firma = $version->aprobada_en;

        return [
            'organizacion' => $portada['organizacion'] ?? null,
            'cif' => $portada['cif'] ?? null,
            'sistemaCodigo' => $conformidad->sistema->codigo,
            'sistemaNombre' => $conformidad->sistema->nombre,
            'alcance' => $conformidad->sistema->alcance_declarado,
            'categoria' => $conformidad->categoria->etiqueta(),
            'autoevaluacion' => $auditoria->codigo,
            'fechaAutoevaluacion' => $auditoria->fecha->format('d/m/Y'),
            'fechaCierre' => $auditoria->fecha_cierre?->format('d/m/Y'),
            'auditor' => $auditoria->auditor,
            'alcanceAuditado' => $auditoria->alcance,
            'firmante' => $version->aprobadaPor?->name,
            'fechaFirma' => $firma?->format('d/m/Y'),
            'vigenteHasta' => $firma === null
                ? null
                : (new Cadencia(Conformidad::VIGENCIA_MESES))->despuesDe($firma)->format('d/m/Y'),
            'mayoresAbiertas' => $this->requisitos->mayoresAbiertas($auditoria),
        ];
    }
}
