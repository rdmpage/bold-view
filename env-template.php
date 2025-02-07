<?php

// For local testing put username and password here, rename as
// env.php and add to .gitignore
// For production (e.g., Heroku) add these as environment variables.

putenv('POSTGRES_HOST=127.0.0.1');
putenv('POSTGRES_PORT=5432');
putenv('POSTGRES_DATABASE=');
putenv('POSTGRES_USERNAME=');
putenv('POSTGRES_PASSWORD=');

putenv('NCBI_API_KEY=');
putenv('THUNDERFOREST_API_KEY=');

?>
