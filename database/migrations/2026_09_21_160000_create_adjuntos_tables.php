<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adjuntos: la documentación que cuelga de un registro.
 *
 * ### Un adjunto NO es una evidencia, y la frontera es el propósito
 *
 * Una **evidencia prueba un requisito**: tiene fecha de obtención, caducidad,
 * periodicidad de renovación y responsable, porque de eso vive su valor
 * probatorio. Un **adjunto documenta un registro**: el título de un curso, el
 * contrato firmado, la hoja de firmas escaneada. Obligar a rellenar cuatro
 * campos de caducidad para subir un PDF es cómo se consigue que no se suba.
 *
 * Las dos claves que ya existen —`acciones_formativas.evidencia_id` y
 * `acuerdos_confidencialidad.evidencia_id`— **se quedan**: siguen sirviendo para
 * lo que sirven, que es señalar cuál es la prueba de la medida.
 *
 * ### Pivotes explícitas y no una relación polimórfica
 *
 * En todo el repositorio no hay un solo `morphTo`, y esto no lo estrena. El
 * dialecto es la pivote con nombre —`implantacion_tarea`, `no_conformidad_tarea`,
 * `evidencia_implantacion`— y a cambio se conservan **claves foráneas reales**,
 * que un morph no puede tener: nadie puede dejar un adjunto colgando de un id
 * que ya no existe. El tercer anfitrión entra con una pivote y sin tocar
 * `adjuntos`.
 *
 * Y es N:M de verdad, no 1:N disfrazado: el certificado de un curso documenta a
 * la vez a la persona que lo hizo y a la sesión donde se impartió, y registrarlo
 * dos veces sería subir el mismo fichero dos veces.
 *
 * ### El disco es propio
 *
 * No el de `evidencias`: en producción ese bucket lleva **Object Lock en modo
 * compliance**, así que un DNI subido por error no se podría borrar nunca — y
 * eso, con datos personales dentro, es un problema y no una garantía. Es el
 * mismo argumento que separó el disco `documentos`.
 */
return new class extends Migration
{
    /** @var array<string, string> pivote => tabla del anfitrión */
    private const PIVOTES = [
        'persona_adjunto' => 'personas',
        'accion_formativa_adjunto' => 'acciones_formativas',
    ];

    public function up(): void
    {
        Schema::create('adjuntos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            $table->string('titulo');
            $table->text('nota')->nullable();

            /*
             * El disco se guarda con la fila porque puede cambiar entre entornos
             * y una ruta sin su disco no localiza nada. Copiado de `evidencias`,
             * que es donde esto ya se aprendió.
             */
            $table->string('disco');
            $table->string('ruta');
            $table->string('nombre_fichero');
            $table->string('mime');
            $table->unsignedBigInteger('tamano');
            $table->char('hash_sha256', 64);

            $table->foreignId('subido_por_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['organizacion_id', 'created_at']);
        });

        DB::statement('ALTER TABLE adjuntos ADD CONSTRAINT adjuntos_titulo_check CHECK (length(trim(titulo)) > 0)');

        /*
         * A diferencia de `evidencias`, aquí NO hay `url_externa`: un adjunto es
         * un fichero que se sube, y un enlace a un panel de un proveedor es una
         * evidencia. Por eso las seis columnas del fichero son `NOT NULL` y no
         * hace falta el `num_nonnulls` de allí; la huella queda garantizada por
         * la propia columna, sin `CHECK` que la exija.
         *
         * **Y `tamano` no lleva `CHECK (> 0)`**, aunque sea tentador. Un fichero
         * vacío es un error de quien lo sube, no una incoherencia de los datos,
         * y con la restricción puesta lo que ve esa persona es un 500 con un
         * mensaje sobre una restricción de PostgreSQL. `unsignedBigInteger` ya
         * impide el negativo, que es lo único imposible de verdad.
         */

        foreach (self::PIVOTES as $pivote => $anfitrion) {
            Schema::create($pivote, function (Blueprint $table) use ($pivote, $anfitrion): void {
                $columna = $anfitrion === 'personas' ? 'persona_id' : 'accion_formativa_id';

                $table->id();
                $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
                $table->foreignId('adjunto_id')->constrained('adjuntos')->cascadeOnDelete();
                $table->foreignId($columna)->constrained($anfitrion)->cascadeOnDelete();
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['adjunto_id', $columna], "{$pivote}_unico");
                $table->index(['organizacion_id', $columna]);
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::PIVOTES) as $pivote) {
            Schema::dropIfExists($pivote);
        }

        Schema::dropIfExists('adjuntos');
    }
};
