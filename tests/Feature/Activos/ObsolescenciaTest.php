<?php

declare(strict_types=1);

use App\Domain\Activo\Models\Activo;
use App\Domain\Activo\Obsolescencia;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| Obsolescencia del software base
|--------------------------------------------------------------------------
|
| El fallo caro aquí no es dejar de avisar de un sistema sin parches: es avisar
| de más. Un panel que marca en rojo cada macOS del parque porque no está en la
| lista se deja de mirar en dos semanas, y entonces tampoco avisa del que sí
| importa.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->obsolescencia = app(Obsolescencia::class);

    $this->activo = fn (array $atributos = []): Activo => Activo::factory()
        ->de($this->organizacion)
        ->create($atributos);
});

it('conoce la fecha de fin de soporte de un sistema de la lista', function (): void {
    expect($this->obsolescencia->finDeSoporteDe('Ubuntu 20.04 LTS')?->toDateString())
        ->toBe('2025-05-31');
});

it('devuelve nulo para un sistema que no conoce', function (): void {
    expect($this->obsolescencia->finDeSoporteDe('SistemaOperativoInventado 1.0'))->toBeNull();
    expect($this->obsolescencia->finDeSoporteDe(null))->toBeNull();
});

it('devuelve nulo para los de versión continua', function (): void {
    // macOS y Amazon Linux no tienen fecha de fin: van por versión mayor. No es
    // lo mismo que no saberlo, pero de cara al aviso significa lo mismo.
    expect($this->obsolescencia->finDeSoporteDe('macOS'))->toBeNull();
});

it('marca vencido lo que pasó de fecha', function (): void {
    $viejo = ($this->activo)(['fin_soporte_so' => Carbon::yesterday()->toDateString()]);
    $nuevo = ($this->activo)(['fin_soporte_so' => Carbon::tomorrow()->toDateString()]);

    expect($this->obsolescencia->soporteVencido($viejo))->toBeTrue()
        ->and($this->obsolescencia->soporteVencido($nuevo))->toBeFalse();
});

it('no marca vencido un activo sin fecha', function (): void {
    // No saber no es incumplir, igual que «por confirmar» no es «no».
    $sinFecha = ($this->activo)(['fin_soporte_so' => null, 'fin_garantia' => null]);

    expect($this->obsolescencia->soporteVencido($sinFecha))->toBeFalse()
        ->and($this->obsolescencia->garantiaVencida($sinFecha))->toBeFalse()
        ->and($this->obsolescencia->aviso($sinFecha))->toBeNull();
});

it('detecta la garantía vencida aparte del sistema operativo', function (): void {
    $activo = ($this->activo)([
        'fin_soporte_so' => Carbon::tomorrow()->toDateString(),
        'fin_garantia' => Carbon::yesterday()->toDateString(),
    ]);

    expect($this->obsolescencia->soporteVencido($activo))->toBeFalse()
        ->and($this->obsolescencia->garantiaVencida($activo))->toBeTrue();
});

it('junta los dos motivos en un solo aviso', function (): void {
    // Dos avisos seguidos sobre el mismo equipo se leen como uno y se ignoran
    // igual, así que va una frase.
    $activo = ($this->activo)([
        'sistema_operativo' => 'Ubuntu 20.04 LTS',
        'fin_soporte_so' => Carbon::yesterday()->toDateString(),
        'fin_garantia' => Carbon::yesterday()->toDateString(),
    ]);

    $aviso = $this->obsolescencia->aviso($activo);

    expect($aviso)->toContain('Ubuntu 20.04 LTS')
        ->and($aviso)->toContain('garantía')
        // Una sola frase: los dos motivos unidos por «y» y un punto al final.
        ->and($aviso)->toContain(' y ')
        ->and($aviso)->toEndWith('.');
});

it('nombra el sistema operativo en el aviso cuando lo hay', function (): void {
    $conNombre = ($this->activo)([
        'sistema_operativo' => 'Debian 12',
        'fin_soporte_so' => Carbon::yesterday()->toDateString(),
    ]);
    $sinNombre = ($this->activo)([
        'sistema_operativo' => null,
        'fin_soporte_so' => Carbon::yesterday()->toDateString(),
    ]);

    expect($this->obsolescencia->aviso($conNombre))->toStartWith('Debian 12')
        ->and($this->obsolescencia->aviso($sinNombre))->toStartWith('El sistema operativo');
});

it('ofrece la lista de sistemas para el formulario', function (): void {
    $sistemas = $this->obsolescencia->sistemasOperativos();

    expect($sistemas)->toHaveKey('Ubuntu 24.04 LTS')
        ->and($sistemas['Ubuntu 24.04 LTS'])->toBe('2029-05-31');
});
