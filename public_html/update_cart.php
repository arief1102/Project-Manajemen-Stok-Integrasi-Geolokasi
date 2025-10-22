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
$quantity = $_POST['quantity'] ?? null;

if (!$cart_id || !$quantity) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Updated query to include berat and petani_id
    $stmt = $pdo->prepare("
        SELECT c.*, p.stok, p.harga, p.nama, p.berat, p.petani_id
        FROM cart c
        JOIN plants p ON c.plant_id = p.plant_id
        JOIN customers cu ON c.customer_id = cu.customer_id
        WHERE c.cart_id = ? AND cu.user_id = ?
    ");
    $stmt->execute([$cart_id, $user['user_id']]);
    $cart_item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cart_item) {
        throw new Exception('Cart item not found');
    }

    if ($quantity > $cart_item['stok']) {
        throw new Exception('Requested quantity exceeds available stock');
    }

    // Update the quantity
    $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ?");
    $stmt->execute([$quantity, $cart_id]);

    $pdo->commit();

    // Calculate the new total price for this item
    $total_price = $quantity * $cart_item['harga'];

    // Prepare the response with updated item details including weight and petani_id
    $response = [
        'success' => true,
        'updatedItem' => [
            'cart_id' => $cart_id,
            'quantity' => $quantity,
            'nama' => $cart_item['nama'],
            'harga' => $cart_item['harga'],
            'total_price' => $total_price,
            'berat' => $cart_item['berat'],
            'petani_id' => $cart_item['petani_id']
        ],
        'message' => 'Cart updated successfully'
    ];

    echo json_encode($response);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}