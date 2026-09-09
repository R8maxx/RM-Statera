<?php

declare(strict_types=1);

namespace App\Domain\Auditoria;

use App\Domain\Auditoria\Enums\AccionAuditada;
use App\Domain\Auditoria\Models\EventoAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Escribe la traza. Todo evento auditado pasa por aquí.
 *
 * Guarda **sólo lo que cambió**, no el modelo entero: un log que copia doscientas
 * filas completas cada vez deja de poder consultarse en un año, y la pregunta
 * que se le hace —«¿cuándo cambió el responsable de esto?»— se contesta con el
 * diff, no con la fotografía.
 *
 * Los valores salen de `getAttributes()` y `getOriginal()`, que son los valores
 * tal y como van a la base: escalares. Pasar por los casts metería enums y
 * objetos `Carbon` en una columna JSONB.
 */
final class RegistroAuditoria
{
    /**
     * Lo que nunca entra en la traza.
     *
     * `updated_at` cambia en cada escritura y no dice nada que no diga ya la
     * fecha del propio evento. `organizacion_id` no cambia nunca —y si cambiara,
     * el problema sería mucho más gordo que un hueco en el log—.
     *
     * @var list<string>
     */
    private const IGNORADOS = ['id', 'organizacion_id', 'created_at', 'updated_at'];

    public function creado(Model $modelo): void
    {
        $this->escribir($modelo, AccionAuditada::Creado, null, $this->auditables($modelo->getAttributes(), $modelo));
    }

    /**
     * El alta y la baja guardan la fotografía; la modificación, sólo el diff.
     *
     * Si no cambió nada auditable —un `touch`, o un `update` que sólo movió
     * `updated_at`— no se escribe evento: una traza llena de filas vacías es más
     * difícil de leer que una traza corta.
     */
    public function actualizado(Model $modelo): void
    {
        $cambiadas = array_diff(array_keys($modelo->getChanges()), self::IGNORADOS);

        if ($cambiadas === []) {
            return;
        }

        $anterior = [];
        $nuevo = [];
        $originales = $modelo->getOriginal();
        $actuales = $modelo->getAttributes();

        foreach ($cambiadas as $campo) {
            $anterior[$campo] = $originales[$campo] ?? null;
            $nuevo[$campo] = $actuales[$campo] ?? null;
        }

        $this->escribir(
            $modelo,
            AccionAuditada::Actualizado,
            $this->auditables($anterior, $modelo),
            $this->auditables($nuevo, $modelo),
        );
    }

    public function eliminado(Model $modelo): void
    {
        $this->escribir($modelo, AccionAuditada::Eliminado, $this->auditables($modelo->getAttributes(), $modelo), null);
    }

    /**
     * @param  ?array<string, mixed>  $anterior
     * @param  ?array<string, mixed>  $nuevo
     */
    private function escribir(Model $modelo, AccionAuditada $accion, ?array $anterior, ?array $nuevo): void
    {
        $organizacionId = $modelo->getAttribute('organizacion_id');

        if ($organizacionId === null) {
            return;
        }

        EventoAuditoria::query()->create([
            // La del modelo, no la del contexto: en modo mantenimiento pueden no
            // ser la misma, y el evento pertenece a quien es dueño del dato.
            'organizacion_id' => $organizacionId,
            // Nulo cuando escribe un comando o un recálculo, no una persona.
            'usuario_id' => Auth::id(),
            // Por nombre corto de clase: el evento tiene que seguir siendo
            // legible cuando el espacio de nombres se mueva de sitio.
            'entidad' => class_basename($modelo),
            'entidad_id' => $modelo->getKey(),
            'accion' => $accion->value,
            'valor_anterior' => $anterior,
            'valor_nuevo' => $nuevo,
            'ip' => Request::ip(),
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Quita lo ignorado y lo que el modelo declara oculto.
     *
     * Respetar `$hidden` no es cosmético: es lo que impide que un hash de
     * contraseña o un secreto de segundo factor acabe copiado en el log,
     * precisamente en la tabla que no se puede borrar.
     *
     * @param  array<string, mixed>  $atributos
     * @return array<string, mixed>
     */
    private function auditables(array $atributos, Model $modelo): array
    {
        $ocultos = array_flip([...self::IGNORADOS, ...$modelo->getHidden()]);

        return array_diff_key($atributos, $ocultos);
    }
}
