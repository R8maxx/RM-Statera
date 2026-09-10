<?php

declare(strict_types=1);

use App\Domain\Activo\Models\Activo;
use App\Domain\Activo\RegistrarDependencia;
use App\Domain\Activo\ValoracionEfectiva;
use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Categorizacion\Enums\NivelDimension;

/*
|--------------------------------------------------------------------------
| La propagación de la valoración
|--------------------------------------------------------------------------
|
| Aquí un fallo infravalora un activo sin que nadie se entere, que es la misma
| clase de fallo silencioso que encabeza la lista de prioridades de cobertura:
| la base de datos que sostiene el servicio esencial se queda sin las medidas
| que le tocan y el sistema pasa la auditoría con un agujero dentro.
|
| Las dos mitades se prueban por separado y se contrastan entre ellas: `de()`
| resuelve un activo recorriendo el grafo y `paraLaOrganizacion()` resuelve
| todos de una vez para la tabla. Si dejan de coincidir, la tabla enseñaría una
| cifra y la ficha otra.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->efectiva = app(ValoracionEfectiva::class);
    $this->registrar = app(RegistrarDependencia::class);

    $this->activo = fn (string $codigo, array $valores = []): Activo => Activo::factory()
        ->de($this->organizacion)
        ->create(['codigo' => $codigo, 'nombre' => "Activo {$codigo}", ...$valores]);
});

it('deja la valoración como está cuando nada depende del activo', function (): void {
    $activo = ($this->activo)('SRV', ['valor_d' => NivelDimension::Medio->value]);

    expect($this->efectiva->de($activo)->disponibilidad)->toBe(NivelDimension::Medio);
    expect($this->efectiva->de($activo)->confidencialidad)->toBe(NivelDimension::Na);
});

it('propaga por toda la cadena y no sólo un salto', function (): void {
    $servicio = ($this->activo)('SRV', ['valor_d' => NivelDimension::Alto->value]);
    $aplicacion = ($this->activo)('APP');
    $base = ($this->activo)('BBDD');

    $this->registrar->vincular($servicio, $aplicacion);
    $this->registrar->vincular($aplicacion, $base);

    // Dos saltos por debajo del servicio: si el recorrido se quedara en el
    // primero, la base de datos seguiría valiendo `na`.
    expect($this->efectiva->de($base)->disponibilidad)->toBe(NivelDimension::Alto);
});

it('no toca la valoración propia', function (): void {
    $servicio = ($this->activo)('SRV', ['valor_d' => NivelDimension::Alto->value]);
    $base = ($this->activo)('BBDD');

    $this->registrar->vincular($servicio, $base);

    expect($this->efectiva->de($base)->disponibilidad)->toBe(NivelDimension::Alto);
    expect($base->fresh()?->valoracion()->disponibilidad)->toBe(NivelDimension::Na);
    expect($base->fresh()?->valor_d)->toBe(NivelDimension::Na->value);
});

it('eleva dimensión a dimensión y no la valoración entera', function (): void {
    $servicio = ($this->activo)('SRV', ['valor_d' => NivelDimension::Alto->value]);
    $base = ($this->activo)('BBDD', ['valor_c' => NivelDimension::Medio->value]);

    $this->registrar->vincular($servicio, $base);

    $efectiva = $this->efectiva->de($base);

    expect($efectiva->disponibilidad)->toBe(NivelDimension::Alto)
        ->and($efectiva->confidencialidad)->toBe(NivelDimension::Medio)
        ->and($efectiva->integridad)->toBe(NivelDimension::Na);
});

it('nunca baja un nivel propio por debajo de lo valorado', function (): void {
    $servicio = ($this->activo)('SRV', ['valor_c' => NivelDimension::Bajo->value]);
    $base = ($this->activo)('BBDD', ['valor_c' => NivelDimension::Alto->value]);

    $this->registrar->vincular($servicio, $base);

    expect($this->efectiva->de($base)->confidencialidad)->toBe(NivelDimension::Alto);
});

it('se queda con el máximo cuando dos activos distintos se apoyan en el mismo', function (): void {
    $uno = ($this->activo)('SRV-1', ['valor_i' => NivelDimension::Bajo->value]);
    $otro = ($this->activo)('SRV-2', ['valor_i' => NivelDimension::Alto->value]);
    $base = ($this->activo)('BBDD');

    $this->registrar->vincular($uno, $base);
    $this->registrar->vincular($otro, $base);

    expect($this->efectiva->de($base)->integridad)->toBe(NivelDimension::Alto);
});

it('no propaga hacia abajo: lo que sostiene sube, lo sostenido no baja', function (): void {
    $servicio = ($this->activo)('SRV');
    $base = ($this->activo)('BBDD', ['valor_c' => NivelDimension::Alto->value]);

    $this->registrar->vincular($servicio, $base);

    // El servicio depende de la base, no al revés: que la base sea confidencial
    // no hace confidencial al servicio.
    expect($this->efectiva->de($servicio)->confidencialidad)->toBe(NivelDimension::Na);
});

it('dice quién eleva la valoración y en qué dimensiones', function (): void {
    $servicio = ($this->activo)('SRV', ['valor_d' => NivelDimension::Alto->value]);
    $base = ($this->activo)('BBDD', ['valor_c' => NivelDimension::Medio->value]);

    $this->registrar->vincular($servicio, $base);

    $motivos = $this->efectiva->motivos($base);

    expect($motivos)->toHaveCount(1);
    expect($motivos[0]['activo']->codigo)->toBe('SRV');
    expect($motivos[0]['dimensiones'])->toBe([Dimension::Disponibilidad]);
});

it('no da motivos cuando la efectiva es la propia', function (): void {
    $servicio = ($this->activo)('SRV', ['valor_d' => NivelDimension::Bajo->value]);
    $base = ($this->activo)('BBDD', ['valor_d' => NivelDimension::Alto->value]);

    $this->registrar->vincular($servicio, $base);

    expect($this->efectiva->motivos($base))->toBeEmpty();
});

it('resuelve lo mismo activo a activo que de una sola consulta para la tabla', function (): void {
    $servicio = ($this->activo)('SRV', ['valor_d' => NivelDimension::Alto->value]);
    $aplicacion = ($this->activo)('APP', ['valor_t' => NivelDimension::Bajo->value]);
    $base = ($this->activo)('BBDD', ['valor_c' => NivelDimension::Medio->value]);
    $suelto = ($this->activo)('HW', ['valor_i' => NivelDimension::Bajo->value]);

    $this->registrar->vincular($servicio, $aplicacion);
    $this->registrar->vincular($aplicacion, $base);

    $deTodos = $this->efectiva->paraLaOrganizacion();

    foreach ([$servicio, $aplicacion, $base, $suelto] as $activo) {
        expect($deTodos[$activo->id]->aArray())
            ->toEqual($this->efectiva->de($activo)->aArray(), "Discrepan en {$activo->codigo}.");
    }
});

it('devuelve la valoración de todos los activos, tengan grafo o no', function (): void {
    $suelto = ($this->activo)('HW', ['valor_i' => NivelDimension::Medio->value]);

    $deTodos = $this->efectiva->paraLaOrganizacion();

    expect($deTodos)->toHaveKey($suelto->id);
    expect($deTodos[$suelto->id]->integridad)->toBe(NivelDimension::Medio);
});

it('no cuenta activos de otra organización en la propagación', function (): void {
    $base = ($this->activo)('BBDD');
    $servicio = ($this->activo)('SRV', ['valor_d' => NivelDimension::Alto->value]);
    $this->registrar->vincular($servicio, $base);

    $ajena = comoOrganizacion();
    $activoAjeno = Activo::factory()->de($ajena)->create(['codigo' => 'AJENO']);

    $deLaAjena = app(ValoracionEfectiva::class)->paraLaOrganizacion();

    expect(array_keys($deLaAjena))->toBe([$activoAjeno->id]);
});
