<?php

declare(strict_types=1);

use App\Domain\Adjunto\Models\Adjunto;
use App\Domain\Persona\Models\AccionFormativa;
use App\Domain\Persona\Models\Persona;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Los adjuntos: la documentación que cuelga de un registro
|--------------------------------------------------------------------------
|
| Un adjunto NO es una evidencia. Una evidencia prueba un requisito y por eso
| lleva caducidad, periodicidad y responsable; esto documenta un registro —el
| título de un curso, el contrato firmado— y no tiene nada de eso.
|
| De ahí las dos diferencias que este fichero clava:
|
| 1. **Borrar SÍ borra el objeto del almacén.** Una evidencia deja el fichero a
|    propósito —Object Lock y valor probatorio—; aquí puede haber datos
|    personales y conservar lo que alguien borró sería el fallo.
| 2. **Disco propio**, para que ese borrado sea posible.
|
*/

beforeEach(function (): void {
    Storage::fake('adjuntos');

    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->persona = Persona::factory()->create();
});

it('sube un documento y guarda su huella', function (): void {
    $this->actingAs($this->usuario)
        ->post("/personas/{$this->persona->id}/adjuntos", [
            'fichero' => UploadedFile::fake()->createWithContent('titulo.pdf', 'contenido sintetico'),
            'titulo' => 'Título de formación',
        ])
        ->assertRedirect();

    $adjunto = Adjunto::query()->sole();

    // La huella se mide del fichero RECIBIDO y antes de subirlo: lo que llegó,
    // no lo que quedó en el bucket.
    expect($adjunto->hash_sha256)->toBe(hash('sha256', 'contenido sintetico'))
        ->and($adjunto->nombre_fichero)->toBe('titulo.pdf')
        ->and($adjunto->disco)->toBe('adjuntos')
        ->and($adjunto->subido_por_id)->toBe($this->usuario->id);

    Storage::disk('adjuntos')->assertExists($adjunto->ruta);
});

it('lo deja vinculado a la persona', function (): void {
    $this->actingAs($this->usuario)
        ->post("/personas/{$this->persona->id}/adjuntos", [
            'fichero' => UploadedFile::fake()->create('contrato.pdf'),
            'titulo' => 'Contrato',
        ]);

    expect($this->persona->refresh()->adjuntos)->toHaveCount(1);
});

/**
 * El nombre del almacén es un ULID y no el original: dos personas que suban
 * «dni.pdf» no pueden pisarse, y el nombre del bucket no filtra nada por sí
 * mismo. El original se guarda en la fila para devolvérselo a quien descargue.
 */
it('guarda con un nombre opaco y bajo el prefijo de la organización', function (): void {
    $this->actingAs($this->usuario)
        ->post("/personas/{$this->persona->id}/adjuntos", [
            'fichero' => UploadedFile::fake()->create('dni.pdf'),
            'titulo' => 'DNI',
        ]);

    $adjunto = Adjunto::query()->sole();

    expect($adjunto->ruta)->toStartWith("{$this->organizacion->id}/")
        ->and($adjunto->ruta)->not->toContain('dni.pdf');
});

it('exige fichero y título', function (): void {
    $this->actingAs($this->usuario)
        ->post("/personas/{$this->persona->id}/adjuntos", ['titulo' => 'Sin fichero'])
        ->assertSessionHasErrors('fichero');

    $this->actingAs($this->usuario)
        ->post("/personas/{$this->persona->id}/adjuntos", [
            'fichero' => UploadedFile::fake()->create('x.pdf'),
        ])
        ->assertSessionHasErrors('titulo');
});

/** La diferencia con una evidencia, y el motivo de que el disco sea propio. */
it('al borrar se lleva también el fichero del almacén', function (): void {
    $this->actingAs($this->usuario)
        ->post("/personas/{$this->persona->id}/adjuntos", [
            'fichero' => UploadedFile::fake()->create('dni.pdf'),
            'titulo' => 'DNI',
        ]);

    $adjunto = Adjunto::query()->sole();
    Storage::disk('adjuntos')->assertExists($adjunto->ruta);

    $this->actingAs($this->usuario)
        ->delete("/personas/{$this->persona->id}/adjuntos/{$adjunto->id}")
        ->assertRedirect();

    expect(Adjunto::query()->count())->toBe(0);
    Storage::disk('adjuntos')->assertMissing($adjunto->ruta);
});

it('descarga por una URL firmada y no por un enlace al bucket', function (): void {
    $this->actingAs($this->usuario)
        ->post("/personas/{$this->persona->id}/adjuntos", [
            'fichero' => UploadedFile::fake()->create('titulo.pdf'),
            'titulo' => 'Título',
        ]);

    $adjunto = Adjunto::query()->sole();

    $this->actingAs($this->usuario)
        ->get("/personas/{$this->persona->id}/adjuntos/{$adjunto->id}/descargar")
        ->assertRedirect();
});

/**
 * `scopeBindings()` sobre la pivote: el adjunto de otra persona no se descarga
 * ni se borra desde ésta. Es la frontera que un morph no podría dar con una
 * clave foránea de verdad.
 */
it('no llega al documento de otra persona desde una URL que no le toca', function (): void {
    $otra = Persona::factory()->create();

    $this->actingAs($this->usuario)
        ->post("/personas/{$otra->id}/adjuntos", [
            'fichero' => UploadedFile::fake()->create('suyo.pdf'),
            'titulo' => 'Suyo',
        ]);

    $adjunto = Adjunto::query()->sole();

    $this->actingAs($this->usuario)
        ->get("/personas/{$this->persona->id}/adjuntos/{$adjunto->id}/descargar")
        ->assertNotFound();

    $this->actingAs($this->usuario)
        ->delete("/personas/{$this->persona->id}/adjuntos/{$adjunto->id}")
        ->assertNotFound();

    expect(Adjunto::query()->count())->toBe(1);
});

it('no llega al documento de otra organización', function (): void {
    comoOrganizacion();
    $ajena = Persona::factory()->create();
    $ajeno = Adjunto::factory()->create();
    $ajena->adjuntos()->attach($ajeno->id, ['organizacion_id' => $ajena->organizacion_id]);

    comoOrganizacion($this->organizacion);

    // 404 y no 403: decir «existe pero no es tuyo» ya sería filtrar.
    $this->actingAs($this->usuario)
        ->get("/personas/{$ajena->id}/adjuntos/{$ajeno->id}/descargar")
        ->assertNotFound();
});

it('cuelga documentos también de una sesión de formación', function (): void {
    $accion = AccionFormativa::factory()->create();

    $this->actingAs($this->usuario)
        ->post("/formacion/{$accion->id}/adjuntos", [
            'fichero' => UploadedFile::fake()->create('temario.pdf'),
            'titulo' => 'Temario',
        ])
        ->assertRedirect();

    expect($accion->refresh()->adjuntos)->toHaveCount(1);
});

/**
 * El mismo fichero documenta a la persona y a la sesión sin subirse dos veces:
 * es el motivo de que la relación sea N:M y no una clave en `adjuntos`.
 */
it('deja el mismo documento colgado de dos registros', function (): void {
    $accion = AccionFormativa::factory()->create();
    $adjunto = Adjunto::factory()->create();

    $this->persona->adjuntos()->attach($adjunto->id, ['organizacion_id' => $this->organizacion->id]);
    $accion->adjuntos()->attach($adjunto->id, ['organizacion_id' => $this->organizacion->id]);

    expect($this->persona->refresh()->adjuntos)->toHaveCount(1)
        ->and($accion->refresh()->adjuntos)->toHaveCount(1)
        ->and(Adjunto::query()->count())->toBe(1);
});

it('manda los documentos a la ficha de la persona', function (): void {
    $adjunto = Adjunto::factory()->create(['titulo' => 'Contrato firmado']);
    $this->persona->adjuntos()->attach($adjunto->id, ['organizacion_id' => $this->organizacion->id]);

    $this->actingAs($this->usuario)
        ->get("/personas/{$this->persona->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('adjuntos', 1)
            ->where('adjuntos.0.titulo', 'Contrato firmado'));
});

it('ofrece la acción de documentos en el menú de la fila', function (): void {
    $this->actingAs($this->usuario)
        ->get('/personas')
        ->assertInertia(function (AssertableInertia $pagina): void {
            $claves = collect($pagina->toArray()['props']['recurso']['accionesFila'])->pluck('clave');

            expect($claves)->toContain('documentos');
        });
});
