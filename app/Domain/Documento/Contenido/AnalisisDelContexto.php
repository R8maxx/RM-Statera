<?php

declare(strict_types=1);

namespace App\Domain\Documento\Contenido;

use App\Domain\Contexto\AnalisisEnCurso;
use App\Domain\Contexto\Enums\TipoCuestion;
use App\Domain\Contexto\Models\AnalisisContexto;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Excepciones\DocumentoNoGenerable;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Documento\Narrativa\MarkdownDocumento;
use App\Domain\Documento\Narrativa\ResolverNarrativa;

/**
 * El análisis del contexto de la organización: § 4.1 y cláusulas 4.1 a 4.3.
 *
 * **Es el cuarto documento calculado y el primero de ámbito organizativo.** Sale
 * de una consulta —no lo redacta nadie— y aun así no cuelga de un sistema: las
 * cuestiones internas y externas y las partes interesadas son de la organización
 * entera. Hasta aquí «calculado» y «exige sistema» eran lo mismo, y romper esa
 * coincidencia es lo que obligó a `TipoDocumento::exigeSistema()` y a rehacer
 * `documentos_sistema_check` por tercera vez.
 *
 * **Implementa `GeneradorDocumento` directamente y no hereda de
 * `DocumentoCalculado`**, igual que `DocumentoRedactado`: aquella clase es la
 * tubería de la tabla larga de requisitos —la consulta por tipo, el agrupado por
 * el nodo padre, las correspondencias cruzadas— y aquí no hay ninguna de las tres
 * cosas. Lo que sí comparte con todas —portada, historial y limitaciones— está en
 * `ArmaContenidoComun`, que es donde tiene que estar para que dos documentos no
 * declaren limitaciones distintas.
 *
 * ### Se construye desde la instantánea, nunca de una consulta nueva
 *
 * Es el fallo más caro que este módulo podía tener, y ya está documentado para el
 * `.docx`: las cuestiones y las partes **viven y se editan** —tienen que hacerlo,
 * o los vínculos a riesgos e implantaciones apuntarían a filas muertas—, así que
 * un documento que consultara las tablas enseñaría el DAFO de hoy bajo la fecha de
 * la aprobación de hace un año. Lo que se imprime es lo que se congeló ese día.
 *
 * Sin análisis aprobado no hay documento, y se dice: generar uno desde el borrador
 * sería entregar un contexto que nadie ha firmado.
 */
final class AnalisisDelContexto implements GeneradorDocumento
{
    use Concerns\ArmaContenidoComun;

    public function __construct(
        private readonly AnalisisEnCurso $analisis,
        private readonly ResolverNarrativa $narrativa,
        private readonly MarkdownDocumento $markdown,
    ) {}

    public function tipo(): TipoDocumento
    {
        return TipoDocumento::AnalisisContexto;
    }

    /**
     * @param  array<string, mixed>  $parametros
     */
    public function construir(Documento $documento, DocumentoVersion $version, array $parametros = []): ContenidoDocumento
    {
        $analisis = $this->analisis->vigente();

        if (! $analisis instanceof AnalisisContexto) {
            throw DocumentoNoGenerable::sinContextoAprobado();
        }

        $instantanea = $analisis->instantanea ?? [];

        return new ContenidoDocumento(
            titulo: $documento->titulo,
            subtitulo: $analisis->etiqueta().' · '.$analisis->fecha_analisis->format('d/m/Y'),

            portada: [
                ...$this->portadaBase($documento, $version),
                /*
                 * El alcance de la portada se queda nulo a propósito. Un documento
                 * de ámbito organizativo no tiene un alcance que imprimir en la
                 * cabecera, y los alcances de los sistemas —que son varios— tienen
                 * su propio apartado. Poner ahí el de uno cualquiera sería decir
                 * que el contexto es de ese sistema.
                 */
                'alcance' => null,
            ],

            // Sin cifras de implantación y sin filas de requisitos: este documento
            // no cuenta medidas. Lo que cuenta va en `extras`, que es de donde lo
            // leen sus cuatro bloques.
            resumen: [],
            filas: [],

            limitaciones: [
                ...$this->limitacionesPropias(),
                ...$this->limitacionesBase($version),
            ],
            historial: $this->historialDe($documento),
            extras: $this->extras($analisis, $instantanea),
            textos: TextosDocumento::desdeMarkdown(
                $this->narrativa->paraDocumento($documento),
                $this->markdown,
            ),
        );
    }

    /**
     * Lo que este documento en concreto no puede afirmar.
     *
     * Van **antes** de las comunes, como en las declaraciones: lo específico
     * primero, porque es lo que el auditor no se sabe de memoria.
     *
     * @return list<string>
     */
    private function limitacionesPropias(): array
    {
        return [
            'Este documento recoge el análisis del contexto **tal como se aprobó**, no como está hoy '
            .'en la herramienta. Las cuestiones y las partes interesadas se siguen editando entre '
            .'revisiones; lo que aquí figura quedó congelado el día de la aprobación, y los cambios '
            .'posteriores los recogerá la revisión siguiente.',

            'Statera **no comprueba que el análisis esté completo**: no verifica que toda parte '
            .'interesada relevante esté registrada, ni que las cuestiones cubran todos los ámbitos '
            .'pertinentes, ni que cada amenaza identificada acabe en un riesgo. Lo que sí señala es lo '
            .'que falta por atar, y esa cifra está en el panel, no en este documento.',

            'El alcance declarado de cada sistema y sus exclusiones **se copian de su ficha**, y '
            .'Statera no comprueba que sean coherentes entre sí ni que entre todos cubran la actividad '
            .'de la organización.',
        ];
    }

    /**
     * Lo que leen los cuatro bloques calculados del cuerpo.
     *
     * **Sale de la instantánea y no de las tablas.** La única excepción es el
     * orden y los rótulos de los cuadrantes, que los declara `TipoCuestion` y no
     * son un dato de la organización: una instantánea de hace tres años que
     * guardara la palabra «Fortalezas» seguiría diciendo lo mismo, pero el orden
     * de lectura de la matriz es del producto.
     *
     * @param  array<string, mixed>  $instantanea
     * @return array<string, mixed>
     */
    private function extras(AnalisisContexto $analisis, array $instantanea): array
    {
        $dafo = $instantanea['dafo'] ?? [];
        $dafo = is_array($dafo) ? $dafo : [];

        $cuadrantes = [];

        foreach (TipoCuestion::enOrdenDeMatriz() as $tipo) {
            $cuestiones = $dafo[$tipo->value] ?? [];

            $cuadrantes[] = [
                'tipo' => $tipo->value,
                'etiqueta' => $tipo->etiqueta().'s',
                'ambito' => $tipo->ambito()->etiqueta(),
                'signo' => $tipo->signo()->etiqueta(),
                'cuestiones' => is_array($cuestiones) ? array_values($cuestiones) : [],
            ];
        }

        $clima = $instantanea['clima'] ?? [];
        $partes = $instantanea['partes'] ?? [];
        $alcance = $instantanea['alcance'] ?? [];

        return [
            'analisis' => [
                'numero' => $analisis->numero,
                'etiqueta' => $analisis->etiqueta(),
                'fecha' => $analisis->fecha_analisis->format('d/m/Y'),
                'nota' => $analisis->nota,
            ],
            'dafo' => $cuadrantes,
            'clima' => is_array($clima) ? $clima : [],
            'partes' => is_array($partes) ? array_values($partes) : [],
            'alcance' => is_array($alcance) ? array_values($alcance) : [],
        ];
    }
}
