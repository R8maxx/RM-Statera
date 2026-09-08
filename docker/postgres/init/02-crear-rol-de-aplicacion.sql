-- La aplicación NO se conecta como superusuario.
--
-- PostgreSQL exime a los superusuarios y a los roles con BYPASSRLS de la
-- seguridad a nivel de fila, incluso con FORCE ROW LEVEL SECURITY. El rol que
-- crea POSTGRES_USER es superusuario, así que si la aplicación se conectara con
-- él la tercera capa del aislamiento multi-tenant existiría en el esquema y no
-- haría absolutamente nada: el peor de los mundos, porque parecería puesta.
--
-- `statera_app` es el rol de la aplicación: sin superusuario, sin BYPASSRLS, y
-- propietario del esquema para poder ejecutar las migraciones. `statera` se
-- queda como rol administrativo para tareas de mantenimiento de la base.
CREATE ROLE statera_app WITH LOGIN PASSWORD 'statera' NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS;

GRANT ALL PRIVILEGES ON DATABASE statera TO statera_app;
GRANT ALL PRIVILEGES ON DATABASE statera_test TO statera_app;

\connect statera
ALTER SCHEMA public OWNER TO statera_app;

\connect statera_test
ALTER SCHEMA public OWNER TO statera_app;
