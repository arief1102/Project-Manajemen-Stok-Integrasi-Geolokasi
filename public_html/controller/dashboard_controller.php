<?php
require_once 'auth_middleware.php';
require_once 'config/db_connect.php';

$user = authenticate();

if (!$user) {
    header("Location: login.php");
    exit();
}

$user_id = $user['user_id'];
$role = $user['role'];

// Check if user has set their location
$show_location_popup = false;
if ($role == 'petani') {
    $stmt = $pdo->prepare("SELECT petani_id, latitude, longitude FROM petani WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $petani = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$petani['latitude'] || !$petani['longitude']) {
        $show_location_popup = true;
    }
    $petani_id = $petani['petani_id'];
} elseif ($role == 'pembeli') {
    $stmt = $pdo->prepare("SELECT customer_id, latitude, longitude FROM customers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer['latitude'] || !$customer['longitude']) {
        $show_location_popup = true;
    }
    $customer_id = $customer['customer_id'];
}

// Initialize variables
$total_plants = 0;
$total_orders = 0;
$monthly_sales = [];
$top_selling_plants = [];
$recent_orders = [];

// Fetch data based on user role
if ($role == 'petani') {
    // Total plants
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_plants FROM plants WHERE petani_id = ? AND is_deleted = 0");
    $stmt->execute([$petani_id]);
    $total_plants = $stmt->fetchColumn();

    // Total orders (count distinct order_id where petani has items)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT o.order_id) as total_orders 
        FROM orders o 
        JOIN order_items oi ON o.order_id = oi.order_id 
        JOIN plants p ON oi.plant_id = p.plant_id 
        WHERE p.petani_id = ?
    ");
    $stmt->execute([$petani_id]);
    $total_orders = $stmt->fetchColumn();

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
    $monthly_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    $top_selling_plants = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($role == 'pembeli') {
    // Total orders for pembeli
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_orders FROM orders WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    $total_orders = $stmt->fetchColumn();

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
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
}