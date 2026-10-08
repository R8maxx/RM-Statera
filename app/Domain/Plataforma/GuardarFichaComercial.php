<?php

declare(strict_types=1);

namespace App\Domain\Plataforma;

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Models\FichaComercial;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Guarda la ficha comercial de un cliente desde la plataforma (punto 54).
 *
 * Dos destinos, a propósito:
 *
 * - **la identificación** —nombre, razón social, CIF, sector— va a
 *   `organizaciones`, **dentro de su contexto**, para que la traza del cliente
 *   registre que la plataforma la cambió;
 * - **el contacto y las notas** van a `fichas_comerciales`, que es de la
 *   plataforma y no pasa por la traza del cliente.
 */
final class GuardarFichaComercial
{
    public function __construct(
        private readonly ContextoOrganizacion $contexto,
        private readonly TrazaPlataforma $traza,
    ) {}

    /**
     * @param  array{nombre: string, razon_social: ?string, cif: ?string, sector: ?string}  $identificacion
     * @param  array{contacto_nombre: ?string, contacto_email: ?string, contacto_telefono: ?string, notas: ?string}  $comercial
     */
    public function __invoke(Organizacion $organizacion, array $identificacion, array $comercial): void
    {
        DB::transaction(function () use ($organizacion, $identificacion, $comercial): void {
            $this->contexto->paraOrganizacion($organizacion, fn () => $organizacion->forceFill($identificacion)->save());

            FichaComercial::query()->updateOrCreate(
                ['organizacion_afectada_id' => $organizacion->id],
                [...$comercial, 'actualizada_por' => Auth::id()],
            );

            $this->traza->registrar(AccionPlataforma::FichaComercialGuardada, $organizacion, [
                'cambios' => array_keys($organizacion->getChanges()),
            ]);
        });
    }
}
