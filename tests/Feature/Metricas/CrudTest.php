<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Metrica\Enums\CalculoIndicador;
use App\Domain\Metrica\Enums\OrigenMedicion;
use App\Domain\Metrica\Enums\Periodicidad;
use App\Domain\Metrica\Enums\SentidoIndicador;
use App\Domain\Metrica\Enums\UnidadIndicador;
use App\Domain\Metrica\Models\Indicador;
use App\Domain\Metrica\Models\Medicion;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| El cuadro de indicadores por la interfaz
|--------------------------------------------------------------------------
|
| Lo que más se prueba aquí es la frontera entre las dos mitades del módulo: un
| indicador es calculado o es manual, y el `CHECK` de la tabla lo impone en las
| dos direcciones. El `FormRequest` existe para que ese rechazo llegue en
| castellano y no como el nombre de una restricción de PostgreSQL.
|
*/

beforeEach(function (): void {
    comoOrganizacion();
    $this->usuario = usuarioCon();
});

it('declara un indicador manual con su método escrito', function (): void {
    $this->actingAs($this->usuario)
        ->post('/indicadores', [
            'codigo' => 'IND-01',
            'nombre' => 'Personal formado',
            'origen' => OrigenMedicion::Manual->value,
            'formula_o_fuente' => 'Recuento sobre la lista de asistencia firmada.',
            'unidad' => UnidadIndicador::Porcentaje->value,
            'sentido' => SentidoIndicador::MayorMejor->value,
            'periodicidad' => Periodicidad::Anual->value,
            'objetivo' => 90,
        ])
        ->assertRedirect();

    $indicador = Indicador::query()->sole();

    expect($indicador->codigo)->toBe('IND-01')
        ->and($indicador->origen)->toBe(OrigenMedicion::Manual)
        ->and($indicador->calculo)->toBeNull()
        ->and((float) $indicador->objetivo)->toBe(90.0);
});

/**
 * La regla que el `CHECK` impone y que aquí se dice en castellano. Sin esto, el
 * error que sube habla de `indicadores_manual_check`, que no lo lee nadie.
 */
it('no deja declarar un indicador manual sin decir de dónde sale la cifra', function (): void {
    $this->actingAs($this->usuario)
        ->post('/indicadores', [
            'codigo' => 'IND-01',
            'nombre' => 'Personal formado',
            'origen' => OrigenMedicion::Manual->value,
            'unidad' => UnidadIndicador::Porcentaje->value,
            'sentido' => SentidoIndicador::MayorMejor->value,
            'periodicidad' => Periodicidad::Anual->value,
        ])
        ->assertSessionHasErrors('formula_o_fuente');

    expect(Indicador::query()->count())->toBe(0);
});

it('no deja declarar un indicador calculado sin cálculo', function (): void {
    $this->actingAs($this->usuario)
        ->post('/indicadores', [
            'codigo' => 'IND-01',
            'nombre' => 'Cumplimiento',
            'origen' => OrigenMedicion::Calculado->value,
            'unidad' => UnidadIndicador::Porcentaje->value,
            'sentido' => SentidoIndicador::MayorMejor->value,
            'periodicidad' => Periodicidad::Trimestral->value,
        ])
        ->assertSessionHasErrors('calculo');
});

/**
 * Cambiar de calculado a manual tiene que limpiar lo que sobra, o la base
 * rechaza la fila con un error que no menciona ninguna de las dos palabras. La
 * limpieza vive en la acción de dominio y no en el formulario, porque vale
 * también para el seeder y para un importador.
 */
it('al pasar de calculado a manual se limpia el cálculo y el marco', function (): void {
    $indicador = Indicador::factory()->calculado(CalculoIndicador::CumplimientoImplantado)->create();

    $this->actingAs($this->usuario)
        ->put("/indicadores/{$indicador->id}", [
            'codigo' => $indicador->codigo,
            'nombre' => $indicador->nombre,
            'origen' => OrigenMedicion::Manual->value,
            'formula_o_fuente' => 'Lo cuenta el responsable del servicio.',
            'unidad' => UnidadIndicador::Recuento->value,
            'sentido' => SentidoIndicador::MenorMejor->value,
            'periodicidad' => Periodicidad::Trimestral->value,
            'activo' => true,
        ])
        ->assertRedirect();

    $indicador->refresh();

    expect($indicador->origen)->toBe(OrigenMedicion::Manual)
        ->and($indicador->calculo)->toBeNull()
        ->and($indicador->marco_id)->toBeNull();
});

/**
 * Un marco acota un cálculo sobre implantaciones. Sobre los demás no significa
 * nada: las evidencias y las tareas sirven a los dos marcos a la vez.
 */
it('rechaza acotar a un marco un cálculo que no se deja acotar', function (): void {
    $marco = Marco::factory()->create();

    $this->actingAs($this->usuario)
        ->post('/indicadores', [
            'codigo' => 'IND-01',
            'nombre' => 'Evidencias caducadas',
            'origen' => OrigenMedicion::Calculado->value,
            'calculo' => CalculoIndicador::EvidenciasCaducadas->value,
            'marco_id' => $marco->id,
            'unidad' => UnidadIndicador::Recuento->value,
            'sentido' => SentidoIndicador::MenorMejor->value,
            'periodicidad' => Periodicidad::Trimestral->value,
        ])
        ->assertSessionHasErrors('marco_id');
});

it('sella a mano la medición de un periodo cerrado', function (): void {
    $indicador = Indicador::factory()->conPeriodicidad(Periodicidad::Trimestral)->create();
    [$inicio] = Periodicidad::Trimestral->periodoAnteriorA(Carbon::today());

    $this->actingAs($this->usuario)
        ->post("/indicadores/{$indicador->id}/mediciones", [
            'fecha' => $inicio->toDateString(),
            'valor' => 42,
            'numerador' => 42,
            'denominador' => 100,
        ])
        ->assertRedirect();

    $medicion = Medicion::query()->sole();

    expect((float) $medicion->valor)->toBe(42.0)
        ->and($medicion->fraccion())->toBe('42 de 100')
        ->and($medicion->registrada_por_id)->toBe($this->usuario->id);
});

/**
 * No se mide el periodo en curso: una cifra a medias habría que corregirla al
 * día siguiente, y la serie contaría un trimestre que todavía no ha pasado.
 */
it('no deja sellar el periodo que está en curso', function (): void {
    $indicador = Indicador::factory()->conPeriodicidad(Periodicidad::Trimestral)->create();

    $this->actingAs($this->usuario)
        ->post("/indicadores/{$indicador->id}/mediciones", [
            'fecha' => Carbon::today()->toDateString(),
            'valor' => 42,
        ])
        ->assertSessionHasErrors('fecha');

    expect(Medicion::query()->count())->toBe(0);
});

it('un numerador sin denominador no se acepta', function (): void {
    $indicador = Indicador::factory()->create();
    [$inicio] = Periodicidad::Trimestral->periodoAnteriorA(Carbon::today());

    $this->actingAs($this->usuario)
        ->post("/indicadores/{$indicador->id}/mediciones", [
            'fecha' => $inicio->toDateString(),
            'valor' => 42,
            'numerador' => 42,
        ])
        ->assertSessionHasErrors('denominador');
});

it('«medir ahora» cierra el periodo de un indicador calculado', function (): void {
    $indicador = Indicador::factory()->calculado(CalculoIndicador::TareasVencidas)->create();

    $this->actingAs($this->usuario)
        ->post("/indicadores/{$indicador->id}/medicion")
        ->assertRedirect();

    expect(Medicion::query()->where('indicador_id', $indicador->id)->count())->toBe(1)
        ->and(Medicion::query()->sole()->origen)->toBe(OrigenMedicion::Calculado);
});

/** Un indicador manual no se mide solo: sellar un cero en su nombre sería inventarse la cifra. */
it('«medir ahora» no hace nada sobre un indicador manual', function (): void {
    $indicador = Indicador::factory()->create();

    $this->actingAs($this->usuario)
        ->from("/indicadores/{$indicador->id}")
        ->post("/indicadores/{$indicador->id}/medicion")
        ->assertRedirect("/indicadores/{$indicador->id}");

    expect(Medicion::query()->count())->toBe(0);
});

it('la ficha enseña la serie y el método', function (): void {
    $indicador = Indicador::factory()->calculado(CalculoIndicador::TareasVencidas)->create();
    Medicion::factory()->for($indicador)->con(3.0)->create();

    $this->actingAs($this->usuario)
        ->get("/indicadores/{$indicador->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('indicadores/Ficha')
            ->has('serie', 1)
            ->where('indicador.metodo', CalculoIndicador::TareasVencidas->metodo())
            ->where('indicador.esCalculado', true));
});

/*
|--------------------------------------------------------------------------
| Permisos
|--------------------------------------------------------------------------
*/

it('el auditor lee y no escribe', function (): void {
    $auditor = usuarioCon(Rol::Auditor);
    $indicador = Indicador::factory()->create();

    $this->actingAs($auditor)->get('/indicadores')->assertOk();
    $this->actingAs($auditor)->get("/indicadores/{$indicador->id}")->assertOk();
    $this->actingAs($auditor)->post('/indicadores', [])->assertForbidden();
    $this->actingAs($auditor)->post("/indicadores/{$indicador->id}/medicion")->assertForbidden();
});

/**
 * El técnico sí mide, y es deliberado: una medición es un dato que se toma, no
 * una decisión que se firma. El verbo de supervisión de este ciclo llega con los
 * objetivos de la 6.2.
 */
it('el técnico define indicadores y los mide', function (): void {
    $tecnico = usuarioCon(Rol::Tecnico);

    expect($tecnico->can(Permiso::IndicadoresGestionar->value))->toBeTrue();
});
