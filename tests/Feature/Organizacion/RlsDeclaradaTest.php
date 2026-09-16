<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| La tercera capa, comprobada por introspección
|--------------------------------------------------------------------------
|
| El aislamiento tiene tres capas: `organizacion_id`, el global scope de Eloquent
| y Row Level Security. Las dos primeras se rompen ruidosamente; la tercera se
| olvida en silencio, porque una tabla sin política se comporta exactamente igual
| que una con política mientras nadie intente cruzar la frontera.
|
| `AislamientoTest` recorre un dataset literal de cuatro modelos, así que una
| tabla nueva sin RLS no la caza nadie. Este test no enumera: pregunta a
| PostgreSQL qué tablas tienen `organizacion_id` y exige que todas la tengan
| activa, **forzada** y con política. Cubre lo que ya existe y lo que traiga el
| siguiente módulo sin que nadie se acuerde de nada.
|
| `FORCE` no es decorativo: sin él, el propietario de la tabla queda exento, y el
| propietario del esquema es `statera_app`, que es justo con quien se conecta la
| aplicación.
|
*/

/**
 * Las cuatro que no la llevan, cada una con su motivo escrito.
 *
 * No es una lista de pendientes: es la declaración de dónde NO está puesta la
 * tercera capa, que es lo que hay que poder contestar cuando lo pregunten.
 *
 * @var array<string, string>
 */
const SIN_RLS_A_PROPOSITO = [
    // `User` no lleva `PerteneceAOrganizacion` a propósito: la autenticación
    // tiene que poder encontrar a alguien ANTES de saber de qué organización es.
    // La consecuencia está escrita en CLAUDE.md — toda consulta de usuarios se
    // acota a mano con `->where('organizacion_id', …)`.
    'users' => 'La autenticación resuelve al usuario antes de que haya contexto.',

    // Las tres de `spatie/laravel-permission`. El aislamiento por organización lo
    // hace la propia librería con su `PermissionRegistrar::setPermissionsTeamId()`,
    // que `ContextoOrganizacion::sincronizarPermisos()` mantiene al día. Son
    // tablas que no controlamos y cuyo esquema cambia con la librería.
    'roles' => 'Aislada por el team id de spatie/laravel-permission.',
    'model_has_roles' => 'Aislada por el team id de spatie/laravel-permission.',
    'model_has_permissions' => 'Aislada por el team id de spatie/laravel-permission.',
];

/**
 * @return list<object{relname: string, relrowsecurity: bool, relforcerowsecurity: bool, politicas: int}>
 */
function tablasConOrganizacion(): array
{
    /** @var list<object{relname: string, relrowsecurity: bool, relforcerowsecurity: bool, politicas: int}> */
    return DB::select(<<<'SQL'
        select c.relname,
               c.relrowsecurity,
               c.relforcerowsecurity,
               (select count(*) from pg_policies p where p.tablename = c.relname) as politicas
        from information_schema.columns col
        join pg_class c on c.relname = col.table_name and c.relkind = 'r'
        where col.column_name = 'organizacion_id'
          and col.table_schema = 'public'
        order by c.relname
    SQL);
}

it('toda tabla con organizacion_id tiene RLS activa, forzada y con política', function (): void {
    $sinProteger = [];

    foreach (tablasConOrganizacion() as $tabla) {
        if (array_key_exists($tabla->relname, SIN_RLS_A_PROPOSITO)) {
            continue;
        }

        if (! $tabla->relrowsecurity || ! $tabla->relforcerowsecurity || (int) $tabla->politicas === 0) {
            $sinProteger[] = sprintf(
                '%s (activa: %s, forzada: %s, políticas: %d)',
                $tabla->relname,
                $tabla->relrowsecurity ? 'sí' : 'NO',
                $tabla->relforcerowsecurity ? 'sí' : 'NO',
                $tabla->politicas,
            );
        }
    }

    expect($sinProteger)->toBe([], sprintf(
        "Estas tablas llevan `organizacion_id` y se quedaron sin la tercera capa del aislamiento:\n- %s\n".
        'Falta su migración de RLS, al estilo de `habilitar_rls_en_tareas`.',
        implode("\n- ", $sinProteger),
    ));
});

/**
 * Al revés: una excepción que deja de serlo tiene que quitarse de la lista.
 *
 * Si alguien le pone RLS a `users` y la lista sigue diciendo que no la lleva, la
 * declaración pasa a mentir — y esta lista existe justamente para poder
 * contestar a la pregunta de dónde no está puesta.
 */
it('la lista de excepciones no arrastra tablas que ya están protegidas', function (): void {
    $porNombre = [];

    foreach (tablasConOrganizacion() as $tabla) {
        $porNombre[$tabla->relname] = $tabla;
    }

    foreach (array_keys(SIN_RLS_A_PROPOSITO) as $tabla) {
        expect(array_key_exists($tabla, $porNombre))->toBeTrue(
            "`{$tabla}` ya no tiene columna `organizacion_id`: sácala de SIN_RLS_A_PROPOSITO.",
        );

        expect($porNombre[$tabla]->relrowsecurity)->toBeFalse(
            "`{$tabla}` ya tiene RLS: sácala de SIN_RLS_A_PROPOSITO.",
        );
    }
});
