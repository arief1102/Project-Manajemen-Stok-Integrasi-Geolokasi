<?php
require_once 'config/db_connect.php';
require_once 'auth_middleware.php';

$user = requireRole('pembeli');

if (!$user) {
    header("Location: login.php");
    exit();
}

$user_id = $user['user_id'];

$stmt = $pdo->prepare("SELECT * FROM customers WHERE user_id = ?");
$stmt->execute([$user_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);

    if ($amount && $amount > 0) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO deposits (customer_id, amount) VALUES (?, ?)");
            $stmt->execute([$customer['customer_id'], $amount]);

            $pdo->commit();

            $message = "Permintaan deposit berhasil dibuat. Silakan transfer ke nomor yang ditampilkan.";
            $message_type = "success";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Terjadi kesalahan saat membuat permintaan deposit.";
            $message_type = "error";
            error_log("Database error: " . $e->getMessage());
        }
    } else {
        $message = "Mohon masukkan jumlah yang valid.";
        $message_type = "error";
    }
}

// Fetch current balance
$current_balance = $customer['balance'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Tambah Saldo - Plant Inventory Jabon Mekar</title>
    <link href="css/styles.css" rel="stylesheet" />
    <link rel="stylesheet" href="css/custom-styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/js/all.min.js"
        integrity="sha512-2bMhOkE/ACz21dJT8zBOMgMecNxx0d37NND803ExktKiKdSzdwn+L7i9fdccw/3V06gM/DBWKbYmQvKMdAA9Nw=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.14.0/sweetalert2.min.css"
        integrity="sha512-WxRv0maH8aN6vNOcgNFlimjOhKp+CUqqNougXbz0E+D24gP5i+7W/gcc5tenxVmr28rH85XHF5eXehpV2TQhRg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        .btn-primary,
        .instruction-title {
            text-transform: uppercase;
            letter-spacing: 1px
        }

        .balance-header,
        .btn-primary {
            background: -o-linear-gradient(315deg, #4caf50, #2196f3);
            background: linear-gradient(135deg, #4caf50, #2196f3)
        }

        .balance-header {
            color: #fff;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 50% 50%/20px;
            text-align: center
        }

        .balance-card {
            border: none;
            border-radius: 15px;
            -webkit-box-shadow: 0 0 30px rgba(0, 0, 0, .1);
            box-shadow: 0 0 30px rgba(0, 0, 0, .1);
            overflow: hidden
        }

        .balance-card .card-header {
            background-color: #f8f9fa;
            border-bottom: none;
            padding: 1.5rem;
            text-align: center
        }

        .balance-card .card-body {
            padding: 2rem
        }

        .current-balance {
            font-size: 2.5rem;
            font-weight: 700;
            color: #4caf50
        }

        .btn,
        .form-control {
            border-radius: 25px
        }

        .btn-primary {
            border: none;
            padding: 10px 30px;
            font-weight: 700;
            -webkit-transition: .3s;
            -o-transition: .3s;
            transition: .3s
        }

        .btn-primary:hover {
            -webkit-transform: translateY(-3px);
            -ms-transform: translateY(-3px);
            transform: translateY(-3px);
            -webkit-box-shadow: 0 5px 15px rgba(0, 0, 0, .2);
            box-shadow: 0 5px 15px rgba(0, 0, 0, .2)
        }

        .balance-icon {
            font-size: 3rem;
            margin-bottom: 1rem
        }

        @-webkit-keyframes float {

            0%,
            100% {
                -webkit-transform: translateY(0);
                transform: translateY(0)
            }

            50% {
                -webkit-transform: translateY(-10px);
                transform: translateY(-10px)
            }
        }

        @keyframes float {

            0%,
            100% {
                -webkit-transform: translateY(0);
                transform: translateY(0)
            }

            50% {
                -webkit-transform: translateY(-10px);
                transform: translateY(-10px)
            }
        }

        .floating-icon {
            -webkit-animation: 3s ease-in-out infinite float;
            animation: 3s ease-in-out infinite float
        }

        .deposit-instructions {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            -webkit-box-shadow: 0 4px 6px rgba(0, 0, 0, .1);
            box-shadow: 0 4px 6px rgba(0, 0, 0, .1);
            -webkit-animation: .5s ease-out fadeIn;
            animation: .5s ease-out fadeIn
        }

        .instruction-title {
            color: #2196f3;
            font-weight: 600;
            margin-bottom: 1rem
        }

        .instruction-list {
            padding-left: 1.2rem;
            counter-reset: item
        }

        .instruction-list li {
            margin-bottom: .75rem;
            position: relative;
            padding-left: .5rem;
            list-style-type: none
        }

        .instruction-list li::before {
            content: counter(item) ".";
            counter-increment: item;
            color: #4caf50;
            font-weight: 700;
            position: absolute;
            left: -1.2rem
        }

        .highlight {
            background: -o-linear-gradient(330deg, rgba(76, 175, 80, .2) 0, rgba(33, 150, 243, .2) 100%);
            background: linear-gradient(120deg, rgba(76, 175, 80, .2) 0, rgba(33, 150, 243, .2) 100%);
            padding: 2px 5px;
            border-radius: 4px;
            font-weight: 600;
            color: #1565c0
        }

        @-webkit-keyframes fadeIn {
            from {
                opacity: 0;
                -webkit-transform: translateY(10px);
                transform: translateY(10px)
            }

            to {
                opacity: 1;
                -webkit-transform: translateY(0);
                transform: translateY(0)
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                -webkit-transform: translateY(10px);
                transform: translateY(10px)
            }

            to {
                opacity: 1;
                -webkit-transform: translateY(0);
                transform: translateY(0)
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
                <div class="balance-header">
                    <div class="container">
                        <i class="fas fa-wallet balance-icon floating-icon"></i>
                        <h1 class="mt-2">Tambah Saldo</h1>
                    </div>
                </div>
                <div class="container-fluid px-4">
                    <div class="row justify-content-center">
                        <div class="col-lg-6">
                            <div class="balance-card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Saldo Anda Sekarang</h5>
                                    <div class="current-balance">
                                        Rp <span
                                            id="currentBalance"><?php echo number_format($current_balance, 2, ',', '.'); ?></span>
                                    </div>
                                    <div class="deposit-instructions">
                                        <h6 class="instruction-title">Petunjuk Deposit:</h6>
                                        <ol class="instruction-list">
                                            <li>Masukkan nominal deposit yang Anda inginkan pada kolom yang tersedia.</li>
                                            <li>Klik tombol "Tambah Saldo".</li>
                                            <li>Konfirmasikan permintaan deposit Anda melalui aplikasi.</li>
                                            <li>Lakukan pembayaran dengan mengklik QRIS berikut untuk melihat barcode:</li>
                                        </ol>
                                        <a href="#" class="qris-link" data-bs-toggle="modal" data-bs-target="#qrisModal">
                                            Klik Untuk Melihat QRIS
                                        </a>
                                        <div class="modal fade" id="qrisModal" tabindex="-1" aria-labelledby="qrisModalLabel" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="qrisModalLabel">QRIS Barcode</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body d-flex justify-content-center">
                                                        <img src="/assets/qris.jpg" alt="QRIS Barcode" class="img-fluid" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <br><br><p>Jika saldo belum bertambah, silakan hubungi admin melalui WhatsApp di  <span class="highlight">0895328552294</span> .</p>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <form id="addBalanceForm" method="POST">
                                        <div class="mb-4">
                                            <label for="amount" class="form-label">Jumlah yang ingin ditambahkan
                                                (Rp)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">Rp</span>
                                                <input type="number" step="0.01" min="0" class="form-control"
                                                    id="amount" name="amount" required>
                                            </div>
                                        </div>
                                        <div class="text-center">
                                            <button type="submit" class="btn btn-primary btn-lg">
                                                <i class="fas fa-plus-circle me-2"></i>Deposit
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
    <script src="js/scripts.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.14.0/sweetalert2.all.min.js"
        integrity="sha512-vHKpHh3VBF4B8QqZ1ppqnNb8zoTBceER6pyGb5XQyGtkCmeGwxDi5yyCmFLZA4Xuf9Jn1LBoAnx9sVvy+MFjNg=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('addBalanceForm');

            // Function to format number as Indonesian Rupiah
            function formatRupiah(number) {
                return new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }).format(number);
            }

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(form);
                const amount = formData.get('amount');

                // Format the amount for display
                const formattedAmount = formatRupiah(amount);

                Swal.fire({
                    title: 'Konfirmasi Deposit',
                    html: `Apakah anda yakin ingin melakukan deposit sejumlah <strong>${formattedAmount}</strong> ?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Ya',
                    cancelButtonText: 'Batal',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch('add_balance.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.text())
                            .then(html => {
                                const parser = new DOMParser();
                                const doc = parser.parseFromString(html, 'text/html');
                                const newBalance = doc.getElementById('currentBalance').textContent;
                                document.getElementById('currentBalance').textContent = newBalance;

                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil!',
                                    text: 'Permintaan deposit berhasil dibuat. Admin akan segera memverifikasi transfer Anda.',
                                });

                                form.reset();
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error!',
                                    text: 'An unexpected error occurred. Please try again.',
                                });
                            });
                    }
                });
            });
        });
    </script>
</body>

</html>