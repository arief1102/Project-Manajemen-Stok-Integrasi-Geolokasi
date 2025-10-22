<?php
require_once 'config/db_connect.php';
require_once 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$app_url = $_ENV['APP_URL'] ?? '';

// Check if user is logged in and is a pembeli
require_once 'auth_middleware.php';
$user = requireRole('pembeli');

$user_id = $user['user_id'];
$role = $user['role'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get customer's location and address
$stmt = $pdo->prepare("SELECT latitude, longitude, alamat_pengiriman FROM customers WHERE user_id = ?");
$stmt->execute([$user_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

$customer_lat = $customer['latitude'] ?? null;
$customer_lng = $customer['longitude'] ?? null;
$customer_address = $customer['alamat_pengiriman'] ?? null;

$location_set = ($customer_lat !== null && $customer_lng !== null);

// Pagination
// $items_per_page = 12;
// $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
// $offset = ($page - 1) * $items_per_page;

// Filtering
$search_name = isset($_GET['search_name']) && $_GET['search_name'] !== '' ? $_GET['search_name'] : null;
$jenis = isset($_GET['jenis']) && $_GET['jenis'] !== '' ? $_GET['jenis'] : null;
$min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float) $_GET['min_price'] : null;
$max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float) $_GET['max_price'] : null;
$max_distance = isset($_GET['max_distance']) && $_GET['max_distance'] !== '' ? (float) $_GET['max_distance'] : null;
$min_stock = isset($_GET['min_stock']) && $_GET['min_stock'] !== '' ? (int) $_GET['min_stock'] : null;

// Base query
$query = "SELECT p.*, pet.nama_lengkap AS petani_name, pet.latitude, pet.longitude, pet.no_hp AS petani_phone";

// Add distance calculation if customer location is available
if ($customer_lat !== null && $customer_lng !== null) {
    $query .= ", (6371 * acos(cos(radians(:customer_lat)) * cos(radians(pet.latitude)) * cos(radians(pet.longitude) - radians(:customer_lng)) + sin(radians(:customer_lat)) * sin(radians(pet.latitude)))) AS distance";
}

$query .= " FROM plants p
            JOIN petani pet ON p.petani_id = pet.petani_id
            WHERE p.is_deleted = 0";

$params = [];

if ($customer_lat !== null && $customer_lng !== null) {
    $params[':customer_lat'] = $customer_lat;
    $params[':customer_lng'] = $customer_lng;
}

// Add filters
if ($search_name !== null) {
    $query .= " AND p.nama LIKE :search_name";
    $params[':search_name'] = '%' . $search_name . '%';
}

if ($jenis !== null) {
    $query .= " AND p.jenis = :jenis";
    $params[':jenis'] = $jenis;
}

if ($min_price !== null) {
    $query .= " AND p.harga >= :min_price";
    $params[':min_price'] = $min_price;
}

if ($max_price !== null) {
    $query .= " AND p.harga <= :max_price";
    $params[':max_price'] = $max_price;
}

if ($min_stock !== null) {
    $query .= " AND p.stok >= :min_stock";
    $params[':min_stock'] = $min_stock;
}

// Add distance filter if applicable
if ($customer_lat !== null && $customer_lng !== null && $max_distance !== null) {
    $query .= " HAVING distance <= :max_distance";
    $params[':max_distance'] = $max_distance;
}

// Add sorting and pagination
// $query .= " ORDER BY p.created_at DESC LIMIT :offset, :items_per_page";
// $params[':offset'] = (int) $offset;
// $params[':items_per_page'] = (int) $items_per_page;

// // Get total number of plants (for pagination)
// $count_query = "SELECT COUNT(*) as total FROM (" . $query . ") as subquery";
// $stmt = $pdo->prepare($count_query);
// foreach ($params as $key => &$val) {
//     $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
// }
// $stmt->execute();
// $total_plants = $stmt->fetchColumn();
// $total_pages = ceil($total_plants / $items_per_page);

// Fetch plants
$stmt = $pdo->prepare($query);
foreach ($params as $key => &$val) {
    $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$plants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch jenis options for filter dropdown
$jenis_query = $pdo->query("SELECT DISTINCT jenis FROM plants WHERE is_deleted = 0 ORDER BY jenis");
$jenis_options = $jenis_query->fetchAll(PDO::FETCH_COLUMN);

// Function to get Google Maps link
function getGoogleMapsLink($latitude, $longitude)
{
    return "https://www.google.com/maps?q={$latitude},{$longitude}";
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
    <title>Lihat Tanaman - Plant Inventory Jabon Mekar</title>

    <!-- CSS Dependencies -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/js/all.min.js"
        integrity="sha512-2bMhOkE/ACz21dJT8zBOMgMecNxx0d37NND803ExktKiKdSzdwn+L7i9fdccw/3V06gM/DBWKbYmQvKMdAA9Nw=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link href="https://js.api.here.com/v3/3.1/mapsjs-ui.css" rel="stylesheet" type="text/css" />
    <link href="css/styles.css" rel="stylesheet" />
    <link rel="stylesheet" href="css/custom-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.14.0/sweetalert2.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/simple-datatables/7.1.2/style.min.css" />

    <style>
        h1 {
            margin-bottom: 30px;
            font-weight: 700;
        }

        /* Filter Card Styles */
        .card.mb-4 {
            background: linear-gradient(135deg, #f5f7fa 0, #c3cfe2 100%);
            border-radius: 15px;
            box-shadow: 0 8px 15px rgba(0, 0, 0, .1);
            margin-bottom: 30px !important;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Form Controls */
        .form-control,
        .form-select {
            border: 1px solid #ced4da;
            border-radius: 8px;
            padding: 10px;
            background-color: #fff;
            transition: .3s;
            width: 100%;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, .3);
            background-color: #fff;
        }

        /* Button Styles */
        .btn-primary,
        .modal-footer .btn {
            padding: 10px 20px;
            font-weight: 600;
            transition: .3s;
        }

        .btn-primary {
            background-color: #3498db;
            border: none;
            border-radius: 8px;
        }

        .btn-primary:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, .1);
        }

        /* Plant Card Styles */
        .plant-card {
            height: 100%;
            max-width: 280px;
            margin: 0 auto;
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, .1);
            transition: transform .3s ease-in-out, box-shadow .3s ease-in-out;
            background-color: #fff;
        }

        .plant-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, .15);
        }

        /* Plant Image Styles */
        .plant-image-container {
            position: relative;
            width: 100%;
            padding-top: 100%;
            /* 1:1 Aspect ratio */
            overflow: hidden;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
            background-color: #f8f9fa;
        }

        .plant-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            transition: transform 0.3s;
        }

        .plant-card:hover .plant-image {
            transform: scale(1.05);
        }

        /* Card Content Styles */
        .card-body {
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: .75rem;
            color: #2c3e50;
        }

        .card-text {
            font-size: .9rem;
            color: #6c757d;
            flex-grow: 1;
        }

        .card-text strong {
            color: #495057;
        }

        .card-footer {
            background-color: transparent;
            border-top: 1px solid rgba(0, 0, 0, .125);
            padding: .75rem 1.25rem;
        }

        /* Modal Styles */
        .modal-content {
            border-radius: 15px;
            overflow: hidden;
            border: none;
        }

        .modal-header {
            background-color: #3498db;
            color: #fff;
            border-bottom: none;
            padding: 1.5rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        /* Modal Image Styles */
        .modal-image-container {
            position: relative;
            width: 100%;
            padding-top: 75%;
            /* 4:3 Aspect ratio */
            overflow: hidden;
            border-radius: 8px;
            margin: 15px 0;
            background-color: #f8f9fa;
        }

        .modal-image-container img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
        }

        /* Map Container Styles */
        .modal-map-container {
            position: relative;
            width: 100%;
            padding-top: 56.25%;
            /* 16:9 Aspect ratio */
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 15px;
            background-color: #f8f9fa;
        }

        .modal-map-container [id^="map"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        .H_ui {
            z-index: 1000;
        }

        /* Modal Footer */
        .modal-footer {
            border-top: none;
            padding: 1rem 1.5rem;
        }

        .modal-footer .btn-secondary {
            background-color: #95a5a6;
            border: none;
        }

        .modal-footer .btn-secondary:hover {
            background-color: #7f8c8d;
        }

        .modal-footer .btn-primary {
            background-color: #2ecc71;
        }

        .modal-footer .btn-primary:hover {
            background-color: #27ae60;
        }

        /* Pagination */
        .pagination {
            margin-top: 30px;
        }

        .page-item .page-link {
            color: #3498db;
            border: none;
            border-radius: 8px;
            margin: 0 5px;
            transition: .3s;
        }

        .page-item.active .page-link,
        .page-item .page-link:hover {
            background-color: #3498db;
            color: #fff;
            box-shadow: 0 2px 5px rgba(52, 152, 219, .3);
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .plant-image-container {
                padding-top: 100%;
                /* Maintain square aspect ratio on mobile */
            }

            .modal-image-container {
                padding-top: 100%;
                /* Square images on mobile */
            }

            .card-body {
                padding: 1rem;
            }

            .card-title {
                font-size: 1.1rem;
            }

            .card-text {
                font-size: .85rem;
            }

            .modal-header,
            .modal-body,
            .modal-footer {
                padding: 1rem;
            }
        }

        @media (min-width: 769px) {
            .plant-card {
                max-width: 300px;
            }

            .modal-dialog {
                max-width: 700px;
            }
        }

        @media (min-width: 992px) {

            .modal-lg,
            .modal-xl {
                max-width: 800px;
            }
        }

        /* Modal Dialog Positioning */
        .modal-dialog {
            display: flex;
            align-items: center;
            min-height: calc(100% - 1rem);
        }

        @media (min-width: 576px) {
            .modal-dialog {
                margin: 1.75rem auto;
            }
        }

        @media (min-width: 768px) {
            .modal-dialog {
                position: fixed !important;
                top: 50% !important;
                left: 50% !important;
                transform: translate(-50%, -50%) !important;
                width: 90% !important;
                margin: 0 !important;
            }

            .modal-content {
                max-height: 90vh;
                overflow-y: auto;
            }
        }

        /* Utilities */
        .modal,
        .modal-open {
            padding-right: 0 !important;
        }

        body.modal-open {
            overflow: auto !important;
        }
    </style>
</head>

<body class="sb-nav-fixed">
    <?php include 'includes/navbar.php'; ?>
    <div id="layoutSidenav">
        <?php include 'includes/sidebar.php'; ?>
        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid px-4">
                    <h1 class="mt-4">Lihat Tanaman</h1>

                    <!-- Filter Form -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-2">
                                    <label for="search_name" class="form-label">Nama Tanaman</label>
                                    <input type="text" class="form-control" id="search_name" name="search_name"
                                        value="<?php echo $search_name !== null ? htmlspecialchars($search_name) : ''; ?>"
                                        placeholder="Masukkan nama tanaman...">
                                </div>
                                <div class="col-md-2">
                                    <label for="jenis" class="form-label">Tipe</label>
                                    <select name="jenis" id="jenis" class="form-select">
                                        <option value="">Semua tipe</option>
                                        <?php foreach ($jenis_options as $option): ?>
                                            <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $jenis == $option ? 'selected' : ''; ?>>
                                                <?php echo ucfirst(htmlspecialchars($option)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="min_price" class="form-label">Min harga</label>
                                    <input type="number" class="form-control" id="min_price" name="min_price"
                                        value="<?php echo $min_price !== null ? $min_price : ''; ?>" min="0">
                                </div>
                                <div class="col-md-2">
                                    <label for="max_price" class="form-label">Max harga</label>
                                    <input type="number" class="form-control" id="max_price" name="max_price"
                                        value="<?php echo $max_price !== null ? $max_price : ''; ?>" min="0">
                                </div>
                                <?php if ($customer_lat !== null && $customer_lng !== null): ?>
                                    <div class="col-md-2">
                                        <label for="max_distance" class="form-label">Max jarak (km)</label>
                                        <input type="number" class="form-control" id="max_distance" name="max_distance"
                                            value="<?php echo $max_distance !== null ? $max_distance : ''; ?>" min="0"
                                            step="0.1">
                                    </div>
                                <?php endif; ?>
                                <div class="col-md-2">
                                    <label for="min_stock" class="form-label">Min stok</label>
                                    <input type="number" class="form-control" id="min_stock" name="min_stock"
                                        value="<?php echo $min_stock !== null ? $min_stock : ''; ?>" min="0">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Plants Grid -->

                    <div class="row row-cols-sm-2 row-cols-md-3 row-cols-xl-4 g-4 plants-grid">
                        <?php foreach ($plants as $plant) { ?>
                            <div class="col">
                                <div class="card plant-card h-100">
                                    <div class="plant-image-container">
                                        <?php if (!empty($plant['gambar'])) { ?>
                                            <img src="<?php echo $app_url . '/' . htmlspecialchars($plant['gambar']); ?>"
                                                class="plant-image" alt="<?php echo htmlspecialchars($plant['nama']); ?>">
                                        <?php } ?>
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($plant['nama']); ?></h5>
                                        <p class="card-text">
                                            <strong>Stok:</strong><?php echo htmlspecialchars($plant['stok']); ?><br>
                                            <strong>Harga:</strong> Rp
                                            <?php echo number_format($plant['harga'], 0, ',', '.'); ?><br>
                                            <strong>Tipe:</strong>
                                            <?php echo ucfirst(htmlspecialchars($plant['jenis'])); ?><br>
                                            <strong>Petani:</strong> <?php echo htmlspecialchars($plant['petani_name']); ?>
                                        </p>
                                    </div>
                                    <div class="card-footer text-center">
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                            data-bs-target="#plantModal<?php echo $plant['plant_id']; ?>">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Plant Modal -->
                            <div class="modal fade" id="plantModal<?php echo $plant['plant_id']; ?>" tabindex="-1"
                                aria-labelledby="plantModalLabel<?php echo $plant['plant_id']; ?>" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title"><?php echo htmlspecialchars($plant['nama']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                aria-label="Close"></button>
                                        </div>
                                        <!-- Replace your existing modal body section with this corrected version -->
                                        <div class="modal-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <?php if ($plant['latitude'] && $plant['longitude']) { ?>
                                                        <div class="modal-map-container">
                                                            <div id="map<?php echo $plant['plant_id']; ?>"
                                                                data-latitude="<?php echo $plant['latitude']; ?>"
                                                                data-longitude="<?php echo $plant['longitude']; ?>"
                                                                style="position: absolute; width: 100%; height: 100%;">
                                                            </div>
                                                        </div>
                                                        <div id="routeInfo<?php echo $plant['plant_id']; ?>" class="mt-2">
                                                            <!-- Route information will be displayed here -->
                                                        </div>
                                                        <p class="mb-2">
                                                            <strong>Lokasi Toko:</strong>
                                                            <span
                                                                id="plantAddress<?php echo $plant['plant_id']; ?>">Loading...</span>
                                                        </p>
                                                        <?php
                                                        $mapsLink = getGoogleMapsLink($plant['latitude'], $plant['longitude']);
                                                        if ($mapsLink) {
                                                        ?>
                                                            <a href="<?php echo htmlspecialchars($mapsLink); ?>" target="_blank"
                                                                class="btn btn-sm btn-secondary mb-3">
                                                                <i class="fas fa-map-marker-alt"></i> Lihat di Google Maps
                                                            </a>
                                                    <?php
                                                        }
                                                    } ?>

                                                    <?php if (!empty($plant['gambar'])) { ?>
                                                        <div class="modal-image-container">
                                                            <img src="<?php echo $app_url . '/' . htmlspecialchars($plant['gambar']); ?>"
                                                                alt="<?php echo htmlspecialchars($plant['nama']); ?>">
                                                        </div>
                                                    <?php } ?>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="plant-details">
                                                        <p><strong>Harga:</strong> Rp
                                                            <?php echo number_format($plant['harga'], 0, ',', '.'); ?>
                                                        </p>
                                                        <p><strong>Tipe:</strong>
                                                            <?php echo ucfirst(htmlspecialchars($plant['jenis'])); ?></p>
                                                        <p><strong>Petani:</strong>
                                                            <?php echo htmlspecialchars($plant['petani_name']); ?></p>
                                                        <p><strong>Kontak:</strong>
                                                            <?php echo htmlspecialchars($plant['petani_phone']); ?></p>
                                                        <p><strong>Stok:</strong>
                                                            <?php echo htmlspecialchars($plant['stok']); ?></p>
                                                      
                                                       <p><strong>Berat:</strong>
                                                            <?php echo htmlspecialchars($plant['berat']); ?> kg</p>
                                                      
                                                        <p><strong>Deskripsi:</strong>
                                                            <?php echo htmlspecialchars($plant['deskripsi']); ?></p>

                                                        <?php if (isset($plant['distance'])) { ?>
                                                            <p><strong>Jarak:</strong>
                                                                <?php echo round($plant['distance'], 2); ?> km</p>
                                                        <?php } ?>

                                                        <?php if ($customer_address) { ?>
                                                            <p><strong>Alamat pengiriman:</strong>
                                                                <?php echo htmlspecialchars($customer_address); ?></p>
                                                        <?php } else { ?>
                                                            <p><strong>Alamat pengiriman:</strong> <span
                                                                    class="text-warning">Belum ditentukan. Mohon perbarui alamat
                                                                    anda.</span></p>
                                                        <?php } ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                            <button type="button" class="btn btn-primary"
                                                onclick="addToCart(<?php echo $plant['plant_id']; ?>)">
                                                <i class="fa-solid fa-cart-shopping"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>

                    <!-- Pagination -->
                    <!-- <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
                                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                    <a class="page-link"
                                        href="?page=<?php echo $i; ?>&jenis=<?php echo urlencode($jenis ?? ''); ?>&min_price=<?php echo $min_price ?? ''; ?>&max_price=<?php echo $max_price ?? ''; ?>&max_distance=<?php echo $max_distance ?? ''; ?>&min_stock=<?php echo $min_stock ?? ''; ?>&search_name=<?php echo urlencode($search_name ?? ''); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php } ?>
                        </ul>
                    </nav> -->
                </div>
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <script>
        function updateGridColumns() {
            const plantGrid = document.querySelector('.plants-grid');
            if (plantGrid) {
                // Remove existing column classes
                plantGrid.classList.remove('row-cols-1', 'row-cols-3');

                // Add appropriate class based on window width
                if (window.innerWidth < 576) { // mobile breakpoint
                    plantGrid.classList.add('row-cols-1');
                } else {
                    plantGrid.classList.add('row-cols-3');
                }
            }
        }

        // Run on page load
        document.addEventListener('DOMContentLoaded', updateGridColumns);

        // Run on window resize
        window.addEventListener('resize', updateGridColumns);
    </script>

    <!-- JavaScript Dependencies -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/js/bootstrap.bundle.min.js"></script>
    <script src="js/scripts.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/simple-datatables/7.1.2/simple-datatables.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.14.0/sweetalert2.all.min.js"></script>

    <!-- HERE Maps API Scripts -->
    <script src="https://js.api.here.com/v3/3.1/mapsjs-core.js"></script>
    <script src="https://js.api.here.com/v3/3.1/mapsjs-service.js"></script>
    <script src="https://js.api.here.com/v3/3.1/mapsjs-mapevents.js"></script>
    <script src="https://js.api.here.com/v3/3.1/mapsjs-ui.js"></script>
    <script src="https://js.api.here.com/v3/3.1/mapsjs-routing.js"></script>

    <script>
        // Grid Layout Functions
        function updateGridColumns() {
            const plantGrid = document.querySelector('.plants-grid');
            if (plantGrid) {
                // Remove existing column classes
                plantGrid.classList.remove('row-cols-1', 'row-cols-3');

                // Add appropriate class based on window width
                if (window.innerWidth < 576) { // mobile breakpoint
                    plantGrid.classList.add('row-cols-1');
                } else {
                    plantGrid.classList.add('row-cols-3');
                }
            }
        }

        // Set location status from PHP
        const locationSet = <?php echo json_encode($location_set); ?>;

        // Initialize platform variables
        var platform;
        var router;
        let initializationAttempts = 0;
        const maxAttempts = 3;

        // Initialize platform with rate limiting
        function initializePlatform(apiKey) {
            if (initializationAttempts >= maxAttempts) {
                console.error("Maximum initialization attempts reached");
                return;
            }

            try {
                platform = new H.service.Platform({
                    apikey: apiKey
                });
                router = platform.getRoutingService(null, 8);
                initializeModals();
            } catch (error) {
                console.error("Platform initialization error:", error);
                initializationAttempts++;
                setTimeout(() => initializePlatform(apiKey), 1000 * initializationAttempts);
            }
        }

        // Fetch the API key and initialize the platform
        fetch("get_here_api_key.php")
            .then(response => response.text())
            .then(apiKey => {
                initializePlatform(apiKey);
            })
            .catch(error => console.error("Error fetching API key:", error));

        // Update cart display with new quantities and total
        function updateCartDisplay(cartItems, totalPrice) {
            let cartCounter = document.getElementById("cartCounter");
            if (cartCounter) {
                let totalQuantity = cartItems.reduce((sum, item) => sum + parseInt(item.quantity), 0);
                cartCounter.textContent = totalQuantity;
            }

            let cartTotal = document.getElementById("cartTotal");
            if (cartTotal) {
                cartTotal.textContent = "Rp " + parseFloat(totalPrice).toLocaleString("id-ID");
            }
        }

        // Reverse geocode coordinates to get address
        function reverseGeocode(latitude, longitude, elementId) {
            if (!platform) {
                console.error("HERE platform not initialized");
                return;
            }

            platform.getSearchService().reverseGeocode({
                    at: `${latitude},${longitude}`
                },
                result => {
                    const address = result.items[0].address;
                    document.getElementById(elementId).innerText = address.label;
                },
                error => {
                    console.error("Reverse geocoding failed:", error);
                    document.getElementById(elementId).innerText = "Address not available";
                });
        }

        // Calculate and display route between two points
        // Calculate and display route between two points
        function calculateRoute(map, fromLat, fromLng, toLat, toLng, plantId) {
            if (!router) {
                console.error("Router not initialized");
                return;
            }

            const params = {
                'routingMode': 'fast',
                'transportMode': 'scooter',
                'origin': `${fromLat},${fromLng}`,
                'destination': `${toLat},${toLng}`,
                'return': 'polyline,summary'
            };

            router.calculateRoute(params, async (result) => {
                try {
                    if (result.routes && result.routes.length) {
                        const route = result.routes[0];
                        const routeShape = route.sections[0].polyline;

                        // Create a linestring from the polyline
                        let lineString;
                        try {
                            lineString = H.geo.LineString.fromFlexiblePolyline(routeShape);
                        } catch (e) {
                            console.error('Error parsing polyline:', e);
                            // Fallback: create linestring from origin and destination
                            lineString = new H.geo.LineString([
                                [fromLat, fromLng],
                                [toLat, toLng]
                            ]);
                        }

                        // Create a polyline for the route
                        const routeLine = new H.map.Polyline(lineString, {
                            style: {
                                lineWidth: 5,
                                strokeColor: '#00A6D6'
                            }
                        });

                        // Add the route polyline to the map
                        map.addObject(routeLine);

                        // Adjust viewport to show entire route
                        setTimeout(() => {
                            try {
                                map.getViewModel().setLookAtData({
                                    bounds: routeLine.getBoundingBox()
                                });
                            } catch (e) {
                                console.error('Error setting viewport:', e);
                            }
                        }, 100);

                        // Update distance and time info
                        const distance = route.sections[0].summary.length / 1000; // Convert to km
                        const time = route.sections[0].summary.duration / 60; // Convert to minutes

                        // Update route info in the modal
                        const routeInfo = document.getElementById(`routeInfo${plantId}`);
                        if (routeInfo) {
                            routeInfo.innerHTML = `
                        <div class="alert alert-info">
                            
                            <p class="mb-0"><i class="fas fa-clock"></i> <strong>Waktu perjalanan:</strong> ${Math.round(time)} menit</p>
                        </div>
                    `;
                        }
                    }
                } catch (error) {
                    console.error('Error processing route:', error);
                    const routeInfo = document.getElementById(`routeInfo${plantId}`);
                    if (routeInfo) {
                        routeInfo.innerHTML = '<div class="alert alert-warning">Tidak dapat menghitung rute perjalanan</div>';
                    }
                }
            }, (error) => {
                console.error('Route calculation error:', error);
                const routeInfo = document.getElementById(`routeInfo${plantId}`);
                if (routeInfo) {
                    routeInfo.innerHTML = '<div class="alert alert-warning">Tidak dapat menghitung rute perjalanan</div>';
                }
            });
        }

        // Initialize map for a specific container with routing
        function initializeMap(mapContainer, petaniLat, petaniLng, customerLat, customerLng, plantId) {
            if (!platform) return;

            const defaultLayers = platform.createDefaultLayers();
            const map = new H.Map(
                mapContainer,
                defaultLayers.vector.normal.map, {
                    zoom: 12,
                    center: {
                        lat: petaniLat,
                        lng: petaniLng
                    },
                    pixelRatio: window.devicePixelRatio || 1
                }
            );

            // Enable map interaction (pan, zoom, pinch)
            const behavior = new H.mapevents.Behavior(new H.mapevents.MapEvents(map));
            const ui = new H.ui.UI.createDefault(map, defaultLayers);

            // Create marker icons with different colors
            const petaniIcon = new H.map.Icon(
                `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="12" r="10" fill="#4CAF50"/>
            <circle cx="12" cy="12" r="5" fill="white"/>
        </svg>`, {
                    size: {
                        w: 24,
                        h: 24
                    }
                }
            );

            const customerIcon = new H.map.Icon(
                `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="12" r="10" fill="#2196F3"/>
            <circle cx="12" cy="12" r="5" fill="white"/>
        </svg>`, {
                    size: {
                        w: 24,
                        h: 24
                    }
                }
            );

            // Add markers with custom icons
            const petaniMarker = new H.map.Marker({
                lat: petaniLat,
                lng: petaniLng
            }, {
                icon: petaniIcon
            });
            const customerMarker = new H.map.Marker({
                lat: customerLat,
                lng: customerLng
            }, {
                icon: customerIcon
            });

            map.addObject(petaniMarker);
            map.addObject(customerMarker);

            // Add legends
            const legendGroup = new H.map.Group();
            const petaniLegend = new H.map.Marker({
                lat: petaniLat,
                lng: petaniLng
            }, {
                icon: new H.map.Icon(
                    '<div style="background: white; padding: 5px; border-radius: 5px;">Lokasi Petani</div>'
                )
            });
            const customerLegend = new H.map.Marker({
                lat: customerLat,
                lng: customerLng
            }, {
                icon: new H.map.Icon(
                    '<div style="background: white; padding: 5px; border-radius: 5px;">Lokasi Anda</div>'
                )
            });
            legendGroup.addObjects([petaniLegend, customerLegend]);
            map.addObject(legendGroup);

            // Calculate and display route
            setTimeout(() => {
                calculateRoute(map, customerLat, customerLng, petaniLat, petaniLng, plantId);
            }, 500);

            // Handle window resize
            window.addEventListener('resize', () => map.getViewPort().resize());

            return map;
        }

        // Initialize modals with maps and routing
        function initializeModals() {
            const modals = document.querySelectorAll('[id^="plantModal"]');
            let activeMap = null;
            let modalOpenDelay = 0;

            modals.forEach(modal => {
                const plantId = modal.id.replace('plantModal', '');
                const mapContainer = document.getElementById(`map${plantId}`);

                if (mapContainer) {
                    const petaniLat = parseFloat(mapContainer.dataset.latitude);
                    const petaniLng = parseFloat(mapContainer.dataset.longitude);
                    const customerLat = <?php echo $customer_lat ?? 'null' ?>;
                    const customerLng = <?php echo $customer_lng ?? 'null' ?>;

                    if (!isNaN(petaniLat) && !isNaN(petaniLng) && customerLat && customerLng) {
                        modal.addEventListener('show.bs.modal', function() {
                            if (activeMap) {
                                activeMap.dispose();
                            }

                            // Add increasing delay for subsequent modals
                            setTimeout(() => {
                                reverseGeocode(petaniLat, petaniLng, `plantAddress${plantId}`);
                                setTimeout(() => {
                                    activeMap = initializeMap(mapContainer, petaniLat, petaniLng, customerLat, customerLng, plantId);
                                }, 500);
                            }, modalOpenDelay);

                            modalOpenDelay = Math.min(modalOpenDelay + 500, 2000); // Cap maximum delay at 2 seconds
                        });

                        modal.addEventListener('hide.bs.modal', function() {
                            if (activeMap) {
                                activeMap.dispose();
                                activeMap = null;
                            }
                            mapContainer.innerHTML = '';
                            modalOpenDelay = 0; // Reset delay when modal is closed
                        });
                    }
                }
            });
        }

        // Add item to cart
        function addToCart(plantId) {
            if (!locationSet) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Alamat anda belum ditentukan',
                    text: 'Mohon tentukan alamat anda terlebih dahulu.',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href = 'update_location.php';
                });
                return;
            }

            fetch('add_to_cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `plant_id=${plantId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: 'Berhasil menambahkan tanaman ke keranjang!',
                        });

                        updateCartDisplay(data.cart_items, data.total_price);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: `Error: ${data.message}`,
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'An error occurred. Please try again.',
                    });
                });
        }

        // Document ready handler
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize grid columns
            updateGridColumns();

            // Initialize tooltips if using Bootstrap tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

            // Initialize dataTables if present
            const dataTable = document.getElementById('datatablesSimple');
            if (dataTable) {
                new simpleDatatables.DataTable(dataTable);
            }
        });

        // Window resize handler
        window.addEventListener('resize', updateGridColumns);
    </script>
</body>

</html>