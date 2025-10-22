<?php
require_once 'config/db_connect.php';

require_once 'auth_middleware.php';
$user = requireRole('admin');



if (!isset($_GET['user_id'])) {
    echo "User ID is required";
    exit();
}

$user_id = $_GET['user_id'];

// Fetch order history for the petani
$stmt = $pdo->prepare("
    SELECT o.order_id, o.total_harga, o.notes, o.order_date, 
           oi.plant_id, oi.kuantitas, oi.harga_per_item, oi.total_harga as item_total, oi.order_item_status,
           p.nama as plant_name,
           c.nama_lengkap as customer_name
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN plants p ON oi.plant_id = p.plant_id
    JOIN customers c ON o.customer_id = c.customer_id
    JOIN petani pt ON p.petani_id = pt.petani_id
    WHERE pt.user_id = ?
    ORDER BY o.order_date DESC
");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($orders)) {
    echo "<p>Tidak ada riwayat pesanan untuk petani ini.</p>";
} else {
    $current_order_id = null;
    foreach ($orders as $order) {
        if ($current_order_id !== $order['order_id']) {
            if ($current_order_id !== null) {
                echo "</tbody></table></div></div></div>";
            }
            $current_order_id = $order['order_id'];
            echo "<div class='order-card'>";
            echo "<div class='order-header'>";
            echo "<h5>Order ID: " . htmlspecialchars($order['order_id']) . "</h5>";
            echo "<p class='mb-1'><strong>Tanggal:</strong> " . htmlspecialchars($order['order_date']) . "</p>";
            echo "<p class='mb-1'><strong>Pembeli:</strong> " . htmlspecialchars($order['customer_name']) . "</p>";
            echo "<p class='mb-1'><strong>Total Harga:</strong> Rp " . number_format($order['total_harga'], 2, ',', '.') . "</p>";
            echo "<p class='mb-0'><strong>Catatan:</strong> " . htmlspecialchars($order['notes']) . "</p>";
            echo "</div>";
            echo "<div class='order-body'>";
            echo "<div class='table-responsive'>";
            echo "<table class='table table-striped table-hover'>";
            echo "<thead><tr><th>Tanaman</th><th>Kuantitas</th><th>Harga per Item</th><th>Total</th><th>Status</th></tr></thead>";
            echo "<tbody>";
        }
        echo "<tr>";
        echo "<td>" . htmlspecialchars($order['plant_name']) . "</td>";
        echo "<td>" . htmlspecialchars($order['kuantitas']) . "</td>";
        echo "<td>Rp " . number_format($order['harga_per_item'], 2, ',', '.') . "</td>";
        echo "<td>Rp " . number_format($order['item_total'], 2, ',', '.') . "</td>";
        echo "<td><span class='status-badge status-" . strtolower($order['order_item_status']) . "'>" . htmlspecialchars($order['order_item_status']) . "</span></td>";
        echo "</tr>";
    }
    echo "</tbody></table></div></div></div>";
}
