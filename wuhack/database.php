<?php


// Content of database.php, establishes mysql connection.
 
$mysqli = new mysqli('localhost', 'user', 'password', 'wuhack');
 
if($mysqli->connect_errno) {
    file_put_contents('php://stderr', print_r(printf("Connection Failed: %s\n", $mysqli->connect_error)." \n", TRUE));
	printf("Connection Failed: %s\n", $mysqli->connect_error);
	exit;
}



?>