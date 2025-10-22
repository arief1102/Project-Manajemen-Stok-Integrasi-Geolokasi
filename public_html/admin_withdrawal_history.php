<?php
require_once 'config/db_connect.php';

require_once 'auth_middleware.php';
$user = requireRole('admin');
$user_id = $user['user_id'];
$role = $user['role'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle withdrawal status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdrawal_id']) && isset($_POST['new_status'])) {
    $withdrawal_id = $_POST['withdrawal_id'];
    $new_status = $_POST['new_status'];

    $stmt = $pdo->prepare("UPDATE withdrawals SET withdrawal_status = ? WHERE withdrawal_id = ?");
    $stmt->execute([$new_status, $withdrawal_id]);

    if ($new_status === 'berhasil') {
        // Fetch withdrawal amount and petani_id
        $stmt = $pdo->prepare("SELECT amount, petani_id FROM withdrawals WHERE withdrawal_id = ?");
        $stmt->execute([$withdrawal_id]);
        $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);

        // Update petani balance
        $stmt = $pdo->prepare("UPDATE petani SET balance = balance - ? WHERE petani_id = ?");
        $stmt->execute([$withdrawal['amount'], $withdrawal['petani_id']]);
    }

    header("Location: admin_withdrawal_history.php");
    exit();
}
// Fetch all withdrawals with petani details including phone number
$stmt = $pdo->prepare("
    SELECT w.*, p.nama_lengkap, p.no_hp 
    FROM withdrawals w 
    JOIN petani p ON w.petani_id = p.petani_id 
    ORDER BY w.created_at DESC
");
$stmt->execute();
$withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Riwayat Penarikan - Admin Plant Inventory Jabon Mekar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/css/bootstrap.min.css"
        integrity="sha512-SbiR/eusphKoMVVXysTKG/7VseWii+Y3FdHrt0EpKgpToZeemhqHeZeLWLhJutz/2ut2Vw1uQEj2MbRF+TVBUA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.14.0/sweetalert2.min.css"
        integrity="sha512-WxRv0maH8aN6vNOcgNFlimjOhKp+CUqqNougXbz0E+D24gP5i+7W/gcc5tenxVmr28rH85XHF5eXehpV2TQhRg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/simple-datatables/7.1.2/style.min.css"
        integrity="sha512-dcdQkw+lfchKGZD+YmuSMwHBoR8AgJGrHXtBVXaxo2sMhlSKB0r04F2W9+BXCfdDjmP75EEl7oVNaHn2FTVNpQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="css/styles.css" rel="stylesheet" />
    <link rel="stylesheet" href="css/custom-styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/js/all.min.js"
        integrity="sha512-2bMhOkE/ACz21dJT8zBOMgMecNxx0d37NND803ExktKiKdSzdwn+L7i9fdccw/3V06gM/DBWKbYmQvKMdAA9Nw=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
       .e-wallet-badge,.status-badge{font-weight:600}.card-header,.table-responsive{padding:20px}.card{border:none;border-radius:10px;box-shadow:0 0 10px rgba(0,0,0,.1)}.card-header{background-color:#f8f9fa;border-bottom:none}.btn-action,.status-badge{padding:5px 10px;font-size:.8rem;border-radius:20px}.table th{white-space:nowrap}.status-badge-berhasil{background-color:#d4edda;color:#155724}.status-badge-pending{background-color:#fff3cd;color:#856404}.status-badge-dibatalkan{background-color:#f8d7da;color:#721c24}.btn-action{margin:2px}@media (max-width:767.98px){.table-responsive{padding:10px}.table td{display:block;padding:.5rem;text-align:right;min-height:2.5rem}.table td::before{content:attr(data-label);float:left;font-weight:700;text-transform:uppercase}.table td[data-label=Aksi]:empty,.table thead{display:none}.table tr{display:block;margin-bottom:1rem;border:1px solid #dee2e6;border-radius:5px}.btn-action{display:block;width:100%;margin-bottom:5px}.table td[data-label="No. HP"]::before{content:"No. HP"}}.e-wallet-badge{display:inline-block;padding:5px 10px;border-radius:20px;font-size:.8rem;color:#fff}.e-wallet-ovo{background-color:#4c3494}.e-wallet-dana{background-color:#008ceb}.e-wallet-gopay{background-color:#00aa13}
    </style>
</head>

<body class="sb-nav-fixed">
    <?php include 'includes/navbar.php'; ?>
    <div id="layoutSidenav">
        <?php include 'includes/sidebar.php'; ?>
        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid px-4">
                    <h1 class="mt-4">Riwayat Penarikan</h1>
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-table me-1"></i>
                            Daftar Penarikan Petani
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nama Petani</th>
                                        <th>No. HP</th>
                                        <th>Jumlah</th>
                                        <th>Status</th>
                                        <th>E-Wallet</th>
                                        <th>Tanggal Dibuat</th>
                                        <th>Tanggal Diperbarui</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($withdrawals as $withdrawal): ?>
                                        <tr>
                                            <td data-label="ID"><?php echo $withdrawal['withdrawal_id']; ?></td>
                                            <td data-label="Nama Petani"><?php echo $withdrawal['nama_lengkap']; ?></td>
                                            <td data-label="No. HP"><?php echo $withdrawal['no_hp']; ?></td>
                                            <td data-label="Jumlah">Rp
                                                <?php echo number_format($withdrawal['amount'], 2, ',', '.'); ?>
                                            </td>
                                            <td data-label="Status">
                                                <span
                                                    class="status-badge status-badge-<?php echo $withdrawal['withdrawal_status']; ?>">
                                                    <?php echo ucfirst($withdrawal['withdrawal_status']); ?>
                                                </span>
                                            </td>
                                            <td data-label="E-Wallet">
                                                <span
                                                    class="e-wallet-badge e-wallet-<?php echo strtolower($withdrawal['e_wallet']); ?>">
                                                    <?php echo strtoupper($withdrawal['e_wallet']); ?>
                                                </span>
                                            </td>
                                            <td data-label="Tanggal Dibuat"><?php echo $withdrawal['created_at']; ?></td>
                                            <td data-label="Tanggal Diperbarui"><?php echo $withdrawal['updated_at']; ?>
                                            </td>
                                            <td data-label="Aksi">
                                                <?php if ($withdrawal['withdrawal_status'] === 'pending'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="withdrawal_id"
                                                            value="<?php echo $withdrawal['withdrawal_id']; ?>">
                                                        <input type="hidden" name="new_status" value="berhasil">
                                                        <button type="submit"
                                                            class="btn btn-success btn-action">Selesai</button>
                                                    </form>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="withdrawal_id"
                                                            value="<?php echo $withdrawal['withdrawal_id']; ?>">
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