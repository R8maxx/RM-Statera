<?php

declare(strict_types=1);

namespace App\Domain\Usuario;

use App\Domain\Traza\Enums\AccionAuditada;
use App\Domain\Traza\RegistroTraza;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dónde tiene la cuenta la sesión abierta, y cerrarla desde lejos.
 *
 * Lee la tabla `sessions` de Laravel, que es donde el driver `database` ya
 * guarda el usuario, la IP y el navegador de cada sesión. No hay tabla propia:
 * la sesión ES esa fila, y una copia se desincronizaría en el primer
 * `session:gc`.
 *
 * **El identificador de sesión no sale nunca hacia el cliente.** Es el valor
 * de la cookie: quien lo lea —una extensión del navegador, un XSS— se sienta en
 * esa sesión. Viaja su huella SHA-256 como `clave`, y cerrar busca la fila
 * comparando huellas entre las de la propia cuenta. El id de sesión tiene 40
 * caracteres aleatorios, así que la huella no se puede deshacer.
 *
 * **Cerrar una sesión cambia también el `remember_token`**, que es lo que hace
 * `DesactivarCuenta`: sin eso, un navegador con «recordarme» abre una sesión
 * nueva sola en su siguiente petición y cerrarla no habría servido de nada. La
 * sesión desde la que se pide sigue abierta —su cookie de recordar deja de
 * valer, pero no la necesita mientras la sesión viva—.
 *
 * `sessions` no lleva `organizacion_id` y no hace falta: se acota por
 * `user_id`, que es la cuenta de quien pregunta y nunca un parámetro.
 */
final class SesionesAbiertas
{
    public function __construct(private readonly RegistroTraza $traza) {}

    /**
     * Las de la cuenta, la actual primero y después de la más reciente a la más
     * antigua. Una sesión quieta más allá de su vida ya no abre nada aunque la
     * fila siga ahí hasta el siguiente barrido, así que no se enseña.
     *
     * @return list<array{clave: string, navegador: string, sistema: string, ip: ?string, ultimaActividad: string, actual: bool}>
     */
    public function de(User $cuenta, string $sesionActual): array
    {
        $vigentesDesde = Carbon::now()->subMinutes((int) config('session.lifetime'))->getTimestamp();

        return DB::table('sessions')
            ->where('user_id', $cuenta->id)
            ->where('last_activity', '>=', $vigentesDesde)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(function (object $sesion) use ($sesionActual): array {
                [$navegador, $sistema] = self::describir(is_string($sesion->user_agent) ? $sesion->user_agent : null);

                return [
                    'clave' => self::clave((string) $sesion->id),
                    'navegador' => $navegador,
                    'sistema' => $sistema,
                    'ip' => is_string($sesion->ip_address) ? $sesion->ip_address : null,
                    'ultimaActividad' => Carbon::createFromTimestamp((int) $sesion->last_activity)->toIso8601String(),
                    'actual' => hash_equals((string) $sesion->id, $sesionActual),
                ];
            })
            ->sortByDesc('actual')
            ->values()
            ->all();
    }

    /**
     * Cierra una sesión de la cuenta por su huella. La actual no: para eso está
     * «Cerrar sesión», que además la invalida en el navegador.
     *
     * @return bool si había una sesión que cerrar
     */
    public function cerrar(User $cuenta, string $clave, string $sesionActual): bool
    {
        $id = DB::table('sessions')
            ->where('user_id', $cuenta->id)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->first(static fn (string $id): bool => hash_equals(self::clave($id), $clave) && ! hash_equals($id, $sesionActual));

        if ($id === null) {
            return false;
        }

        $this->borrar($cuenta, [$id]);

        return true;
    }

    /** @return int cuántas se han cerrado */
    public function cerrarLasDemas(User $cuenta, string $sesionActual): int
    {
        $ids = DB::table('sessions')
            ->where('user_id', $cuenta->id)
            ->where('id', '!=', $sesionActual)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        if ($ids !== []) {
            $this->borrar($cuenta, $ids);
        }

        return count($ids);
    }

    /**
     * La huella que viaja en lugar del id.
     */
    public static function clave(string $idSesion): string
    {
        return hash('sha256', $idSesion);
    }

    /**
     * «Firefox» y «Linux» a partir de la cabecera del navegador.
     *
     * A mano y sin paquete: sólo se quiere reconocer la sesión propia en una
     * lista de dos o tres, no hacer estadística de versiones. El orden importa
     * —Edge y Opera se anuncian también como Chrome, y Chrome como Safari—.
     *
     * @return array{0: string, 1: string}
     */
    public static function describir(?string $agente): array
    {
        $agente ??= '';

        $navegador = match (true) {
            str_contains($agente, 'Edg/') => 'Edge',
            str_contains($agente, 'OPR/') => 'Opera',
            str_contains($agente, 'Firefox/') => 'Firefox',
            str_contains($agente, 'Chrome/') || str_contains($agente, 'CriOS/') => 'Chrome',
            str_contains($agente, 'Safari/') => 'Safari',
            default => 'Un navegador',
        };

        $sistema = match (true) {
            str_contains($agente, 'iPhone') || str_contains($agente, 'iPad') => 'iOS',
            str_contains($agente, 'Android') => 'Android',
            str_contains($agente, 'Windows') => 'Windows',
            str_contains($agente, 'Mac OS X') || str_contains($agente, 'Macintosh') => 'macOS',
            str_contains($agente, 'Linux') => 'Linux',
            default => 'un sistema desconocido',
        };

        return [$navegador, $sistema];
    }

    /**
     * Borra las filas, invalida el «recordarme» y lo deja en la traza con el
     * verbo de salir: para quien la tenía abierta, eso es lo que ha pasado.
     *
     * @param  list<string>  $ids
     */
    private function borrar(User $cuenta, array $ids): void
    {
        DB::transaction(function () use ($cuenta, $ids): void {
            DB::table('sessions')->where('user_id', $cuenta->id)->whereIn('id', $ids)->delete();

            $cuenta->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

            $this->traza->evento($cuenta, AccionAuditada::CierreSesion, null, ['sesiones_cerradas' => count($ids)]);
        });
    }
}
