<?php

declare(strict_types=1);

namespace App\Domain\Usuario;

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Traza\Enums\AccionAuditada;
use App\Domain\Traza\RegistroTraza;
use App\Domain\Usuario\Excepciones\OperacionDeCuentaNoPermitida;
use App\Domain\Usuario\Models\CuentaSistema;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Qué parte de la organización ve una cuenta, y hasta cuándo.
 *
 * **Sólo el auditor externo lleva alcance**, y lo lleva siempre: el § 4.19 lo
 * describe con «acceso limitado al alcance auditado», y un auditor que viene de
 * fuera audita durante unas semanas y no para siempre. Por eso para ese rol
 * hacen falta las dos cosas —al menos un sistema y una fecha de fin— y para los
 * otros dos no hay ninguna: se borran al cambiar de rol, para que un técnico
 * que fue auditor no arrastre un filtro que ya no le corresponde.
 *
 * Los sistemas se buscan con el scope de organización puesto, así que un id de
 * otro cliente sencillamente no se encuentra.
 */
final class AlcanceDeCuenta
{
    public function __construct(private readonly RegistroTraza $traza) {}

    /**
     * @param  list<int>  $sistemas
     */
    public function fijar(User $cuenta, Rol $rol, array $sistemas, ?Carbon $hasta): void
    {
        if ($rol !== Rol::Auditor) {
            $sistemas = [];
            $hasta = null;
        } elseif ($sistemas === [] || $hasta === null) {
            throw OperacionDeCuentaNoPermitida::auditorSinAlcance();
        }

        if ($hasta !== null && $hasta->lt(Carbon::today())) {
            throw OperacionDeCuentaNoPermitida::accesoPasado();
        }

        /** @var list<int> $validos */
        $validos = Sistema::query()->whereKey($sistemas)->orderBy('id')->pluck('id')->all();

        if ($rol === Rol::Auditor && $validos === []) {
            throw OperacionDeCuentaNoPermitida::auditorSinAlcance();
        }

        $anterior = $this->foto($cuenta);

        DB::transaction(function () use ($cuenta, $validos, $hasta): void {
            CuentaSistema::query()
                ->where('user_id', $cuenta->id)
                ->whereNotIn('sistema_id', $validos)
                ->delete();

            foreach ($validos as $sistemaId) {
                CuentaSistema::query()->firstOrCreate(['user_id' => $cuenta->id, 'sistema_id' => $sistemaId]);
            }

            $cuenta->forceFill(['acceso_hasta' => $hasta])->save();
        });

        $nuevo = $this->foto($cuenta);

        if ($anterior !== $nuevo) {
            $this->traza->evento($cuenta, AccionAuditada::Actualizado, $anterior, $nuevo);
        }
    }

    /**
     * @return array{sistemas: list<int>, acceso_hasta: ?string}
     */
    private function foto(User $cuenta): array
    {
        /** @var list<int> $sistemas */
        $sistemas = CuentaSistema::query()
            ->where('user_id', $cuenta->id)
            ->orderBy('sistema_id')
            ->pluck('sistema_id')
            ->all();

        return [
            'sistemas' => $sistemas,
            'acceso_hasta' => $cuenta->acceso_hasta?->toDateString(),
        ];
    }
}
