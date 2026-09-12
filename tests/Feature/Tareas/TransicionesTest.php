<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Tarea\CambiarEstadoTarea;
use App\Domain\Tarea\CrearTarea;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Excepciones\TransicionDeTareaNoPermitida;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Tarea\Models\TareaTransicion;
use App\Domain\Tarea\VincularTarea;
use Illuminate\Support\Carbon;

/**
 * El estado de una tarea lleva histórico, como el de una implantación.
 *
 * El auditor no pregunta si la tarea está cerrada, pregunta desde cuándo — y en
 * el caso de una descartada, por qué (invariante 7).
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

it('el alta deja su propia línea de histórico', function (): void {
    $tarea = app(CrearTarea::class)(['titulo' => 'Revisar la política de contraseñas'], $this->usuario);

    $transicion = TareaTransicion::query()->where('tarea_id', $tarea->id)->sole();

    expect($transicion->estado_anterior)->toBeNull()
        ->and($transicion->estado_nuevo)->toBe(EstadoTarea::Pendiente)
        ->and($transicion->usuario_id)->toBe($this->usuario->id);
});

it('cerrar pone la fecha de cierre y reabrir la quita', function (): void {
    $tarea = Tarea::factory()->create();

    $tarea = app(CambiarEstadoTarea::class)($tarea, EstadoTarea::Hecha, $this->usuario);

    expect($tarea->fecha_cierre?->toDateString())->toBe(Carbon::today()->toDateString());

    $tarea = app(CambiarEstadoTarea::class)($tarea, EstadoTarea::EnCurso, $this->usuario);

    expect($tarea->fecha_cierre)->toBeNull();
});

it('descartar sin decir por qué no se permite', function (): void {
    $tarea = Tarea::factory()->create();

    expect(fn () => app(CambiarEstadoTarea::class)($tarea, EstadoTarea::Descartada, $this->usuario))
        ->toThrow(TransicionDeTareaNoPermitida::class);

    // Y no ha dejado la tarea a medias.
    expect($tarea->fresh()->estado)->toBe(EstadoTarea::Pendiente)
        ->and($tarea->fresh()->fecha_cierre)->toBeNull();
});

it('descartar con motivo lo guarda en el histórico', function (): void {
    $tarea = Tarea::factory()->create();

    app(CambiarEstadoTarea::class)($tarea, EstadoTarea::Descartada, $this->usuario, 'El servicio se retira en octubre.');

    expect($tarea->fresh()->estado)->toBe(EstadoTarea::Descartada)
        ->and(TareaTransicion::query()->where('tarea_id', $tarea->id)->latest('id')->sole()->nota)
        ->toBe('El servicio se retira en octubre.');
});

it('una transición que la máquina no permite no cambia nada', function (): void {
    $tarea = Tarea::factory()->enEstado(EstadoTarea::Hecha)->create();

    expect(fn () => app(CambiarEstadoTarea::class)($tarea, EstadoTarea::Descartada, $this->usuario, 'ya no'))
        ->toThrow(TransicionDeTareaNoPermitida::class);

    expect($tarea->fresh()->estado)->toBe(EstadoTarea::Hecha)
        ->and(TareaTransicion::query()->where('tarea_id', $tarea->id)->count())->toBe(0);
});

it('cambiar al mismo estado no ensucia el histórico', function (): void {
    $tarea = Tarea::factory()->create();

    app(CambiarEstadoTarea::class)($tarea, EstadoTarea::Pendiente, $this->usuario);

    expect(TareaTransicion::query()->where('tarea_id', $tarea->id)->count())->toBe(0);
});

it('una tarea nace vinculada al requisito del que sale', function (): void {
    $marco = Marco::factory()->create(['codigo' => 'ISO-SINTETICO']);
    $sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create();
    $requisito = Requisito::factory()->conCodigo('A.5.1')->create(['marco_id' => $marco->id]);

    $implantacion = Implantacion::factory()->create([
        'organizacion_id' => $this->organizacion->id,
        'sistema_id' => $sistema->id,
        'requisito_id' => $requisito->id,
    ]);

    $tarea = app(CrearTarea::class)->desdeImplantacion(
        $implantacion,
        ['titulo' => 'Redactar la política', 'origen' => OrigenTarea::BrechaImplantacion->value],
        $this->usuario,
    );

    expect($tarea->implantaciones()->pluck('implantaciones.id')->all())->toBe([$implantacion->id])
        ->and($tarea->origen)->toBe(OrigenTarea::BrechaImplantacion);
});

/**
 * La misma tarea hace avanzar un control de ISO y tres medidas del ENS a la vez.
 * Es el argumento del producto, y por eso el vínculo es N:M.
 */
it('la misma tarea sirve a requisitos de dos marcos', function (): void {
    $iso = Marco::factory()->create(['codigo' => 'ISO-SINTETICO']);
    $ens = Marco::factory()->create(['codigo' => 'ENS-SINTETICO']);

    $implantaciones = collect([$iso, $ens])->map(function (Marco $marco): Implantacion {
        $sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create();
        $requisito = Requisito::factory()->conCodigo($marco->codigo === 'ISO-SINTETICO' ? 'A.5.15' : 'op.acc.2')
            ->create(['marco_id' => $marco->id]);

        return Implantacion::factory()->create([
            'organizacion_id' => $this->organizacion->id,
            'sistema_id' => $sistema->id,
            'requisito_id' => $requisito->id,
        ]);
    });

    $tarea = app(CrearTarea::class)(
        ['titulo' => 'Revisar el control de acceso'],
        $this->usuario,
        $implantaciones->all(),
    );

    expect($tarea->implantaciones)->toHaveCount(2);
});

it('vincular dos veces lo mismo no es un error', function (): void {
    $marco = Marco::factory()->create(['codigo' => 'ISO-SINTETICO']);
    $sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create();
    $requisito = Requisito::factory()->conCodigo('A.5.1')->create(['marco_id' => $marco->id]);
    $implantacion = Implantacion::factory()->create([
        'organizacion_id' => $this->organizacion->id,
        'sistema_id' => $sistema->id,
        'requisito_id' => $requisito->id,
    ]);

    $tarea = Tarea::factory()->create();

    app(VincularTarea::class)->vincular($tarea, $implantacion, $this->usuario);
    app(VincularTarea::class)->vincular($tarea, $implantacion, $this->usuario);

    expect($tarea->implantaciones()->count())->toBe(1);
});
