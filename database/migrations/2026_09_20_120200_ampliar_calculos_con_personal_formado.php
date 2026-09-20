<?php

declare(strict_types=1);

use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Añade `personal_formado` al `CHECK` de `indicadores.calculo`. § 4.8.
 *
 * El IND-03 del seeder nació **manual** con un comentario que decía literalmente
 * «sin el módulo de personas (§ 4.8) no hay de dónde sacar el porcentaje de
 * personal formado». Con el § 4.8 dentro sí lo hay, así que el cálculo existe y
 * el `CHECK` tiene que admitirlo.
 *
 * **La lista va escrita a mano y no sale de `CalculoIndicador::cases()`**, y es la
 * misma disciplina que el resto de `CHECK` de enum del repositorio: construirla
 * desde el enum en ejecución haría que una base recién migrada admitiera cualquier
 * caso nuevo sin migración, y el fallo aparecería sólo al desplegar sobre una base
 * existente — que es exactamente donde no se quiere descubrir.
 */
return new class extends Migration
{
    /**
     * Los trece cálculos: los doce de la migración original más el nuevo.
     *
     * @var list<string>
     */
    private const CALCULOS = [
        'cumplimiento_implantado',
        'implantaciones_pendientes',
        'implantadas_sin_evidencia',
        'madurez_media',
        'evidencias_caducadas',
        'tareas_vencidas',
        'tareas_sin_responsable',
        'no_conformidades_abiertas',
        'no_conformidades_sin_verificar',
        'riesgos_sobre_umbral',
        'activos_sin_cifrar',
        'activos_sin_revisar',
        'personal_formado',
    ];

    public function up(): void
    {
        $this->rehacer(self::CALCULOS);
    }

    public function down(): void
    {
        $sinElNuevo = array_filter(
            self::CALCULOS,
            static fn (string $calculo): bool => $calculo !== 'personal_formado',
        );

        /*
         * **El borrado va por `comoMantenimiento()`, y no es adorno.** Una
         * migración no tiene petición ni usuario, así que el scope no ve nada y
         * RLS deniega por defecto: `DB::table(...)->delete()` afectaría a cero
         * filas **sin fallar**, y el `ALTER TABLE` de la línea siguiente moriría
         * con «is violated by some row» — que es exactamente lo que pasó al
         * escribir esto. Es la única puerta que atraviesa las tres capas, y una
         * migración es el caso para el que existe.
         *
         * Y se borra el indicador entero en vez de pasarlo a manual, a
         * diferencia de las tareas que cambian de origen: un indicador manual
         * exige método escrito (`indicadores_manual_check`), así que convertirlo
         * obligaría a **inventar** de dónde sale su cifra. Su serie se va con él
         * por la cascada, que es lo correcto: son mediciones de un cálculo que
         * en el esquema revertido no existe.
         */
        app(ContextoOrganizacion::class)->comoMantenimiento(
            static fn () => DB::table('indicadores')->where('calculo', 'personal_formado')->delete(),
        );

        $this->rehacer($sinElNuevo);
    }

    /**
     * @param  list<string>  $calculos
     */
    private function rehacer(array $calculos): void
    {
        $lista = implode(', ', array_map(
            static fn (string $valor): string => "'".str_replace("'", "''", $valor)."'",
            $calculos,
        ));

        DB::statement('ALTER TABLE indicadores DROP CONSTRAINT IF EXISTS indicadores_calculo_valido_check');
        DB::statement("ALTER TABLE indicadores ADD CONSTRAINT indicadores_calculo_valido_check CHECK (calculo IS NULL OR calculo IN ({$lista}))");
    }
};
