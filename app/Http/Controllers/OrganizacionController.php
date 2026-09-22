<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\GuardarFichaOrganizacion;
use App\Domain\Organizacion\Marca\PiezaDeMarca;
use App\Domain\Organizacion\Models\Organizacion;
use App\Http\Requests\GuardarFichaOrganizacionRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La ficha del tenant: quién es la organización, dónde está y qué le aplica.
 *
 * **Ninguna de las dos rutas lleva parámetro**, y es la decisión que sostiene el
 * aislamiento de este controlador. `organizaciones` es la raíz del tenant: no
 * lleva `PerteneceAOrganizacion`, ni scope global, ni RLS —filtrarse a sí misma
 * no significa nada—, así que un `/organizacion/{organizacion}` habría que
 * acotarlo a mano y **ningún test de aislamiento avisaría si se olvidara**. La
 * organización sale del contexto y de ningún otro sitio. Mismo argumento que ya
 * está escrito en `PerfilFotoController`.
 *
 * Se edita, no se crea ni se borra: dar de alta tenants es panel de
 * superadministración, que está fuera de alcance en los tres documentos.
 */
class OrganizacionController extends Controller
{
    public function __construct(private readonly ContextoOrganizacion $contexto) {}

    public function edit(): Response
    {
        $organizacion = $this->actual();

        return Inertia::render('organizacion/Editar', [
            'organizacion' => [
                'id' => $organizacion->id,
                'nombre' => $organizacion->nombre,
                'razon_social' => $organizacion->razon_social,
                'cif' => $organizacion->cif,
                'sector' => $organizacion->sector,
                'domicilio' => $organizacion->domicilio,
                'codigo_postal' => $organizacion->codigo_postal,
                'municipio' => $organizacion->municipio,
                'provincia' => $organizacion->provincia,
                'url_base_etiquetas' => $organizacion->url_base_etiquetas,
                'sujeto_obligado_ens' => $organizacion->sujeto_obligado_ens,
                'proveedor_sector_publico' => $organizacion->proveedor_sector_publico,
            ],

            /*
             * Derivados, para que la pantalla los enseñe sin guardarlos. Es el
             * primer lector que tiene `leAplicaElEns()`, que llevaba declarado
             * desde la primera migración sin que nadie lo invocara.
             */
            'leAplicaElEns' => $organizacion->leAplicaElEns(),

            /*
             * Las dos piezas de marca, cada una con su URL o nula. El cliente
             * no compone la ruta: la da `Organizacion::urlMarca()` con su
             * sufijo de versión, o el navegador serviría el logo viejo de su
             * caché al cambiarlo.
             */
            'marca' => [
                'logo' => $organizacion->urlMarca(PiezaDeMarca::Logo),
                'simbolo' => $organizacion->urlMarca(PiezaDeMarca::Simbolo),
            ],

            // A dónde apuntará el QR de una etiqueta con la base que hay puesta.
            // Es lo único que no se puede comprobar hasta tener la pegatina en
            // la mano, así que se enseña antes de imprimirla.
            'ejemploEtiqueta' => rtrim(
                $organizacion->url_base_etiquetas ?: (string) config('app.url'), '/'
            ).'/activos/1',
        ]);
    }

    public function update(
        GuardarFichaOrganizacionRequest $request,
        GuardarFichaOrganizacion $guardar,
    ): RedirectResponse {
        $guardar($this->actual(), $request->validated());

        Inertia::flash('exito', 'La ficha de la organización está guardada.');

        return to_route('organizacion.edit');
    }

    private function actual(): Organizacion
    {
        return Organizacion::query()->findOrFail($this->contexto->idObligatorio());
    }
}
