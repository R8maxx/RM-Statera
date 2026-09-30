<?php

declare(strict_types=1);

namespace App\Domain\Vulnerabilidad\Fuentes;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * El catálogo KEV de CISA: las vulnerabilidades que se sabe que se están
 * explotando.
 *
 * **Pesa más que el CVSS al priorizar**, y por eso se consulta aunque sea de
 * otra agencia: una 7,5 que se explota hoy corre más que una 9,8 que nadie ha
 * visto usar. El catálogo es un único JSON con todas las entradas, así que se
 * descarga entero y se guarda doce horas en caché, reducido a lo que la ficha
 * enseña. **No se pregunta por un CVE**: se busca en local, y CISA no llega a
 * saber cuál interesa.
 */
final class CatalogoKev
{
    private const CACHE = 'vulnerabilidades:kev:catalogo';

    private const HORAS = 12;

    /**
     * @return array{nombre: string, desde: string, ransomware: bool}|null
     *
     * @throws FuenteNoDisponible
     */
    public function buscar(string $cve): ?array
    {
        return $this->catalogo()[strtoupper($cve)] ?? null;
    }

    /**
     * @return array<string, array{nombre: string, desde: string, ransomware: bool}>
     *
     * @throws FuenteNoDisponible
     */
    private function catalogo(): array
    {
        /** @var array<string, array{nombre: string, desde: string, ransomware: bool}>|null $guardado */
        $guardado = Cache::get(self::CACHE);

        if ($guardado !== null) {
            return $guardado;
        }

        $catalogo = $this->descargar();
        Cache::put(self::CACHE, $catalogo, now()->addHours(self::HORAS));

        return $catalogo;
    }

    /**
     * @return array<string, array{nombre: string, desde: string, ransomware: bool}>
     *
     * @throws FuenteNoDisponible
     */
    private function descargar(): array
    {
        try {
            $respuesta = Http::timeout((int) config('services.kev.timeout'))
                ->acceptJson()
                ->get((string) config('services.kev.url'));
        } catch (ConnectionException $e) {
            throw new FuenteNoDisponible('El catálogo KEV de CISA no contesta.', previous: $e);
        }

        $entradas = $respuesta->successful() ? $respuesta->json('vulnerabilities') : null;

        if (! is_array($entradas)) {
            throw new FuenteNoDisponible('El catálogo KEV de CISA no ha devuelto un catálogo.');
        }

        $catalogo = [];

        foreach ($entradas as $entrada) {
            if (! is_array($entrada) || ! is_string($entrada['cveID'] ?? null) || ! is_string($entrada['dateAdded'] ?? null)) {
                continue;
            }

            $catalogo[strtoupper($entrada['cveID'])] = [
                'nombre' => is_string($entrada['vulnerabilityName'] ?? null) ? $entrada['vulnerabilityName'] : $entrada['cveID'],
                'desde' => $entrada['dateAdded'],
                'ransomware' => ($entrada['knownRansomwareCampaignUse'] ?? null) === 'Known',
            ];
        }

        return $catalogo;
    }
}
