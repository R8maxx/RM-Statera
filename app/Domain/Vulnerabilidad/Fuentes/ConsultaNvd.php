<?php

declare(strict_types=1);

namespace App\Domain\Vulnerabilidad\Fuentes;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Lo que NVD (NIST) sabe de un CVE, reducido a lo que rellena el formulario.
 *
 * **Sale sólo el identificador.** La respuesta se guarda una hora por CVE: el
 * límite sin clave son unas cinco consultas cada treinta segundos, y pulsar dos
 * veces el botón no tiene por qué gastar dos.
 *
 * Tres decisiones sobre qué se toma de la respuesta:
 *
 * - **La descripción en castellano si NVD la tiene**, que para muchos CVE la
 *   tiene; si no, en inglés, y el formulario lo dice.
 * - **El CVSS de la v3.1, y si no de la v3.0**, prefiriendo la puntuación
 *   primaria —la de NVD— a la de quien lo publicó. De la v4 sólo se trae el
 *   vector: la severidad sigue los tramos de la v3.1, y una puntuación de la v4
 *   metida ahí saldría con otra severidad que la de su propia escala.
 * - **Un CWE concreto**, no los comodines `NVD-CWE-Other` y `NVD-CWE-noinfo`, y
 *   **ocho referencias como mucho**, sólo `http` o `https` —se pintan como
 *   enlaces en la ficha— y las útiles primero.
 */
final class ConsultaNvd
{
    private const REFERENCIAS = 8;

    /**
     * @return array{cve: string, descripcion: string|null, idioma: string|null, cvssPuntuacion: string|null, cvssVector: string|null, cvssVersion: string|null, cwe: string|null, referencias: list<string>, publicada: string|null, rechazada: bool}|null
     *
     * @throws FuenteNoDisponible
     */
    public function consultar(string $cve): ?array
    {
        $cve = strtoupper($cve);
        $clave = "vulnerabilidades:nvd:{$cve}";

        /** @var array{cve: string, descripcion: string|null, idioma: string|null, cvssPuntuacion: string|null, cvssVector: string|null, cvssVersion: string|null, cwe: string|null, referencias: list<string>, publicada: string|null, rechazada: bool}|false|null $guardado */
        $guardado = Cache::get($clave);

        if ($guardado !== null) {
            return $guardado === false ? null : $guardado;
        }

        $datos = $this->pedir($cve);
        Cache::put($clave, $datos ?? false, now()->addHour());

        return $datos;
    }

    /**
     * @return array{cve: string, descripcion: string|null, idioma: string|null, cvssPuntuacion: string|null, cvssVector: string|null, cvssVersion: string|null, cwe: string|null, referencias: list<string>, publicada: string|null, rechazada: bool}|null
     *
     * @throws FuenteNoDisponible
     */
    private function pedir(string $cve): ?array
    {
        $clave = config('services.nvd.clave');

        try {
            $respuesta = Http::timeout((int) config('services.nvd.timeout'))
                ->acceptJson()
                ->withHeaders(is_string($clave) && $clave !== '' ? ['apiKey' => $clave] : [])
                ->get((string) config('services.nvd.url'), ['cveId' => $cve]);
        } catch (ConnectionException $e) {
            throw new FuenteNoDisponible('NVD no contesta.', previous: $e);
        }

        // NVD contesta 404 a un identificador que no conoce, y 200 con cero resultados a otros.
        if ($respuesta->notFound()) {
            return null;
        }

        if (! $respuesta->successful()) {
            throw new FuenteNoDisponible($respuesta->status() === 403 || $respuesta->status() === 429
                ? 'NVD ha limitado las consultas. Prueba dentro de un minuto.'
                : 'NVD no ha podido contestar.');
        }

        $registro = $respuesta->json('vulnerabilities.0.cve');

        if (! is_array($registro) || strtoupper((string) ($registro['id'] ?? '')) !== $cve) {
            return null;
        }

        [$descripcion, $idioma] = $this->descripcion($registro['descriptions'] ?? []);
        [$puntuacion, $vector, $version] = $this->cvss(is_array($registro['metrics'] ?? null) ? $registro['metrics'] : []);

        return [
            'cve' => $cve,
            'descripcion' => $descripcion,
            'idioma' => $idioma,
            'cvssPuntuacion' => $puntuacion,
            'cvssVector' => $vector,
            'cvssVersion' => $version,
            'cwe' => $this->cwe($registro['weaknesses'] ?? []),
            'referencias' => $this->referencias($registro['references'] ?? []),
            'publicada' => is_string($registro['published'] ?? null) ? substr($registro['published'], 0, 10) : null,
            'rechazada' => ($registro['vulnStatus'] ?? null) === 'Rejected',
        ];
    }

    /** @return array{0: string|null, 1: string|null} */
    private function descripcion(mixed $descripciones): array
    {
        $porIdioma = [];

        foreach (is_array($descripciones) ? $descripciones : [] as $una) {
            if (is_array($una) && is_string($una['lang'] ?? null) && is_string($una['value'] ?? null) && trim($una['value']) !== '') {
                $porIdioma[$una['lang']] ??= trim($una['value']);
            }
        }

        foreach (['es', 'en'] as $idioma) {
            if (isset($porIdioma[$idioma])) {
                return [$porIdioma[$idioma], $idioma];
            }
        }

        return [null, null];
    }

    /**
     * @param  array<mixed>  $metricas
     * @return array{0: string|null, 1: string|null, 2: string|null}
     */
    private function cvss(array $metricas): array
    {
        foreach (['cvssMetricV31', 'cvssMetricV30'] as $clave) {
            $elegida = $this->primaria($metricas[$clave] ?? []);

            if ($elegida !== null && is_numeric($elegida['baseScore'] ?? null) && is_string($elegida['vectorString'] ?? null)) {
                return [number_format((float) $elegida['baseScore'], 1, '.', ''), $elegida['vectorString'], (string) ($elegida['version'] ?? '3')];
            }
        }

        $v4 = $this->primaria($metricas['cvssMetricV40'] ?? []);

        return is_string($v4['vectorString'] ?? null) ? [null, $v4['vectorString'], '4.0'] : [null, null, null];
    }

    /** @return array<mixed>|null El `cvssData` de la métrica primaria, o de la primera. */
    private function primaria(mixed $metricas): ?array
    {
        $candidatas = array_values(array_filter(
            is_array($metricas) ? $metricas : [],
            static fn (mixed $una): bool => is_array($una) && is_array($una['cvssData'] ?? null),
        ));

        if ($candidatas === []) {
            return null;
        }

        $primarias = array_values(array_filter($candidatas, static fn (array $una): bool => ($una['type'] ?? null) === 'Primary'));

        return ($primarias[0] ?? $candidatas[0])['cvssData'];
    }

    private function cwe(mixed $debilidades): ?string
    {
        $encontradas = [];

        foreach (is_array($debilidades) ? $debilidades : [] as $debilidad) {
            if (! is_array($debilidad)) {
                continue;
            }

            foreach (is_array($debilidad['description'] ?? null) ? $debilidad['description'] : [] as $una) {
                $valor = is_array($una) && is_string($una['value'] ?? null) ? $una['value'] : '';

                if (preg_match('/^CWE-\d+$/', $valor) === 1) {
                    $encontradas[] = [($debilidad['type'] ?? null) === 'Primary' ? 0 : 1, $valor];
                }
            }
        }

        usort($encontradas, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return $encontradas[0][1] ?? null;
    }

    /**
     * Ocho, y las que sirven primero. NVD las lista por orden de alta, y en un CVE
     * con eco eso son ocho erratas de la misma distribución: se ordenan por sus
     * etiquetas —aviso del fabricante, parche, mitigación— y en la primera vuelta
     * se toma una por sitio.
     *
     * @return list<string>
     */
    private function referencias(mixed $referencias): array
    {
        $candidatas = [];

        foreach (is_array($referencias) ? $referencias : [] as $orden => $una) {
            $url = is_array($una) && is_string($una['url'] ?? null) ? trim($una['url']) : '';

            if (isset($candidatas[$url]) || preg_match('#^https?://#i', $url) !== 1 || strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
                continue;
            }

            $etiquetas = is_array($una['tags'] ?? null) ? $una['tags'] : [];
            $utiles = array_intersect($etiquetas, ['Vendor Advisory', 'Patch', 'Mitigation']);

            $candidatas[$url] = [
                'url' => $url,
                'peso' => $utiles === [] ? 1 : 0,
                'orden' => (int) $orden,
                'sitio' => strtolower((string) parse_url($url, PHP_URL_HOST)),
            ];
        }

        usort($candidatas, static fn (array $a, array $b): int => [$a['peso'], $a['orden']] <=> [$b['peso'], $b['orden']]);

        $elegidas = [];
        $sitios = [];

        foreach ($candidatas as $candidata) {
            if (! isset($sitios[$candidata['sitio']])) {
                $sitios[$candidata['sitio']] = true;
                $elegidas[] = $candidata['url'];
            }
        }

        foreach ($candidatas as $candidata) {
            if (! in_array($candidata['url'], $elegidas, true)) {
                $elegidas[] = $candidata['url'];
            }
        }

        return array_slice($elegidas, 0, self::REFERENCIAS);
    }
}
