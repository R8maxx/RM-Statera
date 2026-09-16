<?php

declare(strict_types=1);

use App\Domain\Auditoria\CerrarAuditoria;
use App\Domain\Auditoria\Enums\EstadoAuditoria;
use App\Domain\Auditoria\Enums\ResultadoPunto;
use App\Domain\Auditoria\Enums\TipoHallazgo;
use App\Domain\Auditoria\Excepciones\AuditoriaCerrada;
use App\Domain\Auditoria\Excepciones\TransicionDeAuditoriaNoPermitida;
use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Auditoria\Models\AuditoriaPunto;
use App\Domain\Auditoria\Models\Hallazgo;
use App\Domain\Auditoria\PrecargarChecklist;
use App\Domain\Catalogo\Enums\TipoRequisito;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Sistema\Models\Sistema;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Cerrar una auditoría es lo que la convierte en un hecho.
 *
 * Es la tercera vez que el producto necesita esto —las otras dos son la versión
 * de un documento emitido y la valoración de un riesgo aceptado— y el motivo es
 * siempre el mismo: lo que se le enseña al auditor siguiente tiene que poder
 * demostrarse tal cual se cerró.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();

    $marco = Marco::factory()->create();
    $this->sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create();

    $this->medida = function (string $codigo, int $orden, array $atributos = []) use ($marco): Implantacion {
        $requisito = Requisito::factory()->create([
            'marco_id' => $marco->id,
            'codigo' => $codigo,
            'tipo' => TipoRequisito::Medida->value,
            'orden' => $orden,
        ]);

        return Implantacion::factory()->for($this->sistema)->create([
            'requisito_id' => $requisito->id,
            ...$atributos,
        ]);
    };

    $this->auditoria = fn (): Auditoria => Auditoria::factory()
        ->paraSistema($this->sistema->id)
        ->create();
});

it('precarga una línea por cada medida exigible y ni una más', function (): void {
    ($this->medida)('op.acc.1', 1);
    ($this->medida)('op.acc.2', 2);

    // Excluida: el motor dice que al sistema no se le exige, así que no se audita.
    ($this->medida)('op.acc.3', 3, [
        'aplica' => false,
        'estado' => EstadoImplantacion::NoAplica->value,
        'justificacion' => 'El sistema no almacena información de ese tipo.',
    ]);

    $auditoria = ($this->auditoria)();

    expect(app(PrecargarChecklist::class)($auditoria))->toBe(2)
        ->and($auditoria->puntos()->count())->toBe(2);
});

it('volver a precargar no duplica ni pisa lo ya revisado', function (): void {
    $primera = ($this->medida)('op.acc.1', 1);
    $auditoria = ($this->auditoria)();

    $precargar = app(PrecargarChecklist::class);
    $precargar($auditoria);

    $punto = AuditoriaPunto::query()->where('implantacion_id', $primera->id)->firstOrFail();
    $punto->update(['resultado' => ResultadoPunto::Conforme->value]);

    // Una medida pasa a ser exigible a mitad de auditoría: hay que poder añadirla.
    ($this->medida)('op.acc.2', 2);

    expect($precargar($auditoria))->toBe(1)
        ->and($auditoria->puntos()->count())->toBe(2)
        ->and($punto->fresh()?->resultado)->toBe(ResultadoPunto::Conforme);
});

/*
 * El que de verdad importa. Sin el congelado, la auditoría de marzo leería el
 * registro de octubre y diría lo que la medida es hoy, no lo que era cuando la
 * miraron. Es el argumento de las dos `jsonb` de `riesgo_valoraciones`.
 */
it('congela al cerrar lo que la implantación decía ese día', function (): void {
    $medida = ($this->medida)('op.acc.1', 1, ['estado' => EstadoImplantacion::EnProgreso->value]);

    $auditoria = ($this->auditoria)();
    app(PrecargarChecklist::class)($auditoria);

    app(CerrarAuditoria::class)->empezar($auditoria);
    app(CerrarAuditoria::class)->cerrar($auditoria, usuarioCon());

    // La medida avanza después de cerrarse la auditoría.
    $medida->update(['estado' => EstadoImplantacion::Implantado->value]);

    $punto = AuditoriaPunto::query()->where('implantacion_id', $medida->id)->firstOrFail();

    expect($punto->estado_congelado)->toBe(EstadoImplantacion::EnProgreso)
        ->and($punto->estadoAuditado())->toBe(EstadoImplantacion::EnProgreso);
});

it('mientras está abierta vale lo que diga el registro de hoy', function (): void {
    $medida = ($this->medida)('op.acc.1', 1, ['estado' => EstadoImplantacion::NoIniciado->value]);

    $auditoria = ($this->auditoria)();
    app(PrecargarChecklist::class)($auditoria);

    $medida->update(['estado' => EstadoImplantacion::Implantado->value]);

    $punto = AuditoriaPunto::query()->where('implantacion_id', $medida->id)->firstOrFail();

    expect($punto->estado_congelado)->toBeNull()
        ->and($punto->load('implantacion')->estadoAuditado())->toBe(EstadoImplantacion::Implantado);
});

/*
 * Y el que impide que todo lo anterior sirva de nada: si la checklist se puede
 * reescribir desde SQL, la auditoría cerrada no demuestra nada.
 */
it('una auditoría cerrada no admite cambios en su checklist ni con SQL en crudo', function (): void {
    $medida = ($this->medida)('op.acc.1', 1);
    $auditoria = ($this->auditoria)();
    app(PrecargarChecklist::class)($auditoria);

    $punto = AuditoriaPunto::query()->where('implantacion_id', $medida->id)->firstOrFail();

    app(CerrarAuditoria::class)->empezar($auditoria);
    app(CerrarAuditoria::class)->cerrar($auditoria, usuarioCon());

    DB::table('auditoria_puntos')
        ->where('id', $punto->id)
        ->update(['resultado' => ResultadoPunto::Conforme->value]);
})->throws(QueryException::class);

it('una auditoría cerrada no admite hallazgos nuevos ni con SQL en crudo', function (): void {
    ($this->medida)('op.acc.1', 1);
    $auditoria = ($this->auditoria)();
    app(PrecargarChecklist::class)($auditoria);

    app(CerrarAuditoria::class)->empezar($auditoria);
    app(CerrarAuditoria::class)->cerrar($auditoria, usuarioCon());

    DB::table('hallazgos')->insert([
        'organizacion_id' => $this->organizacion->id,
        'auditoria_id' => $auditoria->id,
        'auditoria_punto_id' => null,
        'tipo' => TipoHallazgo::NcMayor->value,
        'descripcion' => 'Colado después de cerrar.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('un hallazgo de una auditoría cerrada no se puede borrar', function (): void {
    ($this->medida)('op.acc.1', 1);
    $auditoria = ($this->auditoria)();

    $hallazgo = Hallazgo::factory()->create(['auditoria_id' => $auditoria->id]);

    app(CerrarAuditoria::class)->empezar($auditoria);
    app(CerrarAuditoria::class)->cerrar($auditoria, usuarioCon());

    DB::table('hallazgos')->where('id', $hallazgo->id)->delete();
})->throws(QueryException::class);

it('la propia auditoría cerrada tampoco se reescribe', function (): void {
    $auditoria = ($this->auditoria)();

    app(CerrarAuditoria::class)->empezar($auditoria);
    app(CerrarAuditoria::class)->cerrar($auditoria, usuarioCon());

    DB::table('auditorias')->where('id', $auditoria->id)->update(['alcance' => 'Otro alcance']);
})->throws(QueryException::class);

/*
 * La puerta. Sin ella, una auditoría mal cerrada se queda mal para siempre — que
 * es lo mismo que pasaría con un riesgo aceptado que no se pudiera revaluar.
 */
it('se puede reabrir, y entonces vuelve a admitir cambios', function (): void {
    $medida = ($this->medida)('op.acc.1', 1);
    $auditoria = ($this->auditoria)();
    app(PrecargarChecklist::class)($auditoria);

    $cerrar = app(CerrarAuditoria::class);
    $cerrar->empezar($auditoria);
    $cerrar->cerrar($auditoria, usuarioCon());

    $cerrar->reabrir($auditoria);

    expect($auditoria->estado)->toBe(EstadoAuditoria::EnCurso)
        ->and($auditoria->fecha_cierre)->toBeNull()
        ->and($auditoria->cerrada_por_id)->toBeNull();

    $punto = AuditoriaPunto::query()->where('implantacion_id', $medida->id)->firstOrFail();
    $punto->update(['resultado' => ResultadoPunto::NoConforme->value]);

    expect($punto->fresh()?->resultado)->toBe(ResultadoPunto::NoConforme);
});

it('de cerrada no se vuelve a planificada', function (): void {
    $auditoria = ($this->auditoria)();

    $cerrar = app(CerrarAuditoria::class);
    $cerrar->empezar($auditoria);
    $cerrar->cerrar($auditoria, usuarioCon());

    expect($auditoria->estado->permite(EstadoAuditoria::Planificada))->toBeFalse();

    DB::table('auditorias')->where('id', $auditoria->id)->update([
        'estado' => EstadoAuditoria::Planificada->value,
        'fecha_cierre' => null,
        'cerrada_por_id' => null,
    ]);
})->throws(QueryException::class);

it('no deja cerrar una auditoría que todavía está planificada', function (): void {
    app(CerrarAuditoria::class)->cerrar(($this->auditoria)(), usuarioCon());
})->throws(TransicionDeAuditoriaNoPermitida::class);

it('no deja regenerar la checklist de una auditoría cerrada', function (): void {
    $auditoria = ($this->auditoria)();

    $cerrar = app(CerrarAuditoria::class);
    $cerrar->empezar($auditoria);
    $cerrar->cerrar($auditoria, usuarioCon());

    app(PrecargarChecklist::class)($auditoria);
})->throws(AuditoriaCerrada::class);

it('el cierre queda firmado con su fecha y su autor', function (): void {
    $auditor = User::factory()->create(['organizacion_id' => $this->organizacion->id]);
    $auditoria = ($this->auditoria)();

    $cerrar = app(CerrarAuditoria::class);
    $cerrar->empezar($auditoria);
    $cerrar->cerrar($auditoria, $auditor, 'Dos no conformidades menores.');

    expect($auditoria->estado)->toBe(EstadoAuditoria::Cerrada)
        ->and($auditoria->cerrada_por_id)->toBe($auditor->id)
        ->and($auditoria->fecha_cierre)->not->toBeNull()
        ->and($auditoria->conclusiones)->toBe('Dos no conformidades menores.');
});
