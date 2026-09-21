<?php

declare(strict_types=1);

use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `personas.puesto` deja de ser texto libre y pasa a ser el catálogo `puestos`.
 *
 * ### Esta migración es la que puede perder datos en silencio
 *
 * Una migración **no tiene petición ni usuario**, así que
 * `app.organizacion_actual` no está fijado y RLS deniega por defecto. Medido
 * sobre esta base:
 *
 * | Operación                 | Sin contexto                              |
 * |---------------------------|-------------------------------------------|
 * | `SELECT`                  | 0 filas, sin error                        |
 * | `UPDATE` / `DELETE`       | 0 filas, sin error                        |
 * | `INSERT` con valores      | **ERROR** 42501, ruidoso                  |
 * | `INSERT … SELECT`         | **0 filas, sin error** ← el caso de aquí  |
 *
 * Es decir: sin `comoMantenimiento()`, los dos `INSERT … SELECT` de abajo
 * insertarían **cero filas sin quejarse**, el `DROP COLUMN` de después sí
 * funcionaría, y el puesto de toda la plantilla se habría ido para siempre con
 * una migración que dice «DONE».
 *
 * `comoMantenimiento()` es la única puerta que atraviesa las tres capas, y ésta
 * es su tercera aparición en el repositorio —las otras dos son el recuento de
 * implantaciones del importador del catálogo y el `down()` de
 * `ampliar_calculos_con_personal_formado`—. Nada de `withoutGlobalScopes()`.
 *
 * ### Por qué SQL crudo y no Eloquent
 *
 * Un `Puesto::create()` aquí dispararía `PerteneceAOrganizacion::creating`, que
 * llama a `idObligatorio()` y lanza «No hay organización activa» **aunque
 * mantenimiento esté abierto**: mantenimiento levanta RLS, no rellena
 * `organizacion_id`. Y un modelo en una migración envejece mal: el modelo
 * evoluciona y la migración es historia.
 *
 * ### Los códigos
 *
 * `row_number() OVER (PARTITION BY organizacion_id ORDER BY titulo)` numera desde
 * 1 **en cada organización**, así que el `unique(organizacion_id, codigo)` se
 * cumple por construcción y dos organizaciones con el mismo puesto acaban en dos
 * filas distintas. El `ORDER BY titulo` lo hace determinista: dos ejecuciones
 * sobre el mismo dato dan los mismos códigos.
 *
 * `CodigoPuesto` no se usa aquí, y no por pereza: consulta con Eloquent, que en
 * una migración no ve nada y devolvería `PUE-001` para todas las organizaciones.
 *
 * ### Reversible en el esquema, no en la información
 *
 * El `down()` devuelve la columna y la rellena desde la asignación **vigente**,
 * que es todo lo que una columna de texto sabe representar. Se pierde el
 * histórico entero, las fechas, quién no tenía asignación vigente, los puestos
 * sin ocupar, la ficha de puesto y el organigrama. Sirve para desandar un
 * despliegue del mismo día, no para volver de verdad.
 */
return new class extends Migration
{
    /** El texto del puesto, con los espacios normalizados. */
    private const TITULO = "btrim(regexp_replace(puesto, '\\s+', ' ', 'g'))";

    public function up(): void
    {
        $titulo = self::TITULO;

        app(ContextoOrganizacion::class)->comoMantenimiento(static function () use ($titulo): void {
            // Un puesto por texto distinto y por organización. El `DISTINCT ON`
            // va en la subconsulta porque las funciones de ventana se calculan
            // ANTES del `DISTINCT`: en un solo nivel, la numeración saltaría.
            DB::statement(<<<SQL
                INSERT INTO puestos (organizacion_id, codigo, titulo, created_at, updated_at)
                SELECT organizacion_id,
                       'PUE-' || lpad(row_number() OVER (PARTITION BY organizacion_id ORDER BY titulo)::text, 3, '0'),
                       titulo,
                       now(), now()
                FROM (
                    SELECT DISTINCT ON (organizacion_id, lower({$titulo}))
                           organizacion_id,
                           {$titulo} AS titulo
                    FROM personas
                    WHERE puesto IS NOT NULL AND btrim(puesto) <> ''
                    ORDER BY organizacion_id, lower({$titulo}), id
                ) distintos
            SQL);

            // La asignación vigente de cada quien tenía puesto, desde su alta.
            DB::statement(<<<SQL
                INSERT INTO asignaciones_puesto
                    (organizacion_id, persona_id, puesto_id, desde, created_at, updated_at)
                SELECT p.organizacion_id, p.id, pu.id, p.fecha_alta, now(), now()
                FROM personas p
                INNER JOIN puestos pu
                    ON pu.organizacion_id = p.organizacion_id
                   AND lower(pu.titulo) = lower({$titulo})
                WHERE p.puesto IS NOT NULL AND btrim(p.puesto) <> ''
            SQL);

            /*
             * Comprobar en vez de confiar, porque el fallo de esta migración es
             * mudo: si RLS hubiera denegado, los dos `INSERT` de arriba habrían
             * escrito cero filas sin decir nada y el `DROP COLUMN` se llevaría el
             * dato. Con la excepción, la transacción se deshace entera.
             */
            $conPuesto = (int) DB::scalar("SELECT count(*) FROM personas WHERE puesto IS NOT NULL AND btrim(puesto) <> ''");
            $asignadas = (int) DB::scalar('SELECT count(*) FROM asignaciones_puesto');

            if ($conPuesto !== $asignadas) {
                throw new RuntimeException(
                    "Se iban a perder puestos: {$conPuesto} personas con puesto y {$asignadas} asignaciones creadas.",
                );
            }
        });

        // DDL: RLS no le afecta, y va fuera del callback a propósito.
        Schema::table('personas', function (Blueprint $table): void {
            $table->dropColumn('puesto');
        });
    }

    public function down(): void
    {
        // La columna vuelve primero: el `UPDATE` de abajo la necesita.
        Schema::table('personas', function (Blueprint $table): void {
            $table->string('puesto')->nullable();
        });

        app(ContextoOrganizacion::class)->comoMantenimiento(static function (): void {
            DB::statement(<<<'SQL'
                UPDATE personas p
                   SET puesto = pu.titulo
                  FROM asignaciones_puesto ap
                 INNER JOIN puestos pu ON pu.id = ap.puesto_id
                 WHERE ap.persona_id = p.id
                   AND ap.hasta IS NULL
            SQL);
        });

        // Las tablas NO se tiran aquí: eso es de `create_puestos_tables`, y
        // hacerlo dejaría a la migración de RLS sin tablas que desproteger.
    }
};
