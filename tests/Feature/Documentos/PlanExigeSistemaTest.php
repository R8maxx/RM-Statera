<?php

declare(strict_types=1);

use App\Domain\Documento\Enums\ClasificacionDocumental;
use App\Domain\Documento\Enums\TipoDocumento;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Un plan de adecuación sin sistema es un documento imposible.
 *
 * Sus medidas salen de la categorización de un sistema concreto, así que sin él
 * no hay nada que listar. Esto lo comprueba el `FormRequest`, pero la barrera que
 * no depende de que ningún PHP se acuerde es el `CHECK` de la tabla — y ese
 * `CHECK` **no lo cubría**: estaba escrito en negativo sobre la lista literal
 * `('soa_iso', 'dda_ens')` para que los tipos de ámbito organizativo no obligaran
 * a reescribirlo, y el plan es el primer tipo calculado que llegó después.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
});

it('la base no admite un plan de adecuación sin sistema', function (): void {
    DB::table('documentos')->insert([
        'organizacion_id' => $this->organizacion->id,
        'sistema_id' => null,
        'codigo' => 'PLA-SIN-SISTEMA',
        'titulo' => 'Plan de adecuación sin sistema',
        'tipo' => TipoDocumento::PlanAdecuacionEns->value,
        'clasificacion' => ClasificacionDocumental::UsoInterno->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('sigue admitiendo un documento redactado sin sistema', function (): void {
    // La razón por la que el `CHECK` se escribió en negativo sigue en pie: una
    // política es de la organización entera y no cuelga de ningún sistema.
    DB::table('documentos')->insert([
        'organizacion_id' => $this->organizacion->id,
        'sistema_id' => null,
        'codigo' => 'POL-SIN-SISTEMA',
        'titulo' => 'Política de Seguridad de la Información',
        'tipo' => TipoDocumento::Politica->value,
        'clasificacion' => ClasificacionDocumental::UsoInterno->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('documentos')->where('codigo', 'POL-SIN-SISTEMA')->exists())->toBeTrue();
});
