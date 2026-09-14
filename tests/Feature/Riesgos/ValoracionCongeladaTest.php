<?php

declare(strict_types=1);

use App\Domain\Riesgo\AceptarRiesgo;
use App\Domain\Riesgo\Enums\DecisionRiesgo;
use App\Domain\Riesgo\MetodologiaDeFabrica;
use App\Domain\Riesgo\MetodologiaVigente;
use App\Domain\Riesgo\Models\MetodologiaRiesgo;
use App\Domain\Riesgo\Models\Riesgo;
use App\Domain\Riesgo\Models\RiesgoValoracion;
use App\Domain\Riesgo\ValorarRiesgo;
use Illuminate\Database\QueryException;

/*
|--------------------------------------------------------------------------
| El histórico es comparable, y es inmutable
|--------------------------------------------------------------------------
|
| Dos propiedades que sostienen todo el módulo, y ninguna se ve leyendo el código:
|
| 1. Cambiar la metodología NO revalúa el histórico. Sin la escala congelada, un 12
|    de marzo se vuelve un número sin unidades en cuanto alguien toque la escala, y
|    «histórico comparable» deja de serlo.
|
| 2. Una valoración aceptada no se reescribe, y lo impide un trigger de PostgreSQL,
|    no la buena voluntad. Una aceptación de riesgo editable desde PHP no es una
|    aceptación.
|
*/

function riesgoValorado(int $probabilidad = 3, int $impacto = 3, array $extra = []): RiesgoValoracion
{
    $riesgo = Riesgo::factory()->create();

    return app(ValorarRiesgo::class)($riesgo, [
        'probabilidad' => $probabilidad,
        'impacto' => $impacto,
        ...$extra,
    ]);
}

it('congela la escala con la que se midió', function (): void {
    comoOrganizacion();

    $valoracion = riesgoValorado();

    expect($valoracion->escala)->not->toBeEmpty()
        ->and($valoracion->metodologiaCongelada()->umbralAceptacion)
        ->toBe(MetodologiaDeFabrica::UMBRAL_ACEPTACION);
});

it('cambiar la metodología no revalúa el histórico', function (): void {
    comoOrganizacion();

    // Medido con la escala de fábrica: 3 × 3 = 9, por encima del umbral 8.
    $valoracion = riesgoValorado();

    expect($valoracion->riesgo_intrinseco)->toBe(9)
        ->and($valoracion->nivelIntrinseco()->sobreUmbral())->toBeTrue();

    // La organización sube el listón: ahora sólo le preocupa a partir de 15.
    MetodologiaRiesgo::factory()->conUmbrales(15, 20)->create();
    app(MetodologiaVigente::class)->olvidar();

    // La valoración de antes se sigue leyendo con SU escala, no con la de ahora.
    $releida = $valoracion->fresh();

    expect($releida->metodologiaCongelada()->umbralAceptacion)->toBe(8)
        ->and($releida->nivelIntrinseco()->sobreUmbral())->toBeTrue();

    // Y una medición nueva sí usa la escala nueva.
    $nueva = riesgoValorado();

    expect($nueva->metodologiaCongelada()->umbralAceptacion)->toBe(15)
        ->and($nueva->nivelIntrinseco()->sobreUmbral())->toBeFalse();
});

it('congela las salvaguardas tal como estaban', function (): void {
    // Sin esto la fila MIENTE en cuanto una implantación cambie de estado: la
    // valoración de marzo diría que se apoyaba en controles que en marzo no
    // estaban puestos.
    comoOrganizacion();

    $valoracion = riesgoValorado();

    expect($valoracion->salvaguardas)->toBeArray();
});

it('valorar de nuevo jubila la anterior y deja una sola vigente', function (): void {
    comoOrganizacion();

    $riesgo = Riesgo::factory()->create();
    $valorar = app(ValorarRiesgo::class);

    $primera = $valorar($riesgo, ['probabilidad' => 2, 'impacto' => 2]);
    $segunda = $valorar($riesgo, ['probabilidad' => 4, 'impacto' => 4]);

    expect($primera->fresh()->vigente)->toBeFalse()
        ->and($segunda->vigente)->toBeTrue()
        ->and($riesgo->valoraciones()->count())->toBe(2)
        ->and($riesgo->fresh()->valoracionVigente->riesgo_intrinseco)->toBe(16);
});

it('reevaluar un riesgo ACEPTADO sigue siendo posible', function (): void {
    /*
     * El caso que se escapa al escribir el trigger: jubilar una valoración
     * aceptada es el primer paso de toda reevaluación. Si el trigger lo bloqueara,
     * un riesgo aceptado no se podría volver a mirar nunca, que es lo contrario de
     * lo que pide «reevaluación periódica».
     */
    comoOrganizacion();
    $usuario = usuarioCon();

    $riesgo = Riesgo::factory()->create();
    $valorar = app(ValorarRiesgo::class);

    $valorar($riesgo, ['probabilidad' => 3, 'impacto' => 3, 'probabilidad_residual' => 2, 'impacto_residual' => 2]);
    app(AceptarRiesgo::class)($riesgo->fresh(), $usuario);

    $nueva = $valorar($riesgo->fresh(), ['probabilidad' => 4, 'impacto' => 4]);

    expect($nueva->vigente)->toBeTrue()
        ->and($riesgo->valoraciones()->count())->toBe(2);

    // La aceptada sigue ahí, firmada, y ya no es la vigente.
    $anterior = $riesgo->valoraciones()->where('id', '!=', $nueva->id)->first();

    expect($anterior->estaAceptada())->toBeTrue()
        ->and($anterior->vigente)->toBeFalse();
});

it('una valoración aceptada no se puede reescribir', function (): void {
    comoOrganizacion();
    $usuario = usuarioCon();

    $riesgo = Riesgo::factory()->create();
    app(ValorarRiesgo::class)($riesgo, [
        'probabilidad' => 3,
        'impacto' => 3,
        'probabilidad_residual' => 1,
        'impacto_residual' => 1,
    ]);

    $aceptada = app(AceptarRiesgo::class)($riesgo->fresh(), $usuario);

    // Lo impide la base, no PHP.
    expect(fn () => $aceptada->update(['probabilidad' => 1]))
        ->toThrow(QueryException::class, 'no se modifica');
});

it('una valoración jubilada es histórico y no se toca', function (): void {
    comoOrganizacion();

    $riesgo = Riesgo::factory()->create();
    $valorar = app(ValorarRiesgo::class);

    $primera = $valorar($riesgo, ['probabilidad' => 2, 'impacto' => 2]);
    $valorar($riesgo, ['probabilidad' => 4, 'impacto' => 4]);

    expect(fn () => $primera->fresh()->update(['nota' => 'retocando el pasado']))
        ->toThrow(QueryException::class, 'historico');
});

it('la vigente sin firmar sí se puede corregir', function (): void {
    // Es el equivalente del borrador de un documento: mientras nadie la haya
    // firmado y siga contando, corregirla es lo que se espera.
    comoOrganizacion();

    $valoracion = riesgoValorado();
    $valoracion->update(['nota' => 'me faltaba un matiz']);

    expect($valoracion->fresh()->nota)->toBe('me faltaba un matiz');
});

it('no deja dos vigentes para el mismo riesgo', function (): void {
    comoOrganizacion();

    $riesgo = Riesgo::factory()->create();
    RiesgoValoracion::factory()->for($riesgo)->create();

    // Lo garantiza el índice único parcial, y es lo que permite que el recurso
    // una esta tabla sin multiplicar filas.
    expect(fn () => RiesgoValoracion::factory()->for($riesgo)->create())
        ->toThrow(QueryException::class);
});

it('medio residual no se guarda a medias', function (): void {
    // Un dato que no se puede multiplicar dejaría una fila que parece valorada y
    // no lo está.
    comoOrganizacion();

    $valoracion = riesgoValorado(3, 3, ['probabilidad_residual' => 2, 'justificacion_residual' => 'a medias']);

    expect($valoracion->probabilidad_residual)->toBeNull()
        ->and($valoracion->impacto_residual)->toBeNull()
        ->and($valoracion->riesgo_residual)->toBeNull()
        ->and($valoracion->justificacion_residual)->toBeNull();
});

it('el residual no puede superar al intrínseco', function (): void {
    // Una salvaguarda no empeora un riesgo: es un error de captura, no un juicio.
    comoOrganizacion();

    expect(fn () => riesgoValorado(2, 2, ['probabilidad_residual' => 5, 'impacto_residual' => 5]))
        ->toThrow(QueryException::class);
});

it('la decisión por defecto es mitigar', function (): void {
    comoOrganizacion();

    expect(riesgoValorado()->decision)->toBe(DecisionRiesgo::Mitigar);
});
