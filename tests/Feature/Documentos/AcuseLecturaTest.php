<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Documento\AcusarLectura;
use App\Domain\Documento\CoberturaAcuse;
use App\Domain\Documento\Excepciones\AprobacionNoPermitida;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| El acuse de lectura (ISO 7.3, org.2)
|--------------------------------------------------------------------------
|
| La diferencia entre «la política está publicada» y «la política se conoce», que
| es la única de las dos que la norma exige.
|
| **Cuelga de la VERSIÓN, no del documento.** Quien acusó recibo de la v3 no ha
| leído la v4, y arrastrar el acuse anterior convertiría el registro en un trámite
| que se pasa solo — exactamente lo que la cláusula quiere evitar.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->documento = Documento::factory()->politica()->create();

    $this->vigente = DocumentoVersion::factory()
        ->delDocumento($this->documento->id)
        ->emitida()
        ->create();
});

it('acusar es idempotente y no reescribe la fecha', function (): void {
    $lector = usuarioCon(Rol::Tecnico);
    $acusar = app(AcusarLectura::class);

    $primera = $acusar($this->vigente, $lector);
    $segunda = $acusar($this->vigente->fresh(), $lector);

    expect($segunda->id)->toBe($primera->id)
        ->and($segunda->acusada_en->toDateTimeString())->toBe($primera->acusada_en->toDateTimeString())
        ->and($this->vigente->lecturas()->count())->toBe(1);
});

/**
 * Un borrador se regenera, así que acusar su lectura dejaría una fila diciendo
 * que alguien leyó un documento que ya no existe. Lo que la cláusula 7.3 pide es
 * que la gente conozca lo que está **vigente**.
 */
it('no se acusa la lectura de algo que no está aprobado', function (): void {
    $borrador = DocumentoVersion::factory()->delDocumento($this->documento->id)->generada()->create();

    app(AcusarLectura::class)($borrador, usuarioCon(Rol::Tecnico));
})->throws(AprobacionNoPermitida::class);

it('los pendientes salen de restar, no de una lista guardada', function (): void {
    $lector = usuarioCon(Rol::Tecnico);
    $otro = User::factory()->create([
        'organizacion_id' => $this->organizacion->id,
        'name' => 'Sin leer todavía',
    ]);

    app(AcusarLectura::class)($this->vigente, $lector);

    $cobertura = app(CoberturaAcuse::class)->de($this->vigente->fresh());

    expect($cobertura['acusados'])->toBe(1)
        ->and($cobertura['pendientes'])->toContain($otro->name)
        ->and($cobertura['pendientes'])->not->toContain($lector->name)
        // La cifra va SIEMPRE con su denominador: «1» no dice lo mismo que
        // «1 de 12».
        ->and($cobertura['total'])->toBe(count($cobertura['pendientes']) + $cobertura['acusados']);
});

/**
 * `User` no lleva el scope de organización —la autenticación tiene que poder
 * encontrar a alguien antes de saber de qué organización es—, así que aquí no hay
 * scope global ni RLS que tapen el cruce y la consulta se acota a mano. Sin eso,
 * el acuse de un cliente listaría por su nombre a los empleados de otro.
 */
it('no cuenta como destinatario a nadie de otra organización', function (): void {
    $ajena = Organizacion::factory()->create();
    User::factory()->create(['organizacion_id' => $ajena->id, 'name' => 'De otro cliente']);

    $cobertura = app(CoberturaAcuse::class)->de($this->vigente);

    expect($cobertura['pendientes'])->not->toContain('De otro cliente');
});

it('la ficha manda el acuse sólo cuando el documento lo exige', function (): void {
    $usuario = usuarioCon(Rol::ResponsableSeguridad);

    $props = $this->actingAs($usuario)
        ->get("/documentos/{$this->documento->id}")
        ->viewData('page')['props'];

    expect($props['acuse'])->not->toBeNull()
        ->and($props['acuse']['yaAcusado'])->toBeFalse();

    // Y en un documento que no lo exige, el bloque no viaja: conectar dos cosas
    // abre una puerta lateral si quien pinta decide también qué se manda.
    $this->documento->update(['exige_acuse' => false]);

    $sinAcuse = $this->actingAs($usuario)
        ->get("/documentos/{$this->documento->id}")
        ->viewData('page')['props'];

    expect($sinAcuse['acuse'])->toBeNull();
});

it('se acusa por la ruta de verdad y queda registrado', function (): void {
    $lector = usuarioCon(Rol::Tecnico);

    $this->actingAs($lector)
        ->post("/documentos/{$this->documento->id}/versiones/{$this->vigente->id}/acuse")
        ->assertRedirect("/documentos/{$this->documento->id}");

    expect(app(CoberturaAcuse::class)->loHaAcusado($this->vigente->fresh(), $lector))->toBeTrue();
});

/**
 * Aprobar una versión nueva **no arrastra los acuses de la anterior**. Es toda la
 * razón por la que el acuse cuelga de la versión: si se heredara, nadie volvería
 * a leer nada y el registro diría que sí.
 */
it('una versión nueva empieza sin acuses', function (): void {
    app(AcusarLectura::class)($this->vigente, usuarioCon(Rol::Tecnico));

    // La anterior se jubila: sólo hay una aprobada viva por documento, y lo
    // impone un índice único parcial.
    DocumentoVersion::query()->whereKey($this->vigente->id)->update([
        'estado' => 'obsoleto',
        'obsoleta_en' => now()->toDateString(),
    ]);

    $siguiente = DocumentoVersion::factory()
        ->delDocumento($this->documento->id)
        ->emitida(2)
        ->create();

    expect($siguiente->lecturas()->count())->toBe(0);
});
