<?php

// Conect to Postgres database

putenv("LC_ALL=C");

if (file_exists(dirname(__FILE__) . '/env.php'))
{
	include 'env.php';
}

$host        = "host = " . getenv('POSTGRES_HOST');
$port        = "port = " . getenv('POSTGRES_PORT');
$dbname      = "dbname = " . getenv('POSTGRES_DATABASE');
$credentials = "user = " . getenv('POSTGRES_USERNAME') . " password = " . getenv('POSTGRES_PASSWORD');

// sslmode=require forces the connection to be encrypted (libpq defaults to
// "prefer", which silently falls back to plaintext). Use verify-full once the
// server has a CA-signed / pinned certificate for full protection.
$sslmode = getenv('POSTGRES_SSLMODE') ?: 'require';

// Use pg_connect (per-request), NOT pg_pconnect: persistent connections under
// mod_php get handed back in a bad state, triggering "Cannot set connection to
// blocking mode" on the SET below and failing every query on that worker.
$db = pg_connect( "$host $port $dbname $credentials sslmode=$sslmode gssencmode=disable"  );
if(!$db) {
  echo "Error : Unable to open database\n";
  exit();
} else {
  //echo "Opened database successfully\n";
}

pg_query($db, 'SET ivfflat.probes = 1');
//pg_query($db, 'SET ivfflat.probes = 10');
//pg_query($db, 'SET ivfflat.probes = 40'); // experiments on improving search

?>
