<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El catálogo de amenazas de MAGERIT.
 *
 * **Tabla GLOBAL: no lleva `organizacion_id`, no lleva RLS y no debe llevarlos**
 * (invariante 2). Es lo primero que alguien intentará «arreglar» al ver, en un
 * repositorio donde todas las tablas de datos llevan política de aislamiento, una
 * que no la lleva. No es un olvido: «E.1 Errores de los usuarios» no es un hecho
 * de ninguna organización en concreto, y duplicarlo por tenant es garantizar que
 * se desincronice.
 *
 * Va como datos y no como enum por el invariante 3, igual que los controles:
 * MAGERIT es de 2012, el CCN publica revisiones, y el día que llegue una hay que
 * poder verla en un diff. Lo que sí es enum es `GrupoAmenaza`, porque los cuatro
 * grupos son la ESTRUCTURA del catálogo y no su contenido — mismo reparto que
 * `TipoRequisito` frente a `requisitos`.
 *
 * Y no entra en `requisitos` con un `tipo` nuevo: una amenaza no se implanta, no
 * tiene fila en `aplicabilidad_ens`, no entra en la Declaración de Aplicabilidad
 * y `GeneradorImplantaciones` tendría que aprender a excluirla. Sería meter un
 * tipo ajeno en la tabla más central del modelo para ahorrarse una migración.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const GRUPOS = [
        'desastres_naturales',
        'origen_industrial',
        'errores_no_intencionados',
        'ataques_intencionados',
    ];

    public function up(): void
    {
        Schema::create('amenazas', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo')->unique()->comment('N.1, I.5, E.8, A.11');
            $table->string('grupo');
            $table->string('nombre');
            $table->text('descripcion')->nullable();

            /*
             * Sobre qué dimensiones puede actuar la amenaza y sobre qué tipos de
             * activo tiene sentido plantearla. Las dos en JSONB con GIN por el
             * mismo motivo que los atributos de la ISO 27002: se consultan para
             * filtrar y para proponer, y dos tablas pivote más darían lo mismo
             * con dos joins más.
             *
             * `tipos_activo` es lo que hace cierta la frase que ya está escrita
             * en `TipoActivo`: que la tipología MAGERIT del inventario está ahí
             * para que el análisis de riesgos elija amenazas del catálogo.
             */
            $table->jsonb('dimensiones')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('tipos_activo')->default(DB::raw("'[]'::jsonb"));

            $table->unsignedInteger('orden')->default(0);

            // Mismo contrato que los requisitos: huella del contenido importado
            // para distinguir lo modificado de lo intacto sin comparar campo a
            // campo, y retirada por marca y nunca por borrado, porque puede haber
            // riesgos colgando de la amenaza.
            $table->string('huella', 64)->nullable();
            $table->boolean('vigente')->default(true);
            $table->timestamp('retirado_en')->nullable();

            $table->timestamps();

            $table->index(['grupo', 'orden']);
        });

        $grupos = implode(', ', array_map(static fn (string $grupo): string => "'{$grupo}'", self::GRUPOS));

        DB::statement("ALTER TABLE amenazas ADD CONSTRAINT amenazas_grupo_check CHECK (grupo IN ({$grupos}))");
        DB::statement('ALTER TABLE amenazas ADD CONSTRAINT amenazas_nombre_check CHECK (length(trim(nombre)) > 0)');

        // Las dos columnas se consultan como listas: «qué amenazas aplican a un
        // activo de tipo hardware» es un `@>` sobre `tipos_activo`.
        DB::statement('CREATE INDEX amenazas_dimensiones_gin ON amenazas USING GIN (dimensiones)');
        DB::statement('CREATE INDEX amenazas_tipos_activo_gin ON amenazas USING GIN (tipos_activo)');
    }

    public function down(): void
    {
        Schema::dropIfExists('amenazas');
    }
};
