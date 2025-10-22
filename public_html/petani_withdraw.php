<?php
require_once 'config/db_connect.php';
require_once 'includes/color_scheme.php';
require_once 'auth_middleware.php';
$user = requireRole('petani');

$user_id = $user['user_id'];
$role = $user['role'];
$colorScheme = getColorScheme($role);

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch petani details
$stmt = $pdo->prepare("SELECT * FROM petani WHERE user_id = ?");
$stmt->execute([$user_id]);
$petani = $stmt->fetch(PDO::FETCH_ASSOC);

// Check for pending withdrawals
$stmt = $pdo->prepare("SELECT COUNT(*) FROM withdrawals WHERE petani_id = ? AND withdrawal_status = 'pending'");
$stmt->execute([$petani['petani_id']]);
$pending_withdrawals = $stmt->fetchColumn();

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($pending_withdrawals > 0) {
        $message = json_encode([
            'type' => 'error',
            'text' => "Anda memiliki permintaan penarikan yang masih dalam proses. Silakan tunggu hingga proses selesai sebelum membuat permintaan baru."
        ]);
    } else {
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
        $e_wallet = filter_input(INPUT_POST, 'e_wallet', FILTER_SANITIZE_STRING);

        if ($amount && $amount > 0 && in_array($e_wallet, ['dana', 'ovo', 'gopay'])) {
            if ($amount <= $petani['balance']) {
                $stmt = $pdo->prepare("INSERT INTO withdrawals (petani_id, amount, e_wallet) VALUES (?, ?, ?)");
                if ($stmt->execute([$petani['petani_id'], $amount, $e_wallet])) {
                    $message = json_encode([
                        'type' => 'success',
                        'amount' => number_format($amount, 2, ',', '.'),
                        'no_hp' => $petani['no_hp'],
                        'e_wallet' => strtoupper($e_wallet)
                    ]);
                } else {
                    $message = json_encode([
                        'type' => 'error',
                        'text' => "Terjadi kesalahan saat membuat permintaan penarikan."
                    ]);
                }
            } else {
                $message = json_encode([
                    'type' => 'error',
                    'text' => "Saldo tidak mencukupi untuk melakukan penarikan."
                ]);
            }
        } else {
            $message = json_encode([
                'type' => 'error',
                'text' => "Mohon masukkan jumlah yang valid dan pilih e-wallet."
            ]);
        }
    }
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
    <title>Penarikan Saldo - Plant Inventory Jabon Mekar</title>
    <link href="css/styles.css" rel="stylesheet" />
    <link rel="stylesheet" href="css/custom-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css"
        integrity="sha512-jnSuA4Ss2PkkikSOLtYs8BlYIeeIK1h99ty4YfvRPAlzr377vr3CXDb7sb7eEEBYjDtcYj+AjBH3FLv5uSJuXg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.14.0/sweetalert2.min.css"
        integrity="sha512-WxRv0maH8aN6vNOcgNFlimjOhKp+CUqqNougXbz0E+D24gP5i+7W/gcc5tenxVmr28rH85XHF5eXehpV2TQhRg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/js/all.min.js"
        integrity="sha512-2bMhOkE/ACz21dJT8zBOMgMecNxx0d37NND803ExktKiKdSzdwn+L7i9fdccw/3V06gM/DBWKbYmQvKMdAA9Nw=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
      .withdraw-header{color:#fff;padding:2rem 0;margin-bottom:2rem;border-radius:0 0 50% 50%/20px;text-align:center;background:#218cbc;background:-o-linear-gradient(315deg,#218cbc,#918485);background:linear-gradient(135deg,#218cbc,#918485)}.withdraw-card{border:none;border-radius:15px;-webkit-box-shadow:0 0 30px rgba(0,0,0,.1);box-shadow:0 0 30px rgba(0,0,0,.1);overflow:hidden}.withdraw-card .card-header{background-color:#f8f9fa;border-bottom:none;padding:1.5rem;text-align:center}.withdraw-card .card-body{padding:2rem}.current-balance{font-size:2.5rem;font-weight:700;color:#4caf50}.btn,.form-control,.form-select{border-radius:25px}.btn-primary{border:none;padding:10px 30px;font-weight:700;text-transform:uppercase;letter-spacing:1px;-webkit-transition:.3s;-o-transition:.3s;transition:.3s}.btn-primary:hover{-webkit-transform:translateY(-3px);-ms-transform:translateY(-3px);transform:translateY(-3px);-webkit-box-shadow:0 5px 15px rgba(0,0,0,.2);box-shadow:0 5px 15px rgba(0,0,0,.2)}.withdraw-icon{font-size:3rem;margin-bottom:1rem}@-webkit-keyframes float{0%,100%{-webkit-transform:translateY(0);transform:translateY(0)}50%{-webkit-transform:translateY(-10px);transform:translateY(-10px)}}@keyframes float{0%,100%{-webkit-transform:translateY(0);transform:translateY(0)}50%{-webkit-transform:translateY(-10px);transform:translateY(-10px)}}.floating-icon{-webkit-animation:3s ease-in-out infinite float;animation:3s ease-in-out infinite float}.e-wallet-options{display:-webkit-box;display:-ms-flexbox;display:flex;-webkit-box-pack:justify;-ms-flex-pack:justify;justify-content:space-between;margin-bottom:1rem}.e-wallet-option{-webkit-box-flex:1;-ms-flex:1;flex:1;text-align:center;padding:1rem;border:2px solid #e0e0e0;border-radius:10px;cursor:pointer;-webkit-transition:.3s;-o-transition:.3s;transition:.3s}.e-wallet-option.selected,.e-wallet-option:hover{border-color:#4caf50;background-color:#e8f5e9}.e-wallet-option i{font-size:2rem;margin-bottom:.5rem}
    </style>
    <script src="js/matomo.js"> </script>
</head>

<body class="sb-nav-fixed">
    <?php include 'includes/navbar.php'; ?>
    <div id="layoutSidenav">
        <?php include 'includes/sidebar.php'; ?>
        <div id="layoutSidenav_content">
            <main>
                <div class="withdraw-header">
                    <div class="container">
                        <i class="fas fa-money-bill-wave withdraw-icon floating-icon"></i>
                        <h1 class="mt-2">Penarikan Saldo</h1>
                    </div>
                </div>
                <div class="container-fluid px-4">
                    <div class="row justify-content-center">
                        <div class="col-lg-6">
                            <div class="withdraw-card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Saldo Anda Sekarang</h5>
                                    <div class="current-balance">
                                        Rp <span
                                            id="currentBalance"><?php echo number_format($petani['balance'], 2, ',', '.'); ?></span>
                                    </div>
                                    <div class="petani-phone mt-4">


                                        <span> <strong> No. Telepon anda :
                                                <?php echo $petani['no_hp']; ?></strong></span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php if ($pending_withdrawals > 0): ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            Anda memiliki permintaan penarikan yang masih dalam proses. Silakan tunggu
                                            hingga proses selesai sebelum membuat permintaan baru.
                                        </div>
                                    <?php endif; ?>

                                    <form id="withdrawForm" method="POST">
                                        <div class="mb-4">
                                            <label for="amount" class="form-label">Jumlah yang ingin ditarik
                                                (Rp)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">Rp</span>
                                                <input type="number" step="0.01" min="0"
                                                    max="<?php echo $petani['balance']; ?>" class="form-control"
                                                    id="amount" name="amount" required>
                                            </div>
                                        </div>
                                        <div class="mb-4">
                                            <label class="form-label">Pilih E-Wallet yang terhubung dengan No. Telepon
                                                anda</label>
                                            <div class="e-wallet-options">
                                                <div class="e-wallet-option" data-value="dana">
                                                    <i class="fas fa-wallet"></i>
                                                    <div>DANA</div>
                                                </div>
                                                <div class="e-wallet-option" data-value="ovo">
                                                    <i class="fas fa-mobile-alt"></i>
                                                    <div>OVO</div>
                                                </div>
                                                <div class="e-wallet-option" data-value="gopay">
                                                    <i class="fas fa-motorcycle"></i>
                                                    <div>GoPay</div>
                                                </div>
                                            </div>
                                            <input type="hidden" id="e_wallet" name="e_wallet" required>
                                        </div>
                                        <div class="text-center">
                                            <button type="submit" class="btn btn-primary btn-lg">
                                                <i class="fas fa-hand-holding-usd me-2"></i>Tarik Saldo
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
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
    <script src="js/scripts.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('withdrawForm');
            const eWalletOptions = document.querySelectorAll('.e-wallet-option');
            const eWalletInput = document.getElementById('e_wallet');
            const message = <?php echo $message ? $message : 'null'; ?>;

            eWalletOptions.forEach(option => {
                option.addEventListener('click', function () {
                    eWalletOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                    eWalletInput.value = this.dataset.value;
                });
            });

            // Function to format number as Indonesian Rupiah
            function formatRupiah(number) {
                return new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }).format(number);
            }


            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const formData = new FormData(form);
                    const amount = formData.get('amount');
                    const eWallet = formData.get('e_wallet');
                    const formattedAmount = formatRupiah(amount);


                    if (!eWallet) {
                        Swal.fire({
                            title: 'Error',
                            text: 'Please select an e-wallet',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                        return;
                    }

                    Swal.fire({
                        title: 'Konfirmasi Penarikan',
                        text: `${formattedAmount} akan ditransfer ke No. HP ${<?php echo json_encode($petani['no_hp']); ?>} melaui ${eWallet.toUpperCase()}`,
                        icon: 'info',
                        showCancelButton: true,
                        confirmButtonText: 'Ya, Tarik Saldo',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
            }

            if (message) {
                if (message.type === 'success') {
                    Swal.fire({
                        title: 'Penarikan Berhasil',
                        text: `Berhasil menarik Rp ${message.amount} ke ${message.no_hp} via ${message.e_wallet}. Silakan pantau transaksi pada halaman riwayat penarikan.`,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.href = 'petani_withdrawal_history.php';
                    });
                } else if (message.type === 'error') {
                    Swal.fire({
                        title: 'Gagal',
                        text: message.text,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            }
        });
    </script>
</body>

</html>