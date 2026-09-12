<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Documento\Contenido\RegistroGeneradores;
use App\Domain\Documento\Cuerpo\GuardarCuerpo;
use App\Domain\Documento\Cuerpo\HtmlDocumento;
use App\Domain\Documento\Cuerpo\Nodo;
use App\Domain\Documento\Cuerpo\ResolverCuerpo;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Implantacion\GeneradorImplantaciones;
use App\Domain\Sistema\Models\Sistema;

/**
 * Un documento que se puede editar entero tiene que decir que se ha editado.
 *
 * Es el precio de haber abierto la tabla de controles, y es lo que hace que
 * abrirla sea defendible. Un auditor respeta un documento que dice cómo se hizo;
 * el que no lo dice es el que suspende. Por eso la declaración **no se redacta,
 * se construye**, y por eso su bloque se vuelve a pedir en cada generación: no
 * hay forma de quitarla desde el editor.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();

    $marco = Marco::factory()->create(['codigo' => 'ISO-SINTETICO']);
    $sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create();
    app(GeneradorImplantaciones::class)->generar($sistema);

    $this->documento = Documento::factory()->soa()->paraSistema($sistema->id)->create();
    $this->version = DocumentoVersion::factory()->delDocumento($this->documento->id)->create();

    $this->contenido = fn () => app(RegistroGeneradores::class)
        ->para($this->documento->tipo)
        ->construir($this->documento->fresh(), $this->version->fresh(), []);

    $this->html = fn (): string => app(HtmlDocumento::class)($this->documento->fresh(), ($this->contenido)());

    /** Edita el cuerpo, aplicándole el cambio que se le pase. */
    $this->editar = function (?callable $cambio = null): void {
        $fila = app(ResolverCuerpo::class)->fila($this->documento->fresh(), ($this->contenido)());
        $cuerpo = $fila->cuerpo;

        if ($cambio !== null) {
            $cuerpo = $cambio($cuerpo);
        }

        app(GuardarCuerpo::class)($fila, $cuerpo);
    };
});

it('mientras nadie lo toque, la portada dice que no se mantiene a mano', function (): void {
    expect(($this->html)())
        ->toContain('no se mantienen a mano')
        ->not->toContain('se editó a mano')
        ->not->toContain('Este documento se ha editado a mano');
});

it('en cuanto se edita, lo dice en la portada', function (): void {
    ($this->editar)();

    expect(($this->html)())
        ->toContain('se editó a mano')
        ->not->toContain('no se mantienen a mano');
});

it('y lo añade a las limitaciones, que no se pueden borrar', function (): void {
    // Se guarda un documento SIN el bloque de limitaciones: se ha borrado en el
    // editor, que es lo que alguien haría para que no salga.
    ($this->editar)(fn (array $cuerpo): array => Nodo::de('doc', [], [
        Nodo::de('portada', [], [Nodo::hueco('portada_ficha'), Nodo::hueco('portada_pie')]),
        Nodo::de('seccion', [], [Nodo::parrafo('Sin limitaciones, gracias.')]),
    ]));

    // Y aun así el documento entregado las lleva, con la declaración dentro.
    expect(($this->html)())
        ->toContain('<div class="limitaciones">')
        ->toContain('Este documento se ha editado a mano');
});

it('nombra los apartados calculados que se han tocado', function (): void {
    ($this->editar)(function (array $cuerpo): array {
        $recorrer = function (array $nodo) use (&$recorrer): array {
            // Alguien reescribe a mano las cifras del resumen.
            if (($nodo['attrs']['fuente'] ?? null) === 'resumen_cifras') {
                return Nodo::calculado('resumen_cifras', 'grupo', [Nodo::parrafo('Todo implantado.')]);
            }

            if (is_array($nodo['content'] ?? null)) {
                $nodo['content'] = array_values(array_map($recorrer, $nodo['content']));
            }

            return $nodo;
        };

        return $recorrer($cuerpo);
    });

    // Decir «se ha editado» sin decir QUÉ no le sirve de nada a quien audita, y
    // el nombre va en el idioma del documento, no en el del código.
    expect(($this->html)())
        ->toContain('1 apartados calculados se han modificado')
        ->toContain('las cifras del resumen')
        ->not->toContain('resumen_cifras');
});

it('no marca como tocado un bloque que se guardó tal cual venía', function (): void {
    ($this->editar)();

    expect(($this->html)())
        ->toContain('Este documento se ha editado a mano')
        // Guardar sin cambiar nada no convierte los bloques calculados en
        // «modificados»: la procedencia se comprueba contra la línea base, no se
        // deduce de que alguien haya pulsado Guardar.
        ->not->toContain('apartados calculados se han modificado');
});
