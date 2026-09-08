<?php

declare(strict_types=1);

namespace App\Domain\Catalogo\Importador;

use App\Domain\Catalogo\Excepciones\CatalogoInvalido;
use App\Domain\Catalogo\Models\AplicabilidadEns;
use App\Domain\Catalogo\Models\Mapeo;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\PerfilCumplimiento;
use App\Domain\Catalogo\Models\Refuerzo;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Carga el catálogo normativo desde los YAML versionados en `catalogo/`.
 *
 * Tres propiedades que no son opcionales:
 *
 * - **Idempotente.** Importar dos veces el mismo fichero no produce cambios.
 * - **Empareja por clave natural** `(marco.codigo, requisito.codigo)`, nunca por
 *   id. Los ids son de la base, los códigos son del marco.
 * - **No borra.** Un requisito que desaparece de una revisión se marca como no
 *   vigente, porque puede haber implantaciones colgando de él y el auditor
 *   preguntará por ellas.
 */
final class ImportadorCatalogo
{
    private const TIPOS_REQUISITO = ['clausula', 'control', 'medida'];

    private const CATEGORIAS = ['basica', 'media', 'alta'];

    private const DIMENSIONES = ['C', 'I', 'D', 'A', 'T'];

    /**
     * Ficheros de un directorio en orden seguro de importación: primero los que
     * definen marcos, después los de mapeos, que necesitan que ambos extremos
     * existan.
     *
     * @return list<string>
     */
    public function ficherosDe(string $directorio): array
    {
        $ficheros = glob(rtrim($directorio, '/').'/*.{yaml,yml}', GLOB_BRACE) ?: [];
        sort($ficheros);

        $marcos = [];
        $mapeos = [];

        foreach ($ficheros as $fichero) {
            if ($this->tipoDe($fichero) === 'mapeos') {
                $mapeos[] = $fichero;

                continue;
            }

            $marcos[] = $fichero;
        }

        return [...$marcos, ...$mapeos];
    }

    public function importar(string $fichero, bool $simulacion = false): ResultadoImportacion
    {
        $documento = $this->leer($fichero);

        DB::beginTransaction();

        try {
            $resultado = array_key_exists('mapeos', $documento)
                ? $this->importarMapeos($fichero, $documento, $simulacion)
                : $this->importarMarco($fichero, $documento, $simulacion);

            if ($simulacion) {
                DB::rollBack();
            } else {
                DB::commit();
            }

            return $resultado;
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function leer(string $fichero): array
    {
        if (! is_file($fichero)) {
            throw new CatalogoInvalido($fichero, ['El fichero no existe.']);
        }

        try {
            $documento = Yaml::parseFile($fichero);
        } catch (ParseException $e) {
            throw new CatalogoInvalido($fichero, ['YAML malformado: '.$e->getMessage()]);
        }

        if (! is_array($documento)) {
            throw new CatalogoInvalido($fichero, ['El documento no es un mapa YAML.']);
        }

        if (! array_key_exists('marco', $documento) && ! array_key_exists('mapeos', $documento)) {
            throw new CatalogoInvalido($fichero, ['Falta la clave raíz `marco` o `mapeos`.']);
        }

        return $documento;
    }

    private function tipoDe(string $fichero): string
    {
        try {
            $documento = Yaml::parseFile($fichero);
        } catch (ParseException) {
            return 'desconocido';
        }

        return is_array($documento) && array_key_exists('mapeos', $documento) ? 'mapeos' : 'marco';
    }

    /**
     * @param  array<string, mixed>  $documento
     */
    private function importarMarco(string $fichero, array $documento, bool $simulacion): ResultadoImportacion
    {
        $errores = [];

        $datosMarco = is_array($documento['marco'] ?? null) ? $documento['marco'] : [];

        $validador = Validator::make($datosMarco, [
            'codigo' => ['required', 'string', 'max:255'],
            'nombre' => ['required', 'string', 'max:255'],
            'version' => ['required', 'string', 'max:255'],
            'fecha_vigencia' => ['nullable', 'date'],
            'estado' => ['nullable', 'in:vigente,derogado'],
        ]);

        foreach ($validador->errors()->all() as $error) {
            $errores[] = 'marco: '.$error;
        }

        $requisitosCrudos = $documento['requisitos'] ?? null;

        if (! is_array($requisitosCrudos) || $requisitosCrudos === []) {
            $errores[] = 'requisitos: se esperaba una lista no vacía.';
            $requisitosCrudos = [];
        }

        /** @var list<array<string, mixed>> $planos */
        $planos = [];
        $this->aplanar($requisitosCrudos, null, $planos, $errores, 'requisitos');

        $vistos = [];

        foreach ($planos as $plano) {
            $codigo = $plano['codigo'];

            if (isset($vistos[$codigo])) {
                $errores[] = "requisitos: código duplicado [{$codigo}].";
            }

            $vistos[$codigo] = true;
        }

        $perfilesCrudos = $documento['perfiles'] ?? [];

        if (! is_array($perfilesCrudos)) {
            $errores[] = 'perfiles: se esperaba una lista.';
            $perfilesCrudos = [];
        }

        if ($errores !== []) {
            throw new CatalogoInvalido($fichero, $errores);
        }

        $resultado = new ResultadoImportacion($fichero, 'marco', $simulacion, (string) $datosMarco['codigo']);

        $marco = Marco::query()->updateOrCreate(
            ['codigo' => $datosMarco['codigo']],
            [
                'nombre' => $datosMarco['nombre'],
                'version' => $datosMarco['version'],
                'fecha_vigencia' => $datosMarco['fecha_vigencia'] ?? null,
                'estado' => $datosMarco['estado'] ?? 'vigente',
            ],
        );

        /** @var array<string, Requisito> $existentes */
        $existentes = $marco->requisitos()->get()->keyBy('codigo')->all();

        /** @var array<string, int> $idsPorCodigo */
        $idsPorCodigo = [];

        foreach ($planos as $plano) {
            $codigo = $plano['codigo'];
            $huella = $this->huellaDe($plano);
            $parentId = $plano['parent'] === null ? null : ($idsPorCodigo[$plano['parent']] ?? null);

            $atributos = [
                'marco_id' => $marco->id,
                'codigo' => $codigo,
                'tipo' => $plano['tipo'],
                'parent_id' => $parentId,
                'titulo' => $plano['titulo'],
                'descripcion' => $plano['descripcion'],
                'orden' => $plano['orden'],
                'atributos' => $plano['atributos'],
                'huella' => $huella,
            ];

            $existente = $existentes[$codigo] ?? null;

            if ($existente === null) {
                $requisito = Requisito::query()->create([...$atributos, 'vigente' => true, 'retirado_en' => null]);
                $resultado->nuevos[] = $codigo;
            } else {
                if ($existente->huella === $huella && $existente->vigente) {
                    $resultado->sinCambios++;
                } else {
                    $cambios = $this->cambiosEntre($existente, $atributos);

                    if (! $existente->vigente) {
                        $resultado->reactivados[] = $codigo;
                    }

                    if ($cambios !== [] || $existente->huella !== $huella) {
                        $resultado->modificados[] = [
                            'codigo' => $codigo,
                            'cambios' => $cambios === [] ? ['refuerzos/aplicabilidad'] : $cambios,
                        ];
                    }
                }

                $existente->fill([...$atributos, 'vigente' => true, 'retirado_en' => null])->save();
                $requisito = $existente;
            }

            $idsPorCodigo[$codigo] = $requisito->id;

            $resultado->refuerzos += $this->sincronizarRefuerzos($requisito, $plano['refuerzos']);
            $resultado->celdasAplicabilidad += $this->sincronizarAplicabilidad($requisito, $plano['aplicabilidad']);
        }

        $desaparecidos = array_diff(array_keys($existentes), array_keys($idsPorCodigo));

        foreach ($desaparecidos as $codigo) {
            $requisito = $existentes[$codigo];

            if (! $requisito->vigente) {
                continue;
            }

            // No se borra: se marca. Puede haber implantaciones colgando.
            $requisito->update(['vigente' => false, 'retirado_en' => Carbon::now()]);
            $resultado->retirados[] = $codigo;
        }

        $resultado->perfiles = $this->sincronizarPerfiles($marco, $perfilesCrudos, $idsPorCodigo, $fichero);
        $resultado->implantacionesAfectadas = $this->contarImplantacionesAfectadas($resultado->retirados, $existentes);

        return $resultado;
    }

    /**
     * Convierte el árbol anidado del YAML en una lista en preorden, de forma que
     * todo padre aparece antes que sus hijos y su id ya está resuelto cuando se
     * procesa el hijo.
     *
     * @param  array<int|string, mixed>  $nodos
     * @param  list<array<string, mixed>>  $destino
     * @param  list<string>  $errores
     */
    private function aplanar(array $nodos, ?string $padre, array &$destino, array &$errores, string $ruta): void
    {
        foreach (array_values($nodos) as $indice => $nodo) {
            $donde = "{$ruta}[{$indice}]";

            if (! is_array($nodo)) {
                $errores[] = "{$donde}: se esperaba un mapa.";

                continue;
            }

            $validador = Validator::make($nodo, [
                'codigo' => ['required', 'string', 'max:255'],
                'tipo' => ['required', 'string', 'in:'.implode(',', self::TIPOS_REQUISITO)],
                'titulo' => ['required', 'string'],
                'descripcion' => ['nullable', 'string'],
                'orden' => ['nullable', 'integer', 'min:0'],
                'atributos' => ['nullable', 'array'],
                'refuerzos' => ['nullable', 'array'],
                'aplicabilidad' => ['nullable', 'array'],
                'hijos' => ['nullable', 'array'],
            ]);

            if ($validador->fails()) {
                foreach ($validador->errors()->all() as $error) {
                    $errores[] = "{$donde}: {$error}";
                }

                continue;
            }

            $aplicabilidad = $this->normalizarAplicabilidad($nodo['aplicabilidad'] ?? [], $donde, $errores);
            $refuerzos = $this->normalizarRefuerzos($nodo['refuerzos'] ?? [], $donde, $errores);

            $destino[] = [
                'codigo' => (string) $nodo['codigo'],
                'tipo' => (string) $nodo['tipo'],
                'titulo' => (string) $nodo['titulo'],
                'descripcion' => isset($nodo['descripcion']) ? (string) $nodo['descripcion'] : null,
                'orden' => isset($nodo['orden']) ? (int) $nodo['orden'] : $indice + 1,
                'atributos' => $this->ordenarProfundo(is_array($nodo['atributos'] ?? null) ? $nodo['atributos'] : []),
                'parent' => $padre,
                'refuerzos' => $refuerzos,
                'aplicabilidad' => $aplicabilidad,
            ];

            if (is_array($nodo['hijos'] ?? null) && $nodo['hijos'] !== []) {
                $this->aplanar($nodo['hijos'], (string) $nodo['codigo'], $destino, $errores, "{$donde}.hijos");
            }
        }
    }

    /**
     * @param  list<string>  $errores
     * @return array<string, array{exigencia: string, dimension: ?string}>
     */
    private function normalizarAplicabilidad(mixed $crudo, string $donde, array &$errores): array
    {
        if (! is_array($crudo)) {
            $errores[] = "{$donde}.aplicabilidad: se esperaba un mapa por categoría.";

            return [];
        }

        $normalizada = [];

        foreach ($crudo as $categoria => $valor) {
            if (! in_array($categoria, self::CATEGORIAS, true)) {
                $errores[] = "{$donde}.aplicabilidad: categoría no reconocida [{$categoria}].";

                continue;
            }

            $exigencia = is_array($valor) ? ($valor['exigencia'] ?? null) : $valor;
            $dimension = is_array($valor) ? ($valor['dimension'] ?? null) : null;

            if (! is_string($exigencia) || ($exigencia !== 'no_aplica' && $exigencia !== 'aplica' && preg_match('/^R[0-9]+$/', $exigencia) !== 1)) {
                $errores[] = "{$donde}.aplicabilidad.{$categoria}: exigencia no reconocida. Se esperaba no_aplica, aplica o R<n>.";

                continue;
            }

            if ($dimension !== null && ! in_array($dimension, self::DIMENSIONES, true)) {
                $errores[] = "{$donde}.aplicabilidad.{$categoria}: dimensión moduladora no reconocida [{$dimension}].";

                continue;
            }

            $normalizada[$categoria] = ['exigencia' => $exigencia, 'dimension' => $dimension];
        }

        ksort($normalizada);

        return $normalizada;
    }

    /**
     * @param  list<string>  $errores
     * @return array<string, string>
     */
    private function normalizarRefuerzos(mixed $crudo, string $donde, array &$errores): array
    {
        if (! is_array($crudo)) {
            $errores[] = "{$donde}.refuerzos: se esperaba una lista.";

            return [];
        }

        $normalizados = [];

        foreach (array_values($crudo) as $refuerzo) {
            if (! is_array($refuerzo) || ! isset($refuerzo['codigo'], $refuerzo['descripcion'])) {
                $errores[] = "{$donde}.refuerzos: cada refuerzo necesita `codigo` y `descripcion`.";

                continue;
            }

            $codigo = (string) $refuerzo['codigo'];

            if (preg_match('/^R[0-9]+$/', $codigo) !== 1) {
                $errores[] = "{$donde}.refuerzos: código no reconocido [{$codigo}]. Se esperaba R<n>.";

                continue;
            }

            $normalizados[$codigo] = (string) $refuerzo['descripcion'];
        }

        ksort($normalizados);

        return $normalizados;
    }

    /**
     * Huella del contenido importado. Cubre también refuerzos y matriz de
     * aplicabilidad: cambiar una celda de la matriz es cambiar el requisito a
     * efectos de diff, porque cambia lo que se le exige a la organización.
     *
     * @param  array<string, mixed>  $plano
     */
    private function huellaDe(array $plano): string
    {
        return hash('sha256', (string) json_encode([
            $plano['codigo'],
            $plano['tipo'],
            $plano['titulo'],
            $plano['descripcion'],
            $plano['orden'],
            $plano['atributos'],
            $plano['parent'],
            $plano['refuerzos'],
            $plano['aplicabilidad'],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $nuevos
     * @return list<string>
     */
    private function cambiosEntre(Requisito $existente, array $nuevos): array
    {
        $cambios = [];

        foreach (['tipo', 'titulo', 'descripcion', 'orden', 'parent_id'] as $campo) {
            $anterior = $campo === 'tipo' ? $existente->tipo->value : $existente->{$campo};

            if ($anterior != $nuevos[$campo]) {
                $cambios[] = $campo;
            }
        }

        if ($this->ordenarProfundo($existente->atributos ?? []) != $nuevos['atributos']) {
            $cambios[] = 'atributos';
        }

        return $cambios;
    }

    /**
     * @param  array<string, string>  $refuerzos
     */
    private function sincronizarRefuerzos(Requisito $requisito, array $refuerzos): int
    {
        $requisito->refuerzos()->whereNotIn('codigo', array_keys($refuerzos))->delete();

        $orden = 0;

        foreach ($refuerzos as $codigo => $descripcion) {
            Refuerzo::query()->updateOrCreate(
                ['requisito_id' => $requisito->id, 'codigo' => $codigo],
                ['descripcion' => $descripcion, 'orden' => ++$orden],
            );
        }

        return count($refuerzos);
    }

    /**
     * @param  array<string, array{exigencia: string, dimension: ?string}>  $aplicabilidad
     */
    private function sincronizarAplicabilidad(Requisito $requisito, array $aplicabilidad): int
    {
        $requisito->aplicabilidad()->whereNotIn('categoria', array_keys($aplicabilidad))->delete();

        foreach ($aplicabilidad as $categoria => $celda) {
            AplicabilidadEns::query()->updateOrCreate(
                ['requisito_id' => $requisito->id, 'categoria' => $categoria],
                ['exigencia' => $celda['exigencia'], 'dimension_moduladora' => $celda['dimension']],
            );
        }

        return count($aplicabilidad);
    }

    /**
     * @param  array<int|string, mixed>  $perfiles
     * @param  array<string, int>  $idsPorCodigo
     */
    private function sincronizarPerfiles(Marco $marco, array $perfiles, array $idsPorCodigo, string $fichero): int
    {
        $errores = [];
        $importados = 0;

        foreach (array_values($perfiles) as $indice => $perfil) {
            $donde = "perfiles[{$indice}]";

            if (! is_array($perfil) || ! isset($perfil['codigo'], $perfil['nombre'])) {
                $errores[] = "{$donde}: cada perfil necesita `codigo` y `nombre`.";

                continue;
            }

            $modelo = PerfilCumplimiento::query()->updateOrCreate(
                ['codigo' => (string) $perfil['codigo']],
                [
                    'marco_id' => $marco->id,
                    'nombre' => (string) $perfil['nombre'],
                    'descripcion' => isset($perfil['descripcion']) ? (string) $perfil['descripcion'] : null,
                    'referencia' => isset($perfil['referencia']) ? (string) $perfil['referencia'] : null,
                ],
            );

            $vinculos = [];

            foreach (array_values(is_array($perfil['requisitos'] ?? null) ? $perfil['requisitos'] : []) as $entrada) {
                $codigo = is_array($entrada) ? ($entrada['codigo'] ?? null) : $entrada;

                if (! is_string($codigo) || ! isset($idsPorCodigo[$codigo])) {
                    $errores[] = "{$donde}: el requisito [".(is_string($codigo) ? $codigo : '?').'] no existe en este marco.';

                    continue;
                }

                $vinculos[$idsPorCodigo[$codigo]] = [
                    'exigencia' => is_array($entrada) && isset($entrada['exigencia']) ? (string) $entrada['exigencia'] : null,
                ];
            }

            $modelo->requisitos()->sync($vinculos);
            $importados++;
        }

        if ($errores !== []) {
            throw new CatalogoInvalido($fichero, $errores);
        }

        return $importados;
    }

    /**
     * @param  array<string, mixed>  $documento
     */
    private function importarMapeos(string $fichero, array $documento, bool $simulacion): ResultadoImportacion
    {
        $resultado = new ResultadoImportacion($fichero, 'mapeos', $simulacion);

        $mapeos = $documento['mapeos'];

        if (! is_array($mapeos)) {
            throw new CatalogoInvalido($fichero, ['mapeos: se esperaba una lista.']);
        }

        $errores = [];
        $pendientes = [];

        foreach (array_values($mapeos) as $indice => $mapeo) {
            $donde = "mapeos[{$indice}]";

            $validador = Validator::make(is_array($mapeo) ? $mapeo : [], [
                'origen.marco' => ['required', 'string'],
                'origen.codigo' => ['required', 'string'],
                'destino.marco' => ['required', 'string'],
                'destino.codigo' => ['required', 'string'],
                'tipo' => ['required', 'in:equivalente,parcial,relacionado'],
                'nota' => ['nullable', 'string'],
            ]);

            if ($validador->fails()) {
                foreach ($validador->errors()->all() as $error) {
                    $errores[] = "{$donde}: {$error}";
                }

                continue;
            }

            $origen = $this->requisitoPorCodigo($mapeo['origen']['marco'], $mapeo['origen']['codigo']);
            $destino = $this->requisitoPorCodigo($mapeo['destino']['marco'], $mapeo['destino']['codigo']);

            if ($origen === null) {
                $errores[] = "{$donde}: no existe el requisito origen {$mapeo['origen']['marco']} / {$mapeo['origen']['codigo']}.";
            }

            if ($destino === null) {
                $errores[] = "{$donde}: no existe el requisito destino {$mapeo['destino']['marco']} / {$mapeo['destino']['codigo']}.";
            }

            if ($origen === null || $destino === null) {
                continue;
            }

            $pendientes[] = [$origen, $destino, (string) $mapeo['tipo'], isset($mapeo['nota']) ? (string) $mapeo['nota'] : null];
        }

        if ($errores !== []) {
            throw new CatalogoInvalido($fichero, $errores);
        }

        foreach ($pendientes as [$origenId, $destinoId, $tipo, $nota]) {
            $existente = Mapeo::query()
                ->where('requisito_origen_id', $origenId)
                ->where('requisito_destino_id', $destinoId)
                ->first();

            if ($existente === null) {
                Mapeo::query()->create([
                    'requisito_origen_id' => $origenId,
                    'requisito_destino_id' => $destinoId,
                    'tipo_correspondencia' => $tipo,
                    'nota' => $nota,
                ]);
                $resultado->mapeosNuevos++;

                continue;
            }

            if ($existente->tipo_correspondencia->value !== $tipo || $existente->nota !== $nota) {
                $existente->update(['tipo_correspondencia' => $tipo, 'nota' => $nota]);
                $resultado->mapeosActualizados++;

                continue;
            }

            $resultado->sinCambios++;
        }

        return $resultado;
    }

    private function requisitoPorCodigo(string $codigoMarco, string $codigo): ?int
    {
        return Requisito::query()
            ->delMarco($codigoMarco)
            ->where('codigo', $codigo)
            ->value('id');
    }

    /**
     * Cuántas implantaciones quedarían afectadas por los requisitos retirados.
     *
     * Al importar una revisión de un marco, el comando tiene que decir a qué
     * afecta; no modifica nada en silencio.
     *
     * Cuenta a través de TODAS las organizaciones, así que es el caso legítimo
     * de comando de mantenimiento explícito: sin abrir esa puerta, la política
     * de Row Level Security devolvería cero y el aviso sería mentira.
     *
     * Los ids salen de los requisitos que YA estaban en la base, no del mapa de
     * códigos del fichero: un requisito retirado es precisamente el que ha
     * dejado de aparecer en él.
     *
     * @param  list<string>  $retirados
     * @param  array<string, Requisito>  $existentes
     */
    private function contarImplantacionesAfectadas(array $retirados, array $existentes): int
    {
        if ($retirados === [] || ! Schema::hasTable('implantaciones')) {
            return 0;
        }

        $ids = array_values(array_map(
            static fn (Requisito $requisito): int => $requisito->id,
            array_intersect_key($existentes, array_flip($retirados)),
        ));

        return app(ContextoOrganizacion::class)->comoMantenimiento(
            fn (): int => DB::table('implantaciones')->whereIn('requisito_id', $ids)->count()
        );
    }

    /**
     * Ordena claves de forma recursiva para que la huella no dependa del orden
     * en que estén escritas en el YAML.
     *
     * @param  array<string, mixed>  $valores
     * @return array<string, mixed>
     */
    private function ordenarProfundo(array $valores): array
    {
        ksort($valores);

        foreach ($valores as $clave => $valor) {
            if (is_array($valor)) {
                $valores[$clave] = array_is_list($valor) ? $valor : $this->ordenarProfundo($valor);
            }
        }

        return $valores;
    }
}
