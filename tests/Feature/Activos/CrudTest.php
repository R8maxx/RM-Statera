<?php

declare(strict_types=1);

use App\Domain\Activo\Enums\Clasificacion;
use App\Domain\Activo\Enums\EstadoCicloVida;
use App\Domain\Activo\Enums\EstadoControl;
use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Activo\Models\Activo;
use App\Domain\Activo\RegistrarDependencia;
use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Http\Requests\Concerns\SeleccionVacia;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Inventario de activos, de punta a punta
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->sistema = Sistema::factory()->de($this->organizacion)->create(['codigo' => 'SIS-01']);

    $this->valido = [
        'codigo' => 'SRV-01',
        'nombre' => 'Sede electrónica',
        'tipo' => TipoActivo::Servicios->value,
        'estado_ciclo_vida' => EstadoCicloVida::EnProduccion->value,
        'clasificacion' => Clasificacion::UsoInterno->value,
        'cifrado' => EstadoControl::Si->value,
        'copia_seguridad' => EstadoControl::PorConfirmar->value,
        'propietario_id' => SeleccionVacia::VALOR,
        'custodio_id' => SeleccionVacia::VALOR,
        'valor_c' => NivelDimension::Bajo->value,
        'valor_i' => NivelDimension::Bajo->value,
        'valor_d' => NivelDimension::Alto->value,
        'valor_a' => NivelDimension::Na->value,
        'valor_t' => NivelDimension::Na->value,
        'sistemas' => [],
    ];
});

it('sirve el formulario de alta con las opciones resueltas', function (): void {
    $this->actingAs($this->usuario)
        ->get('/activos/crear')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('activos/Formulario')
            ->where('activo', null)
            ->has('tipos', count(TipoActivo::cases()))
            ->has('estados', count(EstadoCicloVida::cases()))
            ->has('niveles', count(NivelDimension::cases()))
            ->has('clasificaciones', count(Clasificacion::cases()))
            ->has('controles', count(EstadoControl::cases()))
            ->has('dimensiones', 5)
            ->has('sistemas', 1)
            // El mapa de fin de soporte, para proponer la fecha al elegir SO.
            ->has('sistemasOperativos')
        );
});

it('da de alta un activo y le pone la organización activa sin que nadie la envíe', function (): void {
    $this->actingAs($this->usuario)
        ->post('/activos', $this->valido)
        ->assertRedirect();

    $activo = Activo::query()->where('codigo', 'SRV-01')->firstOrFail();

    expect($activo->organizacion_id)->toBe($this->organizacion->id)
        ->and($activo->tipo)->toBe(TipoActivo::Servicios)
        ->and($activo->valoracion()->disponibilidad)->toBe(NivelDimension::Alto)
        ->and($activo->clasificacion)->toBe(Clasificacion::UsoInterno)
        ->and($activo->cifrado)->toBe(EstadoControl::Si)
        // «Por confirmar» se guarda tal cual, no se colapsa en «no».
        ->and($activo->copia_seguridad)->toBe(EstadoControl::PorConfirmar);
});

it('declara el activo en el alcance de varios sistemas a la vez', function (): void {
    $otro = Sistema::factory()->de($this->organizacion)->create(['codigo' => 'SGSI-01']);

    $this->actingAs($this->usuario)
        ->post('/activos', [...$this->valido, 'sistemas' => [$this->sistema->id, $otro->id]])
        ->assertRedirect();

    $activo = Activo::query()->where('codigo', 'SRV-01')->firstOrFail();

    expect($activo->sistemas->pluck('codigo')->sort()->values()->all())->toBe(['SGSI-01', 'SIS-01']);
    // La pivote es datos propios como cualquier otra tabla: sin
    // `organizacion_id` RLS la habría rechazado.
    expect($activo->sistemas->first()?->getRelationValue('pivot')?->getAttribute('organizacion_id'))
        ->toBe($this->organizacion->id);
});

it('rechaza el alta sin los campos obligatorios', function (): void {
    $this->actingAs($this->usuario)
        ->post('/activos', [])
        ->assertSessionHasErrors([
            'codigo', 'nombre', 'tipo', 'estado_ciclo_vida', 'valor_c',
            'clasificacion', 'cifrado', 'copia_seguridad',
        ]);
});

it('rechaza un código repetido dentro de la organización', function (): void {
    Activo::factory()->de($this->organizacion)->create(['codigo' => 'SRV-01']);

    $this->actingAs($this->usuario)
        ->post('/activos', $this->valido)
        ->assertSessionHasErrors('codigo');
});

it('deja repetir el código en otra organización', function (): void {
    // Con RLS activo hay que estar dentro de la otra organización para escribir
    // en ella: la política deniega por defecto, no sólo al leer.
    $ajena = Organizacion::factory()->create();
    comoOrganizacion($ajena);
    Activo::factory()->de($ajena)->create(['codigo' => 'SRV-01']);
    comoOrganizacion($this->organizacion);

    $this->actingAs($this->usuario)
        ->post('/activos', $this->valido)
        ->assertSessionHasNoErrors();
});

it('exige fecha de baja para retirar un activo', function (): void {
    $this->actingAs($this->usuario)
        ->post('/activos', [
            ...$this->valido,
            'estado_ciclo_vida' => EstadoCicloVida::Retirado->value,
        ])
        ->assertSessionHasErrors('fecha_baja');
});

it('no deja dar de baja un activo sin constancia del borrado seguro', function (): void {
    // `mp.si.5`: lo que se exige no es retirar el soporte, es poder demostrar
    // qué se hizo con lo que había dentro.
    $this->actingAs($this->usuario)
        ->post('/activos', [
            ...$this->valido,
            'estado_ciclo_vida' => EstadoCicloVida::DadoDeBaja->value,
            'fecha_baja' => Carbon::today()->toDateString(),
        ])
        ->assertSessionHasErrors('borrado_seguro_en');
});

it('acepta la baja con la constancia del borrado', function (): void {
    $this->actingAs($this->usuario)
        ->post('/activos', [
            ...$this->valido,
            'estado_ciclo_vida' => EstadoCicloVida::DadoDeBaja->value,
            'fecha_baja' => Carbon::today()->toDateString(),
            'borrado_seguro_en' => Carbon::now()->toDateTimeString(),
            'nota_baja' => 'Disco desmagnetizado con acta del proveedor.',
        ])
        ->assertSessionHasNoErrors();

    expect(Activo::query()->where('codigo', 'SRV-01')->firstOrFail()->esperaBorradoSeguro())->toBeFalse();
});

it('señala como pendiente el activo retirado sin borrado seguro', function (): void {
    $activo = Activo::factory()->de($this->organizacion)->retirado()->create();

    expect($activo->esperaBorradoSeguro())->toBeTrue();
});

it('edita un activo y conserva su alcance', function (): void {
    $activo = Activo::factory()->de($this->organizacion)->create(['codigo' => 'SRV-01']);
    $activo->sistemas()->attach($this->sistema->id, ['organizacion_id' => $this->organizacion->id]);

    $this->actingAs($this->usuario)
        ->put("/activos/{$activo->id}", [
            ...$this->valido,
            'nombre' => 'Sede electrónica renombrada',
            'sistemas' => [$this->sistema->id],
        ])
        ->assertRedirect();

    expect($activo->fresh()?->nombre)->toBe('Sede electrónica renombrada');
    expect($activo->fresh()?->sistemas)->toHaveCount(1);
});

it('sirve la ficha con la valoración propia, la efectiva y el grafo', function (): void {
    $servicio = Activo::factory()->de($this->organizacion)
        ->create(['codigo' => 'SRV', 'valor_d' => NivelDimension::Alto->value]);
    $base = Activo::factory()->de($this->organizacion)->create(['codigo' => 'BBDD']);

    app(RegistrarDependencia::class)->vincular($servicio, $base, 'Aquí vive todo.');

    $this->actingAs($this->usuario)
        ->get("/activos/{$base->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('activos/Ficha')
            ->where('activo.codigo', 'BBDD')
            ->where('valoracionPropia.maximo', NivelDimension::Na->value)
            ->where('valoracionEfectiva.maximo', NivelDimension::Alto->value)
            ->has('dependientes', 1)
            ->has('dependeDe', 0)
            ->has('motivos', 1)
            ->where('motivos.0.activo.codigo', 'SRV')
        );
});

it('declara una dependencia desde la ficha', function (): void {
    $servicio = Activo::factory()->de($this->organizacion)->create(['codigo' => 'SRV']);
    $base = Activo::factory()->de($this->organizacion)->create(['codigo' => 'BBDD']);

    $this->actingAs($this->usuario)
        ->post("/activos/{$servicio->id}/dependencias", [
            'depende_de_id' => $base->id,
            'nota' => 'Toda la información vive aquí.',
        ])
        ->assertRedirect("/activos/{$servicio->id}");

    expect($servicio->dependeDe()->pluck('codigo')->all())->toBe(['BBDD']);
});

it('devuelve el ciclo como error del campo y no como un 500', function (): void {
    $servicio = Activo::factory()->de($this->organizacion)->create(['codigo' => 'SRV']);
    $base = Activo::factory()->de($this->organizacion)->create(['codigo' => 'BBDD']);

    app(RegistrarDependencia::class)->vincular($servicio, $base);

    $this->actingAs($this->usuario)
        ->post("/activos/{$base->id}/dependencias", ['depende_de_id' => $servicio->id])
        ->assertSessionHasErrors('depende_de_id');
});

it('retira una dependencia desde la ficha', function (): void {
    $servicio = Activo::factory()->de($this->organizacion)->create(['codigo' => 'SRV']);
    $base = Activo::factory()->de($this->organizacion)->create(['codigo' => 'BBDD']);

    app(RegistrarDependencia::class)->vincular($servicio, $base);

    $this->actingAs($this->usuario)
        ->delete("/activos/{$servicio->id}/dependencias/{$base->id}")
        ->assertRedirect("/activos/{$servicio->id}");

    expect($servicio->dependeDe()->count())->toBe(0);
});

it('elimina un activo y avisa de lo que se queda sin sostén', function (): void {
    $servicio = Activo::factory()->de($this->organizacion)->create(['codigo' => 'SRV']);
    $base = Activo::factory()->de($this->organizacion)->create(['codigo' => 'BBDD']);
    app(RegistrarDependencia::class)->vincular($servicio, $base);

    $this->actingAs($this->usuario)
        ->delete("/activos/{$base->id}")
        ->assertRedirect('/activos');

    expect(Activo::query()->whereKey($base->id)->exists())->toBeFalse();
    expect($servicio->dependeDe()->count())->toBe(0);
});

it('responde 404 y no 403 ante un activo de otra organización', function (): void {
    // Decir «existe pero no es tuyo» ya sería filtrar información.
    $ajena = Organizacion::factory()->create();
    comoOrganizacion($ajena);
    $ajeno = Activo::factory()->de($ajena)->create();
    comoOrganizacion($this->organizacion);

    $this->actingAs($this->usuario)->get("/activos/{$ajeno->id}")->assertNotFound();
    $this->actingAs($this->usuario)->delete("/activos/{$ajeno->id}")->assertNotFound();
});

it('no deja escribir al auditor', function (): void {
    $auditor = usuarioCon(Rol::Auditor);

    $this->actingAs($auditor)->get('/activos')->assertOk();
    $this->actingAs($auditor)->get('/activos/crear')->assertForbidden();
    $this->actingAs($auditor)->post('/activos', $this->valido)->assertForbidden();
});

it('deja al técnico gestionar el inventario', function (): void {
    $tecnico = usuarioCon(Rol::Tecnico);

    $this->actingAs($tecnico)->post('/activos', $this->valido)->assertRedirect();
});
