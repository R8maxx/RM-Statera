<?php

declare(strict_types=1);

namespace App\Domain\Auditoria;

use App\Domain\Auditoria\Enums\EstadoAuditoria;
use App\Domain\Auditoria\Excepciones\TransicionDeAuditoriaNoPermitida;
use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Auditoria\Models\AuditoriaPunto;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cerrar una auditoría, que es lo que la vuelve un hecho.
 *
 * Hace **dos cosas y en este orden**, y el orden no es negociable: primero congela
 * cada línea de la checklist y después marca la auditoría cerrada. Al revés, el
 * trigger de la base bloquea el propio congelado —porque la auditoría ya estaría
 * cerrada— con un error que habla de la checklist y no del orden.
 *
 * **Por qué se congela.** Una implantación cambia: el motor puede dejar de
 * exigirla tras una revaloración, y su estado se mueve cada semana. Si la
 * auditoría de marzo leyera el registro de octubre, su denominador cambiaría bajo
 * los pies y las líneas dirían lo que la medida es hoy y no lo que era cuando se
 * miró. Es exactamente el motivo por el que `riesgo_valoraciones` congela su
 * escala y sus salvaguardas, y por el que el `.docx` de un documento se construye
 * desde la instantánea.
 *
 * **Reabrir es la puerta**, y hace falta: un error material en una auditoría
 * cerrada tiene que poder corregirse. Lo que no puede es corregirse a escondidas,
 * y por eso reabrir es una transición con su autor y no una edición silenciosa.
 * El congelado **no se deshace** al reabrir: se vuelve a escribir al cerrar otra
 * vez, y mientras tanto lo que vale es lo que diga el registro.
 */
final readonly class CerrarAuditoria
{
    /**
     * @throws TransicionDeAuditoriaNoPermitida
     */
    public function cerrar(Auditoria $auditoria, ?User $autor = null, ?string $conclusiones = null): Auditoria
    {
        $this->exigirTransicion($auditoria, EstadoAuditoria::Cerrada);

        return DB::transaction(function () use ($auditoria, $autor, $conclusiones): Auditoria {
            // Primero el congelado. Después, y sólo después, el estado.
            $this->congelar($auditoria);

            $auditoria->update([
                'estado' => EstadoAuditoria::Cerrada->value,
                'fecha_cierre' => Carbon::today(),
                'cerrada_por_id' => $autor?->id,
                'conclusiones' => $conclusiones ?? $auditoria->conclusiones,
            ]);

            return $auditoria->refresh();
        });
    }

    /**
     * @throws TransicionDeAuditoriaNoPermitida
     */
    public function reabrir(Auditoria $auditoria): Auditoria
    {
        $this->exigirTransicion($auditoria, EstadoAuditoria::EnCurso);

        $auditoria->update([
            'estado' => EstadoAuditoria::EnCurso->value,
            'fecha_cierre' => null,
            'cerrada_por_id' => null,
        ]);

        return $auditoria->refresh();
    }

    /**
     * @throws TransicionDeAuditoriaNoPermitida
     */
    public function empezar(Auditoria $auditoria): Auditoria
    {
        $this->exigirTransicion($auditoria, EstadoAuditoria::EnCurso);

        $auditoria->update(['estado' => EstadoAuditoria::EnCurso->value]);

        return $auditoria->refresh();
    }

    /**
     * Escribe en cada punto lo que su implantación decía hoy.
     *
     * Por `update` directo y no por el modelo: son las últimas escrituras que la
     * base va a admitir sobre estas filas, y pasar por eventos aquí no aporta
     * nada — la traza del cierre la deja la propia auditoría.
     */
    private function congelar(Auditoria $auditoria): void
    {
        $puntos = AuditoriaPunto::query()
            ->with('implantacion')
            ->where('auditoria_id', $auditoria->id)
            ->get();

        foreach ($puntos as $punto) {
            $implantacion = $punto->implantacion;

            if ($implantacion === null) {
                continue;
            }

            $punto->update([
                'exigencia_congelada' => $implantacion->exigencia_calculada?->etiqueta(),
                'estado_congelado' => $implantacion->estado->value,
            ]);
        }
    }

    /**
     * @throws TransicionDeAuditoriaNoPermitida
     */
    private function exigirTransicion(Auditoria $auditoria, EstadoAuditoria $destino): void
    {
        if (! $auditoria->estado->permite($destino)) {
            throw TransicionDeAuditoriaNoPermitida::de($auditoria->estado, $destino);
        }
    }
}
