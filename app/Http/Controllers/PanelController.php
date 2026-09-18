<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Activo\ResumenInventario;
use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Contexto\RegistroContexto;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\ResumenCumplimiento;
use App\Domain\NoConformidad\RegistroNoConformidades;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Tarea\ResumenPlanDeAccion;
use App\Http\Resources\Panel\ResumenEvidencias;
use App\Http\Resources\Panel\ResumenPanel;
use App\Http\Resources\Panel\SistemaResumido;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El estado del cumplimiento de un vistazo.
 *
 * Todas las consultas pasan por el scope de organización, así que lo que se
 * cuenta aquí es siempre lo de la organización activa. Las cifras las calcula
 * `ResumenCumplimiento`, en el dominio: son las mismas preguntas que contestará
 * el informe de estado, y no pueden vivir en un controlador.
 */
class PanelController extends Controller
{
    public function __invoke(
        ResumenCumplimiento $resumen,
        ResumenInventario $inventario,
        ResumenPlanDeAccion $plan,
        RegistroNoConformidades $noConformidades,
        RegistroContexto $contexto,
    ): Response {
        $sistemas = Sistema::query()
            ->with('marco')
            ->withCount([
                'implantaciones as aplicables' => fn (Builder $query) => $query->where('aplica', true),
                // Sólo cuentan las implantadas que además son exigibles: sin el
                // `aplica`, una medida excluida y luego implantada inflaba el
                // numerador por encima del denominador.
                'implantaciones as implantadas' => fn (Builder $query) => $query
                    ->where('aplica', true)
                    ->where('estado', EstadoImplantacion::Implantado->value),
            ])
            ->orderBy('codigo')
            ->get()
            ->map(fn (Sistema $sistema): SistemaResumido => new SistemaResumido(
                id: $sistema->id,
                codigo: $sistema->codigo,
                nombre: $sistema->nombre,
                marco: $sistema->marco?->nombre,
                categoria: $sistema->categoria()?->etiqueta(),
                // Alias de `withCount`: no son columnas del modelo, así que se
                // leen por `getAttribute` y no como propiedad.
                aplicables: (int) $sistema->getAttribute('aplicables'),
                implantadas: (int) $sistema->getAttribute('implantadas'),
            ))
            ->values();

        $madurez = $resumen->madurez();
        $evidencias = $resumen->evidencias();

        return Inertia::render('Panel', [
            'sistemas' => $sistemas,
            'resumen' => new ResumenPanel(
                sistemas: $sistemas->count(),
                aplicables: (int) $sistemas->sum(fn (SistemaResumido $sistema): int => $sistema->aplicables),
                implantadas: (int) $sistemas->sum(fn (SistemaResumido $sistema): int => $sistema->implantadas),
                pendientes: $resumen->pendientes(),
                madurezMedia: $madurez['media'],
                madurezEvaluadas: $madurez['evaluadas'],
            ),
            'evidencias' => new ResumenEvidencias(
                total: $evidencias['total'],
                caducadas: $evidencias['caducadas'],
                porCaducar: $evidencias['porCaducar'],
                implantadasSinEvidencia: $resumen->implantadasSinEvidencia(),
            ),
            'porEstado' => $resumen->porEstado(),
            'porMarco' => $resumen->porMarco(),
            // El inventario contesta aquí las preguntas de reparto —qué hay y de
            // qué tipo— y deja en `/activos` sólo lo que pide acción hoy. Un
            // panel es para saber cómo va la cosa; una tabla, para trabajar.
            'inventario' => $inventario->paraElPanel(),
            // El plan de acción contesta la otra mitad de «cómo va la cosa»: el
            // cumplimiento dice qué falta y esto dice quién lo está haciendo.
            'plan' => $plan->paraElPanel(),
            /*
             * Y esto contesta la tercera: qué ha fallado y si se arregló. § 4.14
             * pide «no conformidades abiertas» entre los indicadores del cuadro
             * de mando.
             *
             * **No se manda si quien mira no tiene `no_conformidades.ver`.**
             * Conectar dos módulos abre una puerta lateral al registro del otro
             * sin que nadie la decida; es la misma regla que ya se escribió para
             * el bloque de riesgos de la ficha de un activo. El frontend decide
             * qué pinta y nunca qué autoriza.
             */
            'noConformidades' => $this->puedeVerNoConformidades()
                ? $noConformidades->paraElPanel()
                : null,
            /*
             * Y esto es la pregunta de antes de todas: de qué entorno estamos
             * hablando. Va la última en el panel porque se consulta menos que las
             * otras cuatro —el contexto se revisa una vez al año y el cumplimiento
             * todas las semanas—, y va con la misma guarda de permiso que las no
             * conformidades y por el mismo motivo.
             */
            'contexto' => $this->puedeVerContexto()
                ? $contexto->paraElPanel()
                : null,
        ]);
    }

    private function puedeVerNoConformidades(): bool
    {
        return request()->user()?->can(Permiso::NoConformidadesVer->value) ?? false;
    }

    private function puedeVerContexto(): bool
    {
        return request()->user()?->can(Permiso::ContextoVer->value) ?? false;
    }
}
