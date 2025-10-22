<?php
require_once 'color_scheme.php';
require_once 'config/db_connect.php';
require_once 'auth_middleware.php';

$user = authenticate();

if (!$user) {
    header("Location: login.php");
    exit();
}

$user_id = $user['user_id'];
$role = $user['role'];
$colorScheme = getColorScheme($role);

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user_details = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div id="layoutSidenav_nav">
<nav class="sb-sidenav accordion <?php echo $colorScheme['sidebar']; ?>" id="sidenavAccordion">
        <div class="sb-sidenav-menu">
            <div class="nav">
                <?php if ($role == 'admin'): ?>
                    <div class="sb-sidenav-menu-heading">Admin</div>
                    <a class="nav-link" href="manage_customers.php">
                        <div class="sb-nav-link-icon"><i class="fas fa-users"></i></div>
                        Data Pembeli
                    </a>
                    <a class="nav-link" href="manage_petani.php">
                        <div class="sb-nav-link-icon"><i class="fas fa-users"></i></div>
                        Data Petani
                    </a>
                    <div class="sb-sidenav-menu-heading">Transaksi</div>
                    <a class="nav-link" href="admin_deposit_history.php">
                        <div class="sb-nav-link-icon"><i class="fas fa-history"></i></div>
                        Riwayat Deposit
                    </a>
                    <a class="nav-link" href="admin_withdrawal_history.php">
                        <div class="sb-nav-link-icon"><i class="fas fa-history"></i></div>
                        Riwayat Penarikan
                    </a>
                <?php else: ?>
                    <div class="sb-sidenav-menu-heading">Utama</div>
                    <a class="nav-link" href="index.php">
                        <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                        Beranda
                    </a>

                    <?php if ($role == 'petani' || $role == 'pembeli'): ?>
                        <div class="sb-sidenav-menu-heading">Tanaman</div>
                        <?php if ($role == 'petani'): ?>
                            <a class="nav-link" href="manage_plants.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-seedling"></i></div>
                                Kelola Tanaman
                            </a>
                            <a class="nav-link" href="lihat_tanaman.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-eye"></i></div>
                                Lihat Tanaman
                            </a>
                        <?php endif; ?>
                        <?php if ($role == 'pembeli'): ?>
                            <a class="nav-link" href="browse_plants.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-leaf"></i></div>
                                Lihat Tanaman
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="sb-sidenav-menu-heading">Pesanan</div>
                    <?php if ($role == 'petani'): ?>
                        <a class="nav-link" href="orders.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-shopping-bag"></i></div>
                            Lihat Pesanan
                        </a>
                    <?php endif; ?>
                    <?php if ($role == 'pembeli'): ?>
                        <a class="nav-link" href="my_orders.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-shopping-bag"></i></div>
                            Pesanan Saya
                        </a>
                    <?php endif; ?>

                    <?php if ($role === 'pembeli'): ?>
                        <div class="sb-sidenav-menu-heading">Deposit</div>
                        <a class="nav-link" href="deposit_history.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-history"></i></div>
                            Riwayat Deposit
                        </a>
                    <?php elseif ($role === 'petani'): ?>
                        <div class="sb-sidenav-menu-heading">Transaksi</div>
                        <a class="nav-link" href="petani_withdraw.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-money-bill-wave"></i></div>
                            Penarikan Saldo
                        </a>
                        <a class="nav-link" href="petani_withdrawal_history.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-history"></i></div>
                            Riwayat Penarikan
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="sb-sidenav-footer">
            <div class="small">Masuk sebagai:</div>
            <?php echo htmlspecialchars($user_details['username']); ?> (<?php echo ucfirst($role); ?>)
        </div>
    </nav>
</div>

<style>
   .bg-light-green{background-color:#e8f5e9!important}.bg-light-brown{background-color:#f5e0d3!important}.sb-sidenav-menu .nav-link{color:<?php echo ($role==='admin') ? 'rgba(255, 255, 255, 0.5)':'#333';?>!important;font-weight:600}.sb-sidenav-menu .nav-link:hover{color:<?php echo ($role==='admin') ? '#fff':'#000';?>!important;background-color:rgb(0 0 0 / .1)}.sb-sidenav-footer{background-color:<?php echo ($role==='admin') ? '#343a40':'rgba(0, 0, 0, 0.1)';?>;color:<?php echo ($role==='admin') ? '#fff':'#333';?>}
</style>