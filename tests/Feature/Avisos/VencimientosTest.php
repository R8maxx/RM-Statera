<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Aviso\Notifications\EvidenciasQueVencen;
use App\Domain\Aviso\ResumenVencimientos;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * El primer aviso que da la herramienta.
 *
 * Evidencias guarda `fecha_caducidad` y `periodicidad_renovacion` desde el
 * principio y el panel cuenta las caducadas, pero nadie se enteraba si no entraba
 * a mirar. Una evidencia caducada no prueba nada: el requisito que sostenía se
 * queda sin prueba, y eso es un hallazgo que se encuentra solo.
 */
beforeEach(function (): void {
    Notification::fake();

    $this->organizacion = comoOrganizacion();
    $this->responsable = usuarioCon(Rol::ResponsableSeguridad);
    $this->tecnico = usuarioCon(Rol::Tecnico);
});

it('reparte lo caducado de lo que está por caducar', function (): void {
    Evidencia::factory()->create(['fecha_caducidad' => Carbon::today()->subDays(4), 'titulo' => 'Certificado vencido']);
    Evidencia::factory()->create(['fecha_caducidad' => Carbon::today()->addDays(10), 'titulo' => 'Captura del IdP']);

    // Fuera de la ventana: ni caducada ni inminente.
    Evidencia::factory()->create(['fecha_caducidad' => Carbon::today()->addMonths(6)]);

    // Sin caducidad: no vence nunca, no es asunto de este aviso.
    Evidencia::factory()->create(['fecha_caducidad' => null]);

    $vencimientos = app(ResumenVencimientos::class)();

    expect($vencimientos->caducadas)->toHaveCount(1)
        ->and($vencimientos->porCaducar)->toHaveCount(1)
        ->and($vencimientos->caducadas[0]->titulo)->toBe('Certificado vencido')
        ->and($vencimientos->caducadas[0]->cuando())->toBe('caducó hace 4 días')
        ->and($vencimientos->porCaducar[0]->cuando())->toBe('caduca en 10 días');
});

it('la que caduca hoy cuenta como por caducar, no como caducada', function (): void {
    Evidencia::factory()->create(['fecha_caducidad' => Carbon::today()]);

    $vencimientos = app(ResumenVencimientos::class)();

    expect($vencimientos->caducadas)->toBeEmpty()
        ->and($vencimientos->porCaducar)->toHaveCount(1)
        ->and($vencimientos->porCaducar[0]->cuando())->toBe('caduca hoy');
});

it('avisa al responsable de seguridad y no al técnico', function (): void {
    Evidencia::factory()->create(['fecha_caducidad' => Carbon::today()->subDay()]);

    $this->artisan('avisos:enviar')->assertSuccessful();

    Notification::assertSentTo($this->responsable, EvidenciasQueVencen::class);
    Notification::assertNotSentTo($this->tecnico, EvidenciasQueVencen::class);
});

it('sin nada que vencer no manda nada', function (): void {
    Evidencia::factory()->create(['fecha_caducidad' => null]);
    Evidencia::factory()->create(['fecha_caducidad' => Carbon::today()->addYear()]);

    $this->artisan('avisos:enviar')->assertSuccessful();

    Notification::assertNothingSent();
});

/**
 * La prioridad 2 de cobertura del proyecto, y aquí con un agravante: un aviso
 * cruzado no se queda en la pantalla de quien mira, sale por correo fuera de la
 * herramienta y ya no se puede recoger.
 */
it('la evidencia de una organización no aparece en el aviso de otra', function (): void {
    $ajena = Organizacion::factory()->create();

    comoOrganizacion($ajena);
    Evidencia::factory()->create([
        'fecha_caducidad' => Carbon::today()->subDay(),
        'titulo' => 'Contrato de la otra organización',
    ]);
    $suyo = usuarioCon(Rol::ResponsableSeguridad, $ajena);

    comoOrganizacion($this->organizacion);
    Evidencia::factory()->create(['fecha_caducidad' => Carbon::today()->subDay(), 'titulo' => 'Contrato propio']);

    $this->artisan('avisos:enviar')->assertSuccessful();

    Notification::assertSentTo(
        $this->responsable,
        fn (EvidenciasQueVencen $aviso): bool => str_contains(
            (string) json_encode($aviso->toMail($this->responsable)->toArray()),
            'Contrato propio',
        ) && ! str_contains(
            (string) json_encode($aviso->toMail($this->responsable)->toArray()),
            'Contrato de la otra organización',
        ),
    );

    Notification::assertSentTo($suyo, EvidenciasQueVencen::class);
});

/**
 * Un comando programado no tiene petición ni usuario, así que sin contexto de
 * organización el scope no devuelve nada y RLS deniega por defecto: el comando no
 * revienta, sencillamente no ve nada. Y un aviso que no salta es idéntico a no
 * tener nada que avisar.
 */
it('ve las evidencias aunque arranque sin contexto de organización', function (): void {
    Evidencia::factory()->create(['fecha_caducidad' => Carbon::today()->subDay()]);

    app(ContextoOrganizacion::class)->olvidar();

    $this->artisan('avisos:enviar')->assertSuccessful();

    Notification::assertSentTo($this->responsable, EvidenciasQueVencen::class);
});

it('el resumen no toca la organización activa al terminar', function (): void {
    $contexto = app(ContextoOrganizacion::class);

    Evidencia::factory()->create(['fecha_caducidad' => Carbon::today()->subDay()]);

    $this->artisan('avisos:enviar')->assertSuccessful();

    expect($contexto->id())->toBe($this->organizacion->id);
});

it('--dry-run cuenta pero no envía', function (): void {
    Evidencia::factory()->create(['fecha_caducidad' => Carbon::today()->subDay()]);

    $this->artisan('avisos:enviar', ['--dry-run' => true])->assertSuccessful();

    Notification::assertNothingSent();
});
