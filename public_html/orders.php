<?php
require_once 'config/db_connect.php';
require_once 'auth_middleware.php';
$user = requireRole('petani');
$user_id = $user['user_id'];
$role = $user['role'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get petani_id
$stmt = $pdo->prepare("SELECT petani_id FROM petani WHERE user_id = ?");
$stmt->execute([$user_id]);
$petani = $stmt->fetch(PDO::FETCH_ASSOC);
$petani_id = $petani['petani_id'];

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_item_id = $_POST['order_item_id'];
    $new_status = $_POST['new_status'];

    // Verify that this order item belongs to the current petani and the new status is valid
    $stmt = $pdo->prepare("
        SELECT oi.order_item_id, oi.order_item_status
        FROM order_items oi
        JOIN plants p ON oi.plant_id = p.plant_id
        WHERE oi.order_item_id = ? AND p.petani_id = ?
    ");
    $stmt->execute([$order_item_id, $petani_id]);
    $order_item = $stmt->fetch(PDO::FETCH_ASSOC);

    // Updated status validation logic
    if ($order_item) {
        $current_status = $order_item['order_item_status'];
        $valid_update = false;

        // Check if the current status is already 'dibatalkan' or 'selesai'
        if ($current_status === 'dibatalkan') {
            $error_message = "Pesanan yang sudah dibatalkan tidak dapat diubah statusnya.";
        } elseif ($current_status === 'selesai') {
            $error_message = "Pesanan yang sudah selesai tidak dapat diubah statusnya.";
        } else {
            // Valid status transitions
            switch ($current_status) {
                case 'pending':
                    // Can only move to 'diproses' or 'dibatalkan' from pending
                    $valid_update = in_array($new_status, ['diproses', 'dibatalkan']);
                    break;
                case 'diproses':
                    // Can only move to 'dikirim' from diproses
                    $valid_update = ($new_status === 'dikirim');
                    break;
                case 'dikirim':
                    // Cannot change status once shipped
                    $valid_update = false;
                    break;
            }

            if ($valid_update) {
                // Update the status
                $stmt = $pdo->prepare("UPDATE order_items SET order_item_status = ? WHERE order_item_id = ?");
                $stmt->execute([$new_status, $order_item_id]);
                $success_message = "Status pesanan berhasil diperbarui.";
            } else {
                $error_message = "Perubahan status tidak valid.";
            }
        }
    } else {
        $error_message = "Pesanan tidak ditemukan atau Anda tidak memiliki akses.";
    }
}



// Fetch order items for this petani

$stmt = $pdo->prepare("
    SELECT o.order_id, c.nama_lengkap as customer_name, c.alamat_pengiriman as customer_address,
           c.no_hp as customer_phone, oi.order_item_id, p.nama as plant_name, oi.kuantitas,
           oi.total_harga, o.order_date, oi.order_item_status, o.notes
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN plants p ON oi.plant_id = p.plant_id
    JOIN customers c ON o.customer_id = c.customer_id
    WHERE p.petani_id = ?
    ORDER BY o.order_date DESC, o.order_id
");
$stmt->execute([$petani_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group order items by order_id
$grouped_orders = [];
foreach ($order_items as $item) {
    $order_id = $item['order_id'];
    if (!isset($grouped_orders[$order_id])) {
        $grouped_orders[$order_id] = [
            'order_id' => $order_id,
            'customer_name' => $item['customer_name'],
            'customer_address' => $item['customer_address'],
            'customer_phone' => $item['customer_phone'],
            'order_date' => $item['order_date'],
            'notes' => $item['notes'],
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
    <title>Pesanan - Plant Inventory Jabon Mekar</title>
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
                    <h1 class="mt-4">Pesanan</h1>
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

                                        <p>Pembeli : <?php echo htmlspecialchars($order['customer_name']); ?></p>
                                        <p>Nomor Telepon : <?php echo htmlspecialchars($order['customer_phone']); ?></p>
                                        <p>Alamat pengiriman : <?php echo htmlspecialchars($order['customer_address']); ?>
                                        </p>
                                        <p>Waktu pemesanan : <?php echo $order['order_date']; ?></p>
                                        <p>Catatan : <?php echo htmlspecialchars($order['notes']); ?></p>

                                        <!-- Download Receipt Button -->
                                        <a href="download_receipt.php?order_item_id=<?php echo $item['order_item_id']; ?>"
                                            class="btn btn-success btn-sm mt-2">
                                            <i class="fas fa-download"></i> Download Struk
                                        </a>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-responsive-stack">
                                            <thead>
                                                <tr>
                                                    <th>Tanaman</th>
                                                    <th>Jumlah</th>
                                                    <th>Total Pembayaran</th>
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
                                                        <td data-label="Total Pembayaran">Rp
                                                            <?php echo number_format($item['total_harga'], 2, ',', '.'); ?>
                                                        </td>
                                                        <td data-label="Status">
                                                            <?php echo ucfirst($item['order_item_status']); ?>
                                                        </td>
                                                        <td data-label="Aksi">
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="order_item_id"
                                                                    value="<?php echo $item['order_item_id']; ?>">
                                                                <select name="new_status" class="form-select form-select-sm"
                                                                    style="width: auto; display: inline-block;" <?php echo in_array($item['order_item_status'], ['dibatalkan', 'selesai']) ? 'disabled' : ''; ?>>
                                                                    <option value="diproses" <?php echo $item['order_item_status'] === 'pending' ? '' : 'disabled'; ?>>Diproses</option>
                                                                    <option value="dikirim" <?php echo $item['order_item_status'] === 'diproses' ? '' : 'disabled'; ?>>Dikirim</option>
                                                                    <option value="dibatalkan" <?php echo $item['order_item_status'] === 'pending' ? '' : 'disabled'; ?>>Dibatalkan</option>
                                                                </select>
                                                                <button type="submit" name="update_status"
                                                                    class="btn btn-primary btn-sm" <?php echo in_array($item['order_item_status'], ['dibatalkan', 'selesai']) ? 'disabled' : ''; ?>>
                                                                    Update
                                                                </button>
                                                            </form>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/simple-datatables/7.1.2/simple-datatables.min.js"
        integrity="sha512-3ty9AJncMgK2yFwGuF8Shc5dMwiXeHiEXV5QiOXrhuXzQLLorWeBEpmLWduNl49A9ffIyf+zmQ7nI1PQlUaRYg=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"
        integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="js/datatables-simple-demo.js"></script>
    <script src="js/scripts.js"></script>
    <script>
        $(document).ready(function () { function t() { 768 > $(window).width() ? $(".table-responsive-stack").each(function (t) { $(this).find(".table-responsive-stack-thead").show(), $(this).find("thead").hide() }) : $(".table-responsive-stack").each(function (t) { $(this).find(".table-responsive-stack-thead").hide(), $(this).find("thead").show() }) } $("table.table-responsive-stack").each(function (t) { var e = $(this).attr("id"); $(this).find("th").each(function (t) { $("#" + e + " td:nth-child(" + (t + 1) + ")").prepend('<span class="table-responsive-stack-thead">' + $(this).text() + ":</span> "), $(".table-responsive-stack-thead").hide() }) }), $(".table-responsive-stack").each(function () { var t = $(this).find("th").length; $(this).find("th, td").css("flex-basis", 100 / t + "%") }), t(), window.onresize = function (e) { t() } });
    </script>
</body>

</html>