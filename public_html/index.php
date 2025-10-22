<?php
require_once 'auth_middleware.php';
require 'controller/dashboard_controller.php';

$user = authenticate();
if (!$user) {
    header("Location: login.php");
    exit();
}

$role = $user['role'];

function formatRupiah($amount)
{
    return 'Rp ' . number_format($amount, 2, ',', '.');
}

function formatOrderStatuses($statuses)
{
    $statusArray = explode(',', $statuses);
    $uniqueStatuses = array_unique($statusArray);
    $formattedStatuses = array_map(function ($status) {
        return ucfirst($status);
    }, $uniqueStatuses);
    return implode(', ', $formattedStatuses);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Beranda - Plant Inventory Jabon Mekar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/css/bootstrap.min.css"
        integrity="sha512-SbiR/eusphKoMVVXysTKG/7VseWii+Y3FdHrt0EpKgpToZeemhqHeZeLWLhJutz/2ut2Vw1uQEj2MbRF+TVBUA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/simple-datatables/7.1.2/style.min.css"
        integrity="sha512-dcdQkw+lfchKGZD+YmuSMwHBoR8AgJGrHXtBVXaxo2sMhlSKB0r04F2W9+BXCfdDjmP75EEl7oVNaHn2FTVNpQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />


    <link href="css/styles.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/js/all.min.js"
        integrity="sha512-2bMhOkE/ACz21dJT8zBOMgMecNxx0d37NND803ExktKiKdSzdwn+L7i9fdccw/3V06gM/DBWKbYmQvKMdAA9Nw=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
     .card,.table-responsive{-webkit-box-shadow:0 4px 6px rgba(0,0,0,.1);box-shadow:0 4px 6px rgba(0,0,0,.1)}@media screen and (max-width:767px){#recentOrdersTable{border:0}#recentOrdersTable thead{display:none}#recentOrdersTable tr{margin-bottom:10px;display:block;border-bottom:2px solid #ddd}#recentOrdersTable td{display:block;text-align:right;font-size:13px;border-bottom:1px dotted #ccc}#recentOrdersTable td:last-child{border-bottom:0}#recentOrdersTable td:before{content:attr(data-label);float:left;font-weight:700;text-transform:uppercase}.card-body{padding:.5rem}}.card{-webkit-transition:.3s;-o-transition:.3s;transition:.3s;border:none;border-radius:15px}.card:hover{-webkit-transform:translateY(-5px);-ms-transform:translateY(-5px);transform:translateY(-5px);-webkit-box-shadow:0 8px 15px rgba(0,0,0,.2);box-shadow:0 8px 15px rgba(0,0,0,.2)}.card-body{padding:1.5rem}.card-title{font-size:1.1rem;font-weight:600;margin-bottom:.5rem}.card h2{font-size:2.5rem;font-weight:700}.table-responsive{border-radius:15px;overflow:hidden}.table{margin-bottom:0}.table thead th{background-color:#f8f9fa;border-top:none;font-weight:600;text-transform:uppercase;font-size:.85rem;letter-spacing:.5px}.table tbody tr:hover{background-color:#f1f3f5}.chart-container{position:relative;margin:auto;height:300px;width:100%}@media (max-width:768px){.card{margin-bottom:20px}.chart-container{height:250px}}@media (max-width:576px){.card h2{font-size:2rem}.chart-container{height:200px}}
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
                    <h1 class="mt-4">Plant Inventory Jabon Mekar</h1>
                    <ol class="breadcrumb mb-4">
                        <li class="breadcrumb-item active">Beranda</li>
                    </ol>
                    <div class="row">
                        <?php if ($role == 'petani'): ?>
                            <div class="col-xl-3 col-md-6">
                                <div class="card bg-primary text-white mb-4">
                                    <div class="card-body">
                                        <h5 class="card-title">Total Tanaman</h5>
                                        <h2 class="mb-0" id="totalPlants"><?php echo $total_plants; ?></h2>
                                    </div>
                                    <div class="card-footer d-flex align-items-center justify-content-between">
                                        <a class="small text-white stretched-link" href="manage_plants.php">Kelola
                                            Tanaman</a>
                                        <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="col-xl-3 col-md-6">
                            <div class="card bg-warning text-white mb-4">
                                <div class="card-body">
                                    <h5 class="card-title">Total Pesanan</h5>
                                    <h2 class="mb-0" id="totalOrders"><?php echo $total_orders; ?></h2>
                                </div>
                                <div class="card-footer d-flex align-items-center justify-content-between">
                                    <a class="small text-white stretched-link"
                                        href="<?php echo $user['role'] === 'petani' ? 'orders.php' : 'my_orders.php'; ?>">Lihat
                                        Pesanan</a>
                                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if ($role == 'petani'): ?>
                        <div class="row">
                            <div class="col-xl-6">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <i class="fas fa-chart-area me-1"></i>
                                        Laporan Penjualan Bulanan
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="myAreaChart"
                                                data-labels='<?php echo json_encode(array_column($monthly_sales, 'month')); ?>'
                                                data-values='<?php echo json_encode(array_column($monthly_sales, 'total_sales')); ?>'>
                                            </canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-6">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <i class="fas fa-chart-bar me-1"></i>
                                        Tanaman Terlaris
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="myBarChart"
                                                data-labels='<?php echo json_encode(array_column($top_selling_plants, 'nama')); ?>'
                                                data-values='<?php echo json_encode(array_column($top_selling_plants, 'total_sold')); ?>'>
                                            </canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-table me-1"></i>
                            Pesanan Terakhir
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="recentOrdersTable" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <?php if ($role == 'petani'): ?>
                                                <th>Nama Pembeli</th>
                                            <?php endif; ?>
                                            <th>Total Bayar</th>
                                            <th>Waktu Pemesanan</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_orders as $order): ?>
                                            <tr>
                                                <td data-label="Order ID">
                                                    <?php echo htmlspecialchars($order['order_id']); ?>
                                                </td>
                                                <?php if ($role == 'petani'): ?>
                                                    <td data-label="Nama Pembeli">
                                                        <?php echo htmlspecialchars($order['nama_lengkap']); ?>
                                                    </td>
                                                <?php endif; ?>
                                                <td data-label="Total Bayar">
                                                    <?php echo formatRupiah($order['total_harga']); ?>
                                                </td>
                                                <td data-label="Waktu Pemesanan">
                                                    <?php echo htmlspecialchars($order['order_date']); ?>
                                                </td>
                                                <td data-label="Status">
                                                    <?php echo htmlspecialchars(formatOrderStatuses($order['order_statuses'])); ?>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"
        integrity="sha512-7Pi/otdlbbCR+LnW+F7PwFcSDJOuUJB3OxtEHbg4vSMvzvJjde4Po1v4BR9Gdc9aXNUNFVUY+SK51wWT8WF0Gg=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/simple-datatables/7.1.2/simple-datatables.min.js"
        integrity="sha512-3ty9AJncMgK2yFwGuF8Shc5dMwiXeHiEXV5QiOXrhuXzQLLorWeBEpmLWduNl49A9ffIyf+zmQ7nI1PQlUaRYg=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js"></script>
    <script src="js/scripts.js"></script>
    <script>
        // Utility function to format currency
        function formatCurrency(r) { return new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR" }).format(r) }

        // Function to format order statuses
        function formatOrderStatuses(t) { if (!t) return ""; let e = t.split(","), r = [...new Set(e)]; return r.map(t => t.charAt(0).toUpperCase() + t.slice(1)).join(", ") }

        // Function to create area chart
        function createAreaChart(ctx, data) {
            return new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: "Penjualan",
                        data: data.values,
                        fill: true,
                        backgroundColor: 'rgba(2,117,216,0.1)',
                        borderColor: "rgba(2,117,216,1)",
                        pointBackgroundColor: "rgba(2,117,216,1)",
                        pointBorderColor: "#fff",
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: "rgba(2,117,216,1)",
                        pointHitRadius: 20,
                        pointBorderWidth: 2
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function (value) {
                                    return formatCurrency(value);
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return 'Penjualan: ' + formatCurrency(context.parsed.y);
                                }
                            }
                        }
                    }
                }
            });
        }

        // Function to create bar chart
        function createBarChart(ctx, data) {
            return new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: "Terjual",
                        data: data.values,
                        backgroundColor: "rgba(2,117,216,0.8)",
                        borderColor: "rgba(2,117,216,1)",
                        borderWidth: 1
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }

        // Function to update chart data
        function updateChartData(a, t) { a.data.labels = t.labels, a.data.datasets[0].data = t.values, a.update() }

        // Function to update the recent orders table
        function updateRecentOrdersTable(orders, role) {
            const tableBody = document.querySelector('#recentOrdersTable tbody');
            tableBody.innerHTML = orders.map(order => `
        <tr>
            <td data-label="Order ID">${order.order_id}</td>
            ${role === 'petani' ? `<td data-label="Nama Pembeli">${order.nama_lengkap}</td>` : ''}
            <td data-label="Total Bayar">${formatCurrency(order.total_harga)}</td>
            <td data-label="Waktu Pemesanan">${order.order_date}</td>
            <td data-label="Status">${formatOrderStatuses(order.order_statuses)}</td>
        </tr>
    `).join('');

            // Reinitialize DataTable
            "undefined" != typeof dataTable && dataTable.destroy(), dataTable = new simpleDatatables.DataTable("#recentOrdersTable", { searchable: !1, perPageSelect: !1, responsive: !1 });
        }

        // Function to update the entire dashboard
        function updateDashboard() { fetch("get_dashboard_data.php").then(t => t.json()).then(t => { "petani" === t.role ? (document.getElementById("totalPlants").textContent = t.total_plants, document.getElementById("totalOrders").textContent = t.total_orders, updateChartData(salesChart, { labels: t.monthly_sales.map(t => t.month), values: t.monthly_sales.map(t => t.total_sales) }), updateChartData(plantsChart, { labels: t.top_selling_plants.map(t => t.nama), values: t.top_selling_plants.map(t => t.total_sold) })) : "pembeli" === t.role && (document.getElementById("totalOrders").textContent = t.total_orders), updateRecentOrdersTable(t.recent_orders, t.role) }).catch(t => console.error("Error updating dashboard:", t)) }

        // Initialize charts
        let salesChart, plantsChart;

        // Initialize DataTable
        let dataTable;

        // DOM Content Loaded event listener
        document.addEventListener("DOMContentLoaded", function () { let e = document.getElementById("myAreaChart"), a = document.getElementById("myBarChart"); e && (salesChart = createAreaChart(e.getContext("2d"), { labels: JSON.parse(e.dataset.labels), values: JSON.parse(e.dataset.values) })), a && (plantsChart = createBarChart(a.getContext("2d"), { labels: JSON.parse(a.dataset.labels), values: JSON.parse(a.dataset.values) })); let t = document.getElementById("recentOrdersTable"); t && (dataTable = new simpleDatatables.DataTable(t, { searchable: !1, perPageSelect: !1, responsive: !1 })), updateDashboard(), setInterval(updateDashboard, 3e5) });
    </script>
</body>

</html>