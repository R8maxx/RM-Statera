<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La lista de comprobación que llega del editor.
 *
 * Llega entera, con el orden implícito en la posición. El tope de cincuenta no
 * es técnico: una lista de comprobación más larga que eso es una tarea mal
 * partida, y conviene que el formulario lo diga antes que la base.
 */
class GuardarSubtareasRequest extends FormRequest
{
    /** Cuántos pasos caben en una lista antes de que deje de ser una lista. */
    public const MAXIMO = 50;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pasos' => ['present', 'array', 'max:'.self::MAXIMO],
            // La pertenencia a la tarea no se comprueba aquí: un id que no sea
            // suyo se trata como un paso nuevo, que es lo que hace el dominio.
            'pasos.*.id' => ['nullable', 'integer'],
            'pasos.*.titulo' => ['required', 'string', 'max:255'],
            'pasos.*.hecha' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pasos.max' => 'Una lista de comprobación de más de '.self::MAXIMO.' pasos es una tarea mal partida.',
            'pasos.*.titulo.required' => 'Un paso en blanco deja una casilla que nadie sabe qué marca.',
        ];
    }

    /**
     * @return list<array{id: int|null, titulo: string, hecha: bool}>
     */
    public function pasos(): array
    {
        /** @var list<array<string, mixed>> $pasos */
        $pasos = $this->validated('pasos') ?? [];

        return array_map(
            static fn (array $paso): array => [
                'id' => is_numeric($paso['id'] ?? null) ? (int) $paso['id'] : null,
                'titulo' => (string) ($paso['titulo'] ?? ''),
                'hecha' => filter_var($paso['hecha'] ?? false, FILTER_VALIDATE_BOOL),
            ],
            $pasos,
        );
    }
}
