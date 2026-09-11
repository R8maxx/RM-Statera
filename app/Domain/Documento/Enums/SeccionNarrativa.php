<?php

declare(strict_types=1);

namespace App\Domain\Documento\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Los huecos de texto que la organización redacta en un documento.
 *
 * **Catálogo cerrado, no secciones libres.** Tres motivos, y ninguno es comodidad:
 *
 * 1. Cada hueco tiene un sitio fijo en la plantilla Blade. Con secciones libres
 *    haría falta un ancla de colocación —«¿antes o después de la tabla?»— y eso
 *    es un constructor de documentos, no lo que pide el § 4.5.
 * 2. **La instantánea tiene que poder diffearse.** «¿Qué cambió entre la v3 y la
 *    v4?» exige que `introduccion` se siga llamando `introduccion` en las dos.
 *    Con secciones libres, renombrar una rompe el diff en silencio.
 * 3. PDF/UA exige una jerarquía de encabezados conocida. Con huecos fijos, el
 *    `<h2>` lo pone siempre la plantilla y lo del usuario baja dos niveles.
 *
 * Y lo que **no** está aquí no se puede escribir: de este enum salen a la vez el
 * formulario, las reglas del `FormRequest`, el `CHECK` de las dos tablas y las
 * claves que acepta el resolutor. No hay forma de nombrar un hueco que no exista.
 *
 * El texto de fábrica NO vive aquí: son párrafos largos que ensuciarían el
 * `match`. Vive en `App\Domain\Documento\Narrativa\TextosDeFabrica`.
 */
#[TypeScript]
enum SeccionNarrativa: string
{
    case Introduccion = 'introduccion';
    case ObjetoYAlcance = 'objeto_y_alcance';
    case Metodologia = 'metodologia';
    case NotaResumen = 'nota_resumen';
    case NotaTabla = 'nota_tabla';
    case NotaDerivacion = 'nota_derivacion';
    case NotaMadurez = 'nota_madurez';
    case NotaExclusiones = 'nota_exclusiones';
    case Conclusiones = 'conclusiones';
    case LimitacionesPropias = 'limitaciones_propias';
    case Aprobacion = 'aprobacion';

    /**
     * El orden es el de aparición en el documento, y de él depende el orden del
     * formulario: editar los textos de arriba abajo es leer el documento.
     *
     * @return list<self>
     */
    public static function paraTipo(TipoDocumento $tipo): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $seccion): bool => $seccion->aplicaA($tipo),
        ));
    }

    public function aplicaA(TipoDocumento $tipo): bool
    {
        return match ($this) {
            // La derivación de la categoría y la madurez por marco sólo existen
            // en el ENS; las exclusiones de controles, sólo en ISO.
            self::NotaDerivacion, self::NotaMadurez => $tipo === TipoDocumento::DdaEns,
            self::NotaExclusiones => $tipo === TipoDocumento::SoaIso,
            default => true,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Introduccion => 'Introducción',
            self::ObjetoYAlcance => 'Objeto y alcance',
            self::Metodologia => 'Metodología',
            self::NotaResumen => 'Nota sobre el resumen',
            self::NotaTabla => 'Cómo leer la tabla',
            self::NotaDerivacion => 'Nota sobre la categorización',
            self::NotaMadurez => 'Nota sobre la madurez',
            self::NotaExclusiones => 'Nota sobre las exclusiones',
            self::Conclusiones => 'Conclusiones',
            self::LimitacionesPropias => 'Limitaciones propias',
            self::Aprobacion => 'Aprobación',
        };
    }

    /** La frase que va bajo la etiqueta del campo, y que dice dónde acaba el texto. */
    public function ayuda(): string
    {
        return match ($this) {
            self::Introduccion => 'Abre el documento, justo después de la portada. Para qué existe y a quién va dirigido.',
            self::ObjetoYAlcance => 'Qué cubre este documento, con qué límites. No sustituye al alcance declarado del sistema, que se imprime en la portada.',
            self::Metodologia => 'Cómo se ha determinado lo que aplica y cómo se mantiene al día.',
            self::NotaResumen => 'Encima de las cifras del resumen. Vacío por defecto.',
            self::NotaTabla => 'Bajo el título de la tabla larga. Es lo que explica al auditor cómo se lee cada columna.',
            self::NotaDerivacion => 'Tras la tabla de las cinco dimensiones, en la categorización del sistema.',
            self::NotaMadurez => 'Bajo la tabla de madurez por marco.',
            self::NotaExclusiones => 'Tras el recuento de controles excluidos. Vacío por defecto.',
            self::Conclusiones => 'Cierra el documento, antes de las limitaciones. Vacío por defecto.',
            self::LimitacionesPropias => 'Se añaden DEBAJO de las que declara Statera, que no se pueden quitar. Saber qué declara la herramienta y qué declara la organización es parte de lo que se declara.',
            self::Aprobacion => 'Quién aprueba el documento y con qué decisión. Statera no valida este texto ni registra la aprobación: lo dice el propio documento.',
        };
    }

    /**
     * Si el hueco se imprime como sección propia, con su `<h2>`.
     *
     * Los que no, son notas que se meten dentro de una sección que ya existe y
     * que tiene su propio título.
     */
    public function esSeccionPropia(): bool
    {
        return match ($this) {
            self::Introduccion, self::ObjetoYAlcance, self::Metodologia,
            self::Conclusiones, self::Aprobacion => true,
            default => false,
        };
    }

    /** El `<h2>` de la sección, cuando la tiene. */
    public function titulo(): string
    {
        return match ($this) {
            self::Introduccion => 'Introducción',
            self::ObjetoYAlcance => 'Objeto y alcance',
            self::Metodologia => 'Metodología',
            self::Conclusiones => 'Conclusiones',
            self::Aprobacion => 'Aprobación',
            default => $this->etiqueta(),
        };
    }

    /**
     * El tope de caracteres.
     *
     * No es una cifra redonda por gusto: once secciones a veinte mil caracteres
     * son doscientos veinte kilobytes por versión, encima de una `instantanea`
     * que ya ronda los doscientos. Las notas son notas y se quedan en dos mil.
     */
    public function maxCaracteres(): int
    {
        return match ($this) {
            self::Introduccion, self::Metodologia, self::Conclusiones => 20_000,
            self::ObjetoYAlcance, self::Aprobacion, self::LimitacionesPropias => 8_000,
            default => 2_000,
        };
    }
}
