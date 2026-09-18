<?php

declare(strict_types=1);

use App\Domain\Documento\Enums\ClasificacionDocumental;
use App\Domain\Documento\Enums\TipoDocumento;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * El análisis del contexto es **calculado y sin sistema**, y eso rompe una
 * equivalencia que el `CHECK` daba por buena.
 *
 * `documentos_sistema_check` se construía desde `! esRedactado()`, porque hasta
 * aquí los tres calculados eran de un sistema y los tres redactados de la
 * organización. Las cuestiones internas y externas y las partes interesadas son
 * de la organización entera, así que el motor del `CHECK` pasó a ser
 * `TipoDocumento::exigeSistema()`.
 *
 * **Y esto hay que correrlo sobre una base migrada de verdad, no sólo con
 * `migrate:fresh`**: el `CHECK` de tipo se construye desde `TipoDocumento::cases()`
 * en ejecución, así que sobre una base recién creada ya incluiría el valor nuevo
 * aunque faltara su migración. Lo que este fichero prueba es la otra mitad, la que
 * sí depende de la migración: a quién se le exige sistema.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();

    $this->insertar = fn (string $codigo, TipoDocumento $tipo, ?int $sistema = null): bool => DB::table('documentos')->insert([
        'organizacion_id' => $this->organizacion->id,
        'sistema_id' => $sistema,
        'codigo' => $codigo,
        'titulo' => $tipo->etiqueta(),
        'tipo' => $tipo->value,
        'clasificacion' => ClasificacionDocumental::UsoInterno->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

it('la base admite un análisis del contexto sin sistema', function (): void {
    ($this->insertar)('CTX-SIN-SISTEMA', TipoDocumento::AnalisisContexto);

    expect(DB::table('documentos')->where('codigo', 'CTX-SIN-SISTEMA')->exists())->toBeTrue();
});

/*
 * La otra mitad, y la que de verdad se puede romper al reescribir el `CHECK`:
 * aflojarlo para dejar pasar el análisis del contexto no puede dejar pasar
 * también un plan o una declaración sin sistema.
 */
it('sigue sin admitir un plan de adecuación sin sistema', function (): void {
    ($this->insertar)('PLA-SIN-SISTEMA', TipoDocumento::PlanAdecuacionEns);
})->throws(QueryException::class);

it('sigue sin admitir una declaración de aplicabilidad sin sistema', function (): void {
    ($this->insertar)('SOA-SIN-SISTEMA', TipoDocumento::SoaIso);
})->throws(QueryException::class);

it('sigue admitiendo una política sin sistema', function (): void {
    ($this->insertar)('POL-SIN-SISTEMA', TipoDocumento::Politica);

    expect(DB::table('documentos')->where('codigo', 'POL-SIN-SISTEMA')->exists())->toBeTrue();
});

/*
 * Y la frontera, escrita donde se lee: `esRedactado()` y `exigeSistema()` dejaron
 * de ser la misma pregunta, y el análisis del contexto es el único caso que las
 * separa. Si alguien las vuelve a unir, este test lo dice.
 */
it('calculado y exigir sistema ya no son lo mismo', function (): void {
    expect(TipoDocumento::AnalisisContexto->esRedactado())->toBeFalse()
        ->and(TipoDocumento::AnalisisContexto->exigeSistema())->toBeFalse()
        ->and(TipoDocumento::PlanAdecuacionEns->exigeSistema())->toBeTrue()
        ->and(TipoDocumento::Politica->exigeSistema())->toBeFalse();
});
