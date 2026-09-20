<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Activo\ResumenInventario;
use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Contexto\RegistroContexto;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\ResumenCumplimiento;
use App\Domain\Metrica\RegistroIndicadores;
use App\Domain\NoConformidad\RegistroNoConformidades;
use App\Domain\Objetivo\RegistroObjetivos;
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
        RegistroIndicadores $indicadores,
        RegistroObjetivos $objetivos,
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
            /*
             * El desempeño (§ 4.14, cláusula 9.1). Va detrás del contexto porque
             * es lo último que existe y lo que menos se consulta a diario: un
             * indicador trimestral cambia cuatro veces al año.
             *
             * Misma guarda de permiso que los dos anteriores. Hoy los tres roles
             * del § 4.19 tienen `indicadores.ver`, así que no la ejerce nadie; el
             * test la comprueba quitándole el permiso **al rol** y no al usuario,
             * porque `revokePermissionTo` sobre la persona no quita lo que hereda
             * y el test pasaría por el motivo equivocado.
             */
            'desempeno' => $this->puedeVerIndicadores()
                ? $indicadores->paraElPanel()
                : null,
            /*
             * Y los objetivos (cláusula 6.2), pegados al desempeño porque son las
             * dos mitades de la misma pregunta: los indicadores dicen cómo va y
             * los objetivos dicen contra qué. Separarlos en el panel obligaría a
             * mirar en dos sitios para contestar «¿vamos bien?».
             *
             * **`sinIndicador` es la cifra que está aquí por la norma y no por la
             * pantalla**, como `sinVerificar` en las no conformidades: la 6.2
             * exige que el objetivo sea medible, y uno sin ninguna cifra detrás
             * lo cumple de palabra. Misma guarda de permiso que los tres
             * anteriores.
             */
            'objetivos' => $this->puedeVerObjetivos()
                ? $objetivos->paraElPanel()
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

    private function puedeVerIndicadores(): bool
    {
        return request()->user()?->can(Permiso::IndicadoresVer->value) ?? false;
    }

    private function puedeVerObjetivos(): bool
    {
        return request()->user()?->can(Permiso::ObjetivosVer->value) ?? false;
    }
}
