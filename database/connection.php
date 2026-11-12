<?php
// php/db.php

$host     = 'localhost';
$dbname   = 'primefit';        
$user     = 'root';
$password = '';                

try {
    $database = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $password
    );
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $database->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Ошибка подключения к БД: ' . $e->getMessage());
}