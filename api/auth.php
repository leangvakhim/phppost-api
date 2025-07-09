<?php
require_once __DIR__ . '/../vendor/autoload.php';
include('../connect.php');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$jwtSecret = "BlahBlah123";

function checkAuth()
{
    global $jwtSecret;

    if (!isset($_COOKIE['token'])) {
        echo json_encode(['message' => 'Token not found']);
        exit;
    }

    try {
        $decoded = JWT::decode($_COOKIE['token'], new Key($jwtSecret, 'HS256'));
        return $decoded; // success
    } catch (Exception $e) {
        echo json_encode(['message' => 'Unauthorized: ' . $e->getMessage()]);
        exit;
    }
}