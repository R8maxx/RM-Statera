-- Dos roles, y ninguno es superusuario.
--
-- La aplicación NO se conecta como superusuario: PostgreSQL exime a los
-- superusuarios y a los roles con BYPASSRLS de la seguridad a nivel de fila,
-- incluso con FORCE ROW LEVEL SECURITY. El rol que crea POSTGRES_USER lo es, así
-- que si la aplicación se conectara con él la tercera capa del aislamiento
-- multi-tenant existiría en el esquema y no haría absolutamente nada.
--
-- Y la aplicación tampoco es DUEÑA de las tablas (punto 32). Hasta aquí
-- `statera_app` corría las migraciones y era propietaria de todo, y un dueño
-- puede devolverse a sí mismo cualquier privilegio: el `REVOKE UPDATE, DELETE`
-- sobre `eventos_auditoria` paraba al código distraído, pero unas credenciales
-- comprometidas lo deshacían con un `GRANT`, y con un `ALTER TABLE … DISABLE
-- TRIGGER` apagaban los triggers de inmutabilidad de las versiones emitidas.
-- Por eso ahora son dos:
--
-- - `statera_migrador` es dueño del esquema y de todo lo que hay en él. Sólo
--   lo usan las migraciones (`--database=pgsql_migraciones`).
-- - `statera_app` es con quien se conecta Statera. Lee y escribe filas, y nada
--   más: ni DDL, ni TRUNCATE, ni conceder privilegios.
--
-- `statera` se queda como rol administrativo para mantenimiento de la base.
--
-- **Es idempotente**, porque sirve para dos cosas. En un volumen nuevo lo
-- ejecuta el arranque de PostgreSQL. En uno que ya existía, donde todo es de
-- `statera_app`, se ejecuta a mano una vez y reasigna la propiedad:
--
--   docker compose exec -T postgres psql -U statera -d postgres \
--     -f /docker-entrypoint-initdb.d/02-crear-rol-de-aplicacion.sql

\set ON_ERROR_STOP on

DO $$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'statera_app') THEN
        CREATE ROLE statera_app WITH LOGIN PASSWORD 'statera' NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS;
    END IF;

    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'statera_migrador') THEN
        CREATE ROLE statera_migrador WITH LOGIN PASSWORD 'statera' NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS;
    END IF;
END
$$;

-- Conectar y tablas temporales; nada de crear esquemas.
REVOKE ALL ON DATABASE statera FROM statera_app;
REVOKE ALL ON DATABASE statera_test FROM statera_app;
GRANT CONNECT, TEMPORARY ON DATABASE statera TO statera_app, statera_migrador;
GRANT CONNECT, TEMPORARY ON DATABASE statera_test TO statera_app, statera_migrador;

\connect statera

-- Todo lo que había pasa al migrador: el esquema, las tablas, las secuencias
-- y las funciones de los triggers. En un volumen nuevo no hay nada y es una
-- operación vacía.
REASSIGN OWNED BY statera_app TO statera_migrador;
ALTER SCHEMA public OWNER TO statera_migrador;

REVOKE ALL ON SCHEMA public FROM statera_app;
GRANT USAGE ON SCHEMA public TO statera_app;

-- Lo que ya existe…
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO statera_app;
GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public TO statera_app;

-- …y lo que creen las migraciones a partir de ahora. Sin TRUNCATE: vacía una
-- tabla sin pasar por RLS.
ALTER DEFAULT PRIVILEGES FOR ROLE statera_migrador IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO statera_app;
ALTER DEFAULT PRIVILEGES FOR ROLE statera_migrador IN SCHEMA public
    GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO statera_app;

-- La traza vuelve a quedar como la dejó su migración. El `GRANT` de arriba le
-- habría devuelto el UPDATE y el DELETE; el migrador recupera los suyos, que
-- al reasignar heredó recortados de cuando el dueño se los quitó a sí mismo.
DO $$
BEGIN
    IF to_regclass('public.eventos_auditoria') IS NOT NULL THEN
        GRANT ALL ON eventos_auditoria TO statera_migrador;
        REVOKE UPDATE, DELETE, TRUNCATE ON eventos_auditoria FROM statera_app;
    END IF;
END
$$;

\connect statera_test

-- Todo lo que había pasa al migrador: el esquema, las tablas, las secuencias
-- y las funciones de los triggers. En un volumen nuevo no hay nada y es una
-- operación vacía.
REASSIGN OWNED BY statera_app TO statera_migrador;
ALTER SCHEMA public OWNER TO statera_migrador;

REVOKE ALL ON SCHEMA public FROM statera_app;
GRANT USAGE ON SCHEMA public TO statera_app;

-- Lo que ya existe…
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO statera_app;
GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public TO statera_app;

-- …y lo que creen las migraciones a partir de ahora. Sin TRUNCATE: vacía una
-- tabla sin pasar por RLS.
ALTER DEFAULT PRIVILEGES FOR ROLE statera_migrador IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO statera_app;
ALTER DEFAULT PRIVILEGES FOR ROLE statera_migrador IN SCHEMA public
    GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO statera_app;

-- La traza vuelve a quedar como la dejó su migración. El `GRANT` de arriba le
-- habría devuelto el UPDATE y el DELETE; el migrador recupera los suyos, que
-- al reasignar heredó recortados de cuando el dueño se los quitó a sí mismo.
DO $$
BEGIN
    IF to_regclass('public.eventos_auditoria') IS NOT NULL THEN
        GRANT ALL ON eventos_auditoria TO statera_migrador;
        REVOKE UPDATE, DELETE, TRUNCATE ON eventos_auditoria FROM statera_app;
    END IF;
END
$$;
