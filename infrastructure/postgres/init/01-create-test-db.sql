-- ─────────────────────────────────────────────────────────────
-- Postgres init script (runs once on fresh volume)
--
-- Creates the parallel test database used by phpunit.xml. Schemas
-- and tables inside it are populated by Laravel migrations during
-- the test suite's RefreshDatabase trait.
--
-- POSTGRES_USER (default: dehasoft) is granted ownership; the test
-- DB lives alongside the primary `testproject` DB on the same cluster.
-- ─────────────────────────────────────────────────────────────

CREATE DATABASE testproject_test
    WITH OWNER = dehasoft
         ENCODING = 'UTF8'
         LC_COLLATE = 'en_US.utf8'
         LC_CTYPE = 'en_US.utf8'
         TEMPLATE = template0;

GRANT ALL PRIVILEGES ON DATABASE testproject_test TO dehasoft;
