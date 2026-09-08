-- La suite de tests corre sobre PostgreSQL, no sobre SQLite: el esquema usa
-- CTEs recursivas, JSONB con índices GIN y Row Level Security, y un test verde
-- sobre SQLite no probaría nada de eso.
CREATE DATABASE statera_test OWNER statera;
