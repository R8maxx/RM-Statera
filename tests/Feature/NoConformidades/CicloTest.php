<?php

declare(strict_types=1);

use App\Domain\NoConformidad\CambiarEstadoNoConformidad;
use App\Domain\NoConformidad\Enums\EstadoNoConformidad;
use App\Domain\NoConformidad\Excepciones\TransicionDeNoConformidadNoPermitida;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\NoConformidad\RegistrarNoConformidad;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * El ciclo de la cláusula 10.2, que es lo que este módulo existe para registrar.
 *
 * Lo que se clava aquí son las dos cosas que la base impone y que ninguna
 * pantalla puede saltarse: **las dos fechas acopladas a su estado** y el motivo
 * escrito de las transiciones que son una decisión. Con una sola fecha, la
 * verificación que llega dos meses después no tendría dónde fecharse; sin el
 * motivo, el histórico enseñaría un ir y venir de estados sin explicar ninguno.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();

    $this->cambiar = app(CambiarEstadoNoConformidad::class);

    $this->abrir = fn (): NoConformidad => app(RegistrarNoConformidad::class)([
        'codigo' => 'NC-2026-01',
        'descripcion' => 'El procedimiento de altas no deja constancia de la autorización.',
        'fecha_deteccion' => Carbon::today(),
    ], $this->usuario);
});

it('el alta deja su primera línea de histórico', function (): void {
    $nc = ($this->abrir)();

    expect($nc->estado)->toBe(EstadoNoConformidad::Abierta)
        ->and($nc->transiciones()->count())->toBe(1)
        ->and($nc->transiciones()->first()?->estado_anterior)->toBeNull()
        ->and($nc->transiciones()->first()?->estado_nuevo)->toBe(EstadoNoConformidad::Abierta);
});

/*
 * El recorrido entero, que es lo que le pasa a una no conformidad real: se
 * registra, alguien la coge, se cierra el tratamiento y **después** se comprueba
 * que sirvió. Los dos últimos pasos son dos momentos distintos y por eso son dos
 * fechas.
 */
it('recorre abierta, en tratamiento, cerrada y verificada, con sus dos fechas', function (): void {
    $nc = ($this->abrir)();

    ($this->cambiar)($nc, EstadoNoConformidad::EnTratamiento, $this->usuario);
    expect($nc->fresh()?->fecha_cierre)->toBeNull();

    ($this->cambiar)($nc, EstadoNoConformidad::Cerrada, $this->usuario);
    $nc->refresh();

    expect($nc->fecha_cierre)->not->toBeNull()
        // Tratada no es verificada: ésa es la razón de que sean dos estados.
        ->and($nc->fecha_verificacion)->toBeNull();

    ($this->cambiar)($nc, EstadoNoConformidad::Verificada, $this->usuario, 'Muestreo de diez altas: todas con autorización.');
    $nc->refresh();

    expect($nc->fecha_verificacion)->not->toBeNull()
        ->and($nc->verificada_por_id)->toBe($this->usuario->id)
        ->and($nc->resultado_verificacion)->toContain('Muestreo de diez altas');
});

/*
 * La verificación que sale mal. No es un estado —se quedaría puesto sobre una no
 * conformidad que sigue viva— sino la vuelta a `en_tratamiento`, y esa vuelta
 * suelta **las tres columnas de la verificación**: dejar el resultado puesto sin
 * su fecha sería enseñar una comprobación que ya no consta.
 */
it('la verificación fallida vuelve a tratamiento y suelta las dos fechas', function (): void {
    $nc = ($this->abrir)();

    ($this->cambiar)($nc, EstadoNoConformidad::Cerrada, $this->usuario);
    ($this->cambiar)($nc, EstadoNoConformidad::Verificada, $this->usuario, 'Comprobado por encima.');
    ($this->cambiar)($nc, EstadoNoConformidad::EnTratamiento, $this->usuario, 'Dos altas de marzo siguen sin autorización.');

    $nc->refresh();

    expect($nc->estado)->toBe(EstadoNoConformidad::EnTratamiento)
        ->and($nc->fecha_cierre)->toBeNull()
        ->and($nc->fecha_verificacion)->toBeNull()
        ->and($nc->verificada_por_id)->toBeNull()
        ->and($nc->resultado_verificacion)->toBeNull();

    // Y no se pierde nada: lo que se comprobó está en el histórico, porque al
    // verificar el texto se copia también a la nota de la transición.
    expect($nc->transiciones()->pluck('nota')->implode(' '))
        ->toContain('Comprobado por encima.')
        ->toContain('Dos altas de marzo');
});

it('anular exige motivo, y lo comprueba el dominio y no el formulario', function (): void {
    $nc = ($this->abrir)();

    expect(fn () => ($this->cambiar)($nc, EstadoNoConformidad::Anulada, $this->usuario))
        ->toThrow(TransicionDeNoConformidadNoPermitida::class, 'exige decir por qué');

    expect($nc->fresh()?->estado)->toBe(EstadoNoConformidad::Abierta);

    ($this->cambiar)($nc, EstadoNoConformidad::Anulada, $this->usuario, 'Duplicada de la NC-2026-01.');

    $nc->refresh();

    expect($nc->estado)->toBe(EstadoNoConformidad::Anulada)
        // Anulada también deja de estar abierta, así que lleva fecha de cierre:
        // la pregunta del auditor es «¿desde cuándo dejó de estar abierta?».
        ->and($nc->fecha_cierre)->not->toBeNull();
});

it('verificar exige decir qué se comprobó', function (): void {
    $nc = ($this->abrir)();
    ($this->cambiar)($nc, EstadoNoConformidad::Cerrada, $this->usuario);

    expect(fn () => ($this->cambiar)($nc, EstadoNoConformidad::Verificada, $this->usuario))
        ->toThrow(TransicionDeNoConformidadNoPermitida::class, 'qué se comprobó');

    expect($nc->fresh()?->estado)->toBe(EstadoNoConformidad::Cerrada);
});

it('reabrir el tratamiento exige decir qué falló', function (): void {
    $nc = ($this->abrir)();
    ($this->cambiar)($nc, EstadoNoConformidad::Cerrada, $this->usuario);

    expect(fn () => ($this->cambiar)($nc, EstadoNoConformidad::EnTratamiento, $this->usuario))
        ->toThrow(TransicionDeNoConformidadNoPermitida::class, 'qué falló');
});

/*
 * Volver a `abierta` desde algo ya tratado es decir que nadie la ha cogido, y eso
 * es reescribir el pasado — la misma puerta que `EstadoAuditoria` no abre hacia
 * `planificada`.
 */
it('de cerrada no se vuelve a abierta', function (): void {
    $nc = ($this->abrir)();
    ($this->cambiar)($nc, EstadoNoConformidad::Cerrada, $this->usuario);

    expect(fn () => ($this->cambiar)($nc, EstadoNoConformidad::Abierta, $this->usuario))
        ->toThrow(TransicionDeNoConformidadNoPermitida::class, 'No se permite pasar');
});

it('una anulada por error se reabre', function (): void {
    $nc = ($this->abrir)();
    ($this->cambiar)($nc, EstadoNoConformidad::Anulada, $this->usuario, 'Me he equivocado de hallazgo.');
    ($this->cambiar)($nc, EstadoNoConformidad::Abierta, $this->usuario);

    $nc->refresh();

    expect($nc->estado)->toBe(EstadoNoConformidad::Abierta)
        ->and($nc->fecha_cierre)->toBeNull();
});

/*
 * Y ahora la base, saltándose el dominio entero. Los `CHECK` son la barrera que
 * queda cuando alguien escribe desde un importador, desde `tinker` o desde una
 * migración de datos.
 */
it('la base rechaza una cerrada sin fecha de cierre', function (): void {
    ($this->abrir)();

    DB::statement(
        "update no_conformidades set estado = 'cerrada' where codigo = 'NC-2026-01'"
    );
})->throws(QueryException::class, 'no_conformidades_cierre_coherente_check');

it('la base rechaza una abierta con fecha de cierre', function (): void {
    ($this->abrir)();

    DB::statement(
        "update no_conformidades set fecha_cierre = current_date where codigo = 'NC-2026-01'"
    );
})->throws(QueryException::class, 'no_conformidades_cierre_coherente_check');

it('la base rechaza una verificada sin fecha de verificación', function (): void {
    ($this->abrir)();

    DB::statement(
        "update no_conformidades set estado = 'verificada', fecha_cierre = current_date where codigo = 'NC-2026-01'"
    );
})->throws(QueryException::class, 'no_conformidades_verificacion_coherente_check');

/*
 * La otra dirección del mismo acoplamiento, y la que de verdad se escapa: una
 * fecha de verificación puesta sobre algo que no está verificado deja el registro
 * diciendo que se comprobó la eficacia de un tratamiento que sigue abierto.
 */
it('la base rechaza una fecha de verificación sin el estado verificada', function (): void {
    ($this->abrir)();

    DB::statement(
        "update no_conformidades set estado = 'cerrada', fecha_cierre = current_date, fecha_verificacion = current_date where codigo = 'NC-2026-01'"
    );
})->throws(QueryException::class, 'no_conformidades_verificacion_coherente_check');

it('la base rechaza verificar antes de haber cerrado el tratamiento', function (): void {
    ($this->abrir)();

    DB::statement(
        "update no_conformidades set estado = 'verificada', fecha_cierre = current_date, fecha_verificacion = current_date - 5 where codigo = 'NC-2026-01'"
    );
})->throws(QueryException::class, 'no_conformidades_orden_fechas_check');

/*
 * El `CHECK` que ata el hallazgo al origen, en la única dirección en la que
 * aplica: una no conformidad con hallazgo detrás es de auditoría por definición.
 * La contraria es legítima —la auditoría que el cliente trae en papel—.
 */
it('la base no deja colgar un hallazgo de una no conformidad de otro origen', function (): void {
    ($this->abrir)();

    DB::statement(
        "update no_conformidades set hallazgo_id = 1 where codigo = 'NC-2026-01'"
    );
})->throws(QueryException::class);
