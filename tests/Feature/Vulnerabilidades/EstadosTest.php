<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Vulnerabilidad\Enums\EstadoVulnerabilidad;
use App\Domain\Vulnerabilidad\Models\Vulnerabilidad;

/*
|--------------------------------------------------------------------------
| Las transiciones de una vulnerabilidad
|--------------------------------------------------------------------------
|
| Mitigada no es cerrada: cerrar exige verificación escrita. Aceptar es de
| supervisión y exige motivo. Volver atrás exige motivo. Y todo deja histórico.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->responsable = usuarioCon(Rol::ResponsableSeguridad);
    $this->tecnico = usuarioCon(Rol::Tecnico);
    $this->vulnerabilidad = Vulnerabilidad::factory()->create();

    $this->mover = fn ($quien, string $estado, ?string $nota = null) => $this->actingAs($quien)
        ->post("/vulnerabilidades/{$this->vulnerabilidad->id}/estado", ['estado' => $estado, 'nota' => $nota]);
});

it('recorre abierta, en remediación, mitigada y cerrada con verificación', function (): void {
    ($this->mover)($this->tecnico, 'en_remediacion')->assertSessionHasNoErrors();
    ($this->mover)($this->tecnico, 'mitigada')->assertSessionHasNoErrors();
    ($this->mover)($this->tecnico, 'cerrada')->assertSessionHasErrors('nota');
    ($this->mover)($this->tecnico, 'cerrada', 'Reescaneo del 2026-09-20 limpio.')->assertSessionHasNoErrors();

    $fresca = $this->vulnerabilidad->fresh();

    expect($fresca?->estado)->toBe(EstadoVulnerabilidad::Cerrada)
        ->and($fresca?->verificacion)->toBe('Reescaneo del 2026-09-20 limpio.')
        ->and($fresca?->verificada_por_id)->toBe($this->tecnico->id)
        ->and($fresca?->transiciones()->count())->toBe(3);
});

it('el técnico no acepta; el responsable sí, con motivo', function (): void {
    ($this->mover)($this->tecnico, 'aceptada', 'No hay parche.')->assertSessionHasErrors('nota');
    expect($this->vulnerabilidad->fresh()?->estado)->toBe(EstadoVulnerabilidad::Abierta);

    ($this->mover)($this->responsable, 'aceptada')->assertSessionHasErrors('nota');
    ($this->mover)($this->responsable, 'aceptada', 'Sin parche del fabricante; el servicio está aislado.')->assertSessionHasNoErrors();

    $fresca = $this->vulnerabilidad->fresh();

    expect($fresca?->estado)->toBe(EstadoVulnerabilidad::Aceptada)
        ->and($fresca?->aceptada_por_id)->toBe($this->responsable->id);
});

it('reabrir una aceptada borra la firma de la aceptación', function (): void {
    ($this->mover)($this->responsable, 'aceptada', 'Asumido.');
    ($this->mover)($this->responsable, 'abierta')->assertSessionHasErrors('nota');
    ($this->mover)($this->responsable, 'abierta', 'Salió el parche.')->assertSessionHasNoErrors();

    $fresca = $this->vulnerabilidad->fresh();

    expect($fresca?->estado)->toBe(EstadoVulnerabilidad::Abierta)
        ->and($fresca?->motivo_aceptacion)->toBeNull()
        ->and($fresca?->aceptada_en)->toBeNull();
});

it('devolver una mitigada a remediación exige motivo', function (): void {
    ($this->mover)($this->tecnico, 'mitigada');
    ($this->mover)($this->tecnico, 'en_remediacion')->assertSessionHasErrors('nota');
    ($this->mover)($this->tecnico, 'en_remediacion', 'El reescaneo la sigue encontrando.')->assertSessionHasNoErrors();
});

it('no salta estados que la máquina no admite', function (): void {
    ($this->mover)($this->tecnico, 'cerrada', 'Verificado.')->assertSessionHasErrors('nota');

    expect($this->vulnerabilidad->fresh()?->estado)->toBe(EstadoVulnerabilidad::Abierta);
});

it('mitigada sale del plazo: ya se aplicó el arreglo', function (): void {
    $this->vulnerabilidad->forceFill(['fecha_limite' => today()->subDay()])->save();
    expect(Vulnerabilidad::query()->fueraDePlazo()->count())->toBe(1);

    ($this->mover)($this->tecnico, 'mitigada');

    expect(Vulnerabilidad::query()->fueraDePlazo()->count())->toBe(0)
        ->and(Vulnerabilidad::query()->sinVerificar()->count())->toBe(1);
});

it('el auditor no mueve nada', function (): void {
    ($this->mover)(usuarioCon(Rol::Auditor), 'en_remediacion')->assertForbidden();
});
