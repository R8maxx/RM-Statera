<?php

declare(strict_types=1);

use App\Domain\Documento\Enums\ClasificacionDocumental;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use Inertia\Testing\AssertableInertia;

/**
 * El formulario de la serie documental, tal como lo envía la interfaz.
 *
 * Dos fallos que sólo aparecieron recorriéndolo en el navegador: el centinela de
 * «Sin responsable» llegaba a la validación y el alta fallaba, y el formulario
 * deducía «redactado» de que el tipo no tuviera marco, así que presentaba el acta
 * y el informe de estado como si fueran una política.
 */
beforeEach(function (): void {
    comoOrganizacion();
    $this->usuario = usuarioCon();
});

it('crea un documento sin responsable ni sistema, como los manda el desplegable', function (): void {
    $this->actingAs($this->usuario)
        ->post('/documentos', [
            'tipo' => TipoDocumento::InformeEstado->value,
            'codigo' => 'EST-SGSI-01',
            'titulo' => 'Informe de estado de la seguridad',
            'clasificacion' => ClasificacionDocumental::UsoInterno->value,
            // `SIN_VALOR` de `lib/formularios.ts`.
            'responsable_id' => '__ninguno__',
            'sistema_id' => '__ninguno__',
        ])
        ->assertSessionHasNoErrors();

    $documento = Documento::query()->sole();

    expect($documento->responsable_id)->toBeNull()
        ->and($documento->sistema_id)->toBeNull();
});

it('manda de cada tipo su familia, sin dejar que el formulario la deduzca del marco', function (): void {
    $this->actingAs($this->usuario)
        ->get('/documentos/crear')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('tipos', function ($tipos): bool {
            $porValor = collect($tipos)->keyBy('valor');

            return $porValor['informe_estado']['redactado'] === false
                && $porValor['informe_estado']['exigeSistema'] === false
                && $porValor['acta_revision']['redactado'] === false
                && $porValor['politica']['redactado'] === true
                && $porValor['dda_ens']['exigeSistema'] === true;
        }));
});
