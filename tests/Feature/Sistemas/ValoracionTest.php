<?php

declare(strict_types=1);

use App\Domain\Catalogo\Enums\CategoriaEns;
use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Catalogo\Models\AplicabilidadEns;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Sistema\Models\ValoracionDimension;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Valoración de dimensiones de punta a punta
|--------------------------------------------------------------------------
|
| El ciclo completo desde la interfaz: valorar las cinco dimensiones, ver la
| categoría derivada, previsualizar el recálculo y aplicarlo. Antes de esto sólo
| se podía hacer por consola, así que un sistema creado desde la aplicación
| nacía sin implantaciones.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();

    $this->marco = Marco::factory()->create(['codigo' => 'ENS-SINTETICO']);

    $medida = function (string $codigo, array $celdas, ?Dimension $dimension = null): Requisito {
        $requisito = Requisito::factory()
            ->conCodigo($codigo)
            ->create(['marco_id' => $this->marco->id, 'titulo' => "Medida {$codigo}"]);

        foreach ($celdas as $categoria => $exigencia) {
            $fabrica = AplicabilidadEns::factory()->para(CategoriaEns::from($categoria), $exigencia);

            if ($dimension !== null) {
                $fabrica = $fabrica->moduladaPor($dimension);
            }

            $fabrica->create(['requisito_id' => $requisito->id]);
        }

        return $requisito;
    };

    $medida('org.1', ['basica' => 'aplica', 'media' => 'aplica', 'alta' => 'aplica']);
    $medida('op.acc.5', ['basica' => 'aplica', 'media' => 'R1', 'alta' => 'R2']);
    $medida('op.cont.2', ['basica' => 'no_aplica', 'media' => 'aplica', 'alta' => 'R1'], Dimension::Disponibilidad);

    $this->sistema = Sistema::factory()->de($this->organizacion)->conMarco($this->marco)
        ->create(['codigo' => 'SIS-01']);

    /** @param array<string, string> $niveles */
    $this->cuerpo = function (array $niveles, array $justificaciones = []): array {
        $datos = [];

        foreach (Dimension::cases() as $dimension) {
            $datos["nivel_{$dimension->value}"] = $niveles[$dimension->value] ?? 'na';
            $datos["justificacion_{$dimension->value}"] = $justificaciones[$dimension->value] ?? null;
        }

        return $datos;
    };
});

it('sirve la pantalla con las cinco dimensiones y el mapa de niveles', function (): void {
    $this->actingAs($this->usuario)
        ->get("/sistemas/{$this->sistema->id}/valoracion")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('sistemas/Valoracion')
            ->where('sistema.codigo', 'SIS-01')
            ->where('valorada', false)
            ->where('exigiblesHoy', 0)
            ->has('dimensiones', 5)
            // Las cinco arrancan en `na`, que es lo que significa no valorada.
            ->where('dimensiones.0.nivel', 'na')
            ->has('niveles', 4)
        );
});

it('la correspondencia nivel a categoría viaja como dato, no la reescribe el cliente', function (): void {
    $this->actingAs($this->usuario)
        ->get("/sistemas/{$this->sistema->id}/valoracion")
        ->assertInertia(function (AssertableInertia $pagina): void {
            $niveles = collect(data_get($pagina->toArray(), 'props.niveles'))
                ->mapWithKeys(fn (array $nivel): array => [$nivel['valor'] => $nivel['categoria']]);

            expect($niveles->all())->toBe([
                'na' => null,
                'bajo' => 'Básica',
                'medio' => 'Media',
                'alto' => 'Alta',
            ]);
        });
});

it('previsualiza el recálculo sin escribir ni una fila', function (): void {
    $respuesta = $this->actingAs($this->usuario)
        ->postJson(
            "/sistemas/{$this->sistema->id}/valoracion/simulacion",
            ($this->cuerpo)(['C' => 'bajo']),
        );

    $respuesta->assertOk()
        ->assertJsonPath('categoria', 'Básica')
        ->assertJsonPath('enAmbitoEns', true)
        ->assertJsonPath('hayCambios', true);

    // op.cont.2 está modulada por disponibilidad, que aquí es `na`.
    expect($respuesta->json('creadas'))->toEqualCanonicalizing(['org.1', 'op.acc.5']);

    expect(Implantacion::query()->count())->toBe(0)
        ->and(ValoracionDimension::query()->count())->toBe(0);
});

it('guarda la valoración y genera el conjunto exigible', function (): void {
    $this->actingAs($this->usuario)
        ->put(
            "/sistemas/{$this->sistema->id}/valoracion",
            ($this->cuerpo)(['C' => 'bajo'], ['C' => 'Datos personales de empleados.']),
        )
        ->assertRedirect('/sistemas')
        ->assertSessionHasNoErrors();

    // Cinco filas, siempre las cinco: `na` es una decisión, no un hueco.
    expect(ValoracionDimension::query()->count())->toBe(5)
        ->and($this->sistema->fresh()->categoria())->toBe(CategoriaEns::Basica);

    $codigos = Implantacion::query()->with('requisito')->get()->pluck('requisito.codigo');

    expect($codigos->all())->toEqualCanonicalizing(['org.1', 'op.acc.5']);

    $justificacion = ValoracionDimension::query()
        ->where('sistema_id', $this->sistema->id)
        ->where('dimension', Dimension::Confidencialidad->value)
        ->value('justificacion');

    expect($justificacion)->toBe('Datos personales de empleados.');
});

it('el diff que se previsualiza es el que se aplica', function (): void {
    $cuerpo = ($this->cuerpo)(['C' => 'bajo', 'D' => 'medio']);

    $previsualizado = $this->actingAs($this->usuario)
        ->postJson("/sistemas/{$this->sistema->id}/valoracion/simulacion", $cuerpo)
        ->json();

    $this->actingAs($this->usuario)
        ->put("/sistemas/{$this->sistema->id}/valoracion", $cuerpo)
        ->assertRedirect('/sistemas');

    $codigos = Implantacion::query()->with('requisito')->get()->pluck('requisito.codigo');

    expect($previsualizado['categoria'])->toBe('Media')
        ->and($codigos->all())->toEqualCanonicalizing($previsualizado['creadas']);
});

it('bajar una dimensión avisa de lo que deja de exigirse y no lo borra', function (): void {
    $this->actingAs($this->usuario)
        ->put("/sistemas/{$this->sistema->id}/valoracion", ($this->cuerpo)(['C' => 'bajo', 'D' => 'medio']));

    $continuidad = Implantacion::query()
        ->whereHas('requisito', fn ($consulta) => $consulta->where('codigo', 'op.cont.2'))
        ->firstOrFail();

    $continuidad->update(['estado' => EstadoImplantacion::Implantado->value]);

    $cuerpo = ($this->cuerpo)(['C' => 'bajo']);

    $this->actingAs($this->usuario)
        ->postJson("/sistemas/{$this->sistema->id}/valoracion/simulacion", $cuerpo)
        ->assertJsonPath('dejanDeAplicar', ['op.cont.2']);

    $this->actingAs($this->usuario)
        ->put("/sistemas/{$this->sistema->id}/valoracion", $cuerpo)
        ->assertRedirect('/sistemas');

    // Sigue ahí, con su histórico: lo que deja de exigirse no se borra.
    $continuidad->refresh();

    expect($continuidad->exists)->toBeTrue()
        ->and($continuidad->aplica)->toBeFalse()
        ->and($continuidad->estado)->toBe(EstadoImplantacion::NoAplica)
        ->and($continuidad->estadoPrevioANoAplica())->toBe(EstadoImplantacion::Implantado);
});

it('las cinco dimensiones son obligatorias', function (): void {
    $this->actingAs($this->usuario)
        ->put("/sistemas/{$this->sistema->id}/valoracion", ['nivel_C' => 'bajo'])
        ->assertSessionHasErrors(['nivel_I', 'nivel_D', 'nivel_A', 'nivel_T']);

    expect(ValoracionDimension::query()->count())->toBe(0);
});

it('rechaza un nivel que no existe en el enum', function (): void {
    $this->actingAs($this->usuario)
        ->put("/sistemas/{$this->sistema->id}/valoracion", ($this->cuerpo)(['C' => 'altisimo']))
        ->assertSessionHasErrors('nivel_C');
});

it('la categoría no se acepta como entrada: se deriva', function (): void {
    $this->actingAs($this->usuario)
        ->put(
            "/sistemas/{$this->sistema->id}/valoracion",
            [...($this->cuerpo)(['C' => 'bajo']), 'categoria' => 'alta'],
        )
        ->assertRedirect('/sistemas');

    expect($this->sistema->fresh()->categoria())->toBe(CategoriaEns::Basica);
});

it('las cinco en `na` dejan el sistema fuera del ENS, que no es categoría básica', function (): void {
    $this->actingAs($this->usuario)
        ->postJson("/sistemas/{$this->sistema->id}/valoracion/simulacion", ($this->cuerpo)([]))
        ->assertJsonPath('categoria', null)
        ->assertJsonPath('enAmbitoEns', false);

    $this->actingAs($this->usuario)
        ->put("/sistemas/{$this->sistema->id}/valoracion", ($this->cuerpo)([]))
        ->assertRedirect('/sistemas');

    expect(Implantacion::query()->where('aplica', true)->count())->toBe(0);
});

it('no valora el sistema de otra organización', function (): void {
    $otra = Organizacion::factory()->create();
    comoOrganizacion($otra);

    $ajeno = Sistema::factory()->de($otra)->conMarco($this->marco)->create();

    comoOrganizacion($this->organizacion);

    // 404 y no 403: decir «existe pero no es tuyo» ya sería filtrar información.
    $this->actingAs($this->usuario)
        ->get("/sistemas/{$ajeno->id}/valoracion")
        ->assertNotFound();

    $this->actingAs($this->usuario)
        ->put("/sistemas/{$ajeno->id}/valoracion", ($this->cuerpo)(['C' => 'alto']))
        ->assertNotFound();

    $this->actingAs($this->usuario)
        ->postJson("/sistemas/{$ajeno->id}/valoracion/simulacion", ($this->cuerpo)(['C' => 'alto']))
        ->assertNotFound();

    expect(ValoracionDimension::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('exige sesión iniciada', function (): void {
    $this->get("/sistemas/{$this->sistema->id}/valoracion")->assertRedirect('/login');
    $this->put("/sistemas/{$this->sistema->id}/valoracion", [])->assertRedirect('/login');
});
