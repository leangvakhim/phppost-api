<?php
$host = "localhost";
$username = "root";
$password = "";
$dbname = "phppost";

header("Access-Control-Allow-Origin: http://localhost/phppost/swagger/");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requeted-With");

$allowedOrigin = "http://localhost/phppost/swagger/";
$origin = $_SERVER['HTTP_REFERER'];

if ($origin !== $allowedOrigin) {
    http_response_code(403);
    echo json_encode(['message' => 'Forbidden: Origin not allowed']);
    exit;
}

try{
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $sql = "create database if not exists $dbname";
    $pdo->exec($sql);

    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
}catch(PDOException $e){
    die("Connection failed: ". $e->getMessage());
}
?>