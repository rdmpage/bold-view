<?php

// For local testing put username and password here, rename as
// env.php and add to .gitignore
// For production (e.g., Heroku) add these as environment variables.

putenv('POSTGRES_HOST=127.0.0.1');
putenv('POSTGRES_PORT=5432');
putenv('POSTGRES_DATABASE=');
// Use the least-privilege, read-only role (see sql/create_app_role.sql),
// NOT the postgres superuser.
putenv('POSTGRES_USERNAME=boldview_app');
putenv('POSTGRES_PASSWORD=');
// require = refuse unencrypted connections. See docs/db-hardening.md.
putenv('POSTGRES_SSLMODE=require');

putenv('NCBI_API_KEY=');
putenv('THUNDERFOREST_API_KEY=');

?>
