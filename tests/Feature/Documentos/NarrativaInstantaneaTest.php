<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Documento\EmitirVersion;
use App\Domain\Documento\GenerarDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Documento\Narrativa\GuardarNarrativa;
use App\Domain\Documento\Narrativa\MaterializarSecciones;
use App\Domain\Documento\Render\ClienteGotenberg;
use App\Domain\Implantacion\GeneradorImplantaciones;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Tests\Dobles\GotenbergFalso;

/**
 * La narrativa dentro de una versión.
 *
 * Lo que fija: que lo entregado se pueda demostrar. Una versión emitida no
 * cambia aunque después se reescriban todos los textos.
 */
beforeEach(function (): void {
    Storage::fake('documentos');

    $this->gotenberg = new GotenbergFalso;
    app()->instance(ClienteGotenberg::class, $this->gotenberg);

    $this->organizacion = comoOrganizacion();

    $marco = Marco::factory()->create(['codigo' => 'ISO-SINTETICO']);
    $sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create();
    app(GeneradorImplantaciones::class)->generar($sistema);

    $this->documento = Documento::factory()->soa()->paraSistema($sistema->id)->create();
    app(MaterializarSecciones::class)($this->documento);
});

it('congela el MARKDOWN, no el HTML', function (): void {
    app(GuardarNarrativa::class)($this->documento, ['conclusiones' => 'Cerramos con **buena nota**.']);

    $version = app(GenerarDocumento::class)->encolar($this->documento)->fresh();

    /*
     * El Markdown es lo diffeable, y es lo que contesta «¿qué frase cambió entre
     * la v3 y la v4?». El HTML es una función determinista de él.
     */
    expect($version->instantanea['textos']['conclusiones'])->toBe('Cerramos con **buena nota**.');
    expect($version->instantanea['textos']['conclusiones'])->not->toContain('<strong>');
});

it('el texto de la organización llega al HTML que se le manda a Gotenberg', function (): void {
    app(GuardarNarrativa::class)($this->documento, [
        'conclusiones' => 'El SGSI cubre el alcance declarado.',
    ]);

    app(GenerarDocumento::class)->encolar($this->documento);

    expect($this->gotenberg->html())
        ->toContain('<h2>Conclusiones</h2>')
        ->toContain('El SGSI cubre el alcance declarado.');
});

it('un hueco vacío no imprime su sección', function (): void {
    app(GenerarDocumento::class)->encolar($this->documento);

    // Un `<h2>` con nada debajo se lee como un documento roto, y la mayoría de
    // los huecos vienen vacíos de fábrica.
    expect($this->gotenberg->html())->not->toContain('<h2>Conclusiones</h2>');
});

it('una versión EMITIDA no cambia aunque se reescriban los textos', function (): void {
    app(GuardarNarrativa::class)($this->documento, ['conclusiones' => 'Lo que se entregó.']);

    $servicio = app(GenerarDocumento::class);
    $emitida = app(EmitirVersion::class)($servicio->encolar($this->documento)->fresh());

    app(GuardarNarrativa::class)($this->documento, ['conclusiones' => 'Lo que pensamos ahora.']);

    expect($emitida->fresh()->instantanea['textos']['conclusiones'])->toBe('Lo que se entregó.');
});

it('y la base impide tocar esa fila, no sólo la buena voluntad', function (): void {
    $servicio = app(GenerarDocumento::class);
    $emitida = app(EmitirVersion::class)($servicio->encolar($this->documento)->fresh());

    DocumentoVersion::query()->whereKey($emitida->id)->update(['motivo' => 'otro']);
})->throws(QueryException::class, 'Una version emitida no se modifica');

it('el título del registro manda en el documento', function (): void {
    // Hasta ahora `documentos.titulo` se editaba en el formulario y el H1 del
    // documento seguía saliendo de una constante: renombrarlo no cambiaba nada.
    $this->documento->update(['titulo' => 'Declaración de Aplicabilidad del SGSI corporativo']);

    app(GenerarDocumento::class)->encolar($this->documento->fresh());

    expect($this->gotenberg->html())->toContain('Declaración de Aplicabilidad del SGSI corporativo');
});
