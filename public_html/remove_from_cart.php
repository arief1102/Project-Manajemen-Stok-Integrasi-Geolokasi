<?php
require_once 'config/db_connect.php';
require_once 'auth_middleware.php';

header('Content-Type: application/json');

$user = authenticate();

if (!$user || $user['role'] !== 'pembeli') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$cart_id = $_POST['cart_id'] ?? null;

if (!$cart_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Check if the item exists and belongs to the user
    $stmt = $pdo->prepare("
        SELECT c.*
        FROM cart c
        JOIN customers cu ON c.customer_id = cu.customer_id
        WHERE c.cart_id = ? AND cu.user_id = ?
    ");
    $stmt->execute([$cart_id, $user['user_id']]);
    $cart_item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cart_item) {
        throw new Exception('Cart item not found');
    }

    // Remove the item from the cart
    $stmt = $pdo->prepare("DELETE FROM cart WHERE cart_id = ?");
    $stmt->execute([$cart_id]);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}