<?php

declare(strict_types=1);

namespace App\Domain\Documento\Narrativa;

use App\Domain\Documento\Enums\SeccionNarrativa;
use App\Domain\Documento\Enums\TipoDocumento;

/**
 * El texto con el que Statera viene de fábrica.
 *
 * Es el último eslabón del resolutor: documento → plantilla de la organización →
 * **esto**. Una organización que no ha tocado nada entrega un documento con
 * estos textos, y por eso tienen que estar bien escritos: son lo primero que lee
 * un auditor.
 *
 * **Cuatro de estos textos estaban clavados en las plantillas Blade** y nadie
 * podía cambiarlos sin tocar código. Están transcritos a Markdown carácter a
 * carácter, con `**negrita**` donde había `<strong>`, de modo que un documento
 * generado antes y después de esta entrega dice exactamente lo mismo. Hay un
 * test que lo fija; si alguien mejora la redacción, ese test se pone rojo y hay
 * que actualizarlo a conciencia, que es justo lo que se quiere.
 *
 * Lo que devuelve cadena vacía es un hueco que **no se imprime** mientras nadie
 * escriba en él: una sección con título y nada debajo se lee como un documento
 * roto.
 */
final class TextosDeFabrica
{
    /**
     * El texto de fábrica de un hueco, o cadena vacía si viene sin nada.
     */
    public static function para(TipoDocumento $tipo, SeccionNarrativa $seccion): string
    {
        if (! $seccion->aplicaA($tipo)) {
            return '';
        }

        return match ($seccion) {
            SeccionNarrativa::Introduccion => self::introduccion($tipo),
            SeccionNarrativa::ObjetoYAlcance => self::objetoYAlcance(),
            SeccionNarrativa::Metodologia => self::metodologia($tipo),
            SeccionNarrativa::NotaTabla => self::notaTabla($tipo),
            SeccionNarrativa::NotaDerivacion => self::notaDerivacion(),
            SeccionNarrativa::NotaMadurez => self::notaMadurez(),

            // Vacíos de partida: son huecos que existen para quien los quiera,
            // no texto que Statera tenga nada que decir en nombre de nadie.
            SeccionNarrativa::NotaResumen,
            SeccionNarrativa::NotaExclusiones,
            SeccionNarrativa::Conclusiones,
            SeccionNarrativa::LimitacionesPropias,
            SeccionNarrativa::Aprobacion => '',
        };
    }

    /**
     * Todos los huecos de un tipo de documento, indexados por su clave.
     *
     * @return array<string, string>
     */
    public static function todos(TipoDocumento $tipo): array
    {
        $textos = [];

        foreach (SeccionNarrativa::paraTipo($tipo) as $seccion) {
            $textos[$seccion->value] = self::para($tipo, $seccion);
        }

        return $textos;
    }

    private static function introduccion(TipoDocumento $tipo): string
    {
        return match ($tipo) {
            TipoDocumento::SoaIso => <<<'MD'
                Este documento es la Declaración de Aplicabilidad del sistema de gestión de la seguridad de la información, exigida por la cláusula 6.1.3 d) de ISO/IEC 27001:2022. Recoge, para cada uno de los controles del Anexo A, si la organización lo considera aplicable, por qué, en qué estado de implantación se encuentra y qué evidencia lo sostiene.

                Se genera desde el registro de implantaciones y refleja la situación en la fecha de extracción que figura al final del documento.
                MD,

            TipoDocumento::DdaEns => <<<'MD'
                Este documento es la Declaración de Aplicabilidad del sistema, exigida por el Esquema Nacional de Seguridad (Real Decreto 311/2022). Recoge las medidas del Anexo II que le son exigibles según su categoría, de dónde sale esa exigencia, en qué estado de implantación se encuentran y qué evidencia las sostiene.

                La categoría no se elige: se deriva de valorar el perjuicio en las cinco dimensiones de seguridad, y esa derivación se imprime entera en el apartado siguiente.
                MD,
        };
    }

    private static function objetoYAlcance(): string
    {
        return <<<'MD'
            El objeto de este documento es dejar constancia, ante la dirección y ante un auditor, de qué requisitos le son exigibles al sistema y en qué situación está cada uno.

            El alcance es el del sistema identificado en la portada, con el alcance declarado y las exclusiones que allí figuran. Los activos, las evidencias y las tareas que lo sostienen se gestionan en la herramienta y no se reproducen aquí.
            MD;
    }

    private static function metodologia(TipoDocumento $tipo): string
    {
        $derivacion = match ($tipo) {
            TipoDocumento::SoaIso => 'Los controles del Anexo A se consideran aplicables de partida. Excluir uno exige registrar el motivo, y esa justificación se imprime en el apartado de controles excluidos: sin motivo, la herramienta no permite la exclusión.',
            TipoDocumento::DdaEns => 'El conjunto de medidas exigibles se deriva de la categoría del sistema, que a su vez es el máximo de los niveles asignados a las cinco dimensiones de seguridad. Ninguna medida se marca a mano.',
        };

        return $derivacion."\n\n"
            .'El estado de implantación y el nivel de madurez los mantiene la persona responsable de cada requisito, y cada cambio queda registrado con su fecha y su autor.';
    }

    /**
     * Migrado literal de `soa-iso.blade.php` y `dda-ens.blade.php`.
     */
    private static function notaTabla(TipoDocumento $tipo): string
    {
        return match ($tipo) {
            TipoDocumento::SoaIso => <<<'MD'
                Un control por fila, agrupados por los cuatro temas de ISO/IEC 27001:2022. La columna «Correspondencia ENS» indica qué medidas del Real Decreto 311/2022 cubren lo mismo: la misma prueba vale para los dos marcos.

                **Cómo leer «Origen de la inclusión».** Todo control del Anexo A se incluye de partida, y por eso todas las filas aplicables empiezan por «Anexo A»; lo que se justifica es la **exclusión**, no la inclusión. A continuación se añade, cuando existe, la exigencia **legal** que obliga además a ese control: la medida del ENS que le corresponde y que el sistema del Esquema tiene como exigible.
                MD,

            TipoDocumento::DdaEns => <<<'MD'
                Una medida por fila, agrupadas por el nodo del que cuelgan. «Origen de la exigencia» dice de dónde sale lo que se exige: de la categoría del sistema, de modular por el nivel de una dimensión concreta, de un perfil de cumplimiento o del propio catálogo.

                **Los refuerzos del Anexo II se acumulan**: «Refuerzo 2» significa «hasta el refuerzo 2», es decir, R1 y R2, no sólo R2.
                MD,
        };
    }

    /** Migrado literal de `parciales/derivacion.blade.php`. */
    private static function notaDerivacion(): string
    {
        return 'La categoría es el máximo de las cinco dimensiones, y de ella se deriva el conjunto de '
            .'medidas exigibles del Anexo II. Ninguna medida se marca a mano: cambiar una valoración '
            .'recalcula el conjunto y queda registrado en el histórico de cada implantación.';
    }

    /** Migrado literal de `parciales/anexo-ii.blade.php`. */
    private static function notaMadurez(): string
    {
        return 'La escala L0–L5 del CCN, que es la que se reporta en INES. Cada media va con el número '
            .'de medidas sobre el que se calcula: una media sobre cuatro medidas de setenta y tres no '
            .'dice lo mismo que sobre las setenta y tres.';
    }
}
