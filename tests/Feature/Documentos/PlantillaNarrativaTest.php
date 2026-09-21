<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Documento\Enums\OrigenTexto;
use App\Domain\Documento\Enums\SeccionNarrativa;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoSeccion;
use App\Domain\Documento\Models\PlantillaSeccion;
use App\Domain\Documento\Narrativa\GuardarNarrativa;
use App\Domain\Documento\Narrativa\GuardarPlantilla;
use App\Domain\Documento\Narrativa\MaterializarSecciones;
use App\Domain\Documento\Narrativa\ResolverNarrativa;
use App\Domain\Documento\Narrativa\TextosDeFabrica;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/**
 * La cadena documento → plantilla → fábrica.
 *
 * Lo que fija: que un documento sea un HECHO y no el resultado de un join que
 * cambia bajo los pies, y que «vacío a conciencia» y «no lo he tocado» sean dos
 * cosas distintas.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();

    $marco = Marco::factory()->create(['codigo' => 'ISO-SINTETICO']);
    $this->sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create();

    $this->documento = Documento::factory()->soa()->paraSistema($this->sistema->id)->create();

    $this->resolver = app(ResolverNarrativa::class);
});

it('un documento sin filas resuelve al texto de fábrica', function (): void {
    // Los documentos creados antes de que esto existiera no tienen filas, y su
    // PDF tiene que salir idéntico al de siempre. Sin migración de datos.
    expect($this->documento->secciones()->count())->toBe(0);

    expect($this->resolver->paraDocumento($this->documento)['introduccion'])
        ->toBe(TextosDeFabrica::para(TipoDocumento::SoaIso, SeccionNarrativa::Introduccion));
});

it('materializar es idempotente', function (): void {
    $materializar = app(MaterializarSecciones::class);

    $primera = $materializar($this->documento);

    expect($primera)->toBe(count(SeccionNarrativa::paraTipo(TipoDocumento::SoaIso)));
    expect($materializar($this->documento))->toBe(0);
    expect($this->documento->secciones()->count())->toBe($primera);
});

it('un documento nuevo arranca con la plantilla de la organización', function (): void {
    app(GuardarPlantilla::class)(TipoDocumento::SoaIso, ['introduccion' => 'Nuestra introducción.']);

    app(MaterializarSecciones::class)($this->documento);

    expect($this->resolver->paraDocumento($this->documento->fresh())['introduccion'])
        ->toBe('Nuestra introducción.');
});

it('cambiar la plantilla DESPUÉS no cambia un documento ya creado', function (): void {
    app(GuardarPlantilla::class)(TipoDocumento::SoaIso, ['introduccion' => 'La de entonces.']);
    app(MaterializarSecciones::class)($this->documento);

    app(GuardarPlantilla::class)(TipoDocumento::SoaIso, ['introduccion' => 'La de ahora.']);

    // Lo que dice un documento es un hecho del documento. Es el mismo
    // razonamiento que hay detrás de la instantánea de una versión.
    expect($this->resolver->paraDocumento($this->documento->fresh())['introduccion'])
        ->toBe('La de entonces.');
});

it('una fila vacía NO es lo mismo que una fila ausente', function (): void {
    app(MaterializarSecciones::class)($this->documento);

    app(GuardarNarrativa::class)($this->documento, ['introduccion' => '']);

    // Sin esta distinción, borrar un texto lo resucitaría en la siguiente
    // generación: el fallo silencioso clásico.
    expect($this->resolver->paraDocumento($this->documento->fresh())['introduccion'])->toBe('');
});

it('guardar la plantilla sin tocarla no la congela', function (): void {
    $textos = $this->resolver->paraPlantilla(TipoDocumento::SoaIso);

    app(GuardarPlantilla::class)(TipoDocumento::SoaIso, $textos);

    /*
     * Si guardar sin cambiar nada dejara fila en cada sección, esa organización
     * dejaría de recibir cualquier mejora futura del texto de Statera sin
     * haberlo decidido y sin enterarse. «No lo he tocado» y «no hay fila» tienen
     * que ser lo mismo.
     */
    expect(PlantillaSeccion::query()->count())->toBe(0);
});

it('calcula el origen de cada texto en vez de fiarse de lo que le digan', function (): void {
    app(MaterializarSecciones::class)($this->documento);

    app(GuardarNarrativa::class)($this->documento, ['conclusiones' => 'Algo nuestro.']);

    $filas = $this->resolver->filasDe($this->documento->fresh());

    expect($filas['conclusiones']->origen)->toBe(OrigenTexto::Propio)
        ->and($filas['introduccion']->origen)->toBe(OrigenTexto::Plantilla);
});

it('restablecer devuelve un hueco a la plantilla', function (): void {
    app(GuardarPlantilla::class)(TipoDocumento::SoaIso, ['conclusiones' => 'Lo de la organización.']);
    app(MaterializarSecciones::class)($this->documento);
    app(GuardarNarrativa::class)($this->documento, ['conclusiones' => 'Lo mío.']);

    app(GuardarNarrativa::class)->restablecer($this->documento, SeccionNarrativa::Conclusiones);

    $filas = $this->resolver->filasDe($this->documento->fresh());

    expect($filas['conclusiones']->contenido_md)->toBe('Lo de la organización.')
        ->and($filas['conclusiones']->origen)->toBe(OrigenTexto::Plantilla);
});

it('cambiar el tipo del documento no deja secciones huérfanas', function (): void {
    app(MaterializarSecciones::class)($this->documento);

    expect($this->documento->secciones()->where('seccion', 'nota_exclusiones')->exists())->toBeTrue();

    $ens = Sistema::factory()->de($this->organizacion)
        ->conMarco(Marco::factory()->create(['codigo' => 'ENS-SINTETICO']))
        ->create();

    $this->documento->update(['tipo' => TipoDocumento::DdaEns->value, 'sistema_id' => $ens->id]);
    app(MaterializarSecciones::class)->sincronizarConElTipo($this->documento->fresh());

    // `nota_exclusiones` es de ISO: arrastrarla invisible haría que reapareciera
    // —con un texto de hace meses— si alguien devolviera el documento a SoA.
    expect(DocumentoSeccion::query()->where('seccion', 'nota_exclusiones')->exists())->toBeFalse();
    expect(DocumentoSeccion::query()->where('seccion', 'nota_derivacion')->exists())->toBeTrue();
});

/**
 * El índice, desde que la búsqueda entra en los textos.
 *
 * Los textos viajan al cliente porque la búsqueda los recorre; la familia
 * separa lo calculado de lo redactado, que el dominio distingue desde hace
 * tiempo y la pantalla mezclaba; y quién tocó qué y cuándo lo guardaba el
 * modelo desde la primera migración sin que se enseñara en ninguna parte.
 */
it('el índice manda cada tipo con sus secciones, su familia y su último retoque', function (): void {
    $usuario = usuarioCon(organizacion: $this->organizacion);

    PlantillaSeccion::query()->create([
        'tipo' => TipoDocumento::Politica->value,
        'seccion' => SeccionNarrativa::Introduccion->value,
        'contenido_md' => 'Una frase muy concreta que sólo está aquí.',
        'actualizado_por_id' => $usuario->id,
    ]);

    $this->actingAs($usuario)->get('/plantillas-documento')
        ->assertInertia(function (AssertableInertia $pagina) use ($usuario): void {
            /** @var list<array<string, mixed>> $tipos */
            $tipos = $pagina->toArray()['props']['tipos'];

            $porValor = collect($tipos)->keyBy('valor');

            expect($porValor)->toHaveCount(count(TipoDocumento::cases()));

            $politica = $porValor[TipoDocumento::Politica->value];

            expect($politica['familia'])->toBe('redactado')
                ->and($politica['personalizadas'])->toBe(1)
                ->and($politica['retoque']['por'])->toBe($usuario->name)
                // El contenido va dentro: es lo que el buscador recorre.
                ->and(collect($politica['secciones'])->pluck('contenido'))
                ->toContain('Una frase muy concreta que sólo está aquí.');

            expect($porValor[TipoDocumento::SoaIso->value]['familia'])->toBe('calculado')
                ->and($porValor[TipoDocumento::SoaIso->value]['retoque'])->toBeNull();
        });
});

/**
 * Dieciséis consultas para pintar ocho tarjetas.
 *
 * `personalizadas()` resolvía la plantilla de cada tipo por su cuenta y al lado
 * iba un `count()` por tipo. Se fija el techo porque la pantalla crece con cada
 * tipo de documento nuevo y el coste no debe crecer con él.
 */
it('el índice no consulta una vez por tipo', function (): void {
    $usuario = usuarioCon(organizacion: $this->organizacion);

    /** @var list<string> $consultas */
    $consultas = [];
    DB::listen(function (QueryExecuted $consulta) use (&$consultas): void {
        $consultas[] = $consulta->sql;
    });

    $this->actingAs($usuario)->get('/plantillas-documento')->assertOk();

    $delModulo = array_values(array_filter(
        $consultas,
        fn (string $sql): bool => str_contains($sql, 'documento_plantilla_secciones')
            || str_contains($sql, 'from "documentos"'),
    ));

    /*
     * Tres: los textos, el recuento de documentos y el último retoque. Cuatro
     * con filas propias, porque entonces hay que traer los nombres de quien las
     * tocó. Lo que no puede volver es una consulta POR TIPO, que es lo que había
     * y lo que crece con cada tipo de documento nuevo.
     */
    expect($delModulo)->toHaveCount(3, implode("\n", $delModulo));
});
