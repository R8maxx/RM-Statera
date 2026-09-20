<?php

declare(strict_types=1);

namespace App\Domain\Metrica\Enums;

use App\Domain\Activo\Models\Activo;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Implantacion\ResumenCumplimiento;
use App\Domain\Metrica\Medida;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\Persona\Models\Persona;
use App\Domain\Riesgo\Models\Riesgo;
use App\Domain\Tarea\Models\Tarea;
use Illuminate\Database\Eloquent\Builder;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Los cálculos que Statera sabe hacer solo. § 2.2 los llama «formula_o_fuente».
 *
 * **Es un catálogo cerrado y no una fórmula que alguien evalúe.** La tentación
 * es una columna de texto con `(implantadas / aplicables) * 100` dentro y un
 * intérprete detrás; aquí no entra, por dos motivos y el segundo pesa más:
 *
 * 1. Un evaluador de expresiones en una herramienta que está en el alcance de su
 *    propio SGSI es superficie de ataque a cambio de nada.
 * 2. **Cada caso llama al scope o al resumen que ya existe**, nunca reescribe la
 *    condición. Es lo mismo que hace `Filtro::porScope()`, y es lo que garantiza
 *    que el indicador, la cifra del panel y la lista que sale al pulsarla digan
 *    el mismo número. Con la regla escrita dos veces, el día que cambie una el
 *    indicador dirá 12 y la tabla enseñará 9 — y a partir de ahí nadie se fía de
 *    ninguna de las dos.
 *
 * `medir()` es un `match` sobre el propio enum, así que Larastan caza la rama
 * que falte: no hace falta el `default` ruidoso que sí necesitan los dos `match`
 * sobre cadenas de `MaterializarCuerpo`.
 *
 * **La unidad y el sentido que declara cada caso son los naturales, no los
 * impuestos.** El formulario los propone al elegir el cálculo y el usuario los
 * puede cambiar —medir las tareas vencidas como porcentaje del total abierto es
 * legítimo—, igual que `TipoParteInteresada::ambitoSugerido()` propone y no
 * impone.
 */
#[TypeScript]
enum CalculoIndicador: string
{
    case CumplimientoImplantado = 'cumplimiento_implantado';
    case ImplantacionesPendientes = 'implantaciones_pendientes';
    case ImplantadasSinEvidencia = 'implantadas_sin_evidencia';
    case MadurezMedia = 'madurez_media';
    case EvidenciasCaducadas = 'evidencias_caducadas';
    case TareasVencidas = 'tareas_vencidas';
    case TareasSinResponsable = 'tareas_sin_responsable';
    case NoConformidadesAbiertas = 'no_conformidades_abiertas';
    case NoConformidadesSinVerificar = 'no_conformidades_sin_verificar';
    case RiesgosSobreUmbral = 'riesgos_sobre_umbral';
    case ActivosSinCifrar = 'activos_sin_cifrar';
    case ActivosSinRevisar = 'activos_sin_revisar';

    /*
     * El que llegó con el § 4.8, y el que convierte en calculado lo que era el
     * ejemplo de indicador **manual** del producto: «el porcentaje de personal
     * formado no sale de esta base de datos mientras el § 4.8 no exista». Ya
     * existe.
     */
    case PersonalFormado = 'personal_formado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::CumplimientoImplantado => 'Cumplimiento: medidas implantadas',
            self::ImplantacionesPendientes => 'Cumplimiento: medidas pendientes',
            self::ImplantadasSinEvidencia => 'Implantadas sin evidencia',
            self::MadurezMedia => 'Madurez media (L0–L5)',
            self::EvidenciasCaducadas => 'Evidencias caducadas',
            self::TareasVencidas => 'Tareas fuera de plazo',
            self::TareasSinResponsable => 'Tareas sin responsable',
            self::NoConformidadesAbiertas => 'No conformidades abiertas',
            self::NoConformidadesSinVerificar => 'No conformidades sin verificar',
            self::RiesgosSobreUmbral => 'Riesgos por encima del umbral',
            self::ActivosSinCifrar => 'Activos sin cifrado en reposo',
            self::ActivosSinRevisar => 'Activos sin revisar en 12 meses',
            self::PersonalFormado => 'Personal con formación al día',
        };
    }

    /**
     * El método, que es lo que pregunta la cláusula 9.1 b).
     *
     * Va impreso en la ficha del indicador y en el acta de la revisión por la
     * dirección: un número sin método no se puede discutir, y «¿de dónde sale
     * ese 87 %?» es la primera pregunta de cualquier auditor.
     */
    public function metodo(): string
    {
        return match ($this) {
            self::CumplimientoImplantado => 'Implantaciones exigibles en estado «implantado», sobre el total de exigibles.',
            self::ImplantacionesPendientes => 'Implantaciones exigibles que todavía no están implantadas, sobre el total de exigibles.',
            self::ImplantadasSinEvidencia => 'Implantaciones exigibles en estado «implantado» sin ninguna evidencia vinculada, sobre el total de implantadas.',
            self::MadurezMedia => 'Media del nivel de madurez (L0–L5) de las implantaciones exigibles que lo tienen valorado, indicando sobre cuántas se calcula.',
            self::EvidenciasCaducadas => 'Evidencias con fecha de caducidad pasada, sobre el total del repositorio.',
            self::TareasVencidas => 'Tareas abiertas con fecha límite pasada, sobre el total de tareas abiertas.',
            self::TareasSinResponsable => 'Tareas abiertas sin responsable asignado, sobre el total de tareas abiertas.',
            self::NoConformidadesAbiertas => 'No conformidades que no están cerradas, verificadas ni anuladas, sobre el total registrado.',
            self::NoConformidadesSinVerificar => 'No conformidades cerradas cuya eficacia no se ha verificado (cláusula 10.2 e), sobre el total registrado.',
            self::RiesgosSobreUmbral => 'Riesgos cuya valoración vigente está en o por encima del umbral de aceptación de la metodología, sobre el total de riesgos.',
            self::ActivosSinCifrar => 'Activos vigentes que declaran no cifrar en reposo, sobre el total de activos vigentes. Los «por confirmar» no cuentan.',
            self::ActivosSinRevisar => 'Activos vigentes sin revisión registrada en los últimos doce meses, sobre el total de activos vigentes.',
            self::PersonalFormado => 'Personas en plantilla con al menos una asistencia registrada a una acción formativa o de concienciación en los últimos doce meses, sobre el total en plantilla. Quien se fue no cuenta: pedirle formación pondría un techo inalcanzable.',
        };
    }

    /**
     * Si el cálculo se deja acotar a un marco normativo.
     *
     * Sólo los que cuelgan de `implantaciones`, que es donde el marco existe.
     * Las evidencias, las tareas y los activos son de la organización entera y
     * sirven a los dos marcos a la vez (invariante 6): repartirlos por marco los
     * contaría dos veces o los dejaría fuera de uno.
     */
    public function admiteMarco(): bool
    {
        return match ($this) {
            self::CumplimientoImplantado,
            self::ImplantacionesPendientes,
            self::ImplantadasSinEvidencia,
            self::MadurezMedia => true,
            default => false,
        };
    }

    public function unidad(): UnidadIndicador
    {
        return match ($this) {
            self::CumplimientoImplantado, self::PersonalFormado => UnidadIndicador::Porcentaje,
            // Una media L0–L5 no es un porcentaje ni un recuento: se escribe con
            // un decimal, que es lo que `Dias` ya hace.
            self::MadurezMedia => UnidadIndicador::Dias,
            default => UnidadIndicador::Recuento,
        };
    }

    public function sentido(): SentidoIndicador
    {
        return match ($this) {
            self::CumplimientoImplantado, self::MadurezMedia, self::PersonalFormado => SentidoIndicador::MayorMejor,
            default => SentidoIndicador::MenorMejor,
        };
    }

    /**
     * La cifra de hoy, con su denominador.
     *
     * Corre dentro del contexto de una organización: todas las consultas pasan
     * por el scope y por RLS, así que fuera de contexto **no falla, no ve nada**,
     * que es el motivo de que el comando programado fije la organización una a
     * una en vez de recorrer una consulta global.
     */
    public function medir(?int $marcoId = null): Medida
    {
        $resumen = new ResumenCumplimiento;

        return match ($this) {
            self::CumplimientoImplantado => Medida::fraccion(
                $this->exigibles($marcoId)->where('implantaciones.estado', EstadoImplantacion::Implantado->value)->count(),
                $this->exigibles($marcoId)->count(),
            ),

            // Por el scope y no repitiendo la condición: es el mismo que usan el
            // filtro de `/implantaciones`, la cifra del panel y la consulta del
            // plan de adecuación.
            self::ImplantacionesPendientes => Medida::recuento(
                $this->exigibles($marcoId)->pendientes()->count(),
                $this->exigibles($marcoId)->count(),
            ),

            self::ImplantadasSinEvidencia => Medida::recuento(
                $this->exigibles($marcoId)
                    ->where('implantaciones.estado', EstadoImplantacion::Implantado->value)
                    ->whereDoesntHave('evidencias')
                    ->count(),
                $this->exigibles($marcoId)->where('implantaciones.estado', EstadoImplantacion::Implantado->value)->count(),
            ),

            self::MadurezMedia => $this->madurez($marcoId, $resumen),

            self::EvidenciasCaducadas => Medida::recuento(
                Evidencia::query()->caducadas()->count(),
                Evidencia::query()->count(),
            ),

            self::TareasVencidas => Medida::recuento(
                Tarea::query()->vencidas()->count(),
                Tarea::query()->abiertas()->count(),
            ),

            self::TareasSinResponsable => Medida::recuento(
                Tarea::query()->sinResponsable()->count(),
                Tarea::query()->abiertas()->count(),
            ),

            self::NoConformidadesAbiertas => Medida::recuento(
                NoConformidad::query()->abiertas()->count(),
                NoConformidad::query()->count(),
            ),

            self::NoConformidadesSinVerificar => Medida::recuento(
                NoConformidad::query()->pendientesDeVerificar()->count(),
                NoConformidad::query()->count(),
            ),

            // `scopeSobreUmbral()` resuelve el umbral él mismo cuando no se le
            // pasa, que es justamente por lo que se escribió así: el indicador
            // del panel y el filtro de la tabla lo invocan sin argumentos.
            self::RiesgosSobreUmbral => Medida::recuento(
                Riesgo::query()->sobreUmbral()->count(),
                Riesgo::query()->count(),
            ),

            self::ActivosSinCifrar => Medida::recuento(
                Activo::query()->vigentes()->sinCifrado()->count(),
                Activo::query()->vigentes()->count(),
            ),

            self::ActivosSinRevisar => Medida::recuento(
                Activo::query()->vigentes()->sinRevisar()->count(),
                Activo::query()->vigentes()->count(),
            ),

            /*
             * **Por resta y no por una segunda consulta con la condición
             * contraria**, que sería la misma regla escrita dos veces:
             * `sinFormacionReciente()` es exactamente «activa y sin asistencia en
             * doce meses», así que las formadas son las activas menos ésas. Es el
             * mismo argumento por el que el plan de adecuación saca las
             * implantadas restando en vez de consultarlas aparte.
             */
            self::PersonalFormado => $this->personalFormado(),
        };
    }

    /**
     * El porcentaje de personal con la formación al día.
     *
     * Con la plantilla vacía, `Medida::fraccion()` devuelve valor cero **y suelta
     * el numerador y el denominador**, que es lo que el `CHECK` de `mediciones`
     * admite: una organización que todavía no ha dado de alta a nadie no está al
     * 0 % de formación, está sin plantilla registrada.
     */
    private function personalFormado(): Medida
    {
        $activas = Persona::query()->activas()->count();

        return Medida::fraccion($activas - Persona::query()->sinFormacionReciente()->count(), $activas);
    }

    /**
     * Lo exigible, opcionalmente acotado a un marco.
     *
     * Por `whereHas` y no por `join`, aunque aquí un `join` contra `requisitos`
     * no multiplicaría filas: la consulta se encadena con scopes que cualifican
     * sus columnas y meter una tabla más invita al «column reference is
     * ambiguous» que ya mordió en `Auditoria::puntos()`.
     *
     * @return Builder<Implantacion>
     */
    private function exigibles(?int $marcoId): Builder
    {
        return Implantacion::query()
            ->where('implantaciones.aplica', true)
            ->when(
                $marcoId !== null,
                fn (Builder $consulta): Builder => $consulta->whereHas(
                    'requisito',
                    fn (Builder $requisitos): Builder => $requisitos->where('marco_id', $marcoId),
                ),
            );
    }

    /**
     * La madurez media.
     *
     * Sin marco se delega en `ResumenCumplimiento::madurez()`, que es quien sabe
     * que el nivel se guarda como `l0`…`l5` y que el dígito es el valor de la
     * escala. Con marco hay que acotar, y la aritmética se repite aquí porque
     * ese resumen no admite argumentos: es la única condición duplicada del
     * enum, y está aislada en un método para que se vea.
     */
    private function madurez(?int $marcoId, ResumenCumplimiento $resumen): Medida
    {
        if ($marcoId === null) {
            $madurez = $resumen->madurez();

            return Medida::promedio($madurez['media'], $madurez['evaluadas']);
        }

        $fila = $this->exigibles($marcoId)
            ->whereNotNull('implantaciones.nivel_madurez')
            ->selectRaw('count(*) as evaluadas, avg(cast(substring(implantaciones.nivel_madurez from 2) as integer)) as media')
            ->first();

        $evaluadas = (int) ($fila?->getAttribute('evaluadas') ?? 0);
        $media = $fila?->getAttribute('media');

        return Medida::promedio(
            $evaluadas === 0 || $media === null ? null : round((float) $media, 1),
            $evaluadas,
        );
    }
}
