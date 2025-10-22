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

// Fetch customer_id
$stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE user_id = ?");
$stmt->execute([$user_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);
$customer_id = $customer['customer_id'];

// Fetch deposit history
$stmt = $pdo->prepare("SELECT * FROM deposits WHERE customer_id = ? ORDER BY created_at DESC");
$stmt->execute([$customer_id]);
$deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Riwayat Deposit - Plant Inventory Jabon Mekar</title>
    <link href="css/styles.css" rel="stylesheet" />
    <link rel="stylesheet" href="css/custom-styles.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/simple-datatables/7.1.2/style.min.css"
        integrity="sha512-dcdQkw+lfchKGZD+YmuSMwHBoR8AgJGrHXtBVXaxo2sMhlSKB0r04F2W9+BXCfdDjmP75EEl7oVNaHn2FTVNpQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/js/all.min.js"
        integrity="sha512-2bMhOkE/ACz21dJT8zBOMgMecNxx0d37NND803ExktKiKdSzdwn+L7i9fdccw/3V06gM/DBWKbYmQvKMdAA9Nw=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
        .table-responsive {
            overflow-x: auto
        }

        .table {
            width: 100%;
            margin-bottom: 1rem;
            color: #212529;
            border-collapse: separate;
            border-spacing: 0
        }

        .table td,
        .table th {
            padding: .75rem;
            vertical-align: top;
            border-top: 1px solid #dee2e6
        }

        .table thead th {
            vertical-align: bottom;
            border-bottom: 2px solid #dee2e6;
            background-color: #f8f9fa;
            font-weight: 700
        }

        .table tbody tr:nth-of-type(odd) {
            background-color: rgba(0, 0, 0, .05)
        }

        .table tbody tr:hover {
            background-color: rgba(0, 0, 0, .075)
        }

        .badge {
            display: inline-block;
            padding: .25em .4em;
            font-size: 75%;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: .25rem
        }

        .badge-success {
            color: #fff;
            background-color: #28a745
        }

        .badge-warning {
            color: #212529;
            background-color: #ffc107
        }

        .badge-danger {
            color: #fff;
            background-color: #dc3545
        }

        @media (max-width:767.98px) {
            .deposit-history {
                border: 0
            }

            .deposit-history thead {
                display: none
            }

            .deposit-history tbody tr {
                display: block;
                margin-bottom: 1rem;
                border: 1px solid #dee2e6;
                border-radius: .25rem;
                background-color: #fff
            }

            .deposit-history td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: .5rem;
                border: none;
                text-align: right
            }

            .deposit-history td::before {
                content: attr(data-label);
                font-weight: 700;
                margin-right: 1rem;
                text-align: left
            }

            .deposit-history td:last-child {
                border-bottom: 0
            }

            .deposit-history tr:nth-of-type(odd) {
                background-color: #f8f9fa
            }
        }

        .card {
            position: relative;
            display: flex;
            flex-direction: column;
            min-width: 0;
            word-wrap: break-word;
            background-color: #fff;
            background-clip: border-box;
            border: 1px solid rgba(0, 0, 0, .125);
            border-radius: .25rem
        }

        .card-body {
            flex: 1 1 auto;
            padding: 1.25rem
        }

        .mb-4 {
            margin-bottom: 1.5rem !important
        }

        .fw-bold {
            font-weight: 700 !important
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
                    <h1 class="mt-4">Riwayat Deposit</h1>

                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover deposit-history">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Jumlah</th>
                                            <th>Status</th>
                                            <th>Tanggal Dibuat</th>
                                            <th>Tanggal Diperbarui</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($deposits as $deposit): ?>
                                            <tr>
                                                <td data-label="ID"><?php echo $deposit['deposit_id']; ?></td>
                                                <td data-label="Jumlah">Rp
                                                    <?php echo number_format($deposit['amount'], 2, ',', '.'); ?>
                                                </td>
                                                <td data-label="Status">
                                                    <?php
                                                    $status = $deposit['deposit_status'];
                                                    $badge_class = '';
                                                    switch ($status) {
                                                        case 'pending':
                                                            $badge_class = 'badge-warning';
                                                            break;
                                                        case 'berhasil':
                                                            $badge_class = 'badge-success';
                                                            break;
                                                        case 'dibatalkan':
                                                            $badge_class = 'badge-danger';
                                                            break;
                                                    }
                                                    echo "<span class='badge $badge_class'>" . ucfirst($status) . "</span>";
                                                    ?>
                                                </td>
                                                <td data-label="Tanggal Dibuat">
                                                    <?php echo date('d M Y H:i', strtotime($deposit['created_at'])); ?>
                                                </td>
                                                <td data-label="Tanggal Diperbarui">
                                                    <?php echo date('d M Y H:i', strtotime($deposit['updated_at'])); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/simple-datatables/7.1.2/simple-datatables.min.js"
        integrity="sha512-3ty9AJncMgK2yFwGuF8Shc5dMwiXeHiEXV5QiOXrhuXzQLLorWeBEpmLWduNl49A9ffIyf+zmQ7nI1PQlUaRYg=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="js/datatables-simple-demo.js"></script>
    <script src="js/scripts.js"></script>
</body>

</html>