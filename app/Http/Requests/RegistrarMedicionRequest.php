<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Metrica\Models\Indicador;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * El sellado de una medición manual.
 *
 * **El periodo no llega como dos fechas sueltas**: llega una fecha cualquiera y
 * la periodicidad del indicador decide en qué cubo cae. Dejar que el formulario
 * escriba los dos extremos permitiría sellar «del 3 de marzo al 7 de abril»,
 * que no es ningún trimestre, y la serie tendría puntos que no encajan con
 * ninguno de los demás.
 *
 * El objetivo tampoco llega: lo congela `RegistrarMedicion` desde el indicador,
 * y admitirlo aquí sería dejar que alguien ajustara el listón a la cifra
 * después de conocerla.
 */
class RegistrarMedicionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'valor' => ['required', 'numeric', 'between:-9999999999,9999999999'],
            'numerador' => ['nullable', 'integer', 'min:0'],
            'denominador' => ['nullable', 'integer', 'min:1'],
            'medida_en' => ['nullable', 'date'],
            'nota' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Las dos que la base impone, dichas en castellano.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validador): void {
                $numerador = $this->input('numerador');
                $denominador = $this->input('denominador');

                if ($numerador !== null && $denominador === null) {
                    $validador->errors()->add(
                        'denominador',
                        'Un numerador sin denominador no se puede leer: «43» no dice lo mismo sobre 4 que sobre 307.',
                    );
                }

                /*
                 * No se sella el periodo en curso. Una cifra a medias habría que
                 * corregirla al día siguiente, y la serie contaría un trimestre
                 * que todavía no ha pasado.
                 */
                $indicador = $this->route('indicador');

                if ($indicador instanceof Indicador && $this->date('fecha') !== null) {
                    [$inicio] = $indicador->periodicidad->periodoDe($this->date('fecha'));
                    [$enCurso] = $indicador->periodicidad->periodoDe(now());

                    if ($inicio->equalTo($enCurso)) {
                        $validador->errors()->add(
                            'fecha',
                            'Ese periodo todavía no ha terminado. Se mide cuando cierra, o la cifra habría que corregirla mañana.',
                        );
                    }
                }
            },
        ];
    }
}
