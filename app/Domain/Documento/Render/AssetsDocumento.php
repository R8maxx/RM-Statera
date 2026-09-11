<?php

declare(strict_types=1);

namespace App\Domain\Documento\Render;

/**
 * Los ficheros que acompañan al HTML: la hoja de estilos y las fuentes.
 *
 * **Van como ficheros del multipart, no incrustados en un `<style>`.** Gotenberg
 * deja todo el multipart en el mismo directorio temporal, así que
 * `href="documento.css"` resuelve a `file:///tmp/.../documento.css` y pasa la
 * allow-list `^file:///tmp/.*` del contenedor. Así el CSS es un CSS de verdad,
 * lo comparten el cuerpo y los parciales, y el HTML que viaja no engorda.
 *
 * **Las fuentes NO viajan como ficheros del multipart: van incrustadas en el CSS
 * como `data:`, y eso no es una preferencia.** Chromium trata las fuentes como
 * recurso sujeto a CORS, y el documento se renderiza desde un `file://`, que es
 * un origen opaco: una `url('instrument-sans-400.woff2')` se bloquea **en
 * silencio**, sin error de carga que `failOnResourceLoadingFailed()` pueda
 * cazar, y el PDF sale con Arial y Noto Sans en vez de con la tipografía de
 * marca. Se comprobó mirando los `/BaseFont` del PDF generado, que es la única
 * forma de verlo: a simple vista el documento parece correcto.
 *
 * Un `data:` no se descarga, así que no hay origen que comparar ni petición que
 * bloquear. Cuesta un tercio más de tamaño en el CSS —unos 150 kB— y el CSS no
 * sale del contenedor.
 *
 * **Las fuentes siguen siendo opcionales.** PDF/A-3b exige toda fuente usada
 * embebida, y Chromium embebe un subconjunto de lo que use; si el fichero no
 * está, sale la genérica del contenedor. El `@font-face` se genera desde lo que
 * hay en el directorio, así que dejar los `.woff2` dentro basta para que el
 * documento pase a usarlos, sin tocar código.
 *
 * Las fuentes viven en `resources/fonts/` y son **las mismas** que carga la
 * interfaz: un solo juego de ficheros, dos consumidores. PHP lee el `.woff2` en
 * crudo para meterlo en el multipart y Vite emite su propia copia con hash para
 * el navegador. Lo que NO puede hacer el PDF es apuntar a la copia de
 * `public/build/assets/`: allí el nombre lleva hash de contenido y cambia en
 * cada `npm run build`, así que la generación se rompería en un despliegue.
 */
final class AssetsDocumento
{
    public const HOJA = 'documento.css';

    /**
     * Los pesos que la hoja de estilos usa, con el nombre de fichero esperado.
     *
     * @var array<string, array{familia: string, peso: int, estilo: string}>
     */
    private const FUENTES = [
        'instrument-sans-400.woff2' => ['familia' => 'Instrument Sans', 'peso' => 400, 'estilo' => 'normal'],
        'instrument-sans-500.woff2' => ['familia' => 'Instrument Sans', 'peso' => 500, 'estilo' => 'normal'],
        'instrument-sans-600.woff2' => ['familia' => 'Instrument Sans', 'peso' => 600, 'estilo' => 'normal'],
        'instrument-sans-700.woff2' => ['familia' => 'Instrument Sans', 'peso' => 700, 'estilo' => 'normal'],

        /*
         * Las itálicas entraron con la narrativa editable: el editor ofrece
         * cursiva, y sin cara propia un `<em>` cae a la itálica de OTRA familia
         * y mete una fuente de más en el PDF. Antes de esto ninguna plantilla
         * usaba cursiva justamente por eso.
         */
        'instrument-sans-400-italic.woff2' => ['familia' => 'Instrument Sans', 'peso' => 400, 'estilo' => 'italic'],
        'instrument-sans-600-italic.woff2' => ['familia' => 'Instrument Sans', 'peso' => 600, 'estilo' => 'italic'],

        'jetbrains-mono-400.woff2' => ['familia' => 'JetBrains Mono', 'peso' => 400, 'estilo' => 'normal'],
        'jetbrains-mono-500.woff2' => ['familia' => 'JetBrains Mono', 'peso' => 500, 'estilo' => 'normal'],
    ];

    private readonly string $directorio;

    private readonly string $fuentes;

    public function __construct(?string $directorio = null, ?string $fuentes = null)
    {
        $this->directorio = $directorio ?? resource_path('documentos');
        $this->fuentes = $fuentes ?? resource_path('fonts');
    }

    /**
     * Todo lo que hay que meter en el multipart.
     *
     * @return list<AssetDocumento>
     */
    public function todos(): array
    {
        // Sólo la hoja: las fuentes viajan dentro de ella, en base64.
        return [new AssetDocumento(self::HOJA, $this->hoja())];
    }

    /**
     * La hoja de estilos, con el bloque de `@font-face` que corresponda a las
     * fuentes que realmente viajan.
     */
    public function hoja(): string
    {
        $css = (string) file_get_contents($this->directorio.'/'.self::HOJA);

        return $this->declaracionesDeFuente().$css;
    }

    /** Si el documento va a salir con la tipografía de marca o con la genérica. */
    public function tieneFuentesDeMarca(): bool
    {
        return $this->fuentesPresentes() !== [];
    }

    /**
     * @return array<string, string> Nombre de fichero => contenido.
     */
    private function fuentesPresentes(): array
    {
        $presentes = [];

        foreach (array_keys(self::FUENTES) as $nombre) {
            $ruta = $this->fuentes.'/'.$nombre;

            if (is_file($ruta)) {
                $presentes[$nombre] = (string) file_get_contents($ruta);
            }
        }

        return $presentes;
    }

    /**
     * El `@font-face` de cada fuente presente, más la variable que decide la
     * familia efectiva del documento.
     *
     * Cada cara va como `data:` y no como `url()` a un fichero: desde un
     * documento `file://`, Chromium bloquea la fuente por CORS sin decir nada.
     *
     * Las familias genéricas del final no son decoración: si algún glifo se
     * escapa del subconjunto embebido, es lo que evita que salga un cuadrado.
     */
    private function declaracionesDeFuente(): string
    {
        $presentes = $this->fuentesPresentes();

        $caras = '';
        $familias = [];

        foreach ($presentes as $nombre => $contenido) {
            ['familia' => $familia, 'peso' => $peso, 'estilo' => $estilo] = self::FUENTES[$nombre];
            $familias[$familia] = true;

            $base64 = base64_encode($contenido);

            $caras .= <<<CSS
                @font-face {
                    font-family: '{$familia}';
                    font-style: {$estilo};
                    font-weight: {$peso};
                    src: url(data:font/woff2;base64,{$base64}) format('woff2');
                }

                CSS;
        }

        $sans = isset($familias['Instrument Sans'])
            ? "'Instrument Sans', "
            : '';
        $mono = isset($familias['JetBrains Mono'])
            ? "'JetBrains Mono', "
            : '';

        return $caras.<<<CSS
            :root {
                --fuente-documento: {$sans}'Helvetica Neue', Helvetica, Arial, sans-serif;
                --fuente-cifra: {$mono}'DejaVu Sans Mono', 'Courier New', monospace;
            }

            CSS;
    }
}
