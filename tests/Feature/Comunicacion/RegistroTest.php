<?php

declare(strict_types=1);

use App\Domain\Comunicacion\Enums\SentidoComunicacion;
use App\Domain\Comunicacion\Enums\TipoRetroalimentacion;
use App\Domain\Comunicacion\Excepciones\ComunicacionInvalida;
use App\Domain\Comunicacion\Models\Comunicacion;
use App\Domain\Comunicacion\Models\ComunicacionPrevista;
use App\Domain\Comunicacion\RegistrarComunicacion;
use App\Domain\Comunicacion\RegistroComunicacion;
use App\Domain\Contexto\Models\ParteInteresada;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Lo comunicado y lo recibido
|--------------------------------------------------------------------------
|
| Lo recibido es la retroalimentación de la 9.3.2 e): lleva su tipo, puede
| llevar respuesta, y **no cumple ningún plan**. Lo emitido no lleva ni lo uno
| ni lo otro.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

it('registra lo recibido con su tipo, su parte interesada y su respuesta', function (): void {
    $parte = ParteInteresada::factory()->create();

    $this->actingAs($this->usuario)
        ->post('/comunicaciones', [
            'sentido' => 'recibida',
            'fecha' => Carbon::today()->toDateString(),
            'asunto' => 'Queja por el tiempo de respuesta del soporte',
            'tipo_recibida' => 'queja',
            'parte_interesada_id' => $parte->id,
            'canal' => 'correo',
            'respuesta' => 'Se amplía el horario del soporte.',
        ])
        ->assertRedirect('/comunicaciones')
        ->assertSessionHasNoErrors();

    $recibida = Comunicacion::query()->sole();

    expect($recibida->sentido)->toBe(SentidoComunicacion::Recibida)
        ->and($recibida->tipo_recibida)->toBe(TipoRetroalimentacion::Queja)
        ->and($recibida->parte_interesada_id)->toBe($parte->id)
        ->and($recibida->registrada_por_id)->toBe($this->usuario->id);
});

it('lo recibido necesita su tipo, y lo emitido no puede llevarlo', function (): void {
    $this->actingAs($this->usuario)
        ->post('/comunicaciones', [
            'sentido' => 'recibida',
            'fecha' => Carbon::today()->toDateString(),
            'asunto' => 'Algo',
            'canal' => 'correo',
        ])
        ->assertSessionHasErrors('tipo_recibida');

    $this->actingAs($this->usuario)
        ->post('/comunicaciones', [
            'sentido' => 'emitida',
            'fecha' => Carbon::today()->toDateString(),
            'asunto' => 'Algo',
            'canal' => 'correo',
            'tipo_recibida' => 'queja',
        ])
        ->assertSessionHasErrors('tipo_recibida');
});

it('lo recibido no cumple ningún plan', function (): void {
    $prevista = ComunicacionPrevista::factory()->cada(3)->create();

    expect(fn () => app(RegistrarComunicacion::class)(
        SentidoComunicacion::Recibida,
        Carbon::today(),
        ['asunto' => 'x', 'tipo_recibida' => 'queja', 'canal' => 'correo'],
        $this->usuario,
        $prevista,
    ))->toThrow(ComunicacionInvalida::class, 'no cumple ningún plan');
});

it('lo emitido suelto puede cumplir una línea del plan', function (): void {
    $prevista = ComunicacionPrevista::factory()->cada(6, Carbon::today()->subMonths(7))->create();

    $this->actingAs($this->usuario)
        ->post('/comunicaciones', [
            'sentido' => 'emitida',
            'fecha' => Carbon::today()->toDateString(),
            'asunto' => 'Circular de seguridad',
            'canal' => 'intranet',
            'comunicacion_prevista_id' => $prevista->id,
        ])
        ->assertSessionHasNoErrors();

    expect($prevista->refresh()->vencida())->toBeFalse();
});

it('la edición no mueve la fecha ni la línea del plan', function (): void {
    $prevista = ComunicacionPrevista::factory()->cada(3)->create();
    $hecha = app(RegistrarComunicacion::class)(SentidoComunicacion::Emitida, Carbon::today()->subDays(3), ['asunto' => 'Antes'], $this->usuario, $prevista);
    $cubre = $hecha->cubre_hasta?->toDateString();

    $this->actingAs($this->usuario)
        ->put("/comunicaciones/{$hecha->id}", [
            'asunto' => 'Después',
            'fecha' => Carbon::today()->subYear()->toDateString(),
            'comunicacion_prevista_id' => '__ninguno__',
        ])
        ->assertRedirect();

    $hecha->refresh();

    expect($hecha->asunto)->toBe('Después')
        ->and($hecha->fecha->toDateString())->toBe(Carbon::today()->subDays(3)->toDateString())
        ->and($hecha->comunicacion_prevista_id)->toBe($prevista->id)
        ->and($hecha->cubre_hasta?->toDateString())->toBe($cubre);
});

it('lo recibido sin respuesta se cuenta igual que lo filtra la tabla, y no es rojo', function (): void {
    Comunicacion::factory()->recibida()->create();
    Comunicacion::factory()->recibida(TipoRetroalimentacion::Sugerencia, 'Hecho.')->create();
    Comunicacion::factory()->create();

    $indicador = app(RegistroComunicacion::class)->recibidasSinRespuesta();

    expect($indicador->valor)->toBe(1)
        ->and($indicador->tono)->not->toBe('caducada');

    $this->actingAs($this->usuario)
        ->get('/comunicaciones?filter[sin_respuesta]=1')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('comunicaciones/Index')
            ->has('filas', 1));
});

it('la base rechaza lo incoherente aunque llegue por SQL', function (string $columnas): void {
    $comunicacion = Comunicacion::factory()->create();

    expect(fn () => DB::transaction(fn () => DB::update("update comunicaciones set {$columnas} where id = ?", [$comunicacion->id])))
        ->toThrow(QueryException::class);
})->with([
    'recibida sin tipo' => "sentido = 'recibida'",
    'emitida con tipo' => "tipo_recibida = 'queja'",
    'emitida con respuesta' => "respuesta = 'x'",
    'cubre sin plan' => 'cubre_hasta = fecha + 30',
]);
