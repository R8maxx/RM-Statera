<?php

declare(strict_types=1);

namespace App\Domain\Autorizacion\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Los permisos de la aplicación, por módulo y verbo.
 *
 * Dos verbos y no cinco: `ver` y `gestionar`. Partir la escritura en crear,
 * editar y borrar suena más fino y en la práctica nadie sabe a quién darle sólo
 * «editar», así que se acaba dando todo. Lo que sí se separa es lo que tiene
 * consecuencias distintas.
 *
 * `sistemas.valorar` está aparte de `sistemas.gestionar` a propósito: cambiar el
 * nombre de un sistema no le cambia lo que se le exige, y valorar sus cinco
 * dimensiones sí. Es la decisión que redefine el alcance del cumplimiento
 * entero, y no es la misma persona quien la toma.
 *
 * `documentos.generar` cubre crear el registro, preparar el borrador y mandarlo a
 * revisión, que es todo el trabajo de hacer un documento. Lo que ya **no** cubre
 * es entregarlo: desde el § 4.5, quien firma es quien emite.
 *
 * `documentos.aprobar` es el cuarto verbo del producto, junto a
 * `sistemas.valorar` y `riesgos.aceptar`, y está por el mismo motivo que ellos:
 * aprobar un documento es la dirección declarando que asume lo que dice, y ésa
 * es la razón entera por la que ISO pide la aprobación. Un técnico que prepara la
 * política no debe poder firmarla — y como aprobar es lo que numera y congela el
 * PDF, este permiso es también el que decide quién entrega al auditor.
 *
 * `contexto.aprobar` es el **sexto** verbo de esa familia, y el más obvio de
 * todos: aprobar el análisis del contexto es la dirección declarando cuál es la
 * situación de la organización, y de ahí cuelgan el alcance del SGSI y la entrada
 * de «cambios de contexto» que pide la cláusula 9.3. Registrar y describir una
 * debilidad es trabajo operativo y lo hace el técnico; firmar que ése es el
 * contexto —y congelarlo, porque aprobar es lo que congela— no lo es. Misma línea
 * que separa valorar un riesgo de aceptarlo.
 *
 * `objetivos.aprobar` es el **séptimo** verbo de esa familia, y el que cierra el
 * ciclo que abrió el § 4.14: el módulo de indicadores se quedó a propósito con dos
 * verbos porque una medición es un dato que se toma, no una decisión que se firma.
 * Un objetivo sí se firma —es a lo que la organización se obliga— y sin este verbo
 * quien apunta la cifra sería también quien se compromete con ella.
 *
 * `no_conformidades.verificar` es el **quinto** verbo de esa familia, y el que la
 * explica mejor: comprobar que una acción correctiva funcionó no puede hacerlo
 * quien la ejecutó. Es la cláusula 10.2 e) entera —«revisar la eficacia»—, y sin
 * separarlo, cerrar el tratamiento y declarar que sirvió serían el mismo gesto
 * hecho por la misma persona, que es exactamente lo que el auditor comprueba.
 *
 * Y sí, `no_conformidades.*` rompe el patrón de una sola palabra que llevan los
 * otros nueve módulos. Se queda así porque casa con la tabla —`no_conformidades`—
 * y con la ruta —`/no-conformidades`—, y el dominio se nombra en español: tres
 * nombres distintos para la misma cosa cuesta más que un guion bajo de más.
 *
 * Acusar la lectura NO lleva permiso propio, como no lo lleva `/perfil`: se
 * escribe sobre uno mismo y no redefine nada de la organización. Basta con poder
 * ver el documento.
 *
 * `riesgos.aceptar` es el tercer verbo del producto, y está por el mismo motivo
 * que `sistemas.valorar`: aceptar un riesgo es la organización declarando que
 * conoce una exposición y decide convivir con ella, e ISO 27001 6.1.3 f) exige
 * que lo apruebe el propietario del riesgo. Un técnico que registra y puntúa
 * riesgos no debe poder firmar uno — eso no es un matiz de permisos, es la razón
 * entera por la que la norma pide la aprobación. Cubre también definir la
 * metodología, que es la otra decisión que toma la dirección: fijar el apetito de
 * riesgo es decidir de antemano qué se va a poder aceptar.
 *
 * Y `documentos.redactar` está separado de `documentos.plantillas` por lo mismo:
 * retocar la introducción de UN documento y redefinir el texto base de la
 * organización no son la misma decisión. Lo segundo afecta a todos los
 * documentos que se creen a partir de entonces, que es la misma clase de
 * consecuencia que tiene `sistemas.valorar`.
 *
 * OJO: esto NO son los roles ENS de personas —responsable de la información,
 * del servicio, de seguridad, del sistema y administrador de la seguridad—, que
 * son datos del módulo de personas (§ 4.8) y tienen sus propias
 * incompatibilidades. Aquí se decide quién puede tocar qué en la herramienta.
 */
#[TypeScript]
enum Permiso: string
{
    case PanelVer = 'panel.ver';

    case ContextoVer = 'contexto.ver';
    case ContextoGestionar = 'contexto.gestionar';
    case ContextoAprobar = 'contexto.aprobar';

    case SistemasVer = 'sistemas.ver';
    case SistemasGestionar = 'sistemas.gestionar';
    case SistemasValorar = 'sistemas.valorar';

    case ImplantacionesVer = 'implantaciones.ver';
    case ImplantacionesGestionar = 'implantaciones.gestionar';

    case EvidenciasVer = 'evidencias.ver';
    case EvidenciasGestionar = 'evidencias.gestionar';

    case ActivosVer = 'activos.ver';
    case ActivosGestionar = 'activos.gestionar';

    case RiesgosVer = 'riesgos.ver';
    case RiesgosGestionar = 'riesgos.gestionar';
    case RiesgosAceptar = 'riesgos.aceptar';

    case TareasVer = 'tareas.ver';
    case TareasGestionar = 'tareas.gestionar';

    case AuditoriasVer = 'auditorias.ver';
    case AuditoriasGestionar = 'auditorias.gestionar';

    case NoConformidadesVer = 'no_conformidades.ver';
    case NoConformidadesGestionar = 'no_conformidades.gestionar';
    case NoConformidadesVerificar = 'no_conformidades.verificar';

    /*
     * Las oportunidades de mejora de la cláusula 10.1. **Dos verbos y no tres, y
     * es lo que las separa de las no conformidades de al lado**: una mejora no
     * la firma nadie. No hay eficacia que verificar porque no había nada roto, y
     * no hay compromiso que aprobar porque nadie se obligó a ella — cuando una
     * mejora se convierte en un compromiso, lo que nace es un objetivo de la 6.2,
     * que sí tiene su verbo.
     */
    case MejorasVer = 'mejoras.ver';
    case MejorasGestionar = 'mejoras.gestionar';

    /*
     * El seguimiento y la medición de la cláusula 9.1. Dos verbos y no tres:
     * aquí no hay nada que firmar —una medición es un dato, no una decisión—, y
     * el verbo de supervisión de este ciclo es `objetivos.aprobar`, que llegó
     * con la 6.2 justo debajo.
     */
    case IndicadoresVer = 'indicadores.ver';
    case IndicadoresGestionar = 'indicadores.gestionar';

    /*
     * Los objetivos de seguridad de la cláusula 6.2, y **el séptimo verbo de
     * supervisión** del producto. Medir es un dato y comprometerse a una cifra
     * es una decisión: aprobar un objetivo es la dirección declarando a qué se
     * obliga este año, con su plazo y sus recursos. Quien lo redacta es quien
     * está en el día a día; quien lo firma, no — misma línea que separa valorar
     * un riesgo de aceptarlo y redactar un documento de emitirlo.
     */
    case ObjetivosVer = 'objetivos.ver';
    case ObjetivosGestionar = 'objetivos.gestionar';
    case ObjetivosAprobar = 'objetivos.aprobar';

    /*
     * La revisión por la dirección (cláusula 9.3), y **el octavo verbo de
     * supervisión**. Es el más literal de todos: la cláusula se llama «revisión
     * por la dirección», así que aprobar el acta no es que convenga que lo haga la
     * dirección, es que la norma no admite otra cosa. Preparar la reunión, recoger
     * las entradas y redactar las conclusiones es trabajo de quien lleva el SGSI;
     * firmar que la dirección lo ha revisado, no.
     */
    case RevisionDireccionVer = 'revision_direccion.ver';
    case RevisionDireccionGestionar = 'revision_direccion.gestionar';
    case RevisionDireccionAprobar = 'revision_direccion.aprobar';

    case DocumentosVer = 'documentos.ver';
    case DocumentosGenerar = 'documentos.generar';
    case DocumentosAprobar = 'documentos.aprobar';
    case DocumentosRedactar = 'documentos.redactar';
    case DocumentosPlantillas = 'documentos.plantillas';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PanelVer => 'Ver el panel',
            self::ContextoVer => 'Ver el contexto y las partes interesadas',
            self::ContextoGestionar => 'Registrar cuestiones, partes interesadas y sus requisitos',
            self::ContextoAprobar => 'Aprobar el análisis del contexto',
            self::SistemasVer => 'Ver los sistemas',
            self::SistemasGestionar => 'Dar de alta y editar sistemas',
            self::SistemasValorar => 'Valorar dimensiones y recalcular',
            self::ImplantacionesVer => 'Ver las implantaciones',
            self::ImplantacionesGestionar => 'Gestionar implantaciones',
            self::EvidenciasVer => 'Ver las evidencias',
            self::EvidenciasGestionar => 'Registrar y vincular evidencias',
            self::ActivosVer => 'Ver el inventario de activos',
            self::ActivosGestionar => 'Dar de alta activos y declarar dependencias',
            self::RiesgosVer => 'Ver el análisis de riesgos',
            self::RiesgosGestionar => 'Registrar riesgos, valorarlos y vincular salvaguardas',
            self::RiesgosAceptar => 'Aceptar riesgos y definir la metodología',
            self::TareasVer => 'Ver el plan de acción',
            self::TareasGestionar => 'Crear tareas, asignarlas y moverlas de estado',
            self::AuditoriasVer => 'Ver las auditorías, su checklist y sus hallazgos',
            self::AuditoriasGestionar => 'Registrar auditorías, revisar la checklist y cerrarlas',
            self::NoConformidadesVer => 'Ver las no conformidades y su tratamiento',
            self::NoConformidadesGestionar => 'Abrir no conformidades, analizarlas y vincular acciones correctivas',
            self::NoConformidadesVerificar => 'Verificar la eficacia de una acción correctiva',
            self::MejorasVer => 'Ver el registro de oportunidades de mejora',
            self::MejorasGestionar => 'Registrar mejoras, planificarlas y descartarlas con su motivo',
            self::IndicadoresVer => 'Ver los indicadores y su serie histórica',
            self::IndicadoresGestionar => 'Definir indicadores y registrar mediciones',
            self::ObjetivosVer => 'Ver los objetivos de seguridad y su avance',
            self::ObjetivosGestionar => 'Proponer objetivos, planificarlos y vincular indicadores y actuaciones',
            self::ObjetivosAprobar => 'Aprobar objetivos y declarar si se alcanzaron',
            self::RevisionDireccionVer => 'Ver las revisiones por la dirección y sus actas',
            self::RevisionDireccionGestionar => 'Convocar revisiones, recoger las entradas y registrar las decisiones',
            self::RevisionDireccionAprobar => 'Aprobar el acta de una revisión por la dirección',
            self::DocumentosVer => 'Ver los documentos y descargar sus versiones',
            self::DocumentosGenerar => 'Crear documentos, generar borradores y mandarlos a revisión',
            self::DocumentosAprobar => 'Aprobar documentos y entregar la versión firmada',
            self::DocumentosRedactar => 'Redactar los textos de un documento',
            self::DocumentosPlantillas => 'Definir los textos base de la organización',
        };
    }

    /** Si el permiso escribe. Lo usa la exigencia de segundo factor. */
    public function esDeEscritura(): bool
    {
        return ! str_ends_with($this->value, '.ver');
    }

    /** @return list<self> */
    public static function deEscritura(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $permiso): bool => $permiso->esDeEscritura(),
        ));
    }
}
