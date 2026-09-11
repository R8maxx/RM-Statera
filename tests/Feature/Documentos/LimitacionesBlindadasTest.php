<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Documento\Contenido\DeclaracionAplicabilidadIso;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoSeccion;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Documento\Narrativa\GuardarNarrativa;
use App\Domain\Documento\Narrativa\MaterializarSecciones;
use App\Domain\Implantacion\GeneradorImplantaciones;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Las limitaciones del sistema no se pueden borrar desde ninguna parte.
 *
 * Son las frases que un usuario va a querer quitar —«este documento no lleva
 * aprobación formal», «falta el análisis de riesgos»— y son exactamente las que
 * no deben poder quitarse: un auditor respeta una limitación declarada y
 * suspende una inventada.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();

    $marco = Marco::factory()->create(['codigo' => 'ISO-SINTETICO']);
    $sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create();
    app(GeneradorImplantaciones::class)->generar($sistema);

    $this->documento = Documento::factory()->soa()->paraSistema($sistema->id)->create();
    app(MaterializarSecciones::class)($this->documento);

    $this->usuario = usuarioCon();

    $this->contenido = fn () => app(DeclaracionAplicabilidadIso::class)->construir(
        $this->documento->fresh(),
        DocumentoVersion::factory()->delDocumento($this->documento->id)->make(),
    );
});

it('salen siempre, con o sin limitaciones propias', function (): void {
    $sinPropias = implode(' ', ($this->contenido)()->limitaciones);

    app(GuardarNarrativa::class)($this->documento, [
        'limitaciones_propias' => 'El alcance excluye las oficinas comerciales.',
    ]);

    $conPropias = implode(' ', ($this->contenido)()->limitaciones);

    foreach (['módulo de riesgos', 'no implementa un flujo de aprobación', 'derechos de autor'] as $frase) {
        expect($sinPropias)->toContain($frase);
        expect($conPropias)->toContain($frase);
    }
});

it('las propias van en su propio bloque, debajo y separadas', function (): void {
    app(GuardarNarrativa::class)($this->documento, [
        'limitaciones_propias' => 'El alcance excluye las oficinas comerciales.',
    ]);

    $contenido = ($this->contenido)();

    // Saber qué declara la herramienta y qué declara la organización es parte
    // de lo que se está declarando: no se mezclan en la misma lista.
    expect(implode(' ', $contenido->limitaciones))
        ->not->toContain('oficinas comerciales');

    expect($contenido->textos->html('limitaciones_propias'))
        ->toContain('oficinas comerciales');
});

it('no se puede escribir en un hueco que no existe, ni por la ruta', function (): void {
    $this->actingAs($this->usuario)
        ->put("/documentos/{$this->documento->id}/textos", [
            'limitaciones' => 'Esto no debería entrar.',
            'limitaciones_sistema' => 'Ni esto.',
            'inventada' => 'Ni esto tampoco.',
        ])
        ->assertRedirect();

    // Las claves no están declaradas en el `FormRequest`, que construye sus
    // reglas desde el enum: no llegan a `validated()` y no se escriben.
    expect(DocumentoSeccion::query()->whereIn('seccion', ['limitaciones', 'limitaciones_sistema', 'inventada'])->exists())
        ->toBeFalse();
});

it('y la base tampoco las admite', function (): void {
    DB::table('documento_secciones')->insert([
        'organizacion_id' => $this->organizacion->id,
        'documento_id' => $this->documento->id,
        'seccion' => 'limitaciones',
        'contenido_md' => 'Intento de suplantar las del sistema.',
        'origen' => 'propio',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('restablecer una sección inventada responde 404', function (): void {
    $this->actingAs($this->usuario)
        ->delete("/documentos/{$this->documento->id}/textos/limitaciones")
        ->assertNotFound();
});

it('rechaza el HTML en vez de escaparlo en silencio', function (): void {
    // Escaparlo dejaría un `<b>` impreso en el PDF del auditor y quien lo
    // escribió no sabría de dónde ha salido.
    $this->actingAs($this->usuario)
        ->put("/documentos/{$this->documento->id}/textos", ['conclusiones' => '<b>hola</b>'])
        ->assertSessionHasErrors('conclusiones');
});
