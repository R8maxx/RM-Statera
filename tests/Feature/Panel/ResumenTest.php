<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Catalogo\Enums\TipoRequisito;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Contexto\Enums\TipoCuestion;
use App\Domain\Contexto\Models\CuestionContexto;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Metrica\Enums\Periodicidad;
use App\Domain\Metrica\Enums\SentidoIndicador;
use App\Domain\Metrica\Models\Indicador;
use App\Domain\Metrica\Models\Medicion;
use App\Domain\NoConformidad\Enums\EstadoNoConformidad;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Las cifras del panel
|--------------------------------------------------------------------------
|
| El panel es lo primero que se mira y lo que se enseña en una reunión, así que
| una cifra mal contada aquí se propaga a una decisión. Lo que se comprueba es
| que sólo cuenta lo exigible, que el reparto por estado es el real y que la
| madurez media viaja con el denominador sobre el que se calcula.
|
*/

/**
 * @return array{usuario: User, ens: Marco, iso: Marco}
 */
function escenarioDePanel(): array
{
    $organizacion = comoOrganizacion();

    $ens = Marco::factory()->create(['codigo' => 'ENS', 'nombre' => 'Esquema Nacional de Seguridad']);
    $iso = Marco::factory()->create(['codigo' => 'ISO27001', 'nombre' => 'ISO/IEC 27001']);
    $sistema = Sistema::factory()->de($organizacion)->conMarco($ens)->create(['codigo' => 'SIS-01']);

    $orden = 0;

    $crear = function (Marco $marco, array $atributos) use ($sistema, &$orden): void {
        $orden++;

        $requisito = Requisito::factory()->create([
            'marco_id' => $marco->id,
            'codigo' => "req.{$orden}",
            'tipo' => TipoRequisito::Medida->value,
            'orden' => $orden,
        ]);

        Implantacion::factory()
            ->for($sistema)
            ->create(['requisito_id' => $requisito->id, ...$atributos]);
    };

    $crear($ens, ['estado' => 'implantado', 'nivel_madurez' => 'l4']);
    $crear($ens, ['estado' => 'implantado', 'nivel_madurez' => 'l2']);
    $crear($ens, ['estado' => 'en_progreso']);
    $crear($ens, ['estado' => 'no_iniciado']);
    $crear($iso, ['estado' => 'implantado', 'nivel_madurez' => 'l1']);
    $crear($iso, ['estado' => 'planificado']);

    // Excluida: no se le exige al sistema, así que no cuenta en ninguna cifra.
    $crear($ens, ['estado' => 'no_aplica', 'aplica' => false, 'justificacion' => 'Fuera del alcance.']);

    return [
        'usuario' => usuarioCon(),
        'ens' => $ens,
        'iso' => $iso,
    ];
}

it('reparte por estado en el orden que fija el dominio y sin lo que no aplica', function (): void {
    $escenario = escenarioDePanel();

    $this->actingAs($escenario['usuario'])
        ->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('Panel')
            ->has('porEstado', 4)
            // El orden separa el verde del ámbar: pegados no se distinguen con
            // protanopia. Es una decisión medida, no el orden del enum.
            ->where('porEstado.0.clave', 'implantado')
            ->where('porEstado.0.valor', 3)
            ->where('porEstado.1.clave', 'planificado')
            ->where('porEstado.1.valor', 1)
            ->where('porEstado.2.clave', 'en_progreso')
            ->where('porEstado.2.valor', 1)
            ->where('porEstado.3.clave', 'no_iniciado')
            ->where('porEstado.3.valor', 1)
        );
});

it('cuenta el avance de cada marco por separado', function (): void {
    $escenario = escenarioDePanel();

    $this->actingAs($escenario['usuario'])
        ->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('porMarco', 2)
            // Ordenados por nombre, y la excluida no engorda el denominador.
            ->where('porMarco.0.codigo', 'ENS')
            ->where('porMarco.0.aplicables', 4)
            ->where('porMarco.0.implantadas', 2)
            ->where('porMarco.1.codigo', 'ISO27001')
            ->where('porMarco.1.aplicables', 2)
            ->where('porMarco.1.implantadas', 1)
        );
});

it('da la madurez media con el número de requisitos sobre los que se calcula', function (): void {
    $escenario = escenarioDePanel();

    $this->actingAs($escenario['usuario'])
        ->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            // (4 + 2 + 1) / 3 = 2,333…, redondeado a un decimal. Sólo cuentan
            // las tres que alguien valoró, no las siete que hay.
            ->where('resumen.madurezMedia', 2.3)
            ->where('resumen.madurezEvaluadas', 3)
            ->where('resumen.aplicables', 6)
            ->where('resumen.implantadas', 3)
            ->where('resumen.pendientes', 3)
        );
});

it('distingue una madurez sin valorar de una madurez cero', function (): void {
    $organizacion = comoOrganizacion();
    $usuario = usuarioCon();

    $this->actingAs($usuario)
        ->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('resumen.madurezMedia', null)
            ->where('resumen.madurezEvaluadas', 0)
        );
});

/**
 * El panel contesta las dos mitades de «cómo va la cosa»: el cumplimiento dice
 * qué falta y el plan de acción dice quién lo está haciendo.
 */
it('lleva el plan de acción, con lo abierto sobre el total', function (): void {
    ['usuario' => $usuario] = escenarioDePanel();

    Tarea::factory()->count(2)->create();
    Tarea::factory()->enEstado(EstadoTarea::Hecha)->create();
    Tarea::factory()->vencida()->create();

    $this->actingAs($usuario)
        ->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('plan.total', 4)
            ->where('plan.abiertas', 3)
            ->where('plan.vencidas', 1)
            ->has('plan.porEstado'));
});

/*
 * El reparto por origen, que llegó con el § 4.13. Existe porque «40 tareas
 * abiertas» mezcla la deuda que alguien planificó con el trabajo correctivo que
 * sale de algo que ya falló, y son dos cosas que no se gestionan igual.
 *
 * Se cuenta **sobre lo abierto**, como los otros dos repartos, y los orígenes a
 * cero no salen: cuatro barras de las que tres están vacías no son un reparto,
 * son la lista de valores posibles.
 */
it('reparte el plan por origen, sobre lo abierto y sin los orígenes vacíos', function (): void {
    ['usuario' => $usuario] = escenarioDePanel();

    Tarea::factory()->count(2)->create(['origen' => OrigenTarea::Propia->value]);
    Tarea::factory()->create(['origen' => OrigenTarea::NoConformidad->value]);
    // Cerrada: no está pendiente, así que no entra en el reparto.
    Tarea::factory()->enEstado(EstadoTarea::Hecha)->create(['origen' => OrigenTarea::Riesgo->value]);

    $this->actingAs($usuario)
        ->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('plan.porOrigen', 2)
            // En el orden del enum, que va de lo más reactivo a lo más propio.
            ->where('plan.porOrigen.0.clave', OrigenTarea::NoConformidad->value)
            ->where('plan.porOrigen.0.valor', 1)
            ->where('plan.porOrigen.1.clave', OrigenTarea::Propia->value)
            ->where('plan.porOrigen.1.valor', 2));
});

/*
 * La tercera pregunta del panel (§ 4.14): qué ha fallado y si se arregló.
 *
 * Lo que se clava aquí es que **«sin verificar» sube al panel**, porque es la
 * única cifra del módulo que está por la norma y no por la pantalla: una no
 * conformidad cerrada y sin verificar se lee como resuelta y no lo está.
 */
it('lleva las no conformidades, con lo abierto y lo que falta por verificar', function (): void {
    ['usuario' => $usuario] = escenarioDePanel();

    NoConformidad::factory()->create();
    NoConformidad::factory()->enEstado(EstadoNoConformidad::EnTratamiento)->create();
    NoConformidad::factory()->enEstado(EstadoNoConformidad::Cerrada)->create();
    NoConformidad::factory()->enEstado(EstadoNoConformidad::Verificada)->create();

    $this->actingAs($usuario)
        ->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('noConformidades.total', 4)
            ->where('noConformidades.abiertas', 2)
            ->where('noConformidades.sinVerificar', 1)
            // El reparto incluye los estados cerrados, a diferencia del de
            // tareas: aquí la pregunta es cuántas se han llegado a verificar.
            ->has('noConformidades.porEstado', 4));
});

/*
 * Conectar dos módulos abre una puerta lateral al registro del otro si el
 * frontend es quien decide qué esconder. Lo decide el servidor, como ya se
 * decidió con el bloque de riesgos de la ficha de un activo.
 */
it('no manda las no conformidades a quien no puede verlas', function (): void {
    ['usuario' => $usuario] = escenarioDePanel();

    NoConformidad::factory()->create();

    /*
     * El permiso se le quita **al rol** y no al usuario: los tres roles del
     * § 4.19 llevan hoy `no_conformidades.ver`, así que `revokePermissionTo`
     * sobre la persona no quita nada —spatie mira también lo que hereda del rol—
     * y el test pasaría por el motivo equivocado. Que hoy no haya ningún rol sin
     * este permiso no vuelve inútil la guarda: la lista de `Rol::permisos()` es
     * literal y cambia.
     */
    Role::query()
        ->where('name', Rol::ResponsableSeguridad->value)
        ->where('organizacion_id', $usuario->organizacion_id)
        ->firstOrFail()
        ->revokePermissionTo(Permiso::NoConformidadesVer->value);

    $this->actingAs($usuario->fresh())
        ->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('noConformidades', null));
});

/*
|--------------------------------------------------------------------------
| El contexto de la organización (§ 4.1)
|--------------------------------------------------------------------------
|
| Es la quinta tarjeta y la que contesta a la pregunta de antes de todas: de qué
| entorno estamos hablando. Lo que se clava aquí es que la cifra que importa es
| **desde cuándo** —un análisis de hace tres años ya no describe a nadie— y que el
| reparto del DAFO enseña los cuatro cuadrantes, vacíos incluidos.
|
*/

it('lleva el contexto, con su reparto del DAFO y lo que falta por atar', function (): void {
    ['usuario' => $usuario] = escenarioDePanel();

    CuestionContexto::factory()->deTipo(TipoCuestion::Amenaza)->count(2)->create();
    CuestionContexto::factory()->deTipo(TipoCuestion::Fortaleza)->create();

    $this->actingAs($usuario)
        ->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('contexto.cuestiones', 3)
            // Las dos amenazas, que son las adversas sin riesgo vinculado.
            ->where('contexto.sinRiesgo', 2)
            // Sin análisis aprobado todavía: es el estado de partida y se dice.
            ->where('contexto.analisisVigente', null)
            // Los cuatro cuadrantes, incluido el que está a cero: un DAFO sin
            // oportunidades es justo lo que hay que poder ver.
            ->has('contexto.porTipo', 4));
});

it('no manda el contexto a quien no puede verlo', function (): void {
    ['usuario' => $usuario] = escenarioDePanel();

    CuestionContexto::factory()->create();

    // Al rol y no al usuario, por lo mismo que en las no conformidades:
    // `revokePermissionTo` sobre la persona no quita lo que hereda del rol.
    Role::query()
        ->where('name', Rol::ResponsableSeguridad->value)
        ->where('organizacion_id', $usuario->organizacion_id)
        ->firstOrFail()
        ->revokePermissionTo(Permiso::ContextoVer->value);

    $this->actingAs($usuario->fresh())
        ->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('contexto', null));
});

/*
|--------------------------------------------------------------------------
| El desempeño (§ 4.14, cláusula 9.1)
|--------------------------------------------------------------------------
*/

it('cuenta los indicadores con su denominador y reparte por veredicto', function (): void {
    ['usuario' => $usuario] = escenarioDePanel();

    // En objetivo, fuera de objetivo y retirado: tres casos que se leen distinto.
    $dentro = Indicador::factory()->conObjetivo(90.0, SentidoIndicador::MayorMejor)->create(['codigo' => 'IND-01']);
    Medicion::factory()->for($dentro)->con(95.0, 90.0)->create();

    $fuera = Indicador::factory()->conObjetivo(90.0, SentidoIndicador::MayorMejor)->create(['codigo' => 'IND-02']);
    Medicion::factory()->for($fuera)->con(50.0, 90.0)->create();

    Indicador::factory()->retirado()->create(['codigo' => 'IND-03']);

    $this->actingAs($usuario)
        ->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            // Los retirados cuentan en el total y no en el seguimiento: su serie
            // se conserva, y es la que explica por qué se dejó de medir.
            ->where('desempeno.total', 3)
            ->where('desempeno.activos', 2)
            ->where('desempeno.fueraDeObjetivo', 1)
            // Un indicador nunca medido no está «fuera de objetivo»: está sin
            // medir, que es la distinción de `EstadoControl::PorConfirmar`.
            ->where('desempeno.nuncaMedidos', 0)
            ->has('desempeno.porCumplimiento', 2));
});

/**
 * La cifra que está por la norma y no por la pantalla: un periodo que cerró sin
 * medición es la cláusula 9.1 sin hacer.
 */
it('señala el periodo que cerró sin medir', function (): void {
    ['usuario' => $usuario] = escenarioDePanel();

    Indicador::factory()->conPeriodicidad(Periodicidad::Trimestral)->create(['codigo' => 'IND-01']);

    $this->actingAs($usuario)
        ->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('desempeno.periodoSinMedir', 1)
            ->where('desempeno.nuncaMedidos', 1));
});

it('no manda el desempeño a quien no puede verlo', function (): void {
    ['usuario' => $usuario] = escenarioDePanel();

    Indicador::factory()->create();

    // Al rol y no al usuario, por lo mismo que en las no conformidades y en el
    // contexto: `revokePermissionTo` sobre la persona no quita lo que hereda.
    Role::query()
        ->where('name', Rol::ResponsableSeguridad->value)
        ->where('organizacion_id', $usuario->organizacion_id)
        ->firstOrFail()
        ->revokePermissionTo(Permiso::IndicadoresVer->value);

    $this->actingAs($usuario->fresh())
        ->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('desempeno', null));
});
