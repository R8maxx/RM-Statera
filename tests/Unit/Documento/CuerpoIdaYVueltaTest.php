<?php

declare(strict_types=1);

use App\Domain\Documento\Cuerpo\CuerpoDeFabrica;
use App\Domain\Documento\Cuerpo\GuardarCuerpo;
use App\Domain\Documento\Cuerpo\Nodo;
use App\Domain\Documento\Cuerpo\SanearCuerpo;
use App\Domain\Documento\Enums\TipoDocumento;

/**
 * Abrir el editor y guardar sin tocar nada no cambia el documento.
 *
 * Suena a perogrullada y no lo es: entre el cuerpo que guarda Statera y el que
 * devuelve el editor hay dos transformaciones que no se ven y que estuvieron
 * rotas hasta que se comprobaron de punta a punta.
 *
 * 1. **`jsonb` no conserva el orden de las claves.** La línea base vuelve de
 *    PostgreSQL con las claves ordenadas y el editor manda las suyas en otro
 *    orden. Sin normalizar, dos bloques idénticos dan huellas distintas.
 * 2. **ProseMirror materializa los atributos con su valor de serie.** Lo que
 *    Statera escribió sin `variante` vuelve con `variante: "simple"`.
 *
 * Con cualquiera de las dos sin resolver, **el primer guardado marcaba todos los
 * apartados calculados como editados a mano** en un documento donde nadie había
 * tocado nada, y el PDF entregado lo declaraba en sus limitaciones. Una mentira
 * en la parte del documento que existe justamente para no mentir.
 */
function comoLoDevuelveElEditor(array $nodo): array
{
    // Las claves al revés: es lo que hace `jsonb` con cualquier objeto.
    $volteado = array_reverse($nodo, true);

    if (isset($volteado['content']) && is_array($volteado['content'])) {
        $volteado['content'] = array_map(
            static fn (array $hijo): array => comoLoDevuelveElEditor($hijo),
            $volteado['content'],
        );
    }

    // Y los atributos de serie que ProseMirror escribe aunque nadie los pusiera.
    if (($volteado['type'] ?? null) === 'caja') {
        $volteado['attrs'] = ['variante' => 'simple', ...($volteado['attrs'] ?? [])];
    }

    return $volteado;
}

it('da la misma huella aunque las claves vengan en otro orden', function (): void {
    $nodo = Nodo::calculado('resumen_cifras', 'grupo', [Nodo::parrafo('Cuarenta y uno de noventa y tres.')]);

    expect(GuardarCuerpo::huella(comoLoDevuelveElEditor($nodo)))
        ->toBe(GuardarCuerpo::huella($nodo));
});

it('no cuenta como edición un atributo que sólo trae su valor por defecto', function (): void {
    $nodo = Nodo::calculado('portada_ficha', 'grupo', [
        Nodo::de('caja', ['estilo' => 'aviso_borrador'], [Nodo::parrafo('Borrador.')]),
    ]);

    $devuelto = app(SanearCuerpo::class)(Nodo::de('doc', [], [
        Nodo::de('portada', [], [comoLoDevuelveElEditor($nodo)]),
        Nodo::de('seccion', [], [Nodo::parrafo('x')]),
    ]));

    expect($devuelto)->not->toBeNull()
        ->and(GuardarCuerpo::huella($devuelto['content'][0]['content'][0]))
        ->toBe(GuardarCuerpo::huella($nodo));
});

it('el esqueleto de fábrica sobrevive entero a una ida y vuelta', function (TipoDocumento $tipo): void {
    $fabrica = CuerpoDeFabrica::para($tipo);

    $devuelto = app(SanearCuerpo::class)(comoLoDevuelveElEditor($fabrica));

    expect($devuelto)->not->toBeNull()
        ->and(GuardarCuerpo::huella($devuelto))->toBe(GuardarCuerpo::huella($fabrica));
})->with(TipoDocumento::cases());

it('ningún bloque calculado se marca como tocado si no se ha tocado', function (): void {
    $fabrica = CuerpoDeFabrica::para(TipoDocumento::SoaIso);

    $devuelto = app(SanearCuerpo::class)(comoLoDevuelveElEditor($fabrica));

    expect(GuardarCuerpo::bloquesEditados($devuelto ?? []))->toBe([]);
});

it('el orden de los hijos SÍ es contenido y cambiarlo se nota', function (): void {
    $antes = Nodo::calculado('limitaciones_sistema', 'grupo', [
        Nodo::parrafo('Primero.'),
        Nodo::parrafo('Segundo.'),
    ]);

    $despues = Nodo::calculado('limitaciones_sistema', 'grupo', [
        Nodo::parrafo('Segundo.'),
        Nodo::parrafo('Primero.'),
    ]);

    // Normalizar el orden de las CLAVES no puede llevarse por delante el orden
    // de los párrafos: ahí el orden es lo que dice el documento.
    expect(GuardarCuerpo::huella($despues))->not->toBe(GuardarCuerpo::huella($antes));
});
