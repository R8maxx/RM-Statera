<?php

declare(strict_types=1);

use App\Domain\Activo\Enums\Clasificacion;
use App\Domain\Activo\Enums\EstadoCicloVida;
use App\Domain\Activo\Enums\EstadoControl;
use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Activo\Models\Activo;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Incidente\Enums\ClasificacionIncidente;
use App\Domain\Incidente\Enums\EstadoIncidente;
use App\Domain\Incidente\Enums\PeligrosidadIncidente;
use App\Domain\Incidente\Models\Incidente;
use App\Domain\Sistema\Models\Sistema;
use App\Http\Requests\Concerns\SeleccionVacia;

/*
|--------------------------------------------------------------------------
| El centinela de un grupo de casillas vacío
|--------------------------------------------------------------------------
|
| `CampoCasillas` manda `<input name="x[]" value="">` cuando no hay nada
| marcado, porque sin ese campo el grupo desaparece del `FormData` y el
| servidor entendería «no tocar» en vez de «ninguno».
|
| El problema: `ConvertEmptyStringsToNull` **recorre los arrays**, así que al
| servidor no llegaba `[]` sino `[null]`, y `[null]` sí se valida. Los tres
| formularios con grupos de casillas fallaban con un error en `x.0` que ninguna
| página liga, así que no guardaban y no decían por qué.
|
| Estos tres tests mandan exactamente lo que manda el navegador —`['']`—, que es
| el camino que los tests que ya había no tomaban nunca: todos mandaban `[]`.
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->sistema = Sistema::factory()->de($this->organizacion)->create(['codigo' => 'SIS-01']);
});

it('deja un activo sin alcance al desmarcar todos los sistemas', function (): void {
    $activo = Activo::factory()->de($this->organizacion)->create();
    $activo->sistemas()->attach($this->sistema, ['organizacion_id' => $this->organizacion->id]);

    $this->actingAs($this->usuario)
        ->put("/activos/{$activo->id}", [
            'codigo' => $activo->codigo,
            'nombre' => $activo->nombre,
            'tipo' => TipoActivo::Servicios->value,
            'estado_ciclo_vida' => EstadoCicloVida::EnProduccion->value,
            'clasificacion' => Clasificacion::UsoInterno->value,
            'cifrado' => EstadoControl::Si->value,
            'copia_seguridad' => EstadoControl::PorConfirmar->value,
            'propietario_id' => SeleccionVacia::VALOR,
            'custodio_id' => SeleccionVacia::VALOR,
            'valor_c' => NivelDimension::Bajo->value,
            'valor_i' => NivelDimension::Bajo->value,
            'valor_d' => NivelDimension::Bajo->value,
            'valor_a' => NivelDimension::Na->value,
            'valor_t' => NivelDimension::Na->value,
            // Lo que manda el navegador cuando no queda ninguna casilla marcada.
            'sistemas' => [''],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect("/activos/{$activo->id}");

    expect($activo->fresh()?->sistemas)->toHaveCount(0);
});

it('da el mensaje bueno cuando un riesgo se queda sin ningún activo', function (): void {
    // El centinela contaba como un elemento, así que `min:1` lo daba por bueno
    // y este mensaje —escrito para este caso— no podía dispararse nunca.
    $this->actingAs($this->usuario)
        ->post('/riesgos', [
            'codigo' => 'R-001',
            'titulo' => 'Robo de un portátil',
            'activos' => [''],
        ])
        ->assertSessionHasErrors('activos')
        ->assertSessionDoesntHaveErrors('activos.0');
});

it('desvincula todos los activos de un incidente al desmarcarlos', function (): void {
    $activo = Activo::factory()->de($this->organizacion)->create();

    $incidente = Incidente::factory()->create(['estado' => EstadoIncidente::Abierto->value]);
    $incidente->activos()->attach($activo, ['organizacion_id' => $this->organizacion->id]);

    $this->actingAs($this->usuario)
        ->put("/incidentes/{$incidente->id}", [
            'codigo' => $incidente->codigo,
            'titulo' => $incidente->titulo,
            'descripcion' => $incidente->descripcion,
            'clasificacion' => ClasificacionIncidente::Otros->value,
            'peligrosidad' => PeligrosidadIncidente::Baja->value,
            'fecha_deteccion' => $incidente->fecha_deteccion->toDateTimeString(),
            'activos' => [''],
        ])
        ->assertSessionHasNoErrors();

    expect($incidente->fresh()?->activos)->toHaveCount(0);
});

/*
|--------------------------------------------------------------------------
| Las columnas booleanas derivadas de un array
|--------------------------------------------------------------------------
|
| Las cinco dimensiones y los dos supervisores son columnas, no relaciones, pero
| la pantalla los manda como array para poder usar `CampoCasillas`. La
| derivación corre **sólo si el array viene**: un importador o un test que
| postee los booleanos sueltos tiene que seguir funcionando, y derivar a ciegas
| los apagaría los cinco.
*/

it('deriva las dimensiones del array cuando la pantalla lo manda', function (): void {
    $incidente = Incidente::factory()->create([
        'estado' => EstadoIncidente::Abierto->value,
        'afecta_confidencialidad' => true,
        'afecta_integridad' => false,
    ]);

    $this->actingAs($this->usuario)
        ->put("/incidentes/{$incidente->id}", [
            'codigo' => $incidente->codigo,
            'titulo' => $incidente->titulo,
            'descripcion' => $incidente->descripcion,
            'clasificacion' => ClasificacionIncidente::Otros->value,
            'peligrosidad' => PeligrosidadIncidente::Baja->value,
            'fecha_deteccion' => $incidente->fecha_deteccion->toDateTimeString(),
            'dimensiones' => ['afecta_integridad'],
            'notificables' => ['notificable_aepd'],
        ])
        ->assertSessionHasNoErrors();

    $incidente->refresh();

    expect($incidente->afecta_integridad)->toBeTrue()
        ->and($incidente->afecta_confidencialidad)->toBeFalse()
        ->and($incidente->notificable_aepd)->toBeTrue()
        ->and($incidente->notificable_ccn_cert)->toBeFalse();
});

it('respeta los booleanos sueltos cuando no viene el array', function (): void {
    $incidente = Incidente::factory()->create([
        'estado' => EstadoIncidente::Abierto->value,
        'afecta_disponibilidad' => false,
    ]);

    $this->actingAs($this->usuario)
        ->put("/incidentes/{$incidente->id}", [
            'codigo' => $incidente->codigo,
            'titulo' => $incidente->titulo,
            'descripcion' => $incidente->descripcion,
            'clasificacion' => ClasificacionIncidente::Otros->value,
            'peligrosidad' => PeligrosidadIncidente::Baja->value,
            'fecha_deteccion' => $incidente->fecha_deteccion->toDateTimeString(),
            'afecta_disponibilidad' => true,
        ])
        ->assertSessionHasNoErrors();

    expect($incidente->fresh()?->afecta_disponibilidad)->toBeTrue();
});

it('no guarda el centinela como sistema operativo ni como proveedor', function (): void {
    /*
     * El desplegable del sistema operativo ofrece «ninguno» con el centinela y
     * el request no lo normalizaba: todo activo editado sin sistema operativo
     * acababa con `__ninguno__` en la columna, y la ficha lo pintaba tal cual.
     */
    $activo = Activo::factory()->de($this->organizacion)->create();

    $this->actingAs($this->usuario)
        ->put("/activos/{$activo->id}", [
            'codigo' => $activo->codigo,
            'nombre' => $activo->nombre,
            'tipo' => TipoActivo::Servicios->value,
            'estado_ciclo_vida' => EstadoCicloVida::EnProduccion->value,
            'clasificacion' => Clasificacion::UsoInterno->value,
            'cifrado' => EstadoControl::Si->value,
            'copia_seguridad' => EstadoControl::PorConfirmar->value,
            'propietario_id' => SeleccionVacia::VALOR,
            'custodio_id' => SeleccionVacia::VALOR,
            'proveedor_id' => SeleccionVacia::VALOR,
            'sistema_operativo' => SeleccionVacia::VALOR,
            'valor_c' => NivelDimension::Bajo->value,
            'valor_i' => NivelDimension::Bajo->value,
            'valor_d' => NivelDimension::Bajo->value,
            'valor_a' => NivelDimension::Na->value,
            'valor_t' => NivelDimension::Na->value,
            'sistemas' => [''],
        ])
        ->assertSessionHasNoErrors();

    expect($activo->fresh()?->sistema_operativo)->toBeNull()
        ->and($activo->fresh()?->proveedor_id)->toBeNull();
});
