<?php

declare(strict_types=1);

namespace App\Domain\Implantacion;

use App\Domain\Categorizacion\Enums\OrigenExigencia;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Excepciones\ExclusionNoPermitida;
use App\Domain\Implantacion\Models\Implantacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Excluir un requisito del alcance, o volver a incluirlo, por decisión motivada.
 *
 * Es el corazón de la Declaración de Aplicabilidad de ISO: los 93 controles del
 * Anexo A aplican de partida y la SoA es precisamente la lista de exclusiones
 * con su motivo (`OrigenExigencia::Catalogo`).
 *
 * Y por eso mismo NO vale para el ENS: cuando la exigencia la deriva el motor
 * —categoría, modulación por dimensión o perfil—, excluir a mano contradice el
 * invariante 4 y además no duraría, porque el siguiente recálculo la
 * reactivaría. Ahí el camino es cambiar la valoración.
 *
 * `aplica` y `estado` se mueven juntos: la base tiene una restricción que exige
 * que `estado = 'no_aplica'` sea exactamente lo mismo que `NOT aplica`.
 */
final class CambiarAplicabilidad
{
    public function __construct(private readonly RegistroTransiciones $registro) {}

    /** @throws ExclusionNoPermitida cuando la exigencia la deriva el motor. */
    public function excluir(Implantacion $implantacion, string $justificacion, ?User $usuario = null): Implantacion
    {
        if (! $this->esExcluibleAMano($implantacion)) {
            throw new ExclusionNoPermitida($implantacion->origen_exigencia);
        }

        if (! $implantacion->aplica) {
            return $implantacion;
        }

        $anterior = $implantacion->estado;

        return DB::transaction(function () use ($implantacion, $anterior, $justificacion, $usuario): Implantacion {
            $implantacion->update([
                'aplica' => false,
                'justificacion' => $justificacion,
                'estado' => EstadoImplantacion::NoAplica->value,
            ]);

            // La justificación es la nota de la transición además de la columna:
            // el auditor pregunta por qué se excluyó y cuándo, y las dos cosas
            // tienen que estar en el histórico.
            $this->registro->registrar(
                $implantacion,
                $anterior,
                EstadoImplantacion::NoAplica,
                $usuario,
                $justificacion,
            );

            return $implantacion->refresh();
        });
    }

    /**
     * Vuelve a incluirla, recuperando el estado que tenía antes de excluirse.
     *
     * Para eso se guarda el histórico: quien excluye una medida que estaba
     * implantada y se arrepiente no tiene que volver a recorrer los estados.
     *
     * Se guarda igual que la exclusión, y por el mismo motivo: reincorporar a
     * mano una medida que el motor dejó de exigir deja una fila que dice
     * «aplica» mientras el conjunto exigible dice que no. Ahí el camino es
     * volver a valorar.
     *
     * @throws ExclusionNoPermitida cuando la exigencia la deriva el motor.
     */
    public function incluir(Implantacion $implantacion, ?User $usuario = null, ?string $nota = null): Implantacion
    {
        if (! $this->esExcluibleAMano($implantacion)) {
            throw new ExclusionNoPermitida($implantacion->origen_exigencia);
        }

        if ($implantacion->aplica) {
            return $implantacion;
        }

        $destino = $implantacion->estadoPrevioANoAplica() ?? EstadoImplantacion::NoIniciado;

        return DB::transaction(function () use ($implantacion, $destino, $usuario, $nota): Implantacion {
            $implantacion->update([
                'aplica' => true,
                'justificacion' => null,
                'estado' => $destino->value,
            ]);

            $this->registro->registrar(
                $implantacion,
                EstadoImplantacion::NoAplica,
                $destino,
                $usuario,
                $nota,
            );

            return $implantacion->refresh();
        });
    }

    /**
     * Sólo lo que el propio marco exige sin matriz que derivar.
     *
     * La ficha lo consulta para decidir si enseña el interruptor o una
     * explicación de por qué no está: una casilla deshabilitada sin motivo se
     * lee como un fallo.
     */
    public function esExcluibleAMano(Implantacion $implantacion): bool
    {
        return $implantacion->origen_exigencia === OrigenExigencia::Catalogo
            || $implantacion->origen_exigencia === null;
    }
}
