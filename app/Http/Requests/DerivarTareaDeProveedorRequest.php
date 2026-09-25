<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * Abrir una tarea desde un proveedor. Los mismos campos que la de una prueba de
 * continuidad, que ya es la forma común de «tarea que nace de otro registro».
 */
class DerivarTareaDeProveedorRequest extends DerivarTareaDePruebaRequest {}
