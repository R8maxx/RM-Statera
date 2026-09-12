<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Documento\Contenido\DeclaracionAplicabilidadIso;
use App\Domain\Documento\Models\Documento;
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

/**
 * La ruta que escribía huecos sueltos se retiró con el editor del documento
 * entero; quien impide inventarse un hueco por la ruta de la plantilla es el
 * `FormRequest`, que construye sus reglas desde el enum, y eso lo fija
 * `PlantillaNarrativaTest`. Aquí queda la última capa, que es la que no depende
 * de que ningún PHP se acuerde de comprobarlo.
 */
it('la base no admite un hueco inventado', function (): void {
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

it('restablecer una sección inventada de la plantilla responde 404', function (): void {
    $this->actingAs(usuarioCon(Rol::ResponsableSeguridad))
        ->delete('/plantillas-documento/soa_iso/limitaciones')
        ->assertNotFound();
});

it('rechaza el HTML en vez de escaparlo en silencio', function (): void {
    // Escaparlo dejaría un `<b>` impreso en el PDF del auditor y quien lo
    // escribió no sabría de dónde ha salido. La regla `SinHtml` sigue guardando
    // la puerta que queda: los textos base de la organización.
    $this->actingAs(usuarioCon(Rol::ResponsableSeguridad))
        ->put('/plantillas-documento/soa_iso', ['conclusiones' => '<b>hola</b>'])
        ->assertSessionHasErrors('conclusiones');
});
