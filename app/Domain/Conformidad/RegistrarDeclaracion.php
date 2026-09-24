<?php

declare(strict_types=1);

namespace App\Domain\Conformidad;

use App\Domain\Conformidad\Enums\EstadoConformidad;
use App\Domain\Conformidad\Excepciones\ConformidadNoPermitida;
use App\Domain\Conformidad\Models\Conformidad;
use App\Domain\Documento\Enums\EstadoDocumental;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Obligacion\Cadencia;
use App\Domain\Obligacion\Models\Compromiso;
use App\Domain\Obligacion\RegistrarCumplimiento;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Da por declarada la conformidad: la Declaración está firmada y emitida.
 *
 * **La firma no se hace aquí**: la hace `AprobarVersion`, con el permiso
 * `documentos.aprobar`, que es donde la dirección asume lo que dice el documento.
 * Esto sólo ata la versión emitida a la declaración, y por eso comprueba que sea
 * exactamente la que tiene que ser:
 *
 * - del tipo Declaración de Conformidad y **del mismo sistema**;
 * - emitida (con número) y vigente, no un borrador ni una ya sustituida;
 * - **emitida después de iniciar esta declaración**: una DdC de hace dos años
 *   imprime otra autoevaluación y otra categoría, y atarla a la de hoy sería
 *   respaldar la declaración con un papel que dice otra cosa.
 *
 * La fecha de la declaración es **la de la firma**, no la de hoy: es la que figura
 * en el PDF. Y la vigencia se congela en ese momento (bienal), como
 * `compromiso_cumplimientos.cubre_hasta`.
 *
 * **La anterior vigente se retira en la misma transacción.** La renovación se
 * prepara con la vieja todavía en vigor, y el índice único parcial no admite dos
 * vigentes del mismo sistema: sin esto, la segunda declaración chocaría contra la
 * primera con un error de restricción.
 *
 * **Y se registra el cumplimiento de `ens.conformidad`**, si la organización ha
 * asumido esa obligación para el sistema: es literalmente el hecho que la cumple,
 * y dejar que alguien lo apunte a mano en el calendario es dejar la puerta a que
 * se apunte dos veces o ninguna. Si ya hay un cumplimiento con este documento
 * detrás, no se duplica.
 */
final class RegistrarDeclaracion
{
    public const OBLIGACION = 'ens.conformidad';

    public function __construct(
        private readonly RegistroTransicionesConformidad $registro,
        private readonly RegistrarCumplimiento $cumplimientos,
    ) {}

    public function __invoke(Conformidad $conformidad, DocumentoVersion $version, ?User $usuario = null): Conformidad
    {
        $actual = $conformidad->estado;

        if (! $actual->permite(EstadoConformidad::Declarada)) {
            throw ConformidadNoPermitida::transicion($actual, EstadoConformidad::Declarada);
        }

        $this->comprobarVersion($conformidad, $version);

        $firma = ($version->aprobada_en ?? Carbon::now())->copy()->startOfDay();
        $vigencia = (new Cadencia(Conformidad::VIGENCIA_MESES))->despuesDe($firma);

        return DB::transaction(function () use ($conformidad, $version, $actual, $firma, $vigencia, $usuario): Conformidad {
            $this->retirarAnterior($conformidad, $firma, $usuario);

            $conformidad->update([
                'estado' => EstadoConformidad::Declarada->value,
                'documento_version_id' => $version->id,
                'fecha_declaracion' => $firma,
                'vigente_hasta' => $vigencia,
            ]);

            $this->registro->registrar(
                $conformidad,
                $actual,
                EstadoConformidad::Declarada,
                $usuario,
                "Declaración de Conformidad v{$version->numero}, firmada el {$firma->format('d/m/Y')}.",
            );

            $this->registrarCumplimiento($conformidad, $version, $firma, $usuario);

            return $conformidad->refresh();
        });
    }

    /**
     * @throws ConformidadNoPermitida
     */
    private function comprobarVersion(Conformidad $conformidad, DocumentoVersion $version): void
    {
        $documento = $version->documento;

        if ($documento->tipo !== TipoDocumento::DeclaracionConformidadEns) {
            throw ConformidadNoPermitida::versionNoValida('no es una Declaración de Conformidad.');
        }

        if ($documento->sistema_id !== $conformidad->sistema_id) {
            throw ConformidadNoPermitida::versionNoValida('es la Declaración de Conformidad de otro sistema.');
        }

        if ($version->numero === null || $version->estado !== EstadoDocumental::Aprobado) {
            throw ConformidadNoPermitida::versionNoValida('no está emitida. Apruébala antes: la firma es lo que la emite.');
        }

        /*
         * Contra `emitida_en` y no contra `aprobada_en`, que es una **fecha**:
         * comparada con el instante en que se inició la declaración, una versión
         * firmada esa misma mañana quedaría «antes» y se rechazaría la firma
         * correcta. `emitida_en` es el instante en que se numeró.
         *
         * **Y se compara en SQL, no en PHP.** `emitida_en` es `timestamptz` y
         * `created_at` no, y las dos se escriben con la hora de Madrid sin
         * desfase: leídas por Eloquent, la primera vuelve como UTC y la segunda
         * como hora local, y compararlas en PHP las separa dos horas. En la base
         * las dos se leen igual, que es lo que hace también el desplegable del
         * controlador.
         */
        $emitidaDespues = DocumentoVersion::query()
            ->whereKey($version->id)
            ->where('emitida_en', '>=', $conformidad->created_at)
            ->exists();

        if (! $emitidaDespues) {
            throw ConformidadNoPermitida::versionNoValida(
                'se firmó antes de iniciar esta declaración, así que imprime otra autoevaluación. Genera y aprueba una versión nueva.'
            );
        }
    }

    private function retirarAnterior(Conformidad $conformidad, Carbon $firma, ?User $usuario): void
    {
        $anteriores = Conformidad::query()
            ->where('sistema_id', $conformidad->sistema_id)
            ->whereKeyNot($conformidad->id)
            ->whereIn('estado', [EstadoConformidad::Declarada->value, EstadoConformidad::Publicada->value])
            ->get();

        foreach ($anteriores as $anterior) {
            $estado = $anterior->estado;
            $anterior->update(['estado' => EstadoConformidad::Retirada->value]);

            $this->registro->registrar(
                $anterior,
                $estado,
                EstadoConformidad::Retirada,
                $usuario,
                "Sustituida por la declaración firmada el {$firma->format('d/m/Y')}.",
            );
        }
    }

    private function registrarCumplimiento(Conformidad $conformidad, DocumentoVersion $version, Carbon $firma, ?User $usuario): void
    {
        $compromiso = Compromiso::query()
            ->activos()
            ->where('sistema_id', $conformidad->sistema_id)
            ->whereHas('obligacion', static fn ($obligacion) => $obligacion->where('codigo', self::OBLIGACION))
            ->first();

        if ($compromiso === null) {
            return;
        }

        if ($compromiso->cumplimientos()->where('documento_id', $version->documento_id)->whereDate('fecha', $firma)->exists()) {
            return;
        }

        ($this->cumplimientos)(
            $compromiso,
            $firma,
            [
                'documento_id' => $version->documento_id,
                'nota' => "Declaración de Conformidad v{$version->numero}, registrada desde la conformidad del sistema.",
            ],
            $usuario,
            $conformidad->vigente_hasta ?? (new Cadencia(Conformidad::VIGENCIA_MESES))->despuesDe($firma),
        );
    }
}
