<?php

declare(strict_types=1);

namespace App\Http\Resources\Definicion;

use App\Http\Resources\Enums\MetodoAccion;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Un botón: de fila, masivo o general.
 *
 * `url` puede llevar el marcador `{id}`, que el `DataTable` sustituye por el
 * identificador de la fila. El `permiso` decide si la acción se serializa
 * siquiera; la autorización de verdad sigue estando en el servidor, en la ruta
 * y en la política, nunca aquí.
 */
#[TypeScript]
final class Accion
{
    public ?string $icono = null;

    public ?string $confirmacion = null;

    public bool $destructiva = false;

    public bool $secundaria = false;

    private ?string $permiso = null;

    public function __construct(
        public readonly string $clave,
        public readonly string $etiqueta,
        public readonly string $url,
        public readonly MetodoAccion $metodo = MetodoAccion::Get,
    ) {}

    public static function ver(string $url): self
    {
        return (new self('ver', 'Ver', $url))->icono('Eye');
    }

    public static function editar(string $url): self
    {
        return (new self('editar', 'Editar', $url))->icono('Pencil');
    }

    public static function eliminar(string $url, string $confirmacion): self
    {
        return (new self('eliminar', 'Eliminar', $url, MetodoAccion::Delete))
            ->icono('Trash2')
            ->confirmar($confirmacion)
            ->destructiva();
    }

    /** Nombre del icono de lucide, sin el sufijo `Icon`. */
    public function icono(string $icono): self
    {
        $this->icono = $icono;

        return $this;
    }

    public function confirmar(string $mensaje): self
    {
        $this->confirmacion = $mensaje;

        return $this;
    }

    public function destructiva(): self
    {
        $this->destructiva = true;

        return $this;
    }

    /**
     * Que no es la acción que se espera de esta pantalla.
     *
     * Lo declara el recurso y no la posición del array: `DataTable` pintaba
     * TODA acción general como primario lleno, así que «Nuevo activo» y
     * «Etiquetas QR» salían iguales, y «Nuevo riesgo» y «Metodología» también.
     * Dos botones de color lleno a la vez y no manda ninguno — es el mismo
     * argumento que ya se escribió al introducir «Emitir versión», y DESIGN.md
     * §9 lo pide explícitamente.
     *
     * Quién es secundaria no lo puede deducir el cliente: imprimir las etiquetas
     * de todo el parque y abrir los criterios de aceptación de la dirección son
     * acciones legítimas y perfectamente normales, sólo que no son a lo que va
     * el noventa y cinco por ciento de quien abre esa tabla. Eso lo sabe el
     * recurso.
     */
    public function secundaria(): self
    {
        $this->secundaria = true;

        return $this;
    }

    public function permiso(string $permiso): self
    {
        $this->permiso = $permiso;

        return $this;
    }

    public function permisoRequerido(): ?string
    {
        return $this->permiso;
    }
}
