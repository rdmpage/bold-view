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

$db = pg_pconnect( "$host $port $dbname $credentials gssencmode=disable"  );
if(!$db) {
  echo "Error : Unable to open database\n";
  exit();
} else {
  //echo "Opened database successfully\n";
}

?>
