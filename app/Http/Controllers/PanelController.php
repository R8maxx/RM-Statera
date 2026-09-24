<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Activo\ResumenInventario;
use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Contexto\RegistroContexto;
use App\Domain\Implantacion\ResumenCumplimiento;
use App\Domain\Incidente\RegistroIncidentes;
use App\Domain\Metrica\RegistroIndicadores;
use App\Domain\NoConformidad\RegistroNoConformidades;
use App\Domain\Objetivo\RegistroObjetivos;
use App\Domain\Obligacion\RegistroObligaciones;
use App\Domain\Panel\AlertasDelPanel;
use App\Domain\Persona\RegistroPersonas;
use App\Domain\Tarea\ResumenPlanDeAccion;
use App\Http\Resources\Panel\ResumenEvidencias;
use App\Http\Resources\Panel\ResumenPanel;
use App\Http\Resources\Panel\SistemaResumido;
use App\Http\Resources\Panel\VistaPanel;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El estado del cumplimiento de un vistazo. Tres vistas y una tira.
 *
 * **El panel tenía trece tarjetas apiladas y mezclaba tres preguntas.** Cada
 * módulo nuevo añadía la suya al final, y la pantalla no distinguía entre cómo
 * va el cumplimiento, qué está pasando y de qué organización hablamos. Ahora
 * **se reparte por la pregunta que contesta**, no por módulo.
 *
 * **Las vistas son rutas y no estado de cliente**, que es la decisión ya tomada
 * para las tres pantallas del plan de acción: así se enlazan, se comparten y el
 * sidebar lleva siempre al mismo sitio.
 *
 * **Y cada vista consulta sólo lo suyo.** Antes cada carga del panel calculaba
 * trece resúmenes aunque nadie mirara doce; ahora el reparto es el que decide
 * qué se cuenta.
 *
 * ### El punto de la pestaña
 *
 * Repartir el panel tiene un riesgo y es el único que hay que sujetar: una
 * pestaña puede esconder un incumplimiento detrás de un clic que nadie da. Por
 * eso `AlertasDelPanel` cruza los once registros, cuenta **lo rojo que no está
 * a cero** y cada pestaña sale con su recuento — `VistaPanel::$alertas`. No se
 * mandan las alertas en sí: lo que necesita el conmutador es saber si las hay,
 * y el detalle está dentro, en la tarjeta del módulo que lo produce.
 *
 * Todas las consultas pasan por el scope de organización. Las cifras las calcula
 * el dominio: son las mismas preguntas que contestará el informe de estado, y no
 * pueden vivir en un controlador.
 */
class PanelController extends Controller
{
    /**
     * «¿Cómo vamos con lo exigible?» — la vista por defecto.
     *
     * Abre con el anillo porque es la única cifra que contesta la pregunta de la
     * pantalla en un solo número, y detrás va lo que la sostiene: el reparto por
     * estado, las pruebas que lo demuestran, el avance por marco y los sistemas
     * en los que se mide.
     */
    public function cumplimiento(ResumenCumplimiento $resumen, AlertasDelPanel $alertas): Response
    {
        $sistemas = collect($resumen->porSistema());
        $madurez = $resumen->madurez();
        $pruebas = $resumen->evidencias();

        return Inertia::render('panel/Cumplimiento', [
            ...$this->comunes($alertas),
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
                total: $pruebas['total'],
                caducadas: $pruebas['caducadas'],
                porCaducar: $pruebas['porCaducar'],
                implantadasSinEvidencia: $resumen->implantadasSinEvidencia(),
            ),
            'porEstado' => $resumen->porEstado(),
            'porMarco' => $resumen->porMarco(),
        ]);
    }

    /**
     * «¿Qué está pasando y estamos mejorando?»
     *
     * Las cinco piezas del ciclo vivo, en el orden en que se recorren: lo que hay
     * abierto, lo que se rompió, lo que pasó, lo que se mide y a qué nos
     * comprometimos.
     */
    public function ciclo(
        ResumenPlanDeAccion $plan,
        RegistroNoConformidades $noConformidades,
        RegistroIncidentes $incidentes,
        RegistroIndicadores $indicadores,
        RegistroObjetivos $objetivos,
        RegistroObligaciones $obligaciones,
        AlertasDelPanel $alertas,
    ): Response {
        return Inertia::render('panel/Ciclo', [
            ...$this->comunes($alertas),
            'plan' => $plan->paraElPanel(),
            /*
             * Nulo cuando quien mira no tiene el permiso. Lo decide el servidor:
             * conectar dos módulos abre una puerta lateral al registro del otro
             * si el frontend es quien elige qué esconder.
             */
            'noConformidades' => $this->puede(Permiso::NoConformidadesVer)
                ? $noConformidades->paraElPanel()
                : null,
            'incidentes' => $this->puede(Permiso::IncidentesVer)
                ? $incidentes->paraElPanel()
                : null,
            'desempeno' => $this->puede(Permiso::IndicadoresVer)
                ? $indicadores->paraElPanel()
                : null,
            'objetivos' => $this->puede(Permiso::ObjetivosVer)
                ? $objetivos->paraElPanel()
                : null,
            /*
             * Detrás del plan: es la misma pregunta —qué hay abierto y para
             * cuándo— con otra cadencia. Lo del plan se decidió esta semana; esto
             * se decidió una vez y vuelve solo cada doce o veinticuatro meses.
             */
            'obligaciones' => $this->puede(Permiso::ObligacionesVer)
                ? $obligaciones->paraElPanel()
                : null,
        ]);
    }

    /**
     * «¿De qué estamos hablando?»
     *
     * El entorno y lo que hay dentro del alcance: el contexto que lo enmarca, la
     * gente que lo sostiene y el inventario sobre el que se aplica todo lo
     * demás. Es lo que menos cambia de un día para otro, y por eso es la vista
     * que menos se abre.
     */
    public function organizacion(
        RegistroContexto $contexto,
        RegistroPersonas $personas,
        ResumenInventario $inventario,
        AlertasDelPanel $alertas,
    ): Response {
        return Inertia::render('panel/Organizacion', [
            ...$this->comunes($alertas),
            'contexto' => $this->puede(Permiso::ContextoVer)
                ? $contexto->paraElPanel()
                : null,
            'personas' => $this->puede(Permiso::PersonasVer)
                ? $personas->paraElPanel()
                : null,
            'inventario' => $inventario->paraElPanel(),
        ]);
    }

    /**
     * Lo que va en las tres: el conmutador, con el punto de cada pestaña.
     *
     * **No viaja con `Inertia::once()`.** El recuento es justo lo que puede
     * cambiar entre dos visitas; cachearlo en el cliente dejaría a alguien
     * mirando el estado de hace media hora, que es lo contrario de para lo que
     * está el punto.
     *
     * @return array<string, mixed>
     */
    private function comunes(AlertasDelPanel $alertas): array
    {
        $usuario = request()->user();
        $porBase = $alertas->porBase($usuario);

        /** Qué rutas de módulo cuelgan de cada vista, para el punto de su pestaña. */
        $reparto = [
            'cumplimiento' => ['/evidencias', '/implantaciones', '/documentos', '/sistemas'],
            'ciclo' => ['/tareas', '/no-conformidades', '/incidentes', '/indicadores', '/objetivos', '/auditorias'],
            'organizacion' => ['/contexto', '/personas', '/activos', '/riesgos'],
        ];

        $cuenta = static fn (string $clave): int => array_sum(array_map(
            static fn (string $base): int => $porBase[$base] ?? 0,
            $reparto[$clave],
        ));

        return [
            'vistas' => [
                new VistaPanel(
                    clave: 'cumplimiento',
                    etiqueta: 'Cumplimiento',
                    href: '/panel',
                    pregunta: '¿Cómo vamos con lo exigible?',
                    alertas: $cuenta('cumplimiento'),
                ),
                new VistaPanel(
                    clave: 'ciclo',
                    etiqueta: 'El ciclo',
                    href: '/panel/ciclo',
                    pregunta: '¿Qué está pasando y estamos mejorando?',
                    alertas: $cuenta('ciclo'),
                ),
                new VistaPanel(
                    clave: 'organizacion',
                    etiqueta: 'La organización',
                    href: '/panel/organizacion',
                    pregunta: '¿De qué estamos hablando?',
                    alertas: $cuenta('organizacion'),
                ),
            ],
        ];
    }

    private function puede(Permiso $permiso): bool
    {
        return request()->user()?->can($permiso->value) ?? false;
    }
}
