<?php
require_once 'config/db_connect.php';
require_once 'auth_middleware.php';

$user = requireRole('pembeli');

if (!$user) {
    header("Location: login.php");
    exit();
}

$user_id = $user['user_id'];
$success_message = '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Add the customer coordinates query here
$stmt = $pdo->prepare("SELECT latitude, longitude FROM customers WHERE user_id = ?");
$stmt->execute([$user_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

// Update the cart items query near the top of cart.php
$stmt = $pdo->prepare("
    SELECT c.cart_id, c.quantity, p.plant_id, p.nama, p.harga, p.stok, p.berat,
           pt.petani_id, pt.nama_lengkap as petani_nama, pt.latitude as petani_lat, pt.longitude as petani_lng
    FROM cart c
    JOIN customers cu ON c.customer_id = cu.customer_id
    JOIN plants p ON c.plant_id = p.plant_id
    JOIN petani pt ON p.petani_id = pt.petani_id
    WHERE cu.user_id = ?
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group cart items by petani
$items_by_petani = [];
foreach ($cart_items as $item) {
    if (!isset($items_by_petani[$item['petani_id']])) {
        $items_by_petani[$item['petani_id']] = [
            'nama' => $item['petani_nama'],
            'lat' => $item['petani_lat'],
            'lng' => $item['petani_lng'],
            'items' => []
        ];
    }
    $items_by_petani[$item['petani_id']]['items'][] = $item;
}

// Helper function for distance calculation
function calculateDistance($lat1, $lon1, $lat2, $lon2)
{
    $R = 6371; // Earth's radius in km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $R * $c; // Distance in km
}

// Function to calculate shipping cost
function calculateShippingCost($distance, $weight, $quantity, $method)
{
    if ($weight > 5 || $quantity >= 10) {
        return 50000 + ($distance * 1000) + ($weight * 10000);
    } else if ($method === 'jne' || $method === 'jnt') {
        return 15000 + ($distance * 200) + ($weight * 2000);
    }
    return 0;
}

$total_price = 0;
foreach ($cart_items as $item) {
    $total_price += $item['harga'] * $item['quantity'];
}

// Process order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_order'])) {
    try {
        if (!isset($_POST['selected_items']) || empty($_POST['selected_items'])) {
            throw new Exception("Pilih setidaknya satu item untuk checkout");
        }

        $selected_items = $_POST['selected_items'];
        $pdo->beginTransaction();

        // Get customer balance and ID
        $stmt = $pdo->prepare("SELECT customer_id, balance, latitude, longitude FROM customers WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        // Calculate total price and shipping costs for selected items
        $total_price = 0;
        $total_shipping_cost = 0;
        $shipping_details = [];

        // Group selected items by petani and calculate costs
        foreach ($items_by_petani as $petani_id => $petani_data) {
            $selected_petani_items = array_filter($petani_data['items'], function ($item) use ($selected_items) {
                return in_array($item['cart_id'], $selected_items);
            });

            if (empty($selected_petani_items)) {
                continue;
            }

            // Calculate weight and quantity
            $weight = 0;
            $quantity = 0;
            $subtotal = 0;

            foreach ($selected_petani_items as $item) {
                $weight += ($item['berat'] ?? 0) * $item['quantity'];
                $quantity += $item['quantity'];
                $subtotal += $item['harga'] * $item['quantity'];
            }

            // Get shipping method
            $shipping_method = '';
            if ($weight > 5 || $quantity >= 10) {
                $shipping_method = 'direct';
            } else {
                $shipping_method = $_POST['shipping_method'][$petani_id] ?? '';
                if (!in_array($shipping_method, ['jne', 'jnt'])) {
                    throw new Exception("Pilih metode pengiriman untuk semua pesanan");
                }
            }

            // Calculate distance and shipping cost
            $distance = calculateDistance(
                $customer['latitude'],
                $customer['longitude'],
                $petani_data['lat'],
                $petani_data['lng']
            );

            $shipping_cost = calculateShippingCost($distance, $weight, $quantity, $shipping_method);
            $total_shipping_cost += $shipping_cost;
            $total_price += $subtotal;

            $shipping_details[$petani_id] = [
                'method' => $shipping_method,
                'cost' => $shipping_cost,
                'distance' => $distance,
                'weight' => $weight,
                'items' => $selected_petani_items
            ];
        }

        // Add shipping cost to total
        $total_price += $total_shipping_cost;

        if ($customer['balance'] < $total_price) {
            throw new Exception("Saldo anda tidak cukup");
        }

        // Get the order notes
        $order_notes = isset($_POST['order_notes']) ? trim($_POST['order_notes']) : '';
        if (empty($order_notes)) {
            throw new Exception("Catatan untuk pesanan wajib diisi");
        }

        // Create order
        $stmt = $pdo->prepare("INSERT INTO orders (customer_id, total_harga, notes) VALUES (?, ?, ?)");
        $stmt->execute([$customer['customer_id'], $total_price, $order_notes]);
        $order_id = $pdo->lastInsertId();

        // Process shipping details and order items
        foreach ($shipping_details as $petani_id => $shipping_data) {
            // Insert shipping details
            $stmt = $pdo->prepare("
                INSERT INTO order_shipping 
                    (order_id, petani_id, shipping_method, shipping_cost, distance_km, total_weight)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $order_id,
                $petani_id,
                $shipping_data['method'],
                $shipping_data['cost'],
                $shipping_data['distance'],
                $shipping_data['weight']
            ]);

            // Process order items for this petani
            foreach ($shipping_data['items'] as $item) {
                if ($item['quantity'] > $item['stok']) {
                    throw new Exception("Insufficient stock for " . $item['nama']);
                }

                // Add order item
                $stmt = $pdo->prepare("
                    INSERT INTO order_items 
                        (order_id, plant_id, kuantitas, harga_per_item, total_harga, order_item_status)
                    VALUES (?, ?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([
                    $order_id,
                    $item['plant_id'],
                    $item['quantity'],
                    $item['harga'],
                    $item['harga'] * $item['quantity']
                ]);

                // Update stock
                $stmt = $pdo->prepare("UPDATE plants SET stok = stok - ? WHERE plant_id = ?");
                $stmt->execute([$item['quantity'], $item['plant_id']]);

                // Remove item from cart
                $stmt = $pdo->prepare("DELETE FROM cart WHERE cart_id = ?");
                $stmt->execute([$item['cart_id']]);
            }
        }

        // Update customer balance
        $stmt = $pdo->prepare("UPDATE customers SET balance = balance - ? WHERE customer_id = ?");
        $stmt->execute([$total_price, $customer['customer_id']]);

        $pdo->commit();
        $success_message = "Tanaman berhasil dipesan!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = $e->getMessage();
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
    <title>Keranjang - Plant Inventory Jabon Mekar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/simple-datatables/7.1.2/style.min.css"
        integrity="sha512-dcdQkw+lfchKGZD+YmuSMwHBoR8AgJGrHXtBVXaxo2sMhlSKB0r04F2W9+BXCfdDjmP75EEl7oVNaHn2FTVNpQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="css/styles.css" rel="stylesheet" />
    <link rel="stylesheet" href="css/custom-styles.css">
    <script src="https://js.api.here.com/v3/3.1/mapsjs-core.js"></script>
    <script src="https://js.api.here.com/v3/3.1/mapsjs-service.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/js/all.min.js"
        integrity="sha512-2bMhOkE/ACz21dJT8zBOMgMecNxx0d37NND803ExktKiKdSzdwn+L7i9fdccw/3V06gM/DBWKbYmQvKMdAA9Nw=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
        .item-checkbox {
            width: 20px;
            height: 20px;
            margin-right: 10px;
        }

        .select-all-container {
            margin-bottom: 15px;
        }

        .btn,
        .table th {
            font-weight: 600
        }

        .btn,
        .card,
        .form-control {
            transition: .3s
        }

        #cart-total,
        h1 {
            font-weight: 700
        }

        #cart-total,
        .table th,
        h1 {
            color: #2c3e50
        }

        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif
        }

        .container-fluid {
            max-width: 1200px;
            margin: 0 auto
        }

        h1 {
            margin-bottom: 30px
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .1);
            overflow: hidden
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, .15)
        }

        .card-body {
            padding: 30px
        }

        .table {
            margin-bottom: 0
        }

        .table th {
            background-color: #f1f3f5;
            border-top: none
        }

        .table td {
            vertical-align: middle;
            color: #34495e
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid #ced4da
        }

        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 .2rem rgba(52, 152, 219, .25)
        }

        .btn {
            border-radius: 8px;
            text-transform: uppercase;
            letter-spacing: .5px
        }

        .btn-danger {
            background-color: #e74c3c;
            border: none
        }

        .btn-danger:hover {
            background-color: #c0392b;
            transform: translateY(-2px)
        }

        .btn-primary {
            background-color: #3498db;
            border: none
        }

        .btn-primary:hover {
            background-color: #2980b9;
            transform: translateY(-2px)
        }

        #cart-total {
            font-size: 1.5rem
        }

        @media (max-width:767px) {
            .card-body {
                padding: 20px
            }

            .table-responsive-stack tr {
                display: flex;
                flex-direction: column;
                border: 1px solid #ddd;
                border-radius: 8px;
                margin-bottom: 1rem;
                padding: 10px
            }

            .table-responsive-stack td {
                border: none;
                padding: .5rem 0;
                display: flex;
                justify-content: space-between;
                align-items: center
            }

            .table-responsive-stack td:before {
                content: attr(data-label);
                font-weight: 600;
                color: #2c3e50;
                padding-right: .5rem
            }

            .quantity-input {
                width: 60px;
                margin-left: auto
            }

            .remove-item {
                width: auto;
                margin-left: auto
            }

            #cart-total {
                font-size: 1.2rem
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0
            }

            to {
                opacity: 1
            }
        }

        .fade-in {
            animation: .5s ease-in fadeIn
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
                <div class="container-fluid px-4 fade-in">
                    <h1 class="mt-4">Keranjang</h1>
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <?php if (empty($cart_items)): ?>
                                <p>Keranjang anda kosong.</p>
                            <?php else: ?>
                                <div class="select-all-container">
                                    <input type="checkbox" id="select-all" class="item-checkbox">
                                    <label for="select-all">Pilih Semua</label>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-responsive-stack">
                                        <thead>
                                            <tr>
                                                <th>Pilih</th>
                                                <th>Tanaman</th>
                                                <th>Harga</th>
                                                <th>Jumlah</th>
                                                <th>Total Bayar</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($cart_items as $item): ?>
                                                <tr data-cart-id="<?php echo $item['cart_id']; ?>" data-petani-id="<?php echo $item['petani_id']; ?>">
                                                    <td data-label="Pilih">
                                                        <input type="checkbox" class="item-checkbox" name="selected_items[]"
                                                            value="<?php echo $item['cart_id']; ?>"
                                                            data-weight="<?php echo $item['berat']; ?>"> <!-- Add this data attribute -->
                                                    </td>
                                                    <td data-label="Tanaman"><?php echo htmlspecialchars($item['nama']); ?></td>
                                                    <td data-label="Harga" class="item-price"
                                                        data-price="<?php echo $item['harga']; ?>">
                                                        Rp <?php echo number_format($item['harga'], 2); ?>
                                                    </td>
                                                    <td data-label="Jumlah">
                                                        <input type="number" class="form-control quantity-input"
                                                            data-cart-id="<?php echo $item['cart_id']; ?>"
                                                            value="<?php echo $item['quantity']; ?>" min="1"
                                                            max="<?php echo $item['stok']; ?>">
                                                    </td>
                                                    <td data-label="Total Bayar" class="item-total"
                                                        data-total="<?php echo $item['harga'] * $item['quantity']; ?>">
                                                        Rp <?php echo number_format($item['harga'] * $item['quantity'], 2); ?>
                                                    </td>
                                                    <td data-label="Aksi">
                                                        <button class="btn btn-danger btn-sm remove-item"
                                                            data-cart-id="<?php echo $item['cart_id']; ?>">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-4 d-flex justify-content-between align-items-center flex-wrap">
                                    <h4>Total Terpilih: <span id="selected-total" class="text-primary">Rp 0,00</span></h4>
                                    <form method="POST" id="checkout-form">
                                        <div class="mb-3">
                                            <label for="order-notes" class="form-label">Catatan untuk Petani (Wajib)</label>
                                            <textarea class="form-control" id="order-notes" name="order_notes" rows="3"
                                                placeholder="Masukkan detail tambahan seperti nomor apartemen, nomor rumah, atau instruksi khusus"
                                                required></textarea>
                                        </div>

                                        <div class="mt-4">
                                            <h4 class="mb-3">Opsi Pengiriman</h4>
                                            <?php foreach ($items_by_petani as $petani_id => $petani_data): ?>
                                                <div class="card mb-3 shipping-option"
                                                    data-petani-id="<?php echo $petani_id; ?>"
                                                    data-petani-lat="<?php echo $petani_data['lat']; ?>"
                                                    data-petani-lng="<?php echo $petani_data['lng']; ?>"
                                                    data-customer-lat="<?php echo $customer['latitude']; ?>"
                                                    data-customer-lng="<?php echo $customer['longitude']; ?>">
                                                    <div class="card-body">
                                                        <h5 class="card-title">Pesanan dari:
                                                            <?php echo htmlspecialchars($petani_data['nama']); ?>
                                                        </h5>

                                                        <?php
                                                        $total_weight = 0;
                                                        $total_quantity = 0;
                                                        foreach ($petani_data['items'] as $item) {
                                                            if (in_array($item['cart_id'], $_POST['selected_items'] ?? [])) {
                                                                $total_weight += ($item['berat'] ?? 0) * $item['quantity'];
                                                                $total_quantity += $item['quantity'];
                                                            }
                                                        }
                                                        ?>

                                                        <p class="mb-2">Total Berat: <span
                                                                class="total-weight"><?php echo number_format($total_weight, 2); ?></span>
                                                            kg</p>
                                                        <p class="mb-3">Total Quantity: <span
                                                                class="total-quantity"><?php echo $total_quantity; ?></span></p>

                                                        <div class="shipping-methods">
                                                            <?php if ($total_weight > 5 || $total_quantity >= 10): ?>
                                                                <div class="alert alert-info">
                                                                    Karena berat melebihi 5 kg atau jumlah tanaman melebihi 10, pesanan
                                                                    akan diantar langsung oleh petani.
                                                                </div>
                                                                <input type="hidden"
                                                                    name="shipping_method[<?php echo $petani_id; ?>]"
                                                                    value="direct">
                                                            <?php else: ?>
                                                                <div class="form-check mb-2">
                                                                    <input class="form-check-input shipping-method" type="radio"
                                                                        name="shipping_method[<?php echo $petani_id; ?>]"
                                                                        value="jne" id="jne_<?php echo $petani_id; ?>">
                                                                    <label class="form-check-label"
                                                                        for="jne_<?php echo $petani_id; ?>">
                                                                        JNE
                                                                    </label>
                                                                </div>
                                                                <input type="hidden" class="item-weight" value="<?php echo $item['berat'] ?? 0; ?>">
                                                                <div class="form-check mb-2">
                                                                    <input class="form-check-input shipping-method" type="radio"
                                                                        name="shipping_method[<?php echo $petani_id; ?>]"
                                                                        value="jnt" id="jnt_<?php echo $petani_id; ?>">
                                                                    <label class="form-check-label"
                                                                        for="jnt_<?php echo $petani_id; ?>">
                                                                        J&T
                                                                    </label>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="shipping-cost mt-2">
                                                            Biaya Pengiriman: <span class="cost-value">Rp 0</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                            <div class="mb-3">
                                                <strong>Total Biaya Pengiriman: <span id="total-shipping-cost">Rp
                                                        0</span></strong>
                                            </div>
                                        </div>
                                        <button type="submit" name="process_order"
                                            class="btn btn-primary btn-lg">Checkout</button>
                                    </form>
                                </div>
                            <?php endif; ?>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/simple-datatables/7.1.2/simple-datatables.min.js"
        integrity="sha512-3ty9AJncMgK2yFwGuF8Shc5dMwiXeHiEXV5QiOXrhuXzQLLorWeBEpmLWduNl49A9ffIyf+zmQ7nI1PQlUaRYg=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="js/datatables-simple-demo.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // ========== Helper Functions ==========

            // Calculate distance between two points
            function calculateDistance(lat1, lon1, lat2, lon2) {
                const R = 6371; // Earth's radius in km
                const dLat = (lat2 - lat1) * Math.PI / 180;
                const dLon = (lon2 - lon1) * Math.PI / 180;
                const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                    Math.sin(dLon / 2) * Math.sin(dLon / 2);
                const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
                return R * c; // Distance in km
            }

            // Format price with currency
            function formatPrice(price) {
                // Round to nearest integer
                price = Math.round(price);

                // Format with thousand separators, no decimals
                return 'Rp ' + price.toLocaleString('id-ID', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                });
            }

            // Add fade-in animation to elements
            function addFadeInAnimation(element) {
                element.classList.add("fade-in");
                setTimeout(() => {
                    element.classList.remove("fade-in");
                }, 500);
            }

            // ========== Price Calculation Functions ==========

            // Calculate shipping cost
            function calculateShippingCost(distance, weight, isExpedited, quantity) {
                let cost = 0;
                if (weight > 5 || quantity >= 10) {
                    // Direct delivery by farmer
                    cost = 50000 + (distance * 1000) + (weight * 10000);
                } else if (isExpedited) {
                    // Courier service
                    cost = 15000 + (distance * 200) + (weight * 2000);
                }
                return Math.round(cost);
            }

            // Update shipping costs and totals
            function updateShippingCosts() {
                let totalShippingCost = 0;
                let subtotal = 0;

                // Group selected items by petani
                const selectedItemsByPetani = {};

                // Calculate subtotal and group items by petani
                $('.item-checkbox:checked').each(function() {
                    const row = $(this).closest('tr');
                    const petaniId = row.data('petani-id');
                    const quantity = parseInt(row.find('.quantity-input').val()) || 0;
                    const price = parseFloat(row.find('.item-price').data('price')) || 0;
                    const weight = parseFloat($(this).data('weight')) || 0;

                    // Add to subtotal with rounding
                    subtotal += Math.round(quantity * price);

                    // Initialize petani data if not exists
                    if (!selectedItemsByPetani[petaniId]) {
                        selectedItemsByPetani[petaniId] = {
                            weight: 0,
                            quantity: 0
                        };
                    }

                    // Add to petani totals
                    selectedItemsByPetani[petaniId].weight += weight * quantity;
                    selectedItemsByPetani[petaniId].quantity += quantity;
                });

                // Process each shipping option
                $('.shipping-option').each(function() {
                    const $option = $(this);
                    const petaniId = $option.data('petani-id');
                    const petaniItems = selectedItemsByPetani[petaniId] || {
                        weight: 0,
                        quantity: 0
                    };

                    // Get coordinates
                    const petaniLat = parseFloat($option.data('petani-lat'));
                    const petaniLng = parseFloat($option.data('petani-lng'));
                    const customerLat = parseFloat($option.data('customer-lat'));
                    const customerLng = parseFloat($option.data('customer-lng'));

                    // Calculate distance
                    const distance = calculateDistance(petaniLat, petaniLng, customerLat, customerLng);

                    // Update displays
                    $option.find('.total-weight').text(petaniItems.weight.toFixed(2));
                    $option.find('.total-quantity').text(petaniItems.quantity);

                    // Add or update distance display
                    if (!$option.find('.distance-display').length) {
                        $option.find('.shipping-methods').before(
                            `<div class="distance-display mb-2">Jarak dari petani: ${distance.toFixed(2)} km</div>`
                        );
                    } else {
                        $option.find('.distance-display').text(`Jarak dari petani: ${distance.toFixed(2)} km`);
                    }

                    // Calculate shipping cost with rounding
                    let shippingCost = 0;
                    if (petaniItems.weight > 5 || petaniItems.quantity >= 10) {
                        shippingCost = Math.round(50000 + (distance * 1000) + (petaniItems.weight * 10000));
                        $option.find('.shipping-methods').html(
                            `<div class="alert alert-info">
                        Karena berat melebihi 5kg atau quantity melebihi 10, pesanan akan diantar langsung oleh petani.
                    </div>
                    <input type="hidden" name="shipping_method[${petaniId}]" value="direct">`
                        );
                    } else {
                        const shippingMethod = $option.find('.shipping-method:checked').val();
                        if (shippingMethod === 'jne' || shippingMethod === 'jnt') {
                            shippingCost = Math.round(15000 + (distance * 200) + (petaniItems.weight * 2000));
                        }
                    }

                    // Update shipping cost display with rounded value
                    $option.find('.cost-value').text(formatPrice(shippingCost));
                    totalShippingCost += shippingCost;
                });

                // Round all final totals
                totalShippingCost = Math.round(totalShippingCost);
                subtotal = Math.round(subtotal);
                const grandTotal = Math.round(subtotal + totalShippingCost);

                // Update all total displays with rounded values
                $('#total-shipping-cost').text(formatPrice(totalShippingCost));
                $('#selected-total').text(formatPrice(subtotal)).data('subtotal', subtotal);

                // Update grand total display
                if (!$('#grand-total-container').length) {
                    $('#total-shipping-cost').closest('.mb-3').after(
                        `<div class="mb-3" id="grand-total-container">
                    <strong>Total Pembayaran: <span id="grand-total">${formatPrice(grandTotal)}</span></strong>
                </div>`
                    );
                } else {
                    $('#grand-total').text(formatPrice(grandTotal));
                }
            }

            // Calculate and update total for selected items
            function updateSelectedTotal() {
                let selectedTotal = 0;
                $('.item-checkbox:checked').each(function() {
                    const row = $(this).closest('tr');
                    const quantity = parseInt(row.find('.quantity-input').val()) || 0;
                    const price = parseFloat(row.find('.item-price').data('price')) || 0;
                    selectedTotal += Math.round(quantity * price);
                });
                $('#selected-total').text(formatPrice(selectedTotal));
            }

            // Recalculate cart total
            function recalculateTotal() {
                var total = 0;
                $('.item-total').each(function() {
                    total += Math.round(parseFloat($(this).data('total')));
                });
                $('#cart-total').text(formatPrice(total));
            }

            // ========== Cart Update Functions ==========

            function updateCartDisplay(item) {
                var row = $('tr[data-cart-id="' + item.cart_id + '"]');
                row.find('.quantity-input').val(item.quantity);

                var total = Math.round(item.quantity * item.harga);
                row.find('.item-total')
                    .text(formatPrice(total))
                    .data('total', total);

                row.find('.item-checkbox').data('weight', item.berat);
                updateShippingCosts();
                addFadeInAnimation(row[0]);
            }

            // ========== Event Handlers ==========

            // Handle checkbox changes
            $('#select-all').change(function() {
                $('.item-checkbox').prop('checked', $(this).prop('checked'));
                updateSelectedTotal();
                updateShippingCosts();
            });

            $('.item-checkbox').change(function() {
                updateSelectedTotal();
                $('#select-all').prop('checked', $('.item-checkbox:checked').length === $('.item-checkbox').length);
                updateShippingCosts();
            });

            // Handle quantity changes
            $('.quantity-input').change(function() {
                var cartId = $(this).data('cart-id');
                var quantity = $(this).val();
                updateCartItem(cartId, quantity);
            });

            // Handle remove item
            $('.remove-item').click(function() {
                var cartId = $(this).data('cart-id');
                removeCartItem(cartId);
            });

            // Handle shipping method changes
            $('.shipping-method').change(updateShippingCosts);

            // ========== AJAX Functions ==========

            function updateCartItem(cartId, quantity) {
                $.ajax({
                    url: "update_cart.php",
                    method: "POST",
                    data: {
                        cart_id: cartId,
                        quantity: quantity
                    },
                    dataType: "json",
                    success: function(response) {
                        if (response.success) {
                            updateCartDisplay(response.updatedItem);
                            recalculateTotal();
                            updateSelectedTotal();
                            addFadeInAnimation(document.querySelector(`tr[data-cart-id="${cartId}"]`));
                        } else {
                            alert("Failed to update cart: " + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX error:", error);
                        alert("An error occurred while updating the cart.");
                    }
                });
            }

            function removeCartItem(cartId) {
                $.ajax({
                    url: "remove_from_cart.php",
                    method: "POST",
                    data: {
                        cart_id: cartId
                    },
                    dataType: "json",
                    success: function(response) {
                        if (response.success) {
                            var row = document.querySelector(`tr[data-cart-id="${cartId}"]`);
                            addFadeInAnimation(row);
                            setTimeout(() => {
                                row.remove();
                                recalculateTotal();
                                updateSelectedTotal();
                                updateShippingCosts();
                            }, 500);
                        } else {
                            alert("Failed to remove item: " + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX error:", error);
                        alert("An error occurred while removing the item.");
                    }
                });
            }

            // ========== Form Handling ==========

            $('#checkout-form').submit(function(e) {
                var selectedItems = $('.item-checkbox:checked');
                if (selectedItems.length === 0) {
                    e.preventDefault();
                    alert('Pilih setidaknya satu item untuk checkout');
                    return false;
                }

                var orderNotes = $('#order-notes').val();
                if (orderNotes.length > 500) {
                    e.preventDefault();
                    alert('Catatan terlalu panjang. Mohon batasi hingga 500 karakter.');
                    return false;
                }

                selectedItems.each(function() {
                    var input = $('<input>')
                        .attr('type', 'hidden')
                        .attr('name', 'selected_items[]')
                        .val($(this).val());
                    $('#checkout-form').append(input);
                });
            });

            // Handle order notes input
            $('#order-notes').on('input', function() {
                if (this.value.length > 500) {
                    this.value = this.value.slice(0, 500);
                }
            });

            // ========== Initialization ==========
            recalculateTotal();
            updateSelectedTotal();
            updateShippingCosts();
        });
    </script>
</body>

</html>