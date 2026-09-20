<?php

declare(strict_types=1);

namespace App\Domain\Incidente;

use App\Domain\Incidente\Models\Incidente;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Anota que el incidente se notificó a un supervisor.
 *
 * **Marcar notificable y anotar la notificación son dos actos distintos**, y por
 * eso esto es una acción aparte: lo primero es una valoración que se hace al
 * registrar —«hubo datos personales de por medio»— y lo segundo es un hecho con
 * fecha que el auditor contrasta contra el justificante.
 *
 * **Marca `notificable` si no lo estaba**, porque el `CHECK` lo exige y porque es
 * lo que de verdad ha pasado: si se ha notificado, es que había que notificar.
 * Lo contrario —rechazar la anotación— obligaría a editar el incidente primero
 * para poder decir la verdad sobre él.
 *
 * **Y deja constancia en el histórico sin cambiar el estado.** Notificar no es un
 * paso del ciclo: se puede notificar con el incidente abierto, en tratamiento o
 * resuelto, y meterlo en la máquina de estados obligaría a inventarse un estado
 * «notificado» que no dice nada de cómo va la contención.
 */
final class RegistrarNotificacion
{
    public function __construct(private readonly RegistroTransicionesIncidente $registro) {}

    public function aepd(Incidente $incidente, ?Carbon $cuando = null, ?User $usuario = null, ?string $nota = null): Incidente
    {
        return $this->anotar($incidente, 'aepd', $cuando, $usuario, $nota);
    }

    public function ccnCert(Incidente $incidente, ?Carbon $cuando = null, ?User $usuario = null, ?string $nota = null): Incidente
    {
        return $this->anotar($incidente, 'ccn_cert', $cuando, $usuario, $nota);
    }

    private function anotar(
        Incidente $incidente,
        string $destinatario,
        ?Carbon $cuando,
        ?User $usuario,
        ?string $nota,
    ): Incidente {
        $momento = $cuando ?? Carbon::now();

        return DB::transaction(function () use ($incidente, $destinatario, $momento, $usuario, $nota): Incidente {
            $incidente->update([
                "notificable_{$destinatario}" => true,
                "notificado_{$destinatario}_en" => $momento,
            ]);

            /*
             * Una transición de estado a estado, con el mismo valor en los dos
             * lados. Parece redundante y no lo es: la tabla de transiciones es el
             * histórico del incidente, y «cuándo se notificó y quién lo anotó» es
             * exactamente la clase de pregunta que se le hace. Sin esta fila, el
             * único rastro sería la columna, que no dice quién.
             */
            $this->registro->registrar(
                $incidente,
                $incidente->estado,
                $incidente->estado,
                $usuario,
                $this->texto($destinatario, $momento, $nota),
            );

            return $incidente->refresh();
        });
    }

    private function texto(string $destinatario, Carbon $cuando, ?string $nota): string
    {
        $quien = $destinatario === 'aepd' ? 'la AEPD' : 'el CCN-CERT';
        $linea = sprintf('Notificado a %s el %s.', $quien, $cuando->format('d/m/Y H:i'));

        return $nota === null || trim($nota) === '' ? $linea : $linea.' '.trim($nota);
    }
}
