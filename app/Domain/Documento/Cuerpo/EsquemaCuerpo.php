<?php

declare(strict_types=1);

namespace App\Domain\Documento\Cuerpo;

/**
 * Qué puede contener el cuerpo de un documento.
 *
 * **Es la lista blanca, y es lo único que separa el documento de una inyección.**
 * El cuerpo se guarda como JSON de ProseMirror —el formato nativo del editor, de
 * ida y vuelta sin pérdida— y en ese JSON **no existe ningún nodo de HTML
 * crudo**: no hay nada que sanear porque no hay forma de escribirlo. Lo que no
 * está declarado aquí, `RenderizadorCuerpo` no lo pinta y `GuardarCuerpo` no lo
 * admite.
 *
 * Los nombres de clase son los de `resources/documentos/documento.css`, que no se
 * toca: es lo que permite que el documento se edite entero y el PDF siga saliendo
 * con el diseño de siempre. **Y por eso las clases van enumeradas y escritas
 * enteras, nunca compuestas en ejecución** —la misma regla que ya rige en
 * `CeldaBadge` y en `BarraSegmentada`—: un atributo de clase libre sería una
 * inyección de CSS con otro nombre.
 *
 * Deliberadamente **no hay nodo de imagen**. Una imagen remota tumbaría la
 * generación entera —`failOnResourceLoadingFailed()` está encendido y la
 * allow-list de Gotenberg es `^(file:///tmp/|data:).*`— y una incrustada
 * hincharía la instantánea sin límite.
 *
 * Tampoco hay nodo de SVG. La gráfica es un nodo con **los datos** del reparto y
 * el SVG lo dibuja `GraficaSvg` al renderizar: así el marcado vectorial nunca
 * pasa por el cuerpo editable y no hay que confiar en él.
 */
final class EsquemaCuerpo
{
    /**
     * Los nodos admitidos y los atributos propios de cada uno.
     *
     * Un atributo que no figure aquí se descarta al guardar. `content` y `marks`
     * no son atributos y se tratan aparte.
     *
     * @var array<string, list<string>>
     */
    public const NODOS = [
        // --- Contenedores ---------------------------------------------------
        'doc' => [],
        'portada' => [],
        'seccion' => [],
        'caja' => ['variante', 'estilo'],
        'ficha' => [],
        'cifras' => [],
        'grafica' => ['segmentos'],
        'leyenda' => [],
        'limitaciones' => [],

        /*
         * Una nota al pie de una celda: la dimensión que modula una medida del
         * Anexo II. Es un bloque y no una marca porque va en su propia línea
         * bajo el origen de la exigencia, y ahí `<span>` no vale.
         */
        'nota' => [],

        /*
         * Un contenedor que no pinta nada de sí mismo: sólo sus hijos.
         *
         * Existe porque varios bloques calculados son **varios hermanos a la
         * vez** —las cifras del resumen y el aviso de «se te exigen N de M» son
         * un `<div>` y un `<p>`— y un bloque calculado tiene que ser un solo
         * nodo para poder sustituirse de una pieza al recalcular.
         */
        'grupo' => [],

        // --- Piezas de portada ----------------------------------------------
        'fileteMarca' => [],
        'marcaPortada' => [],
        'pieDePortada' => [],

        // --- Texto ----------------------------------------------------------
        'paragraph' => ['clase', 'estilo'],
        'heading' => ['level', 'clase', 'estilo'],
        'bulletList' => [],
        'orderedList' => [],
        'listItem' => [],
        'text' => [],
        'hardBreak' => [],

        // --- Datos con forma propia -----------------------------------------
        'fichaFila' => ['clave'],
        'cifraDato' => ['valor', 'de', 'etiqueta'],
        'badge' => ['tono'],

        // --- Tablas ---------------------------------------------------------
        'table' => ['clase'],
        'tableRow' => ['clase'],
        'tableHeader' => ['colspan', 'rowspan', 'ancho', 'scope'],
        'tableCell' => ['colspan', 'rowspan', 'clase'],
    ];

    /**
     * Atributos que cualquier bloque puede llevar, y que dicen de dónde salió.
     *
     * `fuente` es qué generador lo produjo, `huella` con qué datos, y `editado`
     * si alguien lo ha tocado después. De estos tres vive todo lo que mantiene
     * honesto un documento que se puede cambiar entero: sin ellos, un documento
     * editado a mano es indistinguible de uno generado, y eso es exactamente lo
     * que un auditor no puede permitirse.
     *
     * @var list<string>
     */
    public const PROCEDENCIA = ['fuente', 'huella', 'editado'];

    /**
     * Los generadores. El valor de `fuente` en un bloque calculado.
     *
     * @var list<string>
     */
    public const FUENTES = [
        'portada_ficha',
        'portada_pie',
        'resumen_cifras',
        'resumen_plan',
        'resumen_grafica',
        'tabla_requisitos',
        'tabla_sin_trabajo',
        'tabla_exclusiones',
        'tabla_derivacion',
        'notas_anexo_ii',
        'tabla_madurez',
        // Las cuatro del análisis del contexto (§ 4.1). Ninguna es una tabla de
        // requisitos: lo que imprimen son cuestiones, partes interesadas, la
        // declaración del cambio climático y el alcance de cada sistema.
        'dafo_cuadrantes',
        'tabla_partes_interesadas',
        'declaracion_climatica',
        'alcance_sistemas',
        // Las tres del acta de revisión por la dirección (§ 4.15). La del medio
        // es la que paga el documento: las siete entradas de la 9.3.2, tal como
        // se congelaron al firmar el acta.
        'ficha_revision',
        'entradas_revision',
        'tabla_decisiones',
        // Las tres de la Declaración de Conformidad (§ 4.17). La primera es la
        // frase que el documento existe para decir, y por eso se recalcula
        // siempre: ver `SIEMPRE_RECALCULADOS`.
        'declaracion_formal',
        'ficha_autoevaluacion',
        'resultado_autoevaluacion',
        'limitaciones_sistema',
        'control_versiones',
    ];

    /**
     * Bloques que se materializan pero que el renderizador **vuelve a pedir** al
     * generar, pasen lo que pasen en el editor.
     *
     * Las limitaciones son la caja fuerte del documento —ahí es donde se declara
     * que se ha editado a mano— y el control de versiones no puede llevar dentro
     * la huella del PDF que lo contiene.
     *
     * **`portada_ficha` entró con el flujo de aprobación (§ 4.5)**, y es lo que
     * hace que la firma llegue al papel. Esa ficha se materializaba UNA vez, al
     * crear el cuerpo, así que se quedaba congelada con la fecha de aquel día, la
     * etiqueta «Borrador» y sin aprobación: el PDF que se le entrega al auditor
     * llevaría en portada los datos de la primera vez que alguien pulsó
     * «Generar». No es narrativa que nadie redacte —organización, código,
     * versión, fecha, clasificación y quién firmó son identificación, y tienen que
     * coincidir con el registro—, así que recalcularla es lo correcto aunque
     * sobreescriba una edición manual; que se editó, lo declaran las limitaciones.
     *
     * @var list<string>
     */
    public const SIEMPRE_RECALCULADOS = [
        'portada_ficha',
        'portada_pie',
        /*
         * **La declaración formal de la DdC (§ 4.17)**, por el mismo motivo que
         * la ficha de portada: quién declara, qué sistema, qué categoría y sobre
         * qué autoevaluación no es narrativa, es identificación, y tiene que
         * coincidir con el registro. Una declaración de conformidad editada a
         * mano para decir «categoría media» sería exactamente el documento que
         * un auditor no puede aceptar. Que se editó, lo declaran las limitaciones.
         */
        'declaracion_formal',
        'limitaciones_sistema',
        'control_versiones',
    ];

    /** Marcas de texto. `cifra` es la monoespaciada tabular del documento. */
    public const MARCAS = ['bold', 'italic', 'link', 'cifra', 'suave'];

    /** Los únicos protocolos que sobreviven en un `href`. */
    public const PROTOCOLOS = ['http', 'https', 'mailto'];

    /** @var array<string, string> variante => clases */
    public const VARIANTES_CAJA = [
        'simple' => 'caja',
        'marca' => 'caja caja--marca',
    ];

    /**
     * Los cuatro estilos en línea que el documento necesita, escritos enteros.
     *
     * `documento.css` no se toca —es lo que garantiza que el PDF siga saliendo
     * igual—, y estas cuatro declaraciones estaban puestas a mano en las
     * plantillas Blade porque son ajustes de un sitio, no clases reutilizables.
     * Aquí llegan **enumeradas**: el nodo guarda la clave, y el valor lo escribe
     * el renderizador. Un atributo `style` libre en un cuerpo editable sería una
     * inyección de CSS con otro nombre —`position: fixed` sobre el sello de
     * clasificación, y el documento deja de decir lo que dice—.
     *
     * @var array<string, string>
     */
    public const ESTILOS = [
        'aviso_borrador' => 'border-left: 3px solid var(--estado-en-progreso); background: var(--estado-en-progreso-suave)',
        'formula' => 'font-size: 10pt; margin-bottom: 0',
        'separado' => 'margin-top: 0.2in',
        'nota_al_pie' => 'margin-top: 0.12in',
    ];

    /**
     * Los tonos del dominio que el documento sabe pintar.
     *
     * **Un tono que no esté aquí sale como `neutro`**, que es un chip gris sin
     * punto: el mismo fallo silencioso que `IconoTipo` tenía con los iconos y
     * `tonos.ts` con los colores, y por tercera vez en el producto. Lo cierra
     * `tests/Unit/Diseno/TonosDelDocumentoTest.php`, que compara este mapa con lo
     * que emiten los enums del dominio y con las clases que declara
     * `documento.css`.
     *
     * `en_revision` entró con el acta de la revisión por la dirección (§ 4.15):
     * es el primer documento que imprime badges de objetivos de seguridad, y
     * `EstadoObjetivo::Propuesto` gasta ese tono. Antes de él ningún cuerpo
     * calculado llegaba a emitirlo, así que el hueco no se notaba.
     *
     * @var array<string, string> tono => clases
     */
    public const TONOS_BADGE = [
        'implantado' => 'badge badge--implantado',
        'planificado' => 'badge badge--planificado',
        'en_progreso' => 'badge badge--en_progreso',
        'en_revision' => 'badge badge--en_revision',
        'no_iniciado' => 'badge badge--no_iniciado',
        'no_aplica' => 'badge badge--no_aplica',
        'neutro' => 'badge badge--neutro',
    ];

    /** @var array<string, string> clave => clases */
    public const CLASES_PARRAFO = [
        'suave' => 'suave',
        'pequeno' => 'pequeno',
        'pequeno_suave' => 'pequeno suave',
        'vacio' => 'vacio',
        'subtitulo_portada' => 'portada__subtitulo',
    ];

    /** @var array<string, string> clave => clases */
    public const CLASES_ENCABEZADO = [
        'titulo_portada' => 'portada__titulo',
    ];

    /** @var array<string, string> clave => clases */
    public const CLASES_TABLA = [
        'fija' => 'tabla--fija',
    ];

    /** @var array<string, string> clave => clases */
    public const CLASES_FILA = [
        'grupo' => 'grupo',
    ];

    /** @var array<string, string> clave => clases */
    public const CLASES_CELDA = [
        'codigo' => 'codigo',
        'huella' => 'huella',
    ];

    /** Los niveles de encabezado que el editor ofrece y el renderizador pinta. */
    public const NIVELES = [1, 2, 3, 4];

    /**
     * Un ancho de columna de tabla: un número y una unidad de imprenta.
     *
     * Las anchuras de la tabla larga están medidas en pulgadas para que las diez
     * columnas quepan en un A4 apaisado sin que Chromium reparta a su gusto. Se
     * valida con una expresión y no se deja libre porque un `width` es un
     * atributo de estilo por la puerta de atrás.
     */
    public const ANCHO = '/^\d{1,3}(\.\d{1,3})?(in|pt|mm|em|%)$/';

    public static function anchoValido(string $ancho): bool
    {
        return preg_match(self::ANCHO, $ancho) === 1;
    }

    public static function admite(string $tipo): bool
    {
        return array_key_exists($tipo, self::NODOS);
    }

    public static function admiteMarca(string $marca): bool
    {
        return in_array($marca, self::MARCAS, true);
    }

    /** Los atributos que un nodo puede llevar, procedencia incluida. */
    /** @return list<string> */
    public static function atributos(string $tipo): array
    {
        if (! self::admite($tipo)) {
            return [];
        }

        // `text` no lleva procedencia: no es un bloque, y marcar la mitad de una
        // frase como «generada» no significa nada.
        if ($tipo === 'text') {
            return [];
        }

        return [...self::NODOS[$tipo], ...self::PROCEDENCIA];
    }

    public static function esFuente(string $fuente): bool
    {
        return in_array($fuente, self::FUENTES, true);
    }

    /** Si un `href` puede salir al documento. */
    public static function enlaceSeguro(string $href): bool
    {
        $esquema = parse_url(trim($href), PHP_URL_SCHEME);

        // Un enlace relativo no tiene esquema y es inofensivo al imprimir. Si
        // `parse_url` falla —devuelve `false`—, no se pinta.
        if ($esquema === null) {
            return ! str_contains($href, ':');
        }

        return is_string($esquema) && in_array(mb_strtolower($esquema), self::PROTOCOLOS, true);
    }
}
