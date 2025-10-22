<?php

require_once 'config/db_connect.php';
require_once 'auth_middleware.php';
$user = requireRole('admin');
$user_id = $user['user_id'];
$role = $user['role'];

// Fetch all petani
$stmt = $pdo->prepare("
    SELECT u.user_id, u.username, u.email, p.nama_lengkap, p.no_hp, p.balance
    FROM users u
    JOIN petani p ON u.user_id = p.user_id
    WHERE u.role = 'petani'
");

$stmt->execute();
$petani = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Kelola Petani - Plant Inventory Jabon Mekar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/simple-datatables/7.1.2/style.min.css"
        integrity="sha512-dcdQkw+lfchKGZD+YmuSMwHBoR8AgJGrHXtBVXaxo2sMhlSKB0r04F2W9+BXCfdDjmP75EEl7oVNaHn2FTVNpQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/css/bootstrap.min.css"
        integrity="sha512-SbiR/eusphKoMVVXysTKG/7VseWii+Y3FdHrt0EpKgpToZeemhqHeZeLWLhJutz/2ut2Vw1uQEj2MbRF+TVBUA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="css/styles.css" rel="stylesheet" />
    <link rel="stylesheet" href="css/custom-styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/js/all.min.js"
        integrity="sha512-2bMhOkE/ACz21dJT8zBOMgMecNxx0d37NND803ExktKiKdSzdwn+L7i9fdccw/3V06gM/DBWKbYmQvKMdAA9Nw=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
       .modal-xl{max-width:90%}.order-card{border:1px solid #e0e0e0;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,.1);margin-bottom:20px;transition:.3s}.order-card:hover{box-shadow:0 4px 8px rgba(0,0,0,.15)}.order-header{background-color:#f8f9fa;border-bottom:1px solid #e0e0e0;padding:15px;border-radius:8px 8px 0 0}.order-body{padding:15px}.table-responsive{margin-top:15px}.status-badge{padding:5px 10px;border-radius:20px;font-size:.8em;font-weight:700}.status-pending{background-color:#ffeeba;color:#856404}.status-diproses{background-color:#b8daff;color:#004085}.status-dikirim{background-color:#c3e6cb;color:#155724}.status-selesai{background-color:#d4edda;color:#155724}
    </style>

</head>
<body class="sb-nav-fixed">
    <?php include 'includes/navbar.php'; ?>
    <div id="layoutSidenav">
        <?php include 'includes/sidebar.php'; ?>
        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid px-4">
                    <h1 class="mt-4">Kelola Petani</h1>
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-table me-1"></i>
                           Data Petani
                        </div>
                        <div class="card-body">
                            <table id="datatablesSimple">
                                <thead>
                                    <tr>
                                        <th>User ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Nama Lengkap</th>
                                        <th>No. HP</th>
                                        <th>Saldo</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($petani as $p): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($p['user_id']); ?></td>
                                            <td><?php echo htmlspecialchars($p['username']); ?></td>
                                            <td><?php echo htmlspecialchars($p['email']); ?></td>
                                            <td><?php echo htmlspecialchars($p['nama_lengkap']); ?></td>
                                            <td><?php echo htmlspecialchars($p['no_hp']); ?></td>
                                            <td>Rp <?php echo number_format($p['balance'], 2, ',', '.'); ?></td>
                                            <td>
                                                <button class="btn btn-primary btn-sm"
                                                    onclick="viewOrderHistory(<?php echo $p['user_id']; ?>)">Riwayat
                                                    Penjualan</button>

                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <!-- Modal for displaying order history -->
    <div class="modal fade" id="orderHistoryModal" tabindex="-1" aria-labelledby="orderHistoryModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="orderHistoryModalLabel">Riwayat Pesanan Petani</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body" id="orderHistoryModalBody">
                    <!-- Order history will be loaded here -->
                </div>
            </div>
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
    <script>
        function viewOrderHistory(userId) {
            // Fetch order history for the petani
            fetch(`get_petani_order_history.php?user_id=${userId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('orderHistoryModalBody').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('orderHistoryModal')).show();
                });
        }
    </script>
</body>
</html>