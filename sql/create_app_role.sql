-- ============================================================================
-- Least-privilege application role for Bold View
-- ============================================================================
-- The web app is READ-ONLY. It only ever runs SELECTs against five tables:
--   boldvector, boldmeta, boldtaxonomy, boldimage, boldannotation
--
-- It currently logs in as the `postgres` SUPERUSER, which means a leaked
-- credential = full control of the cluster (incl. COPY ... PROGRAM shell access).
-- This script creates a role that can only read those five tables.
--
-- HOW TO RUN (as a superuser, e.g. from the DB host):
--   psql -U postgres -d postgres -f sql/create_app_role.sql
--
-- Then set the password (see docs/db-hardening.md for generating one):
--   ALTER ROLE boldview_app WITH PASSWORD 'PASTE-GENERATED-PASSWORD-HERE';
--
-- Finally, point the app at the new role by editing env.php / Heroku config
-- vars: POSTGRES_USERNAME=boldview_app, POSTGRES_PASSWORD=<the password>.
-- ============================================================================

-- Make sure new passwords are stored as SCRAM (not the legacy md5).
-- Safe to run repeatedly; only affects passwords SET after this point.
ALTER SYSTEM SET password_encryption = 'scram-sha-256';
SELECT pg_reload_conf();

-- 1. The login role. NOSUPERUSER / NOCREATEDB / NOCREATEROLE = can do nothing
--    beyond what we explicitly grant below. Password is set separately (above)
--    so this file never contains a secret.
DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'boldview_app') THEN
    CREATE ROLE boldview_app
      LOGIN
      NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION
      CONNECTION LIMIT 20;
  END IF;
END
$$;

-- 2. Allow it to see the schema and use the tables (read-only).
GRANT CONNECT ON DATABASE postgres TO boldview_app;
GRANT USAGE ON SCHEMA public TO boldview_app;

-- 3. SELECT on exactly the five tables the app reads. Nothing else.
GRANT SELECT ON
    public.boldvector,
    public.boldmeta,
    public.boldtaxonomy,
    public.boldimage,
    public.boldannotation
  TO boldview_app;

-- 4. Explicitly make sure it has NO write access anywhere and cannot create
--    objects in public (defensive; these are the defaults for a fresh role,
--    but we assert them in case public was left world-writable).
REVOKE CREATE ON SCHEMA public FROM boldview_app;
REVOKE ALL ON DATABASE postgres FROM PUBLIC;      -- optional hardening
GRANT  CONNECT ON DATABASE postgres TO boldview_app;

-- 5. Sanity check — list what the new role can touch. Should show 5 rows,
--    all with privilege_type = SELECT.
SELECT table_name, privilege_type
FROM information_schema.role_table_grants
WHERE grantee = 'boldview_app'
ORDER BY table_name;
