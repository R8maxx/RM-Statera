<?php

declare(strict_types=1);

namespace App\Domain\Documento\Contenido;

use App\Domain\Activo\ResumenInventario;
use App\Domain\Auditoria\RegistroAuditorias;
use App\Domain\Continuidad\RegistroContinuidad;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Documento\Narrativa\MarkdownDocumento;
use App\Domain\Documento\Narrativa\ResolverNarrativa;
use App\Domain\Documento\ResumenDocumental;
use App\Domain\Implantacion\ResumenCumplimiento;
use App\Domain\Incidente\RegistroIncidentes;
use App\Domain\Mejora\RegistroMejoras;
use App\Domain\Metrica\RegistroIndicadores;
use App\Domain\NoConformidad\RegistroNoConformidades;
use App\Domain\Objetivo\RegistroObjetivos;
use App\Domain\Obligacion\RegistroObligaciones;
use App\Domain\Persona\RegistroFormacion;
use App\Domain\Persona\RegistroPersonas;
use App\Domain\Proveedor\RegistroProveedores;
use App\Domain\Riesgo\RegistroRiesgos;
use App\Domain\Tarea\ResumenPlanDeAccion;
use App\Http\Resources\Panel\AvanceMarco;
use App\Http\Resources\Panel\Indicador;
use App\Http\Resources\Panel\SegmentoEstado;
use App\Http\Resources\Panel\SistemaResumido;

/**
 * El informe de estado de la seguridad: § 4.18, el octavo documento calculado.
 *
 * **Ninguna cifra se calcula aquí.** Cinco clases del dominio llevaban escrito en
 * su cabecera que sus preguntas «son las que contestará el informe de estado», y
 * este generador es el que cumple esa promesa: pide a cada una lo mismo que le
 * pide el panel y lo imprime. Con una consulta propia, el informe entregado a la
 * dirección diría 40 de 52 y el panel, abierto al lado, otra cosa.
 *
 * **Es de la organización entera**, como el acta: cuenta todos sus sistemas y
 * todos sus marcos. Y a diferencia del acta no tiene una fuente congelada detrás
 * —no hay una fila aprobada que imprimir—: lo que se congela es la
 * `instantanea` de cada generación, igual que en la SoA. Por eso la fecha de
 * extracción es la fecha del informe, y la limitación lo dice.
 *
 * **Todos los módulos, sin mirar permisos.** El panel esconde cada tarjeta a quien
 * no tiene el `.ver` de su módulo porque es una pantalla que mira cualquiera; este
 * es un documento que prepara quien tiene `documentos.generar` para la dirección
 * y el auditor, y un informe de estado que se salta los incidentes según quién
 * pulsó «Generar» no es un informe de estado. Lo que imprime son **recuentos**,
 * nunca registros.
 */
final class InformeEstadoSeguridad implements GeneradorDocumento
{
    use Concerns\ArmaContenidoComun;

    public function __construct(
        private readonly ResolverNarrativa $narrativa,
        private readonly MarkdownDocumento $markdown,
        private readonly ResumenCumplimiento $cumplimiento,
        private readonly ResumenPlanDeAccion $plan,
        private readonly ResumenInventario $inventario,
        private readonly RegistroRiesgos $riesgos,
        private readonly RegistroNoConformidades $noConformidades,
        private readonly RegistroIncidentes $incidentes,
        private readonly RegistroAuditorias $auditorias,
        private readonly RegistroMejoras $mejoras,
        private readonly RegistroIndicadores $indicadores,
        private readonly RegistroObjetivos $objetivos,
        private readonly RegistroObligaciones $obligaciones,
        private readonly ResumenDocumental $documental,
        private readonly RegistroPersonas $personas,
        private readonly RegistroFormacion $formacion,
        private readonly RegistroContinuidad $continuidad,
        private readonly RegistroProveedores $proveedores,
    ) {}

    public function tipo(): TipoDocumento
    {
        return TipoDocumento::InformeEstado;
    }

    /**
     * @param  array<string, mixed>  $parametros
     */
    public function construir(Documento $documento, DocumentoVersion $version, array $parametros = []): ContenidoDocumento
    {
        $madurez = $this->cumplimiento->madurez();
        $evidencias = $this->cumplimiento->evidencias();

        return new ContenidoDocumento(
            titulo: $documento->titulo,
            subtitulo: 'Situación del sistema de gestión en la fecha de extracción',

            portada: $this->portadaBase($documento, $version),

            // Sin filas de requisitos: lo que se cuenta son cifras de todo el
            // sistema de gestión, y van en `extras`.
            resumen: [],
            filas: [],

            limitaciones: [
                ...$this->limitacionesPropias(),
                ...$this->limitacionesBase($version),
            ],
            historial: $this->historialDe($documento),
            extras: [
                'cumplimiento' => [
                    'sistemas' => array_map(
                        static fn (SistemaResumido $sistema): array => [
                            'codigo' => $sistema->codigo,
                            'nombre' => $sistema->nombre,
                            'marco' => $sistema->marco,
                            'categoria' => $sistema->categoria,
                            'aplicables' => $sistema->aplicables,
                            'implantadas' => $sistema->implantadas,
                        ],
                        $this->cumplimiento->porSistema(),
                    ),
                    'marcos' => array_map(
                        static fn (AvanceMarco $marco): array => [
                            'nombre' => $marco->nombre,
                            'aplicables' => $marco->aplicables,
                            'implantadas' => $marco->implantadas,
                        ],
                        $this->cumplimiento->porMarco(),
                    ),
                    'estados' => array_map(
                        static fn (SegmentoEstado $segmento): array => [
                            'clave' => $segmento->clave,
                            'etiqueta' => $segmento->etiqueta,
                            'valor' => $segmento->valor,
                        ],
                        $this->cumplimiento->porEstado(),
                    ),
                    'pendientes' => $this->cumplimiento->pendientes(),
                    'madurezMedia' => $madurez['media'],
                    'madurezEvaluadas' => $madurez['evaluadas'],
                    'evidencias' => $evidencias['total'],
                    'evidenciasCaducadas' => $evidencias['caducadas'],
                    'evidenciasPorCaducar' => $evidencias['porCaducar'],
                    'implantadasSinEvidencia' => $this->cumplimiento->implantadasSinEvidencia(),
                ],
                'registros' => $this->registros(),
            ],
            textos: TextosDocumento::desdeMarkdown(
                $this->narrativa->paraDocumento($documento),
                $this->markdown,
            ),
        );
    }

    /**
     * Los registros del sistema de gestión, cada uno con sus cifras.
     *
     * **En el orden en que se recorre el ciclo**, que es el del panel: lo que se
     * tiene, lo que puede ir mal, lo que ha ido mal, cómo se comprueba y se mejora,
     * y lo que lo sostiene. Cada registro trae lo que va mal —`alertas()`— y lo
     * que está a medias —`pendientes()`—, y el total cuando existe: una cifra sin
     * el total del que sale no dice nada.
     *
     * @return list<array{titulo: string, total: ?int, indicadores: list<array{etiqueta: string, valor: int, ayuda: ?string, alerta: bool}>}>
     */
    private function registros(): array
    {
        return [
            $this->registro('Inventario de activos', $this->inventario->vigentes(), $this->inventario->alertas(), [], $this->inventario->pendientesDeCompletar()),
            $this->registro('Riesgos', $this->riesgos->total(), $this->riesgos->alertas(), $this->riesgos->pendientes()),
            $this->registro('Plan de acción', null, $this->plan->alertas(), [], $this->plan->pendientesDeCompletar()),
            $this->registro('Incidentes', $this->incidentes->total(), $this->incidentes->alertas(), $this->incidentes->pendientes()),
            $this->registro('No conformidades', $this->noConformidades->total(), $this->noConformidades->alertas(), $this->noConformidades->pendientes()),
            $this->registro('Auditorías', $this->auditorias->total(), $this->auditorias->alertas(), $this->auditorias->pendientes()),
            $this->registro('Oportunidades de mejora', $this->mejoras->total(), [], $this->mejoras->pendientes()),
            $this->registro('Indicadores', $this->indicadores->total(), $this->indicadores->alertas(), $this->indicadores->pendientes()),
            $this->registro('Objetivos de seguridad', $this->objetivos->total(), $this->objetivos->alertas(), $this->objetivos->pendientes()),
            $this->registro('Obligaciones periódicas', $this->obligaciones->total(), $this->obligaciones->alertas(), $this->obligaciones->pendientes()),
            $this->registro('Documentación', null, $this->documental->alertas(), []),
            $this->registro('Personas', $this->personas->total(), $this->personas->alertas(), $this->personas->pendientes()),
            $this->registro('Formación', $this->formacion->total(), [], $this->formacion->pendientes()),
            // En dos, como en `/continuidad`: «falta la aprobación» es del BIA y
            // «falta el resultado» es de una prueba, y juntos no se sabe de qué.
            $this->registro('Continuidad: análisis de impacto', null, $this->continuidad->alertasDeBia(), [], $this->continuidad->pendientesDeBia(), 'Falta '),
            $this->registro('Continuidad: pruebas', null, $this->continuidad->alertasDePruebas(), [], $this->continuidad->pendientesDePruebas(), 'Falta '),
            $this->registro('Proveedores', $this->proveedores->total(), $this->proveedores->alertas(), $this->proveedores->pendientes()),
        ];
    }

    /**
     * Un registro, con sus indicadores pasados a datos planos.
     *
     * `alerta` sale de dónde viene el indicador —de `alertas()`— y no de su tono:
     * el documento no pinta colores de estado, y lo que tiene que decir es qué
     * cifras piden acción.
     *
     * **Lo que falta por rellenar llega aparte**, con «Sin» o «Falta» delante:
     * el inventario, el plan y la continuidad lo etiquetan con el nombre de lo que
     * falta —«propietario», «la aprobación»— porque la interfaz lo pinta en una
     * línea que ya empieza por «faltan». En una tabla, «propietario · 5» no dice
     * nada.
     *
     * @param  list<Indicador>  $alertas
     * @param  list<Indicador>  $pendientes
     * @param  list<Indicador>  $sinCompletar
     * @return array{titulo: string, total: ?int, indicadores: list<array{etiqueta: string, valor: int, ayuda: ?string, alerta: bool}>}
     */
    private function registro(string $titulo, ?int $total, array $alertas, array $pendientes, array $sinCompletar = [], string $prefijo = 'Sin '): array
    {
        $plano = static fn (bool $alerta, string $prefijo = ''): callable => static fn (Indicador $indicador): array => [
            'etiqueta' => $prefijo.$indicador->etiqueta,
            'valor' => $indicador->valor,
            'ayuda' => $indicador->ayuda,
            'alerta' => $alerta,
        ];

        return [
            'titulo' => $titulo,
            'total' => $total,
            'indicadores' => [
                ...array_map($plano(true), $alertas),
                ...array_map($plano(false), $pendientes),
                ...array_map($plano(false, $prefijo), $sinCompletar),
            ],
        ];
    }

    /**
     * Lo que este informe no puede afirmar.
     *
     * @return list<string>
     */
    private function limitacionesPropias(): array
    {
        return [
            'Este informe **no es el Informe Nacional del Estado de Seguridad (INES)** que se presenta '
            .'anualmente al CCN. Es un informe interno de la situación del sistema de gestión; el INES '
            .'se cumplimenta en la plataforma del CCN, y Statera sólo recuerda cuándo toca presentarlo.',

            'Las cifras son las del registro **en la fecha de extracción** y no se congelan en ninguna '
            .'otra parte: generar el informe otro día da otras cifras. Lo que se conserva es cada versión '
            .'emitida, con su huella.',

            'El informe **no compara con el anterior**. Cada versión emitida guarda sus cifras, pero la '
            .'herramienta todavía no calcula la diferencia entre dos informes.',

            'Cada cifra es un **recuento del registro**: dice cuántas cosas constan, no si lo que consta es '
            .'correcto. Una medida figura implantada porque alguien la marcó así; la herramienta no lo '
            .'comprueba.',
        ];
    }
}
