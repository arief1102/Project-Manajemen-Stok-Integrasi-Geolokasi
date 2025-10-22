<?php
require_once 'config/db_connect.php';
require_once 'auth_middleware.php';

header('Content-Type: application/json');

$user = authenticate();

if (!$user || $user['role'] !== 'pembeli') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in as a customer.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method. Please use POST.']);
    exit;
}

$user_id = $user['user_id'];
$plant_id = $_POST['plant_id'] ?? null;

if (!$plant_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid plant ID. Please select a valid plant.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Get customer_id
    $stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        throw new Exception('Customer not found. Please check your account details.');
    }

    $customer_id = $customer['customer_id'];

    // Check if plant is already in cart
    $stmt = $pdo->prepare("SELECT * FROM cart WHERE customer_id = ? AND plant_id = ?");
    $stmt->execute([$customer_id, $plant_id]);
    $existing_item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_item) {
        // Update quantity
        $stmt = $pdo->prepare("UPDATE cart SET quantity = quantity + 1 WHERE cart_id = ?");
        $stmt->execute([$existing_item['cart_id']]);
    } else {
        // Add new item to cart
        $stmt = $pdo->prepare("INSERT INTO cart (customer_id, plant_id, quantity) VALUES (?, ?, 1)");
        $stmt->execute([$customer_id, $plant_id]);
    }

    // Fetch updated cart data
    $stmt = $pdo->prepare("
        SELECT c.cart_id, c.quantity, p.plant_id, p.nama, p.harga
        FROM cart c
        JOIN plants p ON c.plant_id = p.plant_id
        WHERE c.customer_id = ?
    ");
    $stmt->execute([$customer_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_price = 0;
    foreach ($cart_items as &$item) {
        $item['total'] = $item['harga'] * $item['quantity'];
        $total_price += $item['total'];
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Plant added to your cart successfully.',
        'cart_items' => $cart_items,
        'total_price' => $total_price
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}