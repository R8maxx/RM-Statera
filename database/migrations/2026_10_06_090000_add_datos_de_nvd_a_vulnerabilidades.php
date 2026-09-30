<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que trae la consulta del CVE y merece quedarse: el CWE, las referencias,
 * la marca del catálogo KEV de CISA y el día en que se consultó NVD.
 *
 * **`kev_desde` es una foto**: la fecha en que CISA lo incluyó, tal como se vio
 * al registrarla. Un CVE que entra en el catálogo después no la cambia solo;
 * volver a consultar desde la edición, sí.
 *
 * **`nvd_consultado_el` es la procedencia**, y es fecha y no instante: lo que
 * se lee en la ficha es «datos de NVD consultados el 30 sep 2026».
 *
 * `referencias` es una lista JSON de URL; el `CHECK` sólo exige que sea lista,
 * porque el esquema —`http` o `https`— lo valida el `FormRequest`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vulnerabilidades', function (Blueprint $table): void {
            $table->string('cwe', 20)->nullable()->after('cvss_vector');
            $table->jsonb('referencias')->nullable()->after('cwe');
            $table->date('kev_desde')->nullable()->after('referencias');
            $table->date('nvd_consultado_el')->nullable()->after('kev_desde');
        });

        DB::statement("ALTER TABLE vulnerabilidades ADD CONSTRAINT vulnerabilidades_cwe_check CHECK (cwe IS NULL OR cwe ~ '^CWE-[0-9]+$')");
        DB::statement("ALTER TABLE vulnerabilidades ADD CONSTRAINT vulnerabilidades_referencias_check CHECK (referencias IS NULL OR jsonb_typeof(referencias) = 'array')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE vulnerabilidades DROP CONSTRAINT IF EXISTS vulnerabilidades_referencias_check');
        DB::statement('ALTER TABLE vulnerabilidades DROP CONSTRAINT IF EXISTS vulnerabilidades_cwe_check');

        Schema::table('vulnerabilidades', function (Blueprint $table): void {
            $table->dropColumn(['cwe', 'referencias', 'kev_desde', 'nvd_consultado_el']);
        });
    }
};
