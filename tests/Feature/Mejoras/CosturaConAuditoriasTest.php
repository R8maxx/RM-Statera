<?php

declare(strict_types=1);

use App\Domain\Auditoria\Enums\TipoHallazgo;
use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Auditoria\Models\Hallazgo;
use App\Domain\Auditoria\PrecargarChecklist;
use App\Domain\Auditoria\RegistrarHallazgo;
use App\Domain\Catalogo\Enums\TipoRequisito;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Mejora\Models\Mejora;
use App\Domain\Mejora\RegistrarMejora;
use App\Domain\NoConformidad\Excepciones\HallazgoNoTratable;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\NoConformidad\RegistrarNoConformidad;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| El hallazgo va al registro que le toca
|--------------------------------------------------------------------------
|
| Es la costura que abre la cláusula 10.1, y el motivo por el que las mejoras no
| son una fila más de `no_conformidades`: **una oportunidad de mejora no incumple
| nada**. Tratarla como no conformidad la contaría como incumplimiento en el
| panel, en el indicador del § 4.14 y en la entrada de la 9.3 que lee la revisión
| por la dirección.
|
| La regla vive en el dominio y no sólo en el `FormRequest` porque vale también
| para un importador — mismo criterio que el motivo de `descartada` en tareas.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();

    $this->hallazgo = function (TipoHallazgo $tipo): Hallazgo {
        $marco = Marco::factory()->create();
        $sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create();

        $requisito = Requisito::factory()->create([
            'marco_id' => $marco->id,
            'codigo' => 'op.acc.1',
            'tipo' => TipoRequisito::Medida->value,
            'orden' => 1,
        ]);
        Implantacion::factory()->for($sistema)->create(['requisito_id' => $requisito->id]);

        $auditoria = Auditoria::factory()->paraSistema($sistema->id)->create();
        app(PrecargarChecklist::class)($auditoria);

        return app(RegistrarHallazgo::class)->registrar(
            $auditoria,
            $tipo,
            'Algo que se puede hacer mejor.',
            $auditoria->puntos()->firstOrFail(),
        );
    };
});

it('el dominio rechaza tratar una oportunidad de mejora como no conformidad', function (): void {
    $hallazgo = ($this->hallazgo)(TipoHallazgo::OportunidadMejora);

    expect(fn () => app(RegistrarNoConformidad::class)([
        'codigo' => 'NC-2026-01',
        'origen' => 'auditoria',
        'hallazgo_id' => $hallazgo->id,
        'descripcion' => 'No debería poder abrirse.',
        'fecha_deteccion' => Carbon::today(),
    ], $this->usuario))->toThrow(HallazgoNoTratable::class, 'oportunidades de mejora');

    expect(NoConformidad::query()->count())->toBe(0);
});

it('el formulario de no conformidad lleva la oportunidad de mejora a su registro', function (): void {
    $hallazgo = ($this->hallazgo)(TipoHallazgo::OportunidadMejora);

    $this->actingAs($this->usuario)
        ->get("/no-conformidades/crear?hallazgo={$hallazgo->id}")
        ->assertRedirect("/mejoras/crear?hallazgo={$hallazgo->id}");
});

it('y el de mejora lleva la no conformidad al suyo', function (): void {
    $hallazgo = ($this->hallazgo)(TipoHallazgo::NcMayor);

    $this->actingAs($this->usuario)
        ->get("/mejoras/crear?hallazgo={$hallazgo->id}")
        ->assertRedirect("/no-conformidades/crear?hallazgo={$hallazgo->id}");
});

it('abre la mejora desde el hallazgo con lo que ya se sabe puesto', function (): void {
    $hallazgo = ($this->hallazgo)(TipoHallazgo::OportunidadMejora);

    $this->actingAs($this->usuario)
        ->get("/mejoras/crear?hallazgo={$hallazgo->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('mejoras/Formulario')
            ->where('hallazgo.id', $hallazgo->id)
            ->where('sugerencia.origen', 'auditoria')
            ->where('sugerencia.titulo', $hallazgo->descripcion));
});

it('un hallazgo se trata una vez, y la segunda lleva a la que ya existe', function (): void {
    $hallazgo = ($this->hallazgo)(TipoHallazgo::OportunidadMejora);

    $mejora = app(RegistrarMejora::class)([
        'codigo' => 'OM-2026-01',
        'origen' => 'auditoria',
        'hallazgo_id' => $hallazgo->id,
        'titulo' => 'Automatizar el inventario de software.',
        'fecha_deteccion' => Carbon::today(),
    ], $this->usuario);

    $this->actingAs($this->usuario)
        ->get("/mejoras/crear?hallazgo={$hallazgo->id}")
        ->assertRedirect("/mejoras/{$mejora->id}");

    expect(Mejora::query()->count())->toBe(1);
});

it('la ficha de la auditoría ofrece el camino que corresponde a cada tipo', function (): void {
    $hallazgo = ($this->hallazgo)(TipoHallazgo::OportunidadMejora);

    $this->actingAs($this->usuario)
        ->get("/auditorias/{$hallazgo->auditoria_id}")
        ->assertInertia(function (AssertableInertia $pagina) use ($hallazgo): void {
            $fila = collect($pagina->toArray()['props']['hallazgos'])
                ->firstWhere('id', $hallazgo->id);

            expect($fila['abreMejora'])->toBeTrue()
                ->and($fila['exigeNoConformidad'])->toBeFalse()
                ->and($fila['mejoraId'])->toBeNull();
        });
});
