<?php
require_once 'config/db_connect.php';
require_once 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Function to get Google Maps link
function getGoogleMapsLink($latitude, $longitude)
{
    return "https://www.google.com/maps?q={$latitude},{$longitude}";
}

// Pagination
// $items_per_page = 12;
// $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
// $offset = ($page - 1) * $items_per_page;

// Filtering
$search_name = isset($_GET['search_name']) && $_GET['search_name'] !== '' ? $_GET['search_name'] : null;
$jenis = isset($_GET['jenis']) && $_GET['jenis'] !== '' ? $_GET['jenis'] : null;
$min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float) $_GET['min_price'] : null;
$max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float) $_GET['max_price'] : null;
$min_stock = isset($_GET['min_stock']) && $_GET['min_stock'] !== '' ? (int) $_GET['min_stock'] : null;


// Base query
$query = "SELECT p.*, pet.nama_lengkap AS petani_name, pet.latitude, pet.longitude, pet.no_hp AS petani_phone
          FROM plants p 
          JOIN petani pet ON p.petani_id = pet.petani_id
          WHERE p.is_deleted = 0";

$params = [];

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

function getImagePath($fullPath)
{
    if (strpos($fullPath, 'assets/img/') === 0) {
        return $fullPath;
    } else {
        $filename = basename($fullPath);
        return "assets/img/" . $filename;
    }
}

// // Add sorting and pagination
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

$placeholder_attribution = 'Plant icons created by Kiranshastry - Flaticon';
$placeholder_link = 'https://www.flaticon.com/free-icons/plant';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Cari Tanaman - Plant Inventory Jabon Mekar</title>

    <link href="css/styles.css" rel="stylesheet" />
    <link rel="stylesheet" href="css/custom-styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        .sb-nav-fixed #layoutSidenav #layoutSidenav_content {
            padding-left: 10px !important
        }

        body {
            font-family: Poppins, sans-serif;
            background-color: #f8f9fa;
            color: #333
        }

        .navbar {
            background-color: #2c3e50;
            padding: .5rem 1rem
        }

        .nav-link,
        .navbar-brand {
            color: #ecf0f1 !important
        }

        .container-fluid {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px
        }

        .filter-form,
        h1 {
            margin-bottom: 30px
        }

        h1 {
            color: #2c3e50;
            font-weight: 700;
            text-align: center
        }

        .filter-form {
            background: linear-gradient(135deg, #f5f7fa 0, #c3cfe2 100%);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 8px 15px rgba(0, 0, 0, .1)
        }

        .plant-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: .3s;
            box-shadow: 0 5px 15px rgba(0, 0, 0, .1);
            height: 100%;
            max-width: 250px;
            margin: 0 auto
        }

        .plant-image-container {
            position: relative;
            width: 100%;
            padding-top: 75%;
            overflow: hidden
        }

        .modal-body .plant-image-container img,
        .plant-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            object-fit: cover
        }

        .plant-image {
            height: 100%;
            transition: transform .3s
        }

        .plant-card:hover .plant-image {
            transform: scale(1.05)
        }

        .modal-dialog {
            display: flex;
            align-items: center;
            min-height: calc(100% - 1rem)
        }

        .modal-content {
            width: 100%;
            max-width: 800px;
            margin: auto;
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0, 0, 0, .1)
        }

        .modal-header {
            background-color: #3498db;
            color: #fff;
            border-bottom: none
        }

        .modal-body {
            padding: 2rem
        }

        .modal-footer {
            background-color: #f8f9fa;
            border-top: 1px solid #e9ecef
        }

        .modal-body .plant-image-container {
            width: 100%;
            height: 300px;
            position: relative;
            overflow: hidden;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, .1)
        }

        .modal-body .plant-image-container img {
            height: 100%
        }

        .modal-body .plant-details {
            display: flex;
            flex-direction: column;
            height: 100%
        }

        .modal-body .plant-details p {
            margin-bottom: .5rem;
            font-size: 1rem;
            line-height: 1.5;
            transition: .3s
        }

        .modal-body .plant-details p:hover {
            transform: translateX(5px);
            color: #3498db
        }

        .modal-body .plant-details strong {
            font-weight: 600;
            color: #2c3e50
        }

        @media (min-width:768px) {
            .filter-form {
                max-width: 800px;
                margin-left: auto;
                margin-right: auto
            }

            .row-cols-md-4>* {
                flex: 0 0 auto;
                width: 25%
            }

            .modal-dialog {
                position: fixed !important;
                top: 50% !important;
                left: 50% !important;
                transform: translate(-50%, -50%) !important;
                width: 90% !important;
                max-width: 800px !important;
                margin: 0 !important
            }

            .modal-content {
                max-height: 90vh;
                overflow-y: auto
            }
        }

        @media (max-width:767px) {

            .plant-card,
            h1 {
                margin-bottom: 15px
            }

            body {
                font-size: 14px
            }

            .navbar-brand {
                font-size: 1.2rem
            }

            .container-fluid {
                padding: 10px 5px
            }

            h1 {
                font-size: 1.5rem
            }

            .filter-form {
                padding: 10px
            }

            .form-label,
            .modal-body .plant-details p {
                font-size: .9rem
            }

            .btn,
            .form-control,
            .form-select {
                font-size: .9rem;
                padding: .375rem .75rem
            }

            .card-body,
            .modal-body,
            .modal-footer,
            .modal-header {
                padding: .75rem
            }

            .card-title {
                font-size: 1rem
            }

            .card-text {
                font-size: .8rem
            }

            .modal-dialog {
                margin: .5rem;
                max-width: 100%
            }

            .modal-content {
                border-radius: 10px;
                max-height: 100vh;
                overflow-y: auto
            }

            .modal-body .plant-image-container {
                height: 200px
            }

            .modal-body .row {
                flex-direction: column
            }

            .modal-body .col-md-6 {
                width: 100%;
                margin-bottom: 1rem
            }

            .modal-body {
                padding: 1rem
            }
        }

        .modal,
        .modal-open {
            padding-right: 0 !important
        }

        @media (min-width:576px) {
            .modal-dialog {
                max-width: 500px;
                margin: 1.75rem auto
            }
        }

        .modal-open {
            overflow: auto !important
        }

        #layoutSidenav_content {
            margin-left: 0 !important
        }

        .plant-card .card-body {
            display: flex;
            flex-direction: column
        }

        .plant-card .card-footer {
            margin-top: auto
        }
    </style>
    <!-- <script src="js/matomo.js"> </script> -->
</head>

<body class="sb-nav-fixed">
    <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">

        <ul class="navbar-nav ms-auto me-0 me-md-3 my-2 my-md-0">
            <li class="nav-item">
                <a class="nav-link" href="register.php">Register</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="login.php">Login</a>
            </li>
        </ul>
    </nav>

    <div id="layoutSidenav">
        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid px-4">
                    <h1 class="mt-2">Plant Inventory Jabon Mekar</h1>

                    <!-- Updated Filter Form -->
                    <div class="card mb-4 filter-form">
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
                                            <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $jenis === $option ? 'selected' : ''; ?>>
                                                <?php echo ucfirst(htmlspecialchars($option)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="min_price" class="form-label">Minimum harga</label>
                                    <input type="number" class="form-control" id="min_price" name="min_price"
                                        value="<?php echo $min_price !== null ? $min_price : ''; ?>" min="0">
                                </div>
                                <div class="col-md-2">
                                    <label for="max_price" class="form-label">Maksimum harga</label>
                                    <input type="number" class="form-control" id="max_price" name="max_price"
                                        value="<?php echo $max_price !== null ? $max_price : ''; ?>" min="0">
                                </div>
                                <div class="col-md-2">
                                    <label for="min_stock" class="form-label">Minimum stok</label>
                                    <input type="number" class="form-control" id="min_stock" name="min_stock"
                                        value="<?php echo $min_stock !== null ? $min_stock : ''; ?>" min="0">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary w-100">Terapkan</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Plants Grid -->
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-3">
                        <?php foreach ($plants as $plant): ?>
                            <div class="col">
                                <div class="card h-100 plant-card">
                                    <div class="plant-image-container">
                                        <img src="<?php echo !empty($plant['gambar']) ? htmlspecialchars(getImagePath($plant['gambar'])) : 'assets/img/placeholder.png'; ?>"
                                            class="plant-image" alt="<?php echo htmlspecialchars($plant['nama']); ?>"
                                            onerror="this.onerror=null; this.src='assets/img/placeholder.png'; this.title='<?php echo htmlspecialchars($placeholder_attribution); ?>';">
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
                                    <?php if (empty($plant['gambar'])): ?>
                                        <div class="card-footer">
                                            <small class="text-muted">
                                                <a href="<?php echo htmlspecialchars($placeholder_link); ?>" target="_blank"
                                                    title="<?php echo htmlspecialchars($placeholder_attribution); ?>">
                                                    <?php echo htmlspecialchars($placeholder_attribution); ?>
                                                </a>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    <div class="card-footer text-center">
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                            data-bs-target="#plantModal<?php echo $plant['plant_id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Plant Modal -->
                            <div class="modal fade" id="plantModal<?php echo $plant['plant_id']; ?>" tabindex="-1"
                                aria-labelledby="plantModalLabel<?php echo $plant['plant_id']; ?>" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="plantModalLabel<?php echo $plant['plant_id']; ?>">
                                                <?php echo htmlspecialchars($plant['nama']); ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <?php if ($plant['latitude'] && $plant['longitude']): ?>
                                                        <div class="mb-3">
                                                            <div id="map<?php echo $plant['plant_id']; ?>"
                                                                style="width: 100%; height: 200px; border-radius: 8px; margin-bottom: 10px;">
                                                            </div>
                                                            <p class="mb-2"><strong>Lokasi Toko:</strong> <span
                                                                    id="plantAddress<?php echo $plant['plant_id']; ?>">Sedang
                                                                    memuat...</span></p>
                                                            <?php
                                                            $mapsLink = getGoogleMapsLink($plant['latitude'], $plant['longitude']);
                                                            if ($mapsLink):
                                                                ?>
                                                                <a href="<?php echo htmlspecialchars($mapsLink); ?>" target="_blank"
                                                                    class="btn btn-sm btn-secondary">
                                                                    <i class="fas fa-map-marker-alt"></i> Lihat lokasi di Google
                                                                    Maps
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="plant-image-container">
                                                        <img src="<?php echo !empty($plant['gambar']) ? htmlspecialchars(getImagePath($plant['gambar'])) : 'assets/img/placeholder.png'; ?>"
                                                            alt="<?php echo htmlspecialchars($plant['nama']); ?>"
                                                            class="plant-image"
                                                            onerror="this.onerror=null; this.src='assets/img/placeholder.png'; this.title='<?php echo htmlspecialchars($placeholder_attribution); ?>';">
                                                    </div>
                                                    <?php if (empty($plant['gambar'])): ?>
                                                        <small class="text-muted">
                                                            <a href="<?php echo htmlspecialchars($placeholder_link); ?>"
                                                                target="_blank"
                                                                title="<?php echo htmlspecialchars($placeholder_attribution); ?>">
                                                                <?php echo htmlspecialchars($placeholder_attribution); ?>
                                                            </a>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-6 plant-details">
                                                    <p><strong>Harga:</strong> Rp
                                                        <?php echo number_format($plant['harga'], 0, ',', '.'); ?>
                                                    </p>
                                                    <p><strong>Tipe:</strong>
                                                        <?php echo ucfirst(htmlspecialchars($plant['jenis'])); ?></p>
                                                    <p><strong>Petani:</strong>
                                                        <?php echo htmlspecialchars($plant['petani_name']); ?></p>
                                                    <p><strong>Kontak:</strong>
                                                        <?php echo htmlspecialchars($plant['petani_phone']); ?></p>
                                                    <p><strong>Jumlah stok:</strong>
                                                        <?php echo htmlspecialchars($plant['stok']); ?></p>
                                                    <p><strong>Deskripsi:</strong>
                                                        <?php echo htmlspecialchars($plant['deskripsi']); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary"
                                                data-bs-dismiss="modal">Close</button>
                                            <a href="login.php" class="btn btn-primary">Login untuk beli</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <!-- <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                    <a class="page-link"
                                        href="?page=<?php echo $i; ?>&jenis=<?php echo urlencode($jenis); ?>&min_price=<?php echo $min_price; ?>&max_price=<?php echo $max_price; ?>&min_stock=<?php echo $min_stock; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav> -->
                </div>
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/js/bootstrap.bundle.min.js"
        integrity="sha512-i9cEfJwUwViEPFKdC1enz4ZRGBj8YQo6QByFTF92YXHi7waCqyexvRD75S5NVTsSiTv7rKWqG9Y5eFxmRsOn0A=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="js/scripts.js"></script>
    <!-- Update the JavaScript section at the bottom of the file -->
    <script src="https://js.api.here.com/v3/3.1/mapsjs-core.js"></script>
    <script src="https://js.api.here.com/v3/3.1/mapsjs-service.js"></script>
    <script src="https://js.api.here.com/v3/3.1/mapsjs-mapevents.js"></script>
    <script src="https://js.api.here.com/v3/3.1/mapsjs-ui.js"></script>
    <link rel="stylesheet" type="text/css" href="https://js.api.here.com/v3/3.1/mapsjs-ui.css" />


    <script>
        var platform;
        var mapInstances = {}; // Store map instances for cleanup

        fetch("get_here_api_key.php")
            .then(e => e.text())
            .then(e => {
                platform = new H.service.Platform({ apikey: e });
                initializeModals();
            })
            .catch(e => console.error("Error fetching API key:", e));

        function reverseGeocode(e, r, n) {
            if (!platform) {
                console.error("HERE platform not initialized");
                return;
            }
            platform.getSearchService().reverseGeocode({
                at: e + "," + r
            }, function (e) {
                var r = e.items[0].address;
                document.getElementById(n).innerText = r.label;
            }, function (e) {
                console.error("Reverse geocoding failed: ", e);
                document.getElementById(n).innerText = "Address not available";
            });
        }

        function cleanupMap(mapId) {
            try {
                if (mapInstances[mapId]) {
                    const instance = mapInstances[mapId];

                    // Remove window resize listener if it exists
                    if (instance.resizeListener) {
                        window.removeEventListener('resize', instance.resizeListener);
                    }

                    // Remove marker if it exists
                    if (instance.marker) {
                        instance.map.removeObject(instance.marker);
                    }

                    // Cleanup UI first if it exists
                    if (instance.ui) {
                        instance.ui.dispose();
                    }

                    // Cleanup behavior before map if it exists
                    if (instance.behavior) {
                        try {
                            instance.behavior.disable();
                        } catch (e) {
                            console.debug('Behavior already disabled');
                        }
                    }

                    // Finally dispose the map
                    if (instance.map) {
                        instance.map.dispose();
                    }

                    // Clear the instance
                    delete mapInstances[mapId];
                }
            } catch (error) {
                console.error('Error during map cleanup:', error);
            }
        }

        function initializeMap(mapContainer, lat, lng) {
            if (!platform || !mapContainer) return;

            const mapId = mapContainer.id;

            // Clean up existing map instance if it exists
            cleanupMap(mapId);

            try {
                // Initialize the map
                const defaultLayers = platform.createDefaultLayers();
                const map = new H.Map(
                    mapContainer,
                    defaultLayers.vector.normal.map,
                    {
                        zoom: 15,
                        center: { lat: lat, lng: lng }
                    }
                );

                // Add map interaction and UI controls
                const behavior = new H.mapevents.Behavior(new H.mapevents.MapEvents(map));
                const ui = H.ui.UI.createDefault(map, defaultLayers);

                // Add a marker for the petani location
                const marker = new H.map.Marker({ lat: lat, lng: lng });
                map.addObject(marker);

                // Create resize listener
                const resizeListener = () => {
                    if (map && !map.disposed) {
                        map.getViewPort().resize();
                    }
                };

                // Add resize listener
                window.addEventListener('resize', resizeListener);

                // Store all instances and listeners for cleanup
                mapInstances[mapId] = {
                    map: map,
                    behavior: behavior,
                    ui: ui,
                    marker: marker,
                    resizeListener: resizeListener
                };
            } catch (error) {
                console.error('Error initializing map:', error);
                cleanupMap(mapId); // Cleanup if initialization fails
            }
        }

        function initializeModals() {
            <?php foreach ($plants as $plant): ?>
                <?php if ($plant['latitude'] && $plant['longitude']): ?>
                    const modal<?php echo $plant['plant_id']; ?> = document.getElementById('plantModal<?php echo $plant['plant_id']; ?>');
                    if (!modal<?php echo $plant['plant_id']; ?>) return;

                    // Handle modal show event
                    modal<?php echo $plant['plant_id']; ?>.addEventListener('shown.bs.modal', function () {
                        const mapContainer = document.getElementById('map<?php echo $plant['plant_id']; ?>');
                        if (!mapContainer) return;

                        reverseGeocode(
                            <?php echo $plant['latitude']; ?>,
                            <?php echo $plant['longitude']; ?>,
                            'plantAddress<?php echo $plant['plant_id']; ?>'
                        );

                        // Initialize map after a short delay to ensure the container is ready
                        setTimeout(() => {
                            initializeMap(
                                mapContainer,
                                <?php echo $plant['latitude']; ?>,
                                <?php echo $plant['longitude']; ?>
                            );
                        }, 100);
                    });

                    // Handle modal hide event for cleanup
                    modal<?php echo $plant['plant_id']; ?>.addEventListener('hidden.bs.modal', function () {
                        cleanupMap('map<?php echo $plant['plant_id']; ?>');
                    });
                <?php endif; ?>
            <?php endforeach; ?>
        }
    </script>
</body>

</html>