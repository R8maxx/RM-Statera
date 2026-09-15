<?php

declare(strict_types=1);

use App\Domain\Activo\Models\Activo;
use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Riesgo\Enums\DecisionRiesgo;
use App\Domain\Riesgo\Models\MetodologiaRiesgo;
use App\Domain\Riesgo\Models\Riesgo;
use App\Domain\Riesgo\Models\RiesgoValoracion;
use App\Domain\Riesgo\VincularRiesgo;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| A qué está expuesto un activo
|--------------------------------------------------------------------------
|
| La relación riesgo↔activo es N:M y hasta ahora sólo se recorría en un sentido:
| la ficha del riesgo decía sobre qué activos pesa y el inventario no sabía nada.
| Aquí se fija la vuelta.
|
| Dos cosas importan más que la lista en sí: que el nivel se lea con la escala
| CONGELADA de cada valoración —lo mismo que fija `ValoracionCongeladaTest`, pero
| desde otra pantalla— y que quien no puede ver el análisis de riesgos no lo lea
| entrando por la ficha de un activo, que es el tipo de puerta lateral que se abre
| sola al conectar dos módulos.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

/** Vincula el riesgo al activo por el camino del dominio, con su `organizacion_id`. */
function pesaSobre(Riesgo $riesgo, Activo $activo): void
{
    app(VincularRiesgo::class)->activo($riesgo, $activo);
}

it('lista en la ficha los riesgos que pesan sobre el activo', function (): void {
    $activo = Activo::factory()->de($this->organizacion)->create(['codigo' => 'BBDD']);

    $riesgo = Riesgo::factory()->create(['codigo' => 'R-001', 'titulo' => 'Fuga de la base de datos']);
    RiesgoValoracion::factory()->for($riesgo)->con(4, 5)->conDecision(DecisionRiesgo::Mitigar)->create();
    pesaSobre($riesgo, $activo);

    $this->actingAs($this->usuario)
        ->get("/activos/{$activo->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('activos/Ficha')
            ->where('puedeVerRiesgos', true)
            ->has('riesgos', 1)
            ->where('riesgos.0.codigo', 'R-001')
            ->where('riesgos.0.titulo', 'Fuga de la base de datos')
            ->where('riesgos.0.intrinseco.etiqueta', 'Muy alto (20)')
            ->where('riesgos.0.residual', null)
            ->where('riesgos.0.decision.valor', DecisionRiesgo::Mitigar->value)
        );
});

it('no enseña los riesgos de otro activo', function (): void {
    $mio = Activo::factory()->de($this->organizacion)->create(['codigo' => 'BBDD']);
    $otro = Activo::factory()->de($this->organizacion)->create(['codigo' => 'SRV']);

    pesaSobre(Riesgo::factory()->create(['codigo' => 'R-MIO']), $mio);
    pesaSobre(Riesgo::factory()->create(['codigo' => 'R-OTRO']), $otro);

    $this->actingAs($this->usuario)
        ->get("/activos/{$mio->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('riesgos', 1)
            ->where('riesgos.0.codigo', 'R-MIO')
        );
});

it('sirve la ficha de un activo sin riesgos con la lista vacía', function (): void {
    // El estado vacío es el caso normal, no un borde: la mayor parte del
    // inventario no tiene ningún riesgo registrado encima.
    $activo = Activo::factory()->de($this->organizacion)->create();

    $this->actingAs($this->usuario)
        ->get("/activos/{$activo->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('riesgos', 0));
});

it('ordena por exposición y deja los sin valorar al final', function (): void {
    // Un riesgo sin valorar no es un riesgo bajo: es uno que nadie ha mirado. No
    // compite por el primer puesto, pero tampoco desaparece de la lista.
    $activo = Activo::factory()->de($this->organizacion)->create();

    $bajo = Riesgo::factory()->create(['codigo' => 'R-BAJO']);
    RiesgoValoracion::factory()->for($bajo)->con(1, 2)->create();

    $alto = Riesgo::factory()->create(['codigo' => 'R-ALTO']);
    RiesgoValoracion::factory()->for($alto)->con(5, 4)->create();

    $sinValorar = Riesgo::factory()->create(['codigo' => 'R-NUEVO']);

    foreach ([$bajo, $sinValorar, $alto] as $riesgo) {
        pesaSobre($riesgo, $activo);
    }

    $this->actingAs($this->usuario)
        ->get("/activos/{$activo->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('riesgos.0.codigo', 'R-ALTO')
            ->where('riesgos.1.codigo', 'R-BAJO')
            ->where('riesgos.2.codigo', 'R-NUEVO')
            ->where('riesgos.2.intrinseco', null)
        );
});

it('manda el residual cuando se ha declarado, sin tapar el intrínseco', function (): void {
    // Las dos cifras juntas, igual que en la tabla de riesgos: sólo el residual
    // esconde de qué se partía y sólo el intrínseco hace parecer que nada se hizo.
    $activo = Activo::factory()->de($this->organizacion)->create();

    $riesgo = Riesgo::factory()->create(['codigo' => 'R-010']);
    RiesgoValoracion::factory()->for($riesgo)->con(4, 5)->conResidual(2, 3)->create();
    pesaSobre($riesgo, $activo);

    $this->actingAs($this->usuario)
        ->get("/activos/{$activo->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('riesgos.0.intrinseco.etiqueta', 'Muy alto (20)')
            ->where('riesgos.0.residual.etiqueta', 'Medio (6)')
        );
});

it('lee el nivel con la escala congelada y no con la metodología de hoy', function (): void {
    /*
     * Es lo que la instantánea existe para impedir: interpretar un número de
     * marzo con la escala de octubre. La ficha del activo tiene que respetarlo
     * igual que la tabla de riesgos, porque pinta exactamente el mismo badge.
     *
     * Con la escala de fábrica —umbrales 8 y 15—, un 9 es «Alto». Con la nueva
     * —2 y 4— sería «Muy alto». La valoración ya está emitida: no se reinterpreta.
     */
    $activo = Activo::factory()->de($this->organizacion)->create();

    $riesgo = Riesgo::factory()->create(['codigo' => 'R-020']);
    RiesgoValoracion::factory()->for($riesgo)->con(3, 3)->create();
    pesaSobre($riesgo, $activo);

    MetodologiaRiesgo::factory()->conUmbrales(2, 4)->create();

    $this->actingAs($this->usuario)
        ->get("/activos/{$activo->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('riesgos.0.intrinseco.etiqueta', 'Alto (9)')
        );
});

it('señala el residual sin respaldo y la reevaluación vencida', function (): void {
    // La herramienta no corrige el dato: lo pone delante. Mismo papel que
    // `esperaBorradoSeguro()` en esta misma ficha.
    $activo = Activo::factory()->de($this->organizacion)->create();

    $riesgo = Riesgo::factory()->revisionVencida()->create(['codigo' => 'R-030']);
    RiesgoValoracion::factory()->for($riesgo)->con(4, 5)->conResidual(1, 2)->create();
    pesaSobre($riesgo, $activo);

    $this->actingAs($this->usuario)
        ->get("/activos/{$activo->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('riesgos.0.sinRespaldo', true)
            ->where('riesgos.0.revisionVencida', true)
        );
});

it('no manda la lista a quien no puede ver el análisis de riesgos', function (): void {
    /*
     * El frontend decide qué pinta y nunca qué autoriza. Hoy los tres roles de
     * fábrica llevan `riesgos.ver`, así que el permiso se retira del rol para
     * comprobar el comportamiento —que es lo que se fija aquí, no el reparto de
     * permisos, que tiene su propio test—.
     */
    $activo = Activo::factory()->de($this->organizacion)->create();
    pesaSobre(Riesgo::factory()->create(['codigo' => 'R-040']), $activo);

    $auditor = usuarioCon(Rol::Auditor);
    $auditor->roles->first()?->revokePermissionTo(Permiso::RiesgosVer->value);

    $this->actingAs($auditor->fresh())
        ->get("/activos/{$activo->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('puedeVerRiesgos', false)
            ->has('riesgos', 0)
        );
});

it('no cruza la frontera de organización', function (): void {
    $ajena = Organizacion::factory()->create();
    comoOrganizacion($ajena);
    $activoAjeno = Activo::factory()->de($ajena)->create();
    pesaSobre(Riesgo::factory()->create(['codigo' => 'R-AJENO']), $activoAjeno);

    comoOrganizacion($this->organizacion);
    $mio = Activo::factory()->de($this->organizacion)->create();

    $this->actingAs($this->usuario)
        ->get("/activos/{$mio->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('riesgos', 0));
});
