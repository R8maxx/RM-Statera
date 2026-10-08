<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\GuardarFichaOrganizacion;
use App\Domain\Organizacion\Marca\PiezaDeMarca;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\ResumenSuscripcion;
use App\Domain\Plataforma\Soporte\VentanaSoporte;
use App\Http\Requests\AbrirSoporteRequest;
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

    public function edit(ResumenSuscripcion $resumen): Response
    {
        $organizacion = $this->actual();

        return Inertia::render('organizacion/Editar', [
            // `ficha` y no `organizacion`: ése es un prop compartido que lee el
            // layout, y éste lo pisaba —el lateral perdía el logo del cliente
            // justo en la pantalla donde se sube—.
            'ficha' => [
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
                'reevaluacion_proveedor_alta_meses' => $organizacion->reevaluacion_proveedor_alta_meses,
                'reevaluacion_proveedor_media_meses' => $organizacion->reevaluacion_proveedor_media_meses,
                'reevaluacion_proveedor_baja_meses' => $organizacion->reevaluacion_proveedor_baja_meses,
                'plazo_vulnerabilidad_critica_dias' => $organizacion->plazo_vulnerabilidad_critica_dias,
                'plazo_vulnerabilidad_alta_dias' => $organizacion->plazo_vulnerabilidad_alta_dias,
                'plazo_vulnerabilidad_media_dias' => $organizacion->plazo_vulnerabilidad_media_dias,
                'plazo_vulnerabilidad_baja_dias' => $organizacion->plazo_vulnerabilidad_baja_dias,
                'retencion_personas_meses' => $organizacion->retencion_personas_meses,
            ],

            /*
             * Derivados, para que la pantalla los enseñe sin guardarlos. Es el
             * primer lector que tiene `leAplicaElEns()`, que llevaba declarado
             * desde la primera migración sin que nadie lo invocara.
             */
            'leAplicaElEns' => $organizacion->leAplicaElEns(),

            /*
             * La suscripción, que es lo primero de la pantalla (punto 51): plan,
             * estado, días, consumo frente a los límites e histórico. Se cambia
             * en `/organizacion/plan`, nunca desde esta ficha: el plan y las
             * fechas no están en `$fillable`.
             *
             * `contrato` y `accesoSoporte`, no `suscripcion` ni `soporte`: esos
             * dos son props compartidos que lee el layout, y uno de página con
             * el mismo nombre los pisaría.
             */
            'contrato' => $resumen($organizacion),

            // La puerta a la plataforma (punto 44): abierta hasta cuándo, o nula.
            'accesoSoporte' => [
                'hasta' => $organizacion->soporteAbierto() ? $organizacion->soporte_hasta?->toIso8601String() : null,
                'horasPorDefecto' => VentanaSoporte::HORAS_POR_DEFECTO,
            ],

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

    /**
     * Abrir la puerta a la plataforma (punto 44). Se cierra sola al pasar el
     * plazo; cerrarla antes es `cerrarSoporte()`.
     */
    public function abrirSoporte(AbrirSoporteRequest $request, VentanaSoporte $ventana): RedirectResponse
    {
        $ventana->abrir($this->actual(), $request->horas());

        Inertia::flash('exito', 'Acceso de soporte abierto. Quien entre sólo podrá leer, y se te avisará por correo.');

        return to_route('organizacion.edit');
    }

    public function cerrarSoporte(VentanaSoporte $ventana): RedirectResponse
    {
        $ventana->cerrar($this->actual());

        Inertia::flash('exito', 'Acceso de soporte cerrado. Si había alguien dentro, sale en su siguiente paso.');

        return to_route('organizacion.edit');
    }

    private function actual(): Organizacion
    {
        return Organizacion::query()->findOrFail($this->contexto->idObligatorio());
    }
}
