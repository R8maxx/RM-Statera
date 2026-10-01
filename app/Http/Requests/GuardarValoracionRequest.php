<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Catalogo\Models\PerfilCumplimiento;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Categorizacion\ValoracionDimensiones;
use App\Domain\Sistema\Models\Sistema;
use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La única fuente de verdad de la validación de una valoración.
 *
 * Las cinco dimensiones son obligatorias. No valorar una y valorarla `na` son lo
 * mismo a efectos del Anexo I, pero dejar el campo vacío no deja constancia de
 * que alguien lo haya decidido: la categoría del sistema —y con ella todo lo que
 * se le exige— sale de aquí.
 *
 * La categoría NO se acepta como entrada. Se deriva (invariante 4).
 *
 * **El perfil de cumplimiento es la otra entrada del motor**, y viaja con la
 * valoración para que el diff enseñe las dos a la vez. Sólo uno del marco del
 * sistema; que lleve medidas lo comprueba además el dominio.
 */
class GuardarValoracionRequest extends FormRequest
{
    use NormalizaSeleccionVacia;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reglas = [];

        foreach (Dimension::cases() as $dimension) {
            $reglas[$this->claveNivel($dimension)] = ['required', Rule::enum(NivelDimension::class)];
            $reglas[$this->claveJustificacion($dimension)] = ['nullable', 'string', 'max:2000'];
        }

        $sistema = $this->route('sistema');

        $reglas['perfil_id'] = [
            'nullable',
            'integer',
            Rule::exists('perfiles_cumplimiento', 'id')
                ->where('marco_id', $sistema instanceof Sistema ? $sistema->marco_id : 0),
        ];

        return $reglas;
    }

    /**
     * El perfil pedido: `false` si el formulario no lo trae —no se toca el que
     * tenga—, `null` si se quita, o el modelo.
     */
    public function perfil(): PerfilCumplimiento|false|null
    {
        if (! $this->has('perfil_id')) {
            return false;
        }

        $id = $this->validated('perfil_id');

        return $id === null ? null : PerfilCumplimiento::query()->findOrFail($id);
    }

    /** @return list<string> */
    protected function seleccionesOpcionales(): array
    {
        return ['perfil_id'];
    }

    /** La valoración validada, como value object: la entrada del motor. */
    public function valoracion(): ValoracionDimensiones
    {
        $niveles = [];

        foreach (Dimension::cases() as $dimension) {
            $niveles[$dimension->value] = $this->string($this->claveNivel($dimension))->toString();
        }

        return ValoracionDimensiones::desdeArray($niveles);
    }

    /** @return array<string, ?string> Indexadas por código de dimensión. */
    public function justificaciones(): array
    {
        $justificaciones = [];

        foreach (Dimension::cases() as $dimension) {
            $texto = trim((string) $this->input($this->claveJustificacion($dimension), ''));

            $justificaciones[$dimension->value] = $texto === '' ? null : $texto;
        }

        return $justificaciones;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $atributos = [];

        foreach (Dimension::cases() as $dimension) {
            $nombre = mb_strtolower($dimension->nombre());

            $atributos[$this->claveNivel($dimension)] = "nivel de {$nombre}";
            $atributos[$this->claveJustificacion($dimension)] = "justificación de {$nombre}";
        }

        $atributos['perfil_id'] = 'perfil de cumplimiento';

        return $atributos;
    }

    /**
     * Claves planas y no anidadas (`nivel_C`, no `dimensiones[C][nivel]`) para
     * que el nombre del control y la clave del error coincidan: es lo que usa el
     * formulario para llevar el foco al campo que falla.
     */
    private function claveNivel(Dimension $dimension): string
    {
        return "nivel_{$dimension->value}";
    }

    private function claveJustificacion(Dimension $dimension): string
    {
        return "justificacion_{$dimension->value}";
    }
}
