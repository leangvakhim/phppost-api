<?php

include('../connect.php');
include('./auth.php');
$auth = checkAuth();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getPost();
        break;
    case 'POST':
        if (isset($_GET['id'])) {
            updatePost();
        } else {
            createPost();
        }
        break;
    case 'PUT':
        deletePost();
        break;
    default:
        echo json_encode(["message" => "Invalid request method"]);
        break;
    }

    function getPost()
    {
        $auth = checkAuth();
        if (!in_array($auth->role, ['admin', 'creator', 'guest'])) {
            echo json_encode(['message' => 'Forbidden: insufficient permissions']);
            exit;
        }

        global $pdo;
        if (isset($_GET['id'])) {
            $id = $_GET['id'];
            $stmt = $pdo->prepare("
                select
                    tbPost.*,
                    concat('http://localhost/phppost/uploads/', tbPost.image) as image_url
                from tbPost
                where tbPost.id = :id && tbPost.active = 1
            ");
            $stmt->execute([':id' => $id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                echo json_encode($result);
            } else {
                echo json_encode(["message" => "Post not found"]);
            }
        } else {
            $stmt = $pdo->query("
                select
                    tbPost.*,
                    concat('http://localhost/phppost/uploads/', tbPost.image) as image_url
                from tbPost
                where tbPost.active = 1
            ");
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($result) {
                echo json_encode($result);
            } else {
                echo json_encode(["message" => "Post not found"]);
            }
        }
    }

    function createPost()
    {
        $auth = checkAuth();
        if (!in_array($auth->role, ['admin', 'creator'])) {
            echo json_encode(['message' => 'Forbidden: insufficient permissions']);
            exit;
        }

        global $pdo;

        $uploadDir = "../uploads/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $fileName = basename($_FILES['image']['name']);
        $uploadFile = $uploadDir . $fileName;
        move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile);

        $stmt = $pdo->prepare("insert into tbPost (title, content, image, active, created_at, updated_at) values (:title, :content, :image, :active, :created_at, :updated_at)");
        if ($stmt->execute([
            ':title' => $_POST['title'],
            ':content' => $_POST['content'],
            ':image' => $fileName,
            ':active' => 1,
            ':created_at' => date('Y-m-d H:i:s'),
            ':updated_at' => date('Y-m-d H:i:s')
        ])) {
            echo json_encode(['message' => "Post created successfully"]);
        } else {
            echo json_encode(['message' => "Unable to create post"]);
        }
    }

    function updatePost()
    {
        $auth = checkAuth();
        if (!in_array($auth->role, ['admin', 'creator'])) {
            echo json_encode(['message' => 'Forbidden: insufficient permissions']);
            exit;
        }

        global $pdo;
        $id = $_GET['id'];

        $uploadDir = "../uploads/";

        $fileName = basename($_FILES['image']['name']);
        $uploadFile = $uploadDir . $fileName;
        move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile);

        $stmt = $pdo->prepare(
            "update tbPost set
                title = :title,
                content = :content,
                image = :image,
                updated_at = :updated_at
                where id = :id"
        );
        if ($stmt->execute([
            ':title' => $_POST['title'],
            ':content' => $_POST['content'],
            ':image' => $fileName,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':id' => $id,
        ])) {
            echo json_encode(['message' => "Post updated successfully"]);
        } else {
            echo json_encode(['message' => "Unable to update post"]);
        }
    }

    function deletePost()
    {
        $auth = checkAuth();
        if (!in_array($auth->role, ['admin'])) {
            echo json_encode(['message' => 'Forbidden: insufficient permissions(Only admin are allow!)']);
            exit;
        }

        global $pdo;
        $id = $_GET['id'];

        $stmt = $pdo->prepare("select active from tbPost where id = :id");
        $stmt->execute([':id' => $id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$current) {
            echo json_encode(['message' => "Post not found"]);
            return;
        }

        $newActive = $current['active'] == 1 ? 0 : 1;

        $stmt = $pdo->prepare("update tbPost set active = :active where id = :id");
        if ($stmt->execute([
            ':id' => $id,
            ':active' => $newActive
        ])) {
            echo json_encode(['message' => "Post active status update successfully"]);
        } else {
            echo json_encode(['message' => "Unable to update post active status"]);
        }
    }

?>