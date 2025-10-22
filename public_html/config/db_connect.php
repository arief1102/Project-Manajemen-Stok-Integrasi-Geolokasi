<?php

$host = 'mysql12.serv00.com';
$dbname = 'm10768_plantjabonmekar';
$username = 'm10768_arief';
$password = 'As201c%9}b.7jnSMye5oQn)u5K1WDm';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Could not connect to the database $dbname :" . $e->getMessage());
}