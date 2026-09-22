---
paths:
  - docker-compose.yml
  - docker/**
  - .env.example
---

# La infraestructura de desarrollo

## Desvíos respecto al stack

Sección viva. Aquí se anota lo que difiere de `stack-gestor-cumplimiento.md` y por qué, para que nadie lo "arregle" sin contexto.

- **La aplicación corre en un contenedor también en desarrollo**, que es lo contrario
  de lo que decía la cabecera del `docker-compose.yml` («hay PHP local y el ciclo de
  edición es más rápido así»). El motivo no es comodidad: el lock exige diez
  extensiones —`pdo_pgsql`, `gd`, `zip`, `gmp`, `intl`, `pcntl`…— y con el intérprete
  del host lo que funciona depende de qué tenga compilado cada máquina. En Windows
  faltaban `pcntl` y `posix`, que `laravel/horizon` pide como requisitos duros, así que
  **Horizon no podía arrancar** y cada `composer install` necesitaba
  `--ignore-platform-req`. Un entorno donde una dependencia declarada no se puede
  ejecutar no es un entorno de desarrollo, es un entorno parecido. El destino es
  Ubuntu, y aun ahí la imagen es lo que hace que el entorno sea el mismo en todas
  partes. Con la aplicación dentro, `queue` corre Horizon de verdad y el test de
  integración de Gotenberg deja de auto-saltarse.

  Cuatro cosas que no se ven leyendo el `docker-compose.yml`:

  1. **MinIO tiene DOS endpoints, y el reparto no es cosmético: conectar y firmar
     son dos preguntas distintas.** `AWS_ENDPOINT` es por dónde sale el servidor y
     `AWS_ENDPOINT_PUBLICO` es con qué host se firma la URL temporal que abre el
     navegador; los monta `AlmacenServiceProvider` sobre `DiscoConEndpointPublico`,
     y si coinciden —o el público está vacío, que es el caso de producción— no se
     monta nada y el disco es el de Laravel.

     Las dos mitades, porque cada una tiene su trampa:

     - **Firmar.** `temporaryUrl()` firma con SigV4 y **el host va dentro de la
       firma**, así que reescribirlo después la invalida —eso es exactamente lo que
       hace la opción `temporary_url` de Laravel, y por eso no se usa—. El nombre
       tiene que ser desde el principio el que el navegador vaya a resolver:
       `minio.localhost`, porque los navegadores mandan cualquier `*.localhost` a
       loopback por su cuenta y ahí está publicado el 9000.
     - **Conectar.** Ese nombre **el servidor no lo puede usar**: libcurl, desde la
       7.77, resuelve internamente todo nombre terminado en `.localhost` a
       127.0.0.1 sin preguntar al resolutor. El alias de red de Docker estaba bien
       puesto —`getent hosts minio.localhost` devolvía la IP del contenedor— y curl
       ni lo consultaba. Durante cinco días **no se generó un solo PDF**: cada
       subida moría en 0 ms con «Connection refused», y dentro del contenedor
       127.0.0.1:9000 es php-fpm, así que el síntoma no menciona ni a MinIO ni al
       DNS. El endpoint de conexión es `http://minio:9000` y **nunca un
       `*.localhost`**.

     Y el hook que Laravel documenta para esto, `buildTemporaryUrlsUsing()`, **no
     sirve en un disco de S3**: lo consulta `FilesystemAdapter::temporaryUrl()` y
     `AwsS3V3Adapter` sobrescribe ese método sin mirarlo. Registrarlo compila, no
     avisa y no se aplica nunca. De ahí la subclase.
  2. **Las imágenes de MinIO vienen de `quay.io`, no de Docker Hub**, donde
     `minio/minio` y `minio/mc` ya no existen. Un repositorio que no existe se anuncia
     como «pull access denied», que parece un problema de credenciales y no lo es. Van
     ancladas, como Gotenberg y PostgreSQL, y **`minio` se queda sin healthcheck a
     propósito**: el recomendado es `mc ready local`, y que `mc` siga dentro de esa
     imagen es un detalle de MinIO que ya ha cambiado una vez. Quien espera es
     `minio-init`, en su propio bucle.
  3. **Dónde están los servicios lo declara el compose, no el `.env`.** `DB_HOST`,
     `REDIS_HOST`, `GOTENBERG_URL` y `AWS_ENDPOINT` van en el `environment` de `app` y
     `queue` aunque también estén en `.env.example`, y no es duplicación por descuido:
     son la topología de esta red, no una preferencia de nadie. Dotenv **no pisa una
     variable que ya esté en el entorno**, así que el compose gana y un `.env`
     heredado de otra máquina no manda la aplicación a `127.0.0.1` — que dentro de un
     contenedor es el propio contenedor, y el error que sale habla de que PostgreSQL
     no acepta conexiones, no de que el host esté mal.
  4. **Los contenedores escriben en la carpeta del proyecto con el UID del host**
     (`ARG UID`, y `gosu www-data` en el entrypoint). En Linux el uid del contenedor es
     el que queda en el fichero: sin eso, `vendor/`, `node_modules/` y `storage/` se
     llenan de ficheros de root que el dueño del repositorio no puede borrar. php-fpm
     es la excepción y arranca como root, porque el maestro tiene que poder crear sus
     workers.

  El arranque es completo a propósito —dependencias, `APP_KEY`, migraciones, catálogo
  y buckets— porque cada paso manual documentado es un paso que alguien se salta: el
  bucket de MinIO llevaba desde el principio creándose a mano, y olvidarlo fallaba
  mucho después, al subir una evidencia.

- **Cliente de Redis: `predis`, no `phpredis`.** La máquina de desarrollo no tiene la extensión `phpredis` compilada y el stack no elige cliente. `predis` es PHP puro y no requiere extensión. Si en producción se instala `phpredis`, basta cambiar `REDIS_CLIENT` en el entorno.

- **`laravel/passport` se retiró.** Venía en el esqueleto inicial junto con sus seis migraciones OAuth. No hay API pública que autenticar (§12 del stack descarta la SPA con API separada) y la autenticación va por Fortify. Si algún día hace falta OAuth para integraciones, se vuelve a valorar entonces.
