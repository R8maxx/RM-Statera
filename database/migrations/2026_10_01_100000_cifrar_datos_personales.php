<?php

declare(strict_types=1);

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Persona\HuellaNif;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los datos personales de `personas`, cifrados en reposo (punto 35, § 6).
 *
 * NIF, los dos teléfonos, el domicilio y la fecha de nacimiento pasan a
 * guardarse cifrados con `APP_KEY`, que es lo que hacen los casts del modelo.
 * Las columnas pasan a `text`, porque un valor cifrado ocupa varias veces lo
 * que ocupaba en claro, y la fecha deja de ser `date`, porque cifrada ya no es
 * una fecha.
 *
 * **El índice único del NIF se muda a una huella.** Cifrado, el mismo NIF da
 * un texto distinto cada vez y el índice dejaría de ver dos iguales.
 * `nif_huella` es un HMAC del NIF normalizado (`HuellaNif`) con su propia
 * clave, y es lo que se exige único por organización.
 *
 * Las filas se recorren con `comoMantenimiento()`: sin él, RLS deniega por
 * defecto y los `UPDATE` afectan a cero filas sin fallar. Van por el query
 * builder y no dejan traza, que es lo que se quiere: el dato no ha cambiado,
 * sólo cómo se guarda.
 *
 * **Lo que no se reescribe es la traza.** Los eventos anteriores a esta
 * migración guardan esos datos en claro en `eventos_auditoria`, que no se puede
 * modificar. Es el hueco que aborda el punto 36.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const CIFRADAS = ['nif', 'telefono', 'telefono_fijo', 'direccion', 'fecha_nacimiento'];

    public function up(): void
    {
        Schema::table('personas', function (Blueprint $table): void {
            $table->char('nif_huella', 64)->nullable();
        });

        DB::statement('ALTER TABLE personas ALTER COLUMN nif TYPE text');
        DB::statement('ALTER TABLE personas ALTER COLUMN telefono TYPE text');
        DB::statement('ALTER TABLE personas ALTER COLUMN telefono_fijo TYPE text');
        DB::statement('ALTER TABLE personas ALTER COLUMN fecha_nacimiento TYPE text USING fecha_nacimiento::text');

        $this->recorrer(static function (object $fila): array {
            $cambios = [];

            foreach (self::CIFRADAS as $columna) {
                if ($fila->{$columna} !== null) {
                    $cambios[$columna] = Crypt::encryptString((string) $fila->{$columna});
                }
            }

            $cambios['nif_huella'] = HuellaNif::de($fila->nif);

            return $cambios;
        });

        DB::statement('DROP INDEX IF EXISTS personas_organizacion_id_nif_unique');
        DB::statement('CREATE UNIQUE INDEX personas_organizacion_id_nif_huella_unique ON personas (organizacion_id, nif_huella) WHERE nif_huella IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS personas_organizacion_id_nif_huella_unique');

        $this->recorrer(static function (object $fila): array {
            $cambios = [];

            foreach (self::CIFRADAS as $columna) {
                if ($fila->{$columna} !== null) {
                    $cambios[$columna] = Crypt::decryptString((string) $fila->{$columna});
                }
            }

            return $cambios;
        });

        DB::statement('ALTER TABLE personas ALTER COLUMN nif TYPE varchar(32)');
        DB::statement('ALTER TABLE personas ALTER COLUMN telefono TYPE varchar(32)');
        DB::statement('ALTER TABLE personas ALTER COLUMN telefono_fijo TYPE varchar(32)');
        DB::statement('ALTER TABLE personas ALTER COLUMN fecha_nacimiento TYPE date USING fecha_nacimiento::date');

        Schema::table('personas', function (Blueprint $table): void {
            $table->dropColumn('nif_huella');
        });

        DB::statement('CREATE UNIQUE INDEX personas_organizacion_id_nif_unique ON personas (organizacion_id, nif) WHERE nif IS NOT NULL');
    }

    /**
     * @param  Closure(object): array<string, mixed>  $cambios
     */
    private function recorrer(Closure $cambios): void
    {
        app(ContextoOrganizacion::class)->comoMantenimiento(static function () use ($cambios): void {
            $filas = DB::table('personas')->select(['id', ...self::CIFRADAS])->orderBy('id')->get();

            foreach ($filas as $fila) {
                // Una persona sin ningún dato personal no tiene nada que
                // reescribir, y un `UPDATE` sin columnas es un error de sintaxis.
                $valores = $cambios($fila);

                if ($valores !== []) {
                    DB::table('personas')->where('id', $fila->id)->update($valores);
                }
            }
        });
    }
};
