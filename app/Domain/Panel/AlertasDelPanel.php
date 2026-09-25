<?php

declare(strict_types=1);

namespace App\Domain\Panel;

use App\Domain\Activo\ResumenInventario;
use App\Domain\Auditoria\RegistroAuditorias;
use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Continuidad\RegistroContinuidad;
use App\Domain\Documento\ResumenDocumental;
use App\Domain\Evidencia\RegistroEvidencias;
use App\Domain\Incidente\RegistroIncidentes;
use App\Domain\Metrica\RegistroIndicadores;
use App\Domain\NoConformidad\RegistroNoConformidades;
use App\Domain\Objetivo\RegistroObjetivos;
use App\Domain\Obligacion\RegistroObligaciones;
use App\Domain\Persona\RegistroPersonas;
use App\Domain\Proveedor\RegistroProveedores;
use App\Domain\Riesgo\RegistroRiesgos;
use App\Domain\Tarea\ResumenPlanDeAccion;
use App\Domain\Vulnerabilidad\RegistroVulnerabilidades;
use App\Http\Resources\Panel\Indicador;
use App\Models\User;

/**
 * Todo lo que va mal hoy, en un solo sitio y delante.
 *
 * **Es la respuesta al problema que tenía el panel**, y el problema no era que
 * fuera largo: era que mezclaba tres preguntas. Para saber si algo iba mal había
 * que recorrer once tarjetas, porque el rojo de cada módulo vivía dentro de la
 * suya — las evidencias caducadas en «Pruebas», el plazo de la AEPD en
 * «Incidentes», la salida sin cerrar en «Personas»—. Once rojos en once sitios,
 * y el que no se mira es el que muerde.
 *
 * Es **el mismo diagnóstico que ya estaba escrito para el inventario**, un nivel
 * más arriba: «nueve recuentos del mismo tamaño, varios a cero, mezclando tres
 * cosas distintas: incumplimiento real, dato que falta y perfil. Había que
 * leerse los nueve para saber si algo iba mal». Y la misma solución.
 *
 * ### Qué entra: el rojo, y sólo el rojo
 *
 * Se filtra por **tono**, no por una lista de claves. `alertas()` de cada
 * registro devuelve también cosas que piden atención sin estar incumplidas
 * —`bloqueadas` en tareas, `con_no_conformidades` en auditorías—, y meterlas
 * aquí volvería a la fila de cifras que hay que leerse entera. El rojo del
 * producto es `caducada`, tiene dueños contados y cada módulo declara el suyo.
 *
 * **Y lo que está a cero no se pinta**, por lo mismo que en la tira de una
 * tabla: una tarjeta gastada en decir «cero» enseña a ignorar la tira, y a la
 * tercera vez ya nadie la mira.
 *
 * ### La lista de fuentes es literal, y hay test
 *
 * Como `Rol::permisos()`, y con el mismo riesgo: olvidar un módulo nuevo aquí no
 * rompe nada — su rojo sencillamente deja de verse, que es el fallo silencioso
 * que este componente existe para cerrar. Lo caza `AlertasDelPanelTest`, que
 * recorre `app/Domain/` buscando registros con `alertas()` y exige que estén
 * declarados.
 *
 * ### Cada fuente va con su permiso
 *
 * Misma regla que ya tenía cada tarjeta por separado: conectar dos módulos abre
 * una puerta lateral al registro del otro si nadie lo decide. Que hoy los tres
 * roles del § 4.19 tengan todos los `.ver` no la hace innecesaria: la hace no
 * ejercida.
 */
final readonly class AlertasDelPanel
{
    /**
     * El tono del rojo del producto. Ver `DESIGN.md` § 3.
     *
     * No es una constante de estilo: es la frontera de qué sube aquí. Un módulo
     * que quiera aparecer en la tira declara su alerta con este tono, y ésa es
     * toda la conexión que hace falta.
     */
    public const TONO_ROJO = 'caducada';

    /**
     * Qué registro mira cada permiso.
     *
     * El orden **es el de la tira**, y no es alfabético: va de lo que caduca solo
     * —una evidencia, una tarea— a lo que alguien decidió y dejó a medias. Lo que
     * vence sin que nadie lo toque es lo que hay que ver primero, porque es lo
     * único que puede empeorar mientras se mira la pantalla.
     *
     * @var list<array{0: Permiso, 1: class-string}>
     */
    public const FUENTES = [
        [Permiso::EvidenciasVer, RegistroEvidencias::class],
        [Permiso::TareasVer, ResumenPlanDeAccion::class],
        // Tercera y no última: un compromiso se pasa de fecha sin que nadie lo
        // toque, igual que una evidencia y que una tarea.
        [Permiso::ObligacionesVer, RegistroObligaciones::class],
        [Permiso::IncidentesVer, RegistroIncidentes::class],
        [Permiso::ContinuidadVer, RegistroContinuidad::class],
        // Una reevaluación y un certificado caducan solos, como una evidencia.
        [Permiso::ProveedoresVer, RegistroProveedores::class],
        // Un plazo de remediación vence solo, como una tarea.
        [Permiso::VulnerabilidadesVer, RegistroVulnerabilidades::class],
        [Permiso::RiesgosVer, RegistroRiesgos::class],
        [Permiso::NoConformidadesVer, RegistroNoConformidades::class],
        [Permiso::ObjetivosVer, RegistroObjetivos::class],
        [Permiso::IndicadoresVer, RegistroIndicadores::class],
        [Permiso::PersonasVer, RegistroPersonas::class],
        [Permiso::DocumentosVer, ResumenDocumental::class],
        [Permiso::ActivosVer, ResumenInventario::class],
        [Permiso::AuditoriasVer, RegistroAuditorias::class],
    ];

    /**
     * Qué módulos cuelgan de cada vista, para el punto de su pestaña.
     *
     * **Por `base` del indicador, y completa.** Estaba escrita en el controlador,
     * y obligaciones, proveedores y vulnerabilidades entraron en `FUENTES` sin
     * entrar en ella: su rojo se contaba y no caía en ninguna pestaña, que es
     * justo el fallo silencioso que el punto existe para cerrar. `AlertasTest` exige ahora que toda `base` de toda
     * fuente esté en una vista y en una sola.
     *
     * @var array<string, list<string>>
     */
    public const VISTAS = [
        'cumplimiento' => ['/evidencias', '/implantaciones', '/documentos', '/sistemas'],
        'ciclo' => [
            '/tareas', '/obligaciones', '/no-conformidades', '/mejoras', '/incidentes',
            '/vulnerabilidades', '/continuidad/bia', '/continuidad/pruebas',
            '/indicadores', '/objetivos', '/auditorias',
        ],
        'organizacion' => [
            '/contexto', '/contexto/cuestiones', '/partes-interesadas', '/personas',
            '/formacion', '/activos', '/riesgos', '/proveedores',
        ],
    ];

    /**
     * @return list<Indicador>
     */
    public function __invoke(?User $usuario): array
    {
        $alertas = [];

        foreach (self::FUENTES as [$permiso, $registro]) {
            if ($usuario === null || ! $usuario->can($permiso->value)) {
                continue;
            }

            foreach ($this->alertasDe($registro) as $alerta) {
                if ($alerta->tono === self::TONO_ROJO && $alerta->valor > 0) {
                    $alertas[] = $alerta;
                }
            }
        }

        return $alertas;
    }

    /**
     * Cuántas alertas caen en cada módulo, para el punto de la pestaña.
     *
     * Se agrupa por `base` —`/tareas`, `/incidentes`— porque es lo que el propio
     * indicador ya lleva encima: quien lo pinta no tiene que saber de qué
     * registro salió, y aquí tampoco hace falta un segundo mapa que mantener.
     *
     * @return array<string, int>
     */
    public function porBase(?User $usuario): array
    {
        $cuenta = [];

        foreach ($this($usuario) as $alerta) {
            $cuenta[$alerta->base] = ($cuenta[$alerta->base] ?? 0) + 1;
        }

        return $cuenta;
    }

    /**
     * @param  class-string  $registro
     * @return list<Indicador>
     */
    private function alertasDe(string $registro): array
    {
        /** @var object{alertas: callable} $instancia */
        $instancia = app($registro);

        /** @var list<Indicador> $alertas */
        $alertas = $instancia->alertas();

        return $alertas;
    }
}
