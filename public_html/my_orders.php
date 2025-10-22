<?php
require_once 'config/db_connect.php';

// Check if user is logged in and is a pembeli
require_once 'auth_middleware.php';
$user = requireRole('pembeli');
$user_id = $user['user_id'];
$role = $user['role'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get customer_id
$stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE user_id = ?");
$stmt->execute([$user_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);
$customer_id = $customer['customer_id'];

// Handle order cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $order_item_id = $_POST['order_item_id'];

    try {
        $pdo->beginTransaction();

        // First, check if the order item is eligible for cancellation
        $stmt = $pdo->prepare("
            SELECT oi.order_item_id, oi.order_item_status, o.customer_id
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            WHERE oi.order_item_id = ? AND o.customer_id = ? AND oi.order_item_status = 'pending'
        ");
        $stmt->execute([$order_item_id, $customer_id]);
        $order_item = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order_item) {
            // Update the order item status to dibatalkan
            $stmt = $pdo->prepare("UPDATE order_items SET order_item_status = 'dibatalkan' WHERE order_item_id = ?");
            $stmt->execute([$order_item_id]);

            // REFUND THE PAYMENT TO CUSTOMER
            //$stmt = $pdo->prepare("UPDATE customers SET balance = balance + ? WHERE customer_id = ?");
            //$stmt->execute([$order_item['total_harga'], $customer_id]);

            $pdo->commit();
            $success_message = "Pesanan berhasil dibatalkan.";
        } else {
            throw new Exception("Pesanan tidak dapat dibatalkan karena status bukan pending.");
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}


// Mark order item as completed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_completed'])) {
    $order_item_id = $_POST['order_item_id'];

    try {
        $pdo->beginTransaction();

        // First, get the order item details
        $stmt = $pdo->prepare("
            SELECT oi.order_item_id, oi.plant_id, oi.total_harga, o.customer_id, p.petani_id
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            JOIN plants p ON oi.plant_id = p.plant_id
            WHERE oi.order_item_id = ? AND o.customer_id = ? AND oi.order_item_status = 'dikirim'
        ");
        $stmt->execute([$order_item_id, $customer_id]);
        $order_item = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order_item) {
            // Update the order item status
            $stmt = $pdo->prepare("UPDATE order_items SET order_item_status = 'selesai' WHERE order_item_id = ?");
            $stmt->execute([$order_item_id]);
          
            // KURANGI SALDO CUSTOMER
            $stmt = $pdo->prepare("UPDATE customers SET balance = balance - ? WHERE customer_id = ?");
            $stmt->execute([$order_item['total_harga'], $customer_id]);

            // Transfer the payment from customer to petani
            $stmt = $pdo->prepare("UPDATE petani SET balance = balance + ? WHERE petani_id = ?");
            $stmt->execute([$order_item['total_harga'], $order_item['petani_id']]);

            $pdo->commit();
            $success_message = "Pesanan ditandai sudah selesai dan pembayaran telah diteruskan ke petani.";
        } else {
            throw new Exception("Order item not found or not eligible for completion.");
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}

// Fetch orders for this customer
$stmt = $pdo->prepare("
    SELECT o.order_id, o.total_harga as order_total, o.order_date,
           oi.order_item_id, oi.kuantitas, oi.total_harga as item_total, oi.order_item_status,
           p.nama as plant_name,
           pet.nama_lengkap AS petani_name, pet.no_hp AS petani_phone
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN plants p ON oi.plant_id = p.plant_id
    JOIN petani pet ON p.petani_id = pet.petani_id
    WHERE o.customer_id = ?
    ORDER BY o.order_date DESC, o.order_id, oi.order_item_id
");
$stmt->execute([$customer_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group order items by order_id
$grouped_orders = [];
foreach ($order_items as $item) {
    $order_id = $item['order_id'];
    if (!isset($grouped_orders[$order_id])) {
        $grouped_orders[$order_id] = [
            'order_id' => $order_id,
            'order_date' => $item['order_date'],
            'order_total' => $item['order_total'],
            'items' => []
        ];
    }
    $grouped_orders[$order_id]['items'][] = $item;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Pesanan saya - Plant Inventory Jabon Mekar</title>
    <link href="css/styles.css" rel="stylesheet" />
    <link rel="stylesheet" href="css/custom-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/simple-datatables/7.1.2/style.min.css"
        integrity="sha512-dcdQkw+lfchKGZD+YmuSMwHBoR8AgJGrHXtBVXaxo2sMhlSKB0r04F2W9+BXCfdDjmP75EEl7oVNaHn2FTVNpQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/js/all.min.js"
        integrity="sha512-2bMhOkE/ACz21dJT8zBOMgMecNxx0d37NND803ExktKiKdSzdwn+L7i9fdccw/3V06gM/DBWKbYmQvKMdAA9Nw=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
        @media (max-width:767px) {
            .table-responsive-stack td {
                display: block;
                text-align: right;
                padding-left: 50%;
                position: relative
            }

            .table-responsive-stack td:before {
                content: attr(data-label);
                position: absolute;
                left: 6px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                text-align: left;
                font-weight: 700
            }

            .table-responsive-stack tr {
                display: block;
                margin-bottom: 1em;
                border-bottom: 2px solid #ddd
            }

            .table-responsive-stack th {
                display: none
            }
        }
    </style>
    <script src="js/matomo.js"> </script>
</head>

<body class="sb-nav-fixed">
    <?php include 'includes/navbar.php'; ?>
    <div id="layoutSidenav">
        <?php include 'includes/sidebar.php'; ?>
        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid px-4">
                    <h1 class="mt-4">Pesanan saya</h1>
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <?php foreach ($grouped_orders as $order): ?>
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h5>Pesanan #<?php echo $order['order_id']; ?></h5>
                                        <p><strong> Waktu Pemesanan : <?php echo $order['order_date']; ?></strong></p>
                                        <p><strong>Total Pembayaran : Rp
                                                <?php echo number_format($order['order_total'], 2, ',', '.'); ?></strong>
                                        </p>

                                        <!-- DOWNLOAD STRUK-->
                                        <a href="generate_receipt_pembeli.php?order_id=<?php echo $order['order_id']; ?>"
                                            class="btn btn-primary btn-sm float-end">
                                            <i class="fas fa-download"></i> Download Struk
                                        </a>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-responsive-stack">
                                            <thead>
                                                <tr>
                                                    <th>Tanaman</th>
                                                    <th>Jumlah</th>
                                                    <th>Harga</th>
                                                    <th>Petani</th>
                                                    <th>Status</th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($order['items'] as $item): ?>
                                                    <tr>
                                                        <td data-label="Tanaman">
                                                            <?php echo htmlspecialchars($item['plant_name']); ?>
                                                        </td>
                                                        <td data-label="Jumlah"><?php echo $item['kuantitas']; ?></td>
                                                        <td data-label="Harga">Rp
                                                            <?php echo number_format($item['item_total'], 2, ',', '.'); ?>
                                                        </td>
                                                        <td data-label="Petani">
                                                            <?php echo htmlspecialchars($item['petani_name']); ?><br>
                                                            <small>No HP:
                                                                <?php echo htmlspecialchars($item['petani_phone']); ?></small>
                                                        </td>
                                                        <td data-label="Status">
                                                            <?php echo ucfirst($item['order_item_status']); ?>
                                                        </td>
                                                        <td data-label="Aksi">
                                                            <?php if ($item['order_item_status'] == 'dikirim'): ?>
                                                                <form method="POST" class="mark-completed-form">
                                                                    <input type="hidden" name="order_item_id"
                                                                        value="<?php echo $item['order_item_id']; ?>">
                                                                    <button type="submit" name="mark_completed"
                                                                        class="btn btn-success btn-sm">Selesaikan pesanan</button>
                                                                </form>
                                                            <?php elseif ($item['order_item_status'] == 'pending'): ?>
                                                                <form method="POST" class="cancel-order-form">
                                                                    <input type="hidden" name="order_item_id"
                                                                        value="<?php echo $item['order_item_id']; ?>">
                                                                    <button type="submit" name="cancel_order"
                                                                        class="btn btn-danger btn-sm">Batalkan pesanan</button>
                                                                </form>
                                                            <?php elseif ($item['order_item_status'] == 'selesai'): ?>
                                                                <span class="text-success">Selesai</span>
                                                            <?php elseif ($item['order_item_status'] == 'dibatalkan'): ?>
                                                                <span class="text-danger">Dibatalkan</span>
                                                            <?php else: ?>
                                                                <?php echo ucfirst($item['order_item_status']); ?>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/js/bootstrap.bundle.min.js"
        integrity="sha512-i9cEfJwUwViEPFKdC1enz4ZRGBj8YQo6QByFTF92YXHi7waCqyexvRD75S5NVTsSiTv7rKWqG9Y5eFxmRsOn0A=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="js/scripts.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/simple-datatables/7.1.2/simple-datatables.min.js"
        integrity="sha512-3ty9AJncMgK2yFwGuF8Shc5dMwiXeHiEXV5QiOXrhuXzQLLorWeBEpmLWduNl49A9ffIyf+zmQ7nI1PQlUaRYg=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="js/datatables-simple-demo.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function () { function e() { 768 > $(window).width() ? $(".table-responsive-stack").each(function (e) { $(this).find(".table-responsive-stack-thead").show(), $(this).find("thead").hide() }) : $(".table-responsive-stack").each(function (e) { $(this).find(".table-responsive-stack-thead").hide(), $(this).find("thead").show() }) } $(".mark-received-form").on("submit", function (e) { e.preventDefault(); var t = $(this); $.ajax({ url: "my_orders.php", type: "POST", data: t.serialize(), success: function (e) { e.includes("success") ? t.replaceWith('<span class="text-success">Received</span>') : alert("Failed to mark item as received. Please try again.") }, error: function () { alert("An error occurred. Please try again.") } }) }), $("table.table-responsive-stack").each(function (e) { var t = $(this).attr("id"); $(this).find("th").each(function (e) { $("#" + t + " td:nth-child(" + (e + 1) + ")").prepend('<span class="table-responsive-stack-thead">' + $(this).text() + ":</span> "), $(".table-responsive-stack-thead").hide() }) }), $(".table-responsive-stack").each(function () { var e = $(this).find("th").length; $(this).find("th, td").css("flex-basis", 100 / e + "%") }), e(), window.onresize = function (t) { e() } });
    </script>
</body>

</html>