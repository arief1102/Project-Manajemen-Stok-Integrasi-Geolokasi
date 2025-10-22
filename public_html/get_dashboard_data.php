<?php
require_once 'auth_middleware.php';
require_once 'config/db_connect.php';

header('Content-Type: application/json');

$user = authenticate();

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $user['user_id'];
$role = $user['role'];

$response = [
    'role' => $role,
    'total_plants' => 0,
    'total_orders' => 0,
    'monthly_sales' => [],
    'top_selling_plants' => [],
    'recent_orders' => []
];

try {
    if ($role == 'petani') {
        // Get petani_id
        $stmt = $pdo->prepare("SELECT petani_id FROM petani WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $petani = $stmt->fetch(PDO::FETCH_ASSOC);
        $petani_id = $petani['petani_id'];

        // Total plants
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_plants FROM plants WHERE petani_id = ? AND is_deleted = 0");
        $stmt->execute([$petani_id]);
        $response['total_plants'] = $stmt->fetchColumn();

        // Total orders
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT o.order_id) as total_orders 
            FROM orders o 
            JOIN order_items oi ON o.order_id = oi.order_id 
            JOIN plants p ON oi.plant_id = p.plant_id 
            WHERE p.petani_id = ?
        ");
        $stmt->execute([$petani_id]);
        $response['total_orders'] = $stmt->fetchColumn();

        // Monthly sales
        $stmt = $pdo->prepare("
            SELECT DATE_FORMAT(o.order_date, '%Y-%m') as month, SUM(oi.total_harga) as total_sales
            FROM orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            JOIN plants p ON oi.plant_id = p.plant_id
            WHERE p.petani_id = ?
            GROUP BY DATE_FORMAT(o.order_date, '%Y-%m')
            ORDER BY month DESC
            LIMIT 12
        ");
        $stmt->execute([$petani_id]);
        $response['monthly_sales'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Top selling plants
        $stmt = $pdo->prepare("
            SELECT p.nama, SUM(oi.kuantitas) as total_sold
            FROM order_items oi
            JOIN plants p ON oi.plant_id = p.plant_id
            WHERE p.petani_id = ?
            GROUP BY p.plant_id
            ORDER BY total_sold DESC
            LIMIT 5
        ");
        $stmt->execute([$petani_id]);
        $response['top_selling_plants'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent orders for petani
        $stmt = $pdo->prepare("
            SELECT DISTINCT o.order_id, c.nama_lengkap, 
                   SUM(oi.total_harga) as total_harga, o.order_date, 
                   GROUP_CONCAT(DISTINCT oi.order_item_status) as order_statuses
            FROM orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            JOIN plants p ON oi.plant_id = p.plant_id
            JOIN customers c ON o.customer_id = c.customer_id
            WHERE p.petani_id = ?
            GROUP BY o.order_id
            ORDER BY o.order_date DESC
            LIMIT 5
        ");
        $stmt->execute([$petani_id]);
        $response['recent_orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($role == 'pembeli') {
        // Get customer_id
        $stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        $customer_id = $customer['customer_id'];

        // Total orders for pembeli
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_orders FROM orders WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        $response['total_orders'] = $stmt->fetchColumn();

        // Recent orders for pembeli
        $stmt = $pdo->prepare("
            SELECT o.order_id, o.total_harga, o.order_date, 
                   GROUP_CONCAT(DISTINCT oi.order_item_status) as order_statuses
            FROM orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            WHERE o.customer_id = ?
            GROUP BY o.order_id
            ORDER BY o.order_date DESC
            LIMIT 5
        ");
        $stmt->execute([$customer_id]);
        $response['recent_orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode($response);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'A database error occurred. Please try again later.']);
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred. Please try again later.']);
}