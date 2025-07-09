<?php
require_once __DIR__ . '/../vendor/autoload.php';

include('../connect.php');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$jwtSecret = "BlahBlah123";

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getUser();
        break;
    case 'POST':
        if (isset($_GET['login'])) {
            login();
        } elseif (isset($_GET['logout'])) {
            logout();
        } elseif (isset($_GET['id'])) {
            updateUser();
        } else {
            createUser();
        }
        break;
    case 'PUT':
        deleteUser();
        break;
    default:
        echo json_encode(["message" => "Invalid request method"]);
        break;
}

function getUser()
{
    $auth = checkAuth();

    if (!in_array($auth->role, ['admin'])) {
        echo json_encode(['message' => 'Forbidden: insufficient permissions']);
        exit;
    }

    global $pdo;
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("select * from tbUser where id = :id && active = 1");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            unset($result['password']);
            echo json_encode($result);
        } else {
            echo json_encode(["message" => "User not found"]);
        }
    } else {
        $stmt = $pdo->query("select * from tbUser where active = 1");
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as &$user) {
                unset($user['password']);
            }
            echo json_encode($result);
        } else {
            echo json_encode(["message" => "User not found"]);
        }
    }
}

function createUser()
{
    global $auth;
    if (!in_array($auth->role, ['admin'])) {
        echo json_encode(['message' => 'Forbidden: insufficient permissions']);
        exit;
    }

    global $pdo;

    $password = $_POST['password'];
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("insert into tbUser (username, password, role, active, created_at, updated_at) values (:username, :password, :role, :active, :created_at, :updated_at)");
    if ($stmt->execute([
        ':username' => $_POST['username'],
        ':password' => $hashedPassword,
        ':role' => $_POST['role'],
        ':active' => 1,
        ':created_at' => date('Y-m-d H:i:s'),
        ':updated_at' => date('Y-m-d H:i:s')
    ])) {
        echo json_encode(['message' => "User created successfully"]);
    } else {
        echo json_encode(['message' => "Unable to create user"]);
    }
}

function updateUser()
{
    global $auth;
    if (!in_array($auth->role, ['admin'])) {
        echo json_encode(['message' => 'Forbidden: insufficient permissions']);
        exit;
    }

    global $pdo;
    $id = $_GET['id'];

    $stmt = $pdo->prepare(
        "update tbUser set
                username = :username,
                role = :role,
                updated_at = :updated_at
                where id = :id"
    );
    if ($stmt->execute([
        ':username' => $_POST['username'],
        ':role' => $_POST['role'],
        ':updated_at' => date('Y-m-d H:i:s'),
        ':id' => $id,
    ])) {
        echo json_encode(['message' => "User updated successfully"]);
    } else {
        echo json_encode(['message' => "Unable to update user"]);
    }
}

function deleteUser()
{
    global $auth;
    if (!in_array($auth->role, ['admin'])) {
        echo json_encode(['message' => 'Forbidden: insufficient permissions']);
        exit;
    }

    global $pdo;
    $id = $_GET['id'];

    $stmt = $pdo->prepare("select active from tbUser where id = :id");
    $stmt->execute([':id' => $id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        echo json_encode(['message' => "User not found"]);
        return;
    }

    $newActive = $current['active'] == 1 ? 0 : 1;

    $stmt = $pdo->prepare("update tbUser set active = :active where id = :id");
    if ($stmt->execute([
        ':id' => $id,
        ':active' => $newActive
    ])) {
        echo json_encode(['message' => "User's active status update successfully"]);
    } else {
        echo json_encode(['message' => "Unable to update user's active status"]);
    }
}

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

function login()
{
    global $pdo;
    global $jwtSecret;

    if (!isset($_POST['username']) || !isset($_POST['password'])) {
        echo json_encode(['message' => "Username and password are required"]);
        return;
    }

    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("select * from tbUser where username = :username and active = 1");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        unset($user['password']);

        $issuedAt = time();
        $expirationTime = $issuedAt + 3600; // 1 hour
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'uid' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role']
        ];

        $jwt = JWT::encode($payload, $jwtSecret, 'HS256');

        // Clear existing token cookie if present
        if (isset($_COOKIE['token'])) {
            setcookie(
                "token",
                "",
                [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]
            );
        }

        // Set new token based on authenticated role
        setcookie(
            "token",
            $jwt,
            [
                'expires' => time() + 3600,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );

        echo json_encode([
            'message' => "Login successful",
            // 'token' => $jwt,
            'user' => $user
        ]);
    } else {
        echo json_encode(['message' => "Invalid username or password"]);
    }
}

function logout()
{
    setcookie(
        "token",
        "",
        [
            'expires' => time() - 3600, // expire in the past
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]
    );

    echo json_encode([
        'message' => "Logout successful"
    ]);
}
