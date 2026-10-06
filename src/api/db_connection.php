<?php

$dotenv->load(__DIR__.'/.env');

function db_connect(){
   	try {
		$pdo = new PDO($dsn, $user, $pass, $opt);
		echo 'DB is connected';
	} catch(PDOException $e) {
		echo "Connection failed: " . $e->getMessage();
	}
}