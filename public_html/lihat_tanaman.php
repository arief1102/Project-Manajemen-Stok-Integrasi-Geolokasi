<?php


require_once 'config/db_connect.php';

// Check if user is logged in and is a petani
require_once 'auth_middleware.php';
$user = requireRole('petani');

$user_id = $user['user_id'];
$role = $user['role'];

// Initialize filter variables
$min_stock = isset($_GET['min_stock']) ? intval($_GET['min_stock']) : 0;
$plant_type = isset($_GET['plant_type']) ? $_GET['plant_type'] : '';
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';

// Modify the SQL query to include filters and search
$sql = "
    SELECT p.*, u.username as petani_name, pt.petani_id,
           (SELECT COUNT(*) FROM order_items oi WHERE oi.plant_id = p.plant_id) as order_count,
           (SELECT COUNT(DISTINCT oi.order_id) FROM order_items oi
            JOIN plants p2 ON oi.plant_id = p2.plant_id
            WHERE p2.petani_id = p.petani_id) as total_orders,
           (SELECT COUNT(*) FROM plants p3 WHERE p3.petani_id = p.petani_id AND p3.is_deleted = 0) as total_plants
    FROM plants p
    JOIN petani pt ON p.petani_id = pt.petani_id
    JOIN users u ON pt.user_id = u.user_id
    WHERE p.is_deleted = 0
";

if ($min_stock > 0) {
    $sql .= " AND p.stok >= :min_stock";
}

if ($plant_type !== '') {
    $sql .= " AND p.jenis = :plant_type";
}

if ($search_name !== '') {
    $sql .= " AND p.nama LIKE :search_name";
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);

if ($min_stock > 0) {
    $stmt->bindParam(':min_stock', $min_stock, PDO::PARAM_INT);
}

if ($plant_type !== '') {
    $stmt->bindParam(':plant_type', $plant_type, PDO::PARAM_STR);
}

if ($search_name !== '') {
    $search_param = "%{$search_name}%";
    $stmt->bindParam(':search_name', $search_param, PDO::PARAM_STR);
}

$stmt->execute();
$plants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to get top-selling plants for a petani 
function getTopSellingPlants($pdo, $petani_id, $limit = 3)
{
    $stmt = $pdo->prepare("
        SELECT p.nama, SUM(oi.kuantitas) as total_sold 
        FROM order_items oi 
        JOIN plants p ON oi.plant_id = p.plant_id 
        WHERE p.petani_id = :petani_id 
        GROUP BY p.plant_id, p.nama
        HAVING total_sold > 20 
        ORDER BY total_sold DESC 
        LIMIT :limit
    ");
    $stmt->bindParam(':petani_id', $petani_id, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}




// Placeholder image attribution
$placeholder_attribution = 'Plant icons created by Kiranshastry - Flaticon';
$placeholder_link = 'https://www.flaticon.com/free-icons/plant';

// Function to get the correct image path
function getImagePath($fullPath)
{
    if (strpos($fullPath, 'assets/img/') === 0) {
        return $fullPath;
    } else {
        $filename = basename($fullPath);
        return "assets/img/" . $filename;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lihat Tanaman - Plant Inventory Jabon Mekar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/css/bootstrap.min.css"
        integrity="sha512-SbiR/eusphKoMVVXysTKG/7VseWii+Y3FdHrt0EpKgpToZeemhqHeZeLWLhJutz/2ut2Vw1uQEj2MbRF+TVBUA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="css/styles.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/js/all.min.js"
        integrity="sha512-2bMhOkE/ACz21dJT8zBOMgMecNxx0d37NND803ExktKiKdSzdwn+L7i9fdccw/3V06gM/DBWKbYmQvKMdAA9Nw=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
        .container-fluid {
            max-width: 1200px;
            margin: 0 auto
        }

        .filter-form {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            -webkit-box-shadow: 0 2px 4px rgba(0, 0, 0, .1);
            box-shadow: 0 2px 4px rgba(0, 0, 0, .1);
            max-width: 500px;
            margin-left: auto;
            margin-right: auto
        }

        .filter-form .form-control,
        .filter-form .form-select {
            border-radius: 20px;
            width: 100%
        }

        .filter-form .btn {
            border-radius: 20px;
            padding: 8px 20px
        }

        .plant-card {
            height: 100%;
            border: none;
            border-radius: 15px;
            overflow: hidden;
            -webkit-box-shadow: 0 4px 15px rgba(0, 0, 0, .1);
            box-shadow: 0 4px 15px rgba(0, 0, 0, .1);
            -webkit-transition: -webkit-transform .3s ease-in-out, -webkit-box-shadow .3s ease-in-out;
            transition: transform .3s ease-in-out, box-shadow .3s ease-in-out, -webkit-transform .3s ease-in-out, -webkit-box-shadow .3s ease-in-out;
            -o-transition: transform .3s ease-in-out, box-shadow .3s ease-in-out
        }

        .plant-card:hover {
            -webkit-transform: translateY(-5px);
            -ms-transform: translateY(-5px);
            transform: translateY(-5px);
            -webkit-box-shadow: 0 6px 20px rgba(0, 0, 0, .15);
            box-shadow: 0 6px 20px rgba(0, 0, 0, .15)
        }

        .plant-image-container {
            position: relative;
            width: 100%;
            padding-top: 75%;
            overflow: hidden
        }

        .plant-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            -o-object-fit: cover;
            object-fit: cover;
            -o-object-position: center;
            object-position: center
        }

        .card-body {
            padding: 1.25rem
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: .75rem
        }

        .card-text {
            font-size: .9rem;
            color: #6c757d
        }

        .card-text strong {
            color: #495057
        }

        .card-footer {
            background-color: transparent;
            border-top: 1px solid rgba(0, 0, 0, .125);
            padding: .75rem 1.25rem
        }

        .petani-link {
            color: #4a90e2;
            text-decoration: none;
            position: relative;
            -webkit-transition: color .3s;
            -o-transition: color .3s;
            transition: color .3s
        }

        .petani-link:hover {
            color: #2c3e50
        }

        .modal-content {
            border: none;
            border-radius: 15px;
            overflow: hidden
        }

       

        .modal-header {
            border-bottom: none
        }

        .petani-avatar {
            width: 150px;
            height: 150px;
            -o-object-fit: cover;
            object-fit: cover;
            border: 5px solid #fff;
            -webkit-box-shadow: 0 0 10px rgba(0, 0, 0, .1);
            box-shadow: 0 0 10px rgba(0, 0, 0, .1)
        }

        .stat-box,
        .top-selling-plants {
            background-color: #f8f9fa;
            border-radius: 10px
        }

        .stat-box {
            padding: 15px;
            -webkit-transition: .3s;
            -o-transition: .3s;
            transition: .3s
        }

        .stat-box:hover {
            background-color: #e9ecef;
            -webkit-transform: translateY(-5px);
            -ms-transform: translateY(-5px);
            transform: translateY(-5px)
        }

        .stat-box i {
            color: #007bff
        }

        .top-selling-plants {
            padding: 10px
        }

        .list-group-item {
            border: none;
            background-color: transparent;
            padding: 10px 0
        }

        .text-bronze {
            color: #cd7f32
        }

        .badge {
            font-size: .8em
        }

        @media (min-width:576px) {
            .row-cols-sm-2>* {
                -webkit-box-flex: 0;
                -ms-flex: 0 0 auto;
                flex: 0 0 auto;
                width: 50%
            }
        }

        @media (min-width:768px) {
            .row-cols-md-3>* {
                -webkit-box-flex: 0;
                -ms-flex: 0 0 auto;
                flex: 0 0 auto;
                width: 33.33333%
            }
        }

        @media (min-width:992px) {
            .row-cols-lg-4>* {
                -webkit-box-flex: 0;
                -ms-flex: 0 0 auto;
                flex: 0 0 auto;
                width: 25%
            }
        }

        @media (min-width:1200px) {
            .row-cols-xl-4>* {
                -webkit-box-flex: 0;
                -ms-flex: 0 0 auto;
                flex: 0 0 auto;
                width: 25%
            }
        }
    </style>
    <link rel="stylesheet" href="css/custom-styles.css">
    <script src="js/matomo.js"> </script>
</head>

<body class="sb-nav-fixed">
    <?php include 'includes/navbar.php'; ?>
    <div id="layoutSidenav">
        <?php include 'includes/sidebar.php'; ?>
        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid px-4">
                    <h1 class="mt-4">Lihat Tanaman</h1>

                    <!-- Updated Filter Form -->
                    <div class="filter-form">
                        <form action="" method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="search_name" class="form-label">Cari Nama Tanaman</label>
                                <input type="text" class="form-control" id="search_name" name="search_name"
                                    value="<?php echo htmlspecialchars($search_name); ?>"
                                    placeholder="Masukkan nama tanaman">
                            </div>
                            <div class="col-md-2">
                                <label for="min_stock" class="form-label">Stok Minimum</label>
                                <input type="number" class="form-control" id="min_stock" name="min_stock" min="0"
                                    value="<?php echo $min_stock; ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="plant_type" class="form-label">Jenis Tanaman</label>
                                <select class="form-select" id="plant_type" name="plant_type">
                                    <option value="" <?php echo $plant_type === '' ? 'selected' : ''; ?>>Semua</option>
                                    <option value="indoor" <?php echo $plant_type === 'indoor' ? 'selected' : ''; ?>>
                                        Indoor</option>
                                    <option value="outdoor" <?php echo $plant_type === 'outdoor' ? 'selected' : ''; ?>>
                                        Outdoor</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <a href="lihat_tanaman.php" class="btn btn-secondary w-100">Reset</a>
                            </div>
                        </form>
                    </div>


                    <!-- PLANTS CARD -->
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-4 g-4">
                        <?php foreach ($plants as $plant): ?>
                            <div class="col">
                                <div class="card plant-card h-100">
                                    <div class="plant-image-container">
                                        <?php if ($plant['gambar']): ?>
                                            <img src="<?php echo htmlspecialchars(getImagePath($plant['gambar'])); ?>"
                                                class="plant-image" alt="<?php echo htmlspecialchars($plant['nama']); ?>"
                                                onerror="this.onerror=null; this.src='assets/img/placeholder.png'; this.title='<?php echo htmlspecialchars($placeholder_attribution); ?>';">
                                        <?php else: ?>
                                            <img src="assets/img/placeholder.png" class="plant-image" alt="Placeholder image"
                                                title="<?php echo htmlspecialchars($placeholder_attribution); ?>">
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($plant['nama']); ?></h5>
                                        <p class="card-text">
                                            <strong>Pemilik:</strong>
                                            <a class="btn btn-primary" style="--bs-btn-padding-y: .25rem; --bs-btn-padding-x: .5rem; --bs-btn-font-size: .75rem;" href="#" class="petani-link" data-bs-toggle="modal"
                                                data-bs-target="#petaniModal<?php echo $plant['petani_id']; ?>">
                                                <?php echo htmlspecialchars($plant['petani_name']); ?>
                                            </a><br>
                                            <strong>Jenis:</strong>
                                            <?php echo ucfirst(htmlspecialchars($plant['jenis'])); ?><br>
                                            <strong>Harga:</strong> Rp
                                            <?php echo number_format($plant['harga'], 0, ',', '.'); ?><br>
                                            <strong>Stok:</strong> <?php echo htmlspecialchars($plant['stok']); ?><br>
                                            <strong>Jumlah Pesanan:</strong>
                                            <?php echo htmlspecialchars($plant['order_count']); ?>
                                        </p>
                                    </div>
                                    <?php if (!$plant['gambar']): ?>
                                        <div class="card-footer">
                                            <small class="text-muted">
                                                <a href="<?php echo htmlspecialchars($placeholder_link); ?>" target="_blank"
                                                    title="<?php echo htmlspecialchars($placeholder_attribution); ?>">
                                                    Plant icons created by Kiranshastry - Flaticon
                                                </a>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <?php foreach ($plants as $plant): ?> <!-- Modal for each petani -->
        <div class="modal fade" id="petaniModal<?php echo $plant['petani_id']; ?>" tabindex="-1" aria-labelledby="petaniModalLabel<?php echo $plant['petani_id']; ?>" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="petaniModalLabel<?php echo $plant['petani_id']; ?>"> <i class="fas fa-user-circle me-2"></i>Informasi Petani </h5> <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-4"> <img src="assets/img/farmer-profile.png" class="rounded-circle petani-avatar" alt="<?php echo htmlspecialchars($plant['petani_name']); ?>">
                            <h4 class="mt-2"><?php echo htmlspecialchars($plant['petani_name']); ?></h4>
                        </div>
                        <div class="row text-center mb-4">
                            <div class="col">
                                <div class="stat-box"> <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                                    <h5><?php echo $plant['total_orders']; ?></h5>
                                    <p>Total Pesanan</p>
                                </div>
                            </div>
                            <div class="col">
                                <div class="stat-box"> <i class="fas fa-seedling fa-2x mb-2"></i>
                                    <h5><?php echo $plant['total_plants']; ?></h5>
                                    <p>Total Tanaman</p>
                                </div>
                            </div>
                        </div>
                        <div class="top-selling-plants">
                            <h5 class="text-center mb-3"><i class="fas fa-crown me-2"></i>Tanaman Terlaris</h5>
                            <small class="text-muted d-block text-center mb-3">(Minimum 20 penjualan)</small>
                            <ul class="list-group">
                                <?php
                                $top_selling = getTopSellingPlants($pdo, $plant['petani_id']);
                                if (empty($top_selling)):
                                ?>
                                    <li class="list-group-item text-center">
                                        Belum ada tanaman yang terjual lebih dari 20 unit
                                    </li>
                                    <?php else:
                                    foreach ($top_selling as $index => $top_plant):
                                        $medal_class = ['text-warning', 'text-secondary', 'text-bronze'][$index] ?? '';
                                    ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>
                                                <?php if ($index < 3): ?>
                                                    <i class="fas fa-medal <?php echo $medal_class; ?> me-2"></i>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($top_plant['nama']); ?>
                                            </span>
                                            <span class="badge bg-primary rounded-pill">Terjual: <?php echo $top_plant['total_sold']; ?></span>
                                        </li>
                                <?php
                                    endforeach;
                                endif;
                                ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div> <?php endforeach; ?>



    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/js/bootstrap.bundle.min.js"
        integrity="sha512-i9cEfJwUwViEPFKdC1enz4ZRGBj8YQo6QByFTF92YXHi7waCqyexvRD75S5NVTsSiTv7rKWqG9Y5eFxmRsOn0A=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="js/scripts.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var petaniLinks = document.querySelectorAll(".petani-link");
            petaniLinks.forEach(function(link) {
                link.addEventListener("click", function(e) {
                    e.preventDefault();
                    var targetModalId = this.getAttribute("data-bs-target");
                    var targetModal = document.querySelector(targetModalId);
                    var modal = new bootstrap.Modal(targetModal);
                    modal.show();
                });
            });

            // Add event listeners for all modals
            var allModals = document.querySelectorAll(".modal");
            allModals.forEach(function(modal) {
                modal.addEventListener("hidden.bs.modal", function(event) {
                    document.body.classList.remove("modal-open");
                    var backdrop = document.querySelector(".modal-backdrop");
                    if (backdrop) {
                        backdrop.remove();
                    }
                });
            });
        })
    </script>

</body>

</html>