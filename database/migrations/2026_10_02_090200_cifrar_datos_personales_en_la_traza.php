<?php

declare(strict_types=1);

use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Lo que la traza guardó en claro antes del punto 35, cifrado ahora (punto 36).
 *
 * Desde que los casts cifran NIF, teléfonos, domicilio y fecha de nacimiento,
 * `RegistroTraza` los guarda como van a la base, es decir, cifrados. Los eventos
 * anteriores los guardaron en claro, y `eventos_auditoria` no se modifica. Ésta
 * es la **única vez** que se modifica en bloque, con el motivo escrito, igual
 * que la migración de las horas del punto 33.
 *
 * **Se cifran y no se borran**, y es a propósito. Cifrados quedan igual que los
 * posteriores al punto 35: la traza no tiene dos formatos según la fecha, sigue
 * pudiendo decir qué cambió y nadie los lee sin la clave. El borrado de verdad
 * lo hace `SeudonimizarPersona`, persona a persona, cuando vence el plazo o
 * alguien ejerce su derecho de supresión, y ése sí quita las claves.
 *
 * Sólo se cifra lo que no lo estaba: un valor que ya descifra se deja, así que
 * correrla dos veces no cifra dos veces.
 *
 * Va como migrador, que es el dueño de la tabla y el único que puede escribir
 * en ella, y con `comoMantenimiento()` para pasar RLS.
 *
 * **El `down()` no deshace nada.** Descifrar devolvería a claro también los
 * valores que ya se escribieron cifrados después del punto 35, y no hay forma de
 * distinguirlos.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const CLAVES = ['nif', 'telefono', 'telefono_fijo', 'direccion', 'fecha_nacimiento'];

    public function up(): void
    {
        app(ContextoOrganizacion::class)->comoMantenimiento(function (): void {
            $eventos = DB::table('eventos_auditoria')
                ->where('entidad', 'Persona')
                ->orderBy('id')
                ->get(['id', 'valor_anterior', 'valor_nuevo']);

            foreach ($eventos as $evento) {
                $cambios = [];

                foreach (['valor_anterior', 'valor_nuevo'] as $columna) {
                    $valores = $evento->{$columna} === null ? null : json_decode((string) $evento->{$columna}, true);

                    if (! is_array($valores)) {
                        continue;
                    }

                    $cifrados = $this->cifrar($valores);

                    if ($cifrados !== $valores) {
                        $cambios[$columna] = json_encode($cifrados, JSON_UNESCAPED_UNICODE);
                    }
                }

                if ($cambios !== []) {
                    DB::table('eventos_auditoria')->where('id', $evento->id)->update($cambios);
                }
            }
        });
    }

    public function down(): void
    {
        //
    }

    /**
     * @param  array<string, mixed>  $valores
     * @return array<string, mixed>
     */
    private function cifrar(array $valores): array
    {
        foreach (self::CLAVES as $clave) {
            $valor = $valores[$clave] ?? null;

            if ($valor === null || $valor === '' || $this->yaCifrado((string) $valor)) {
                continue;
            }

            $valores[$clave] = Crypt::encryptString((string) $valor);
        }

        return $valores;
    }

    private function yaCifrado(string $valor): bool
    {
        try {
            Crypt::decryptString($valor);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
