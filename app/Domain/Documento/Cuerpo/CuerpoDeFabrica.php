<?php

declare(strict_types=1);

namespace App\Domain\Documento\Cuerpo;

use App\Domain\Documento\Enums\SeccionNarrativa;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Narrativa\TextosDeFabrica;

/**
 * El documento con el que nace un documento nuevo.
 *
 * Fija **el orden** —qué sección va antes de cuál— y trae redactado lo que
 * Statera tiene algo que decir. A partir de ahí el usuario manda: puede
 * reescribirlo, reordenarlo o quitarlo, porque eso es lo que significa editar el
 * documento entero.
 *
 * Los huecos calculados (`Nodo::hueco()`) marcan dónde va cada bloque que sale
 * del registro; los rellena `MaterializarCuerpo`. Un hueco sin materializar no
 * se pinta, así que un esqueleto a medias produce un documento incompleto, nunca
 * uno roto.
 *
 * **Estrena el cuerpo con lo que la organización ya tenía escrito.** Un
 * documento que llevaba meses con sus conclusiones redactadas en el editor de
 * huecos no las pierde al pasar al cuerpo editable: `$narrativa` trae esos
 * textos ya convertidos a nodos y sustituyen a los de fábrica hueco por hueco.
 * Sin eso, la entrega se llevaría por delante texto escrito a mano y lo haría en
 * silencio.
 *
 * **Una sección sin nada dentro no se pinta.** Cinco de los once huecos vienen
 * vacíos de fábrica, y un `<h2>` con nada debajo se lee como un documento roto.
 * Antes lo comprobaba la plantilla Blade; ahora se decide aquí, que es el único
 * sitio donde se sabe si hay texto.
 *
 * La prosa de fábrica sale de `TextosDeFabrica` en vez de estar transcrita aquí
 * otra vez. Eso mantiene vivo el test que la clava palabra por palabra —el que
 * garantiza que un documento generado antes y después de esta entrega dice lo
 * mismo— y evita la única forma real de perder una negrita: copiarla a mano.
 */
final class CuerpoDeFabrica
{
    /**
     * @param  array<string, list<array<string, mixed>>>  $narrativa  por clave de sección
     */
    private function __construct(
        private readonly TipoDocumento $tipo,
        private readonly array $narrativa,
    ) {}

    /**
     * El esqueleto completo, como documento de ProseMirror.
     *
     * @param  array<string, list<array<string, mixed>>>  $narrativa
     * @return array<string, mixed>
     */
    public static function para(TipoDocumento $tipo, array $narrativa = []): array
    {
        return (new self($tipo, $narrativa))->construir();
    }

    /**
     * @return array<string, mixed>
     */
    private function construir(): array
    {
        return Nodo::de('doc', [], [
            $this->portada(),

            ...$this->seccion(SeccionNarrativa::Introduccion),
            ...$this->seccion(SeccionNarrativa::ObjetoYAlcance),
            ...$this->seccion(SeccionNarrativa::Metodologia),

            ...match ($this->tipo) {
                TipoDocumento::SoaIso => $this->cuerpoIso(),
                TipoDocumento::DdaEns => $this->cuerpoEns(),
            },

            ...$this->seccion(SeccionNarrativa::Conclusiones),
            ...$this->seccion(SeccionNarrativa::Aprobacion),

            $this->limitaciones(),
            $this->controlDeVersiones(),
        ]);
    }

    /**
     * La portada.
     *
     * El filete y la marca son literales —son la marca, no contenido—, y todo lo
     * que identifica al documento viene del registro: título, sistema, versión,
     * fecha, clasificación y alcance declarado. El pie va aparte de la ficha
     * porque es la frase que cambia cuando alguien edita el documento a mano.
     *
     * @return array<string, mixed>
     */
    private function portada(): array
    {
        return Nodo::de('portada', [], [
            Nodo::de('fileteMarca'),
            Nodo::de('marcaPortada', [], [Nodo::texto('Statera')]),
            Nodo::hueco('portada_ficha'),
            Nodo::hueco('portada_pie'),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function cuerpoIso(): array
    {
        return [
            $this->resumen(),

            Nodo::de('seccion', [], [
                Nodo::encabezado(2, 'Controles del Anexo A'),
                ...$this->prosa(SeccionNarrativa::NotaTabla),
                Nodo::hueco('tabla_requisitos'),
            ]),

            Nodo::de('seccion', [], [
                Nodo::encabezado(2, 'Controles excluidos y su justificación'),
                ...$this->prosa(SeccionNarrativa::NotaExclusiones),
                Nodo::hueco('tabla_exclusiones'),
            ]),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function cuerpoEns(): array
    {
        return [
            Nodo::de('seccion', [], [
                Nodo::encabezado(2, 'Categorización del sistema'),
                Nodo::hueco('tabla_derivacion'),
                ...$this->prosa(SeccionNarrativa::NotaDerivacion),
            ]),

            $this->resumen(),

            Nodo::de('seccion', [], [
                Nodo::encabezado(2, 'Medidas del Anexo II'),
                ...$this->prosa(SeccionNarrativa::NotaTabla),
                Nodo::hueco('tabla_requisitos'),
            ]),

            Nodo::de('seccion', [], [
                Nodo::encabezado(2, 'Notas sobre la lectura del Anexo II'),
                Nodo::hueco('notas_anexo_ii'),
                Nodo::encabezado(3, 'Madurez por marco', null, 'separado'),
                ...$this->prosa(SeccionNarrativa::NotaMadurez),
                Nodo::hueco('tabla_madurez'),
            ]),
        ];
    }

    /** @return array<string, mixed> */
    private function resumen(): array
    {
        return Nodo::de('seccion', [], [
            Nodo::encabezado(2, 'Resumen'),
            ...$this->prosa(SeccionNarrativa::NotaResumen),
            Nodo::hueco('resumen_cifras'),
            Nodo::hueco('resumen_grafica'),
        ]);
    }

    /**
     * Las limitaciones.
     *
     * El hueco lo rellena el sistema y **se vuelve a pedir en cada generación**
     * (`EsquemaCuerpo::SIEMPRE_RECALCULADOS`): es donde se declara lo que el
     * documento no puede afirmar, incluido el hecho de haberse editado a mano.
     *
     * Lo que la organización quiera añadir va **debajo y bajo su propio `<h3>`**,
     * nunca mezclado con lo anterior ni en su lugar: saber qué declara la
     * herramienta y qué declara la organización es parte de lo que se está
     * declarando.
     *
     * @return array<string, mixed>
     */
    private function limitaciones(): array
    {
        $propias = $this->prosa(SeccionNarrativa::LimitacionesPropias);

        return Nodo::de('seccion', [], [
            Nodo::encabezado(2, 'Limitaciones de esta declaración'),
            Nodo::hueco('limitaciones_sistema'),
            ...($propias === [] ? [] : [
                Nodo::encabezado(3, 'Limitaciones declaradas por la organización'),
                ...$propias,
            ]),
        ]);
    }

    /** @return array<string, mixed> */
    private function controlDeVersiones(): array
    {
        return Nodo::de('seccion', [], [
            Nodo::encabezado(2, 'Control de versiones'),
            Nodo::hueco('control_versiones'),
        ]);
    }

    /**
     * Una sección con su `<h2>`, o nada si no hay texto que poner debajo.
     *
     * @return list<array<string, mixed>>
     */
    private function seccion(SeccionNarrativa $seccion): array
    {
        $prosa = $this->prosa($seccion);

        if ($prosa === []) {
            return [];
        }

        return [Nodo::de('seccion', [], [
            Nodo::encabezado(2, $seccion->titulo()),
            ...$prosa,
        ])];
    }

    /**
     * El texto de un hueco: el de la organización si lo hay, el de fábrica si no.
     *
     * @return list<array<string, mixed>>
     */
    private function prosa(SeccionNarrativa $seccion): array
    {
        if (! $seccion->aplicaA($this->tipo)) {
            return [];
        }

        $heredada = $this->narrativa[$seccion->value] ?? [];

        if ($heredada !== []) {
            return $heredada;
        }

        return self::enParrafos(TextosDeFabrica::para($this->tipo, $seccion));
    }

    /**
     * Un texto de fábrica, en párrafos.
     *
     * @return list<array<string, mixed>>
     */
    private static function enParrafos(string $texto): array
    {
        $parrafos = preg_split('/\n\s*\n/u', trim($texto)) ?: [];

        return array_values(array_map(
            static fn (string $parrafo): array => Nodo::parrafoRico(trim($parrafo)),
            array_filter($parrafos, static fn (string $parrafo): bool => trim($parrafo) !== ''),
        ));
    }
}
