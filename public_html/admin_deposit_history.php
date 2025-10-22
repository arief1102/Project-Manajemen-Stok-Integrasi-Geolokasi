<?php
require_once 'config/db_connect.php';

require_once 'auth_middleware.php';
$user = requireRole('admin');

$user_id = $user['user_id'];
$role = $user['role'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle deposit status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deposit_id']) && isset($_POST['new_status'])) {
    $deposit_id = $_POST['deposit_id'];
    $new_status = $_POST['new_status'];

    $stmt = $pdo->prepare("UPDATE deposits SET deposit_status = ? WHERE deposit_id = ?");
    $stmt->execute([$new_status, $deposit_id]);

    if ($new_status === 'berhasil') {
        // Fetch deposit amount and customer_id
        $stmt = $pdo->prepare("SELECT amount, customer_id FROM deposits WHERE deposit_id = ?");
        $stmt->execute([$deposit_id]);
        $deposit = $stmt->fetch(PDO::FETCH_ASSOC);

        // Update customer balance
        $stmt = $pdo->prepare("UPDATE customers SET balance = balance + ? WHERE customer_id = ?");
        $stmt->execute([$deposit['amount'], $deposit['customer_id']]);
    }

    header("Location: admin_deposit_history.php");
    exit();
}

// Fetch all deposits with customer details including phone number
$stmt = $pdo->prepare("
    SELECT d.*, c.nama_lengkap, c.no_hp 
    FROM deposits d 
    JOIN customers c ON d.customer_id = c.customer_id 
    ORDER BY d.created_at DESC
");
$stmt->execute();
$deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Riwayat Deposit - Admin Plant Inventory Jabon Mekar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/css/bootstrap.min.css"
        integrity="sha512-SbiR/eusphKoMVVXysTKG/7VseWii+Y3FdHrt0EpKgpToZeemhqHeZeLWLhJutz/2ut2Vw1uQEj2MbRF+TVBUA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/simple-datatables/7.1.2/style.min.css"
        integrity="sha512-dcdQkw+lfchKGZD+YmuSMwHBoR8AgJGrHXtBVXaxo2sMhlSKB0r04F2W9+BXCfdDjmP75EEl7oVNaHn2FTVNpQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.14.0/sweetalert2.min.css"
        integrity="sha512-WxRv0maH8aN6vNOcgNFlimjOhKp+CUqqNougXbz0E+D24gP5i+7W/gcc5tenxVmr28rH85XHF5eXehpV2TQhRg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="css/styles.css" rel="stylesheet" />
    <link rel="stylesheet" href="css/custom-styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/js/all.min.js"
        integrity="sha512-2bMhOkE/ACz21dJT8zBOMgMecNxx0d37NND803ExktKiKdSzdwn+L7i9fdccw/3V06gM/DBWKbYmQvKMdAA9Nw=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
        .card-header,.table-responsive{padding:20px}.card{border:none;border-radius:10px;box-shadow:0 0 10px rgba(0,0,0,.1)}.card-header{background-color:#f8f9fa;border-bottom:none}.btn-action,.status-badge{padding:5px 10px;font-size:.8rem;border-radius:20px}.table th{white-space:nowrap}.status-badge{font-weight:600}.status-badge-berhasil{background-color:#d4edda;color:#155724}.status-badge-pending{background-color:#fff3cd;color:#856404}.status-badge-dibatalkan{background-color:#f8d7da;color:#721c24}.btn-action{margin:2px}@media (max-width:767.98px){.table-responsive{padding:10px}.table td{display:block;padding:.5rem;text-align:right;min-height:2.5rem}.table td::before{content:attr(data-label);float:left;font-weight:700;text-transform:uppercase}.table td[data-label=Aksi]:empty,.table thead{display:none}.table tr{display:block;margin-bottom:1rem;border:1px solid #dee2e6;border-radius:5px}.btn-action{display:block;width:100%;margin-bottom:5px}.table td[data-label="No. HP"]::before{content:"No. HP"}}
    </style>
</head>

<body class="sb-nav-fixed">
    <?php include 'includes/navbar.php'; ?>
    <div id="layoutSidenav">
        <?php include 'includes/sidebar.php'; ?>
        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid px-4">
                    <h1 class="mt-4">Riwayat Deposit</h1>
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-table me-1"></i>
                            Daftar Deposit Customer
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nama</th>
                                        <th>No. HP</th>
                                        <th>Jumlah</th>
                                        <th>Status</th>
                                        <th>Tanggal Dibuat</th>
                                        <th>Tanggal Diperbarui</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($deposits as $deposit): ?>
                                        <tr>
                                            <td data-label="ID"><?php echo $deposit['deposit_id']; ?></td>
                                            <td data-label="Nama"><?php echo $deposit['nama_lengkap']; ?></td>
                                            <td data-label="No. HP"><?php echo $deposit['no_hp']; ?></td>
                                            <td data-label="Jumlah">Rp
                                                <?php echo number_format($deposit['amount'], 2, ',', '.'); ?>
                                            </td>
                                            <td data-label="Status">
                                                <span
                                                    class="status-badge status-badge-<?php echo $deposit['deposit_status']; ?>">
                                                    <?php echo ucfirst($deposit['deposit_status']); ?>
                                                </span>
                                            </td>
                                            <td data-label="Tanggal Dibuat"><?php echo $deposit['created_at']; ?></td>
                                            <td data-label="Tanggal Diperbarui"><?php echo $deposit['updated_at']; ?></td>
                                            <td data-label="Aksi">
                                                <?php if ($deposit['deposit_status'] === 'pending'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="deposit_id"
                                                            value="<?php echo $deposit['deposit_id']; ?>">
                                                        <input type="hidden" name="new_status" value="berhasil">
                                                        <button type="submit"
                                                            class="btn btn-success btn-action">Selesai</button>
                                                    </form>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="deposit_id"
                                                            value="<?php echo $deposit['deposit_id']; ?>">
                                                        <input type="hidden" name="new_status" value="dibatalkan">
                                                        <button type="submit" class="btn btn-danger btn-action">Batal</button>
                                                    </form>
                                                <?php endif; ?>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/js/bootstrap.bundle.min.js"
        integrity="sha512-i9cEfJwUwViEPFKdC1enz4ZRGBj8YQo6QByFTF92YXHi7waCqyexvRD75S5NVTsSiTv7rKWqG9Y5eFxmRsOn0A=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.14.0/sweetalert2.all.min.js"
        integrity="sha512-vHKpHh3VBF4B8QqZ1ppqnNb8zoTBceER6pyGb5XQyGtkCmeGwxDi5yyCmFLZA4Xuf9Jn1LBoAnx9sVvy+MFjNg=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/simple-datatables/7.1.2/simple-datatables.min.js"
        integrity="sha512-3ty9AJncMgK2yFwGuF8Shc5dMwiXeHiEXV5QiOXrhuXzQLLorWeBEpmLWduNl49A9ffIyf+zmQ7nI1PQlUaRYg=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="js/datatables-simple-demo.js"></script>
    <script src="js/scripts.js"></script>
</body>

</html>