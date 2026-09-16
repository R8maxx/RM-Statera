<?php

declare(strict_types=1);

namespace App\Domain\Documento\Contenido;

use App\Domain\Categorizacion\Enums\OrigenExigencia;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Http\Resources\Implantacion\Correspondencia;

/**
 * La Declaración de Aplicabilidad de ISO/IEC 27001:2022 (cláusula 6.1.3 d).
 *
 * El auditor exige cuatro cosas y no acepta tres: los controles necesarios, la
 * **justificación de su inclusión**, si están implantados, y la **justificación
 * de excluir** cualquier control del Anexo A.
 *
 * **Aquí la aplicabilidad es una decisión.** Los 93 controles aplican de
 * partida y la SoA es precisamente la lista de exclusiones con su motivo; por
 * eso esta declaración lleva dos columnas de justificación y la del ENS no.
 *
 * **La inclusión se justifica desde donde ISO espera.** Un control entra por
 * pertenecer al Anexo A, por tratar un riesgo identificado («control incluido a
 * raíz del riesgo R-014»), por una exigencia legal —que el ENS pida la misma
 * medida lo es— o por decisión motivada. Los riesgos llegan del § 4.3 por el
 * vínculo de salvaguarda, así que la referencia es real o no se escribe: lo que
 * NO se hace es inventarse un riesgo para rellenar la columna.
 *
 * Lo que la herramienta no hace, y por eso sigue declarado en las limitaciones,
 * es **exigir** que todo control aplicable tenga un riesgo detrás ni comprobar
 * que el análisis cubra el alcance entero.
 */
final class DeclaracionAplicabilidadIso extends DocumentoCalculado
{
    use Concerns\ResumeLaAplicabilidad;

    public function tipo(): TipoDocumento
    {
        return TipoDocumento::SoaIso;
    }

    protected function tipoDeRequisito(): string
    {
        return 'control';
    }

    public function construir(Documento $documento, DocumentoVersion $version, array $parametros = []): ContenidoDocumento
    {
        $implantaciones = $this->implantaciones($documento);
        $correspondencias = $this->correspondenciasDe($implantaciones);

        $filas = $implantaciones
            ->map(fn (Implantacion $i): FilaRequisito => $this->fila($i, $correspondencias))
            ->values()
            ->all();

        $sistema = $documento->sistema;

        return new ContenidoDocumento(
            // El título del registro, no una constante: hasta ahora se podía
            // editar en el formulario y el documento seguía diciendo otra cosa.
            titulo: $documento->titulo,
            subtitulo: 'ISO/IEC 27001:2022 — Anexo A',
            portada: [
                ...$this->portadaBase($documento, $version),
                // La cláusula 4.3 y sus exclusiones son campos distintos de las
                // exclusiones de controles, y el auditor los lee por separado.
                'alcance' => $sistema?->alcance_declarado,
                'exclusionesAlcance' => $sistema?->exclusiones_justificadas,
            ],
            resumen: $this->resumen($documento, $filas),
            filas: $filas,
            limitaciones: [
                'La **justificación de inclusión** de cada control recoge su origen real: pertenencia '
                .'al Anexo A, tratamiento de un riesgo del registro cuando el control está vinculado '
                .'como salvaguarda, exigencia legal derivada del ENS y decisión motivada. **La '
                .'herramienta no exige que todo control aplicable tenga un riesgo detrás ni comprueba '
                .'que el análisis de riesgos cubra el alcance completo**, de modo que la ausencia de '
                .'referencia a un riesgo no significa que no exista, sino que no se ha vinculado.',

                'Los títulos de control siguen ISO/IEC 27002:2022. **La redacción íntegra de los '
                .'controles no se reproduce** aquí por estar protegida por derechos de autor.',

                ...$this->limitacionesBase($version, $filas),
            ],
            historial: $this->historialDe($documento),
            textos: $this->textosDe($documento),
        );
    }

    /** @param  array<int, list<Correspondencia>>  $correspondencias */
    protected function fila(Implantacion $implantacion, array $correspondencias): FilaRequisito
    {
        $requisito = $implantacion->requisito;

        return new FilaRequisito(
            grupo: $this->grupo($implantacion),
            codigo: $requisito->codigo,
            titulo: $requisito->titulo,
            aplica: $implantacion->aplica,
            estado: $implantacion->estado->value,
            estadoEtiqueta: $implantacion->estado->etiqueta(),
            estadoTono: $implantacion->estado->value,
            justificacion: $implantacion->justificacion,
            justificacionInclusion: $this->justificacionInclusion($implantacion, $correspondencias),
            madurez: $implantacion->nivel_madurez?->etiqueta(),
            madurezValor: $implantacion->nivel_madurez?->valor(),
            responsable: $implantacion->responsable?->name,
            evidencias: $this->evidenciasDe($implantacion),
            correspondencias: $this->codigosCorrespondientes($implantacion, $correspondencias),
        );
    }

    /** Los cuatro temas del Anexo A: A.5 organizativos, A.6 personas, A.7 físicos, A.8 tecnológicos. */
    protected function grupoRaiz(): string
    {
        return 'Anexo A';
    }

    /**
     * Por qué este control está dentro, dicho sin mentir.
     *
     * El mapeo cruzado tapa parte del hueco del análisis de riesgos, y no como
     * apaño: que el ENS exija la misma medida es un **requisito legal**, y un
     * requisito legal es una justificación de inclusión perfectamente legítima
     * para ISO. Es además el argumento de venta del producto puesto por escrito
     * en el entregable.
     *
     * @param  array<int, list<Correspondencia>>  $correspondencias
     */
    private function justificacionInclusion(Implantacion $implantacion, array $correspondencias): ?string
    {
        if (! $implantacion->aplica) {
            return null;
        }

        /*
         * Telegráfico, y la frase larga una sola vez en la introducción de la
         * sección. Repetir «Pertenece al Anexo A de ISO/IEC 27001:2022» en las
         * noventa y tres filas ensancha la columna, empuja al resto y esconde lo
         * único que distingue unas filas de otras, que es lo de después.
         */
        $motivos = ['Anexo A'];

        $exigidasPorEns = [];

        foreach ($correspondencias[$implantacion->requisito_id] ?? [] as $correspondencia) {
            foreach ($correspondencia->implantaciones as $otra) {
                if ($otra->aplica) {
                    $exigidasPorEns[$correspondencia->codigo] = true;
                }
            }
        }

        // Que el ENS exija la misma medida es un requisito LEGAL, y un requisito
        // legal es justificación de inclusión perfectamente válida para ISO.
        if ($exigidasPorEns !== []) {
            $motivos[] = 'exigido por el ENS ('.implode(', ', array_keys($exigidasPorEns)).')';
        }

        /*
         * La justificación que ISO espera de verdad. El control está dentro
         * porque trata un riesgo identificado, y el vínculo ya existe: es la
         * salvaguarda del § 4.3, registrada contra la implantación y no contra
         * el requisito, que es lo que hace que un mismo control valga a la vez
         * de prueba de cumplimiento y de tratamiento sin apuntarlo dos veces.
         */
        if ($implantacion->riesgos->isNotEmpty()) {
            $motivos[] = 'tratamiento del riesgo '.$implantacion->riesgos->pluck('codigo')->implode(', ');
        }

        if ($implantacion->origen_exigencia === OrigenExigencia::Perfil) {
            $motivos[] = 'perfil de cumplimiento del sistema';
        }

        if ($implantacion->notas !== null && trim($implantacion->notas) !== '') {
            $motivos[] = 'decisión motivada: '.trim($implantacion->notas);
        }

        return implode(' · ', $motivos);
    }
}
