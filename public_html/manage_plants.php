<?php
require_once 'config/db_connect.php';

require_once 'auth_middleware.php';
$user = requireRole('petani');

$user_id = $user['user_id'];
$role = $user['role'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch petani information
$stmt = $pdo->prepare("SELECT * FROM petani WHERE user_id = ?");
$stmt->execute([$user_id]);
$petani = $stmt->fetch(PDO::FETCH_ASSOC);

$petani_id = $petani['petani_id'];

// Check if petani has set their location
$location_set = ($petani['latitude'] !== null && $petani['longitude'] !== null);

// Fetch plants for this petani
$stmt = $pdo->prepare("SELECT * FROM plants WHERE petani_id = ? AND is_deleted = 0");
$stmt->execute([$petani_id]);
$plants = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Tanaman - Plant Inventory Jabon Mekar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/css/bootstrap.min.css"
        integrity="sha512-SbiR/eusphKoMVVXysTKG/7VseWii+Y3FdHrt0EpKgpToZeemhqHeZeLWLhJutz/2ut2Vw1uQEj2MbRF+TVBUA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="css/styles.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.14.0/sweetalert2.min.css"
        integrity="sha512-WxRv0maH8aN6vNOcgNFlimjOhKp+CUqqNougXbz0E+D24gP5i+7W/gcc5tenxVmr28rH85XHF5eXehpV2TQhRg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="css/custom-styles.css">
    <link rel="stylesheet" type="text/css" href="https://js.api.here.com/v3/3.1/mapsjs-ui.css" />
    <script defer src="https://js.api.here.com/v3/3.1/mapsjs-core.js" type="text/javascript" charset="utf-8"></script>
    <script defer src="https://js.api.here.com/v3/3.1/mapsjs-service.js" type="text/javascript"
        charset="utf-8"></script>
    <script defer src="https://js.api.here.com/v3/3.1/mapsjs-ui.js" type="text/javascript" charset="utf-8"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/js/all.min.js"
        integrity="sha512-2bMhOkE/ACz21dJT8zBOMgMecNxx0d37NND803ExktKiKdSzdwn+L7i9fdccw/3V06gM/DBWKbYmQvKMdAA9Nw=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <script defer src="https://js.api.here.com/v3/3.1/mapsjs-mapevents.js" type="text/javascript"
        charset="utf-8"></script>

    <style>
       .modal-content{border:none;border-radius:15px;-webkit-box-shadow:0 10px 30px rgba(0,0,0,.2);box-shadow:0 10px 30px rgba(0,0,0,.2)}.modal-header{background-color:#f8f9fa;border-bottom:none;padding:20px 30px;border-top-left-radius:15px;border-top-right-radius:15px}.modal-title{font-weight:700;color:#333}.modal-body{padding:30px}.modal-footer{border-top:none;padding:20px 30px}.form-label{font-weight:600;color:#555;margin-bottom:.5rem}.form-control,.form-select{border-radius:8px;border:1px solid #ced4da;padding:10px 15px;-webkit-transition:border-color .15s ease-in-out,-webkit-box-shadow .15s ease-in-out;transition:border-color .15s ease-in-out,box-shadow .15s ease-in-out,-webkit-box-shadow .15s ease-in-out;-o-transition:border-color .15s ease-in-out,box-shadow .15s ease-in-out}.btn,.btn-sm,.card{-webkit-transition:.3s;-o-transition:.3s;transition:.3s}.form-control:focus,.form-select:focus{border-color:#80bdff;-webkit-box-shadow:0 0 0 .2rem rgba(0,123,255,.25);box-shadow:0 0 0 .2rem rgba(0,123,255,.25)}.btn{padding:10px 20px;border-radius:8px;font-weight:600}.btn-secondary{background-color:#6c757d;border-color:#6c757d}.btn-secondary:hover{background-color:#5a6268;border-color:#545b62}.modal.fade .modal-dialog{-webkit-transition:-webkit-transform .3s ease-out;transition:transform .3s ease-out;-o-transition:transform .3s ease-out;transition:transform .3s ease-out,-webkit-transform .3s ease-out;-webkit-transform:scale(.95);-ms-transform:scale(.95);transform:scale(.95)}.modal.show .modal-dialog{-webkit-transform:scale(1);-ms-transform:scale(1);transform:scale(1)}.custom-file-input::-webkit-file-upload-button{visibility:hidden}.custom-file-input::before{content:'Select Image';display:inline-block;background:-webkit-gradient(linear,left top,left bottom,from(#f9f9f9),to(#e3e3e3));background:-o-linear-gradient(top,#f9f9f9,#e3e3e3);background:linear-gradient(top,#f9f9f9,#e3e3e3);border:1px solid #999;border-radius:3px;padding:5px 8px;outline:0;white-space:nowrap;-webkit-user-select:none;cursor:pointer;text-shadow:1px 1px #fff;font-weight:700;font-size:10pt}.custom-file-input:hover::before{border-color:#000}.custom-file-input:active::before{background:-webkit-linear-gradient(top,#e3e3e3,#f9f9f9)}#plantsTable{width:100%;border-collapse:separate;border-spacing:0;margin-bottom:1rem;border-radius:10px;overflow:hidden;-webkit-box-shadow:0 4px 6px rgba(0,0,0,.1);box-shadow:0 4px 6px rgba(0,0,0,.1);-webkit-transition:-webkit-box-shadow .3s;transition:box-shadow .3s;-o-transition:box-shadow .3s;transition:box-shadow .3s,-webkit-box-shadow .3s}#plantsTable:hover{-webkit-box-shadow:0 8px 12px rgba(0,0,0,.15);box-shadow:0 8px 12px rgba(0,0,0,.15)}#plantsTable td,#plantsTable th{padding:1rem;vertical-align:middle;border-bottom:1px solid #e0e0e0}#plantsTable thead th{background-color:#f8f9fa;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#333;border-bottom:2px solid #dee2e6}#plantsTable tbody tr{-webkit-transition:background-color .3s;-o-transition:background-color .3s;transition:background-color .3s;-webkit-animation:.5s ease-out fadeIn;animation:.5s ease-out fadeIn}#plantsTable tbody tr:hover{background-color:#f5f5f5}#plantsTable tbody tr:nth-of-type(odd){background-color:rgba(0,0,0,.02)}#plantsTable tbody tr:last-child td{border-bottom:none}.btn-sm{padding:.375rem .75rem;font-size:.875rem;line-height:1.5;border-radius:.25rem}.btn-primary{color:#fff;background-color:#007bff;border-color:#007bff}.btn-primary:hover{background-color:#0056b3;border-color:#0056b3}.btn-danger{color:#fff;background-color:#dc3545;border-color:#dc3545}.btn-danger:hover{background-color:#c82333;border-color:#bd2130}@media screen and (max-width:767px){#plantsTable{border-radius:0}#plantsTable thead{display:none}#plantsTable,#plantsTable tbody,#plantsTable td,#plantsTable tr{display:block;width:100%}#plantsTable tr{margin-bottom:1rem;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden;-webkit-box-shadow:0 2px 4px rgba(0,0,0,.1);box-shadow:0 2px 4px rgba(0,0,0,.1)}#plantsTable td{text-align:right;padding:.75rem;position:relative;border-bottom:1px solid #e0e0e0}#plantsTable td::before{content:attr(data-label);position:absolute;left:.75rem;width:50%;padding-right:10px;white-space:nowrap;font-weight:700;text-align:left;text-transform:uppercase;font-size:.85em;color:#555}#plantsTable td:last-child{border-bottom:none}}@-webkit-keyframes fadeIn{from{opacity:0}to{opacity:1}}@keyframes fadeIn{from{opacity:0}to{opacity:1}}.card{border:none;border-radius:15px;overflow:hidden;-webkit-box-shadow:0 6px 18px rgba(0,0,0,.1);box-shadow:0 6px 18px rgba(0,0,0,.1)}.card:hover{-webkit-box-shadow:0 8px 22px rgba(0,0,0,.15);box-shadow:0 8px 22px rgba(0,0,0,.15)}.card-header{background-color:#f8f9fa;border-bottom:none;padding:1.25rem 1.5rem;font-weight:700;color:#333}.card-body{padding:1.5rem}
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
                    <h1 class="mt-4">Kelola Tanaman</h1>

                    <div class="mb-4">
                        <?php if ($location_set): ?>
                            <button type="button" class="btn btn-primary" onclick="showPlantModal('add')">
                                <i class="fa-solid fa-plus"></i>
                            <?php else: ?>
                                <button type="button" class="btn btn-primary" onclick="showLocationAlert()" disabled>
                                    <i class="fa-solid fa-plus"></i></button>
                                <small class="text-muted ms-2">Mohon tentukan lokasi toko anda terlebih dahulu.</small>
                            <?php endif; ?>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-table me-1"></i>
                            Tanaman Anda
                        </div>
                        <div class="card-body">
                            <table id="plantsTable" class="table">
                                <thead>
                                    <tr>
                                        <th>Tanaman</th>
                                        <th>Jenis</th>
                                        <th>Harga</th>
                                        <th>Stok</th>
                                      <th>Berat (kg)</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($plants as $plant): ?>
                                        <tr>
                                            <td data-label="Nama"><?php echo htmlspecialchars($plant['nama']); ?></td>
                                            <td data-label="Jenis"><?php echo htmlspecialchars($plant['jenis']); ?></td>
                                            <td data-label="Harga">Rp
                                                <?php echo number_format($plant['harga'], 0, ',', '.'); ?>
                                            </td>
                                            <td data-label="Stok"><?php echo htmlspecialchars($plant['stok']); ?></td>
                                          <td data-label="Berat"><?php echo number_format($plant['berat'], 2); ?> kg</td>
                                            <td data-label="Aksi">
                                                <button class="btn btn-sm btn-primary"
                                                    onclick="showPlantModal('edit', <?php echo $plant['plant_id']; ?>)">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger"
                                                    onclick="deletePlant(<?php echo $plant['plant_id']; ?>)">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
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

    <!-- Add Plant Modal -->
    <div class="modal fade" id="plantModal" tabindex="-1" aria-labelledby="plantModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="plantModalLabel">Add/Edit Plant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="plantForm" enctype="multipart/form-data">
                        <input type="hidden" id="plantId" name="plant_id">
                        <input type="hidden" id="action" name="action">
                        <div class="mb-3">
                            <label for="plantName" class="form-label">Nama Tanaman</label>
                            <input type="text" class="form-control" id="plantName" name="nama" required>
                        </div>
                        <div class="mb-3">
                            <label for="plantType" class="form-label">Jenis</label>
                            <select class="form-select" id="plantType" name="jenis" required>
                                <option value="indoor">Indoor</option>
                                <option value="outdoor">Outdoor</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="plantPrice" class="form-label">Harga</label>
                            <input type="number" class="form-control" id="plantPrice" name="harga" required>
                        </div>
                        <div class="mb-3">
                            <label for="plantStock" class="form-label">Stok</label>
                            <input type="number" class="form-control" id="plantStock" name="stok" required>
                        </div>
                      <div class="mb-3">
                        <label for="plantWeight" class="form-label">Berat (kg)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="plantWeight" name="berat" required>
                      </div>
                        <div class="mb-3">
                            <label for="plantDescription" class="form-label">Deskripsi</label>
                            <textarea class="form-control" placeholder="Maximum 400 characters" maxlength="400"
                                id="plantDescription" name="deskripsi" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="plantImage" class="form-label">Foto Tanaman</label>
                            <input type="file" class="form-control" id="plantImage" name="gambar"
                                accept="image/jpg, image/jpeg, image/webp">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i
                            class="fa-solid fa-xmark"></i></button>
                    <button type="button" class="btn btn-primary" onclick="savePlant()"><i
                            class="fa-solid fa-check"></i></button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"
        integrity="sha512-7Pi/otdlbbCR+LnW+F7PwFcSDJOuUJB3OxtEHbg4vSMvzvJjde4Po1v4BR9Gdc9aXNUNFVUY+SK51wWT8WF0Gg=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="js/scripts.js"></script>



    <!-- Add SweetAlert2 JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.14.0/sweetalert2.all.min.js"
        integrity="sha512-vHKpHh3VBF4B8QqZ1ppqnNb8zoTBceER6pyGb5XQyGtkCmeGwxDi5yyCmFLZA4Xuf9Jn1LBoAnx9sVvy+MFjNg=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <!-- Custom script for plant management -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            window.showLocationAlert = function () { Swal.fire({ title: "Lokasi belum ditentukan", text: "Silakan tentukan lokasi toko", icon: "warning", confirmButtonText: "OK" }) };

            window.showPlantModal = function (action, plantId = null) {
                <?php if (!$location_set): ?>
                    showLocationAlert();
                    return;
                <?php endif; ?>

                document.getElementById("action").value = action, document.getElementById("plantId").value = plantId, document.getElementById("plantModalLabel").textContent = "add" === action ? "Tambah Tanaman Baru" : "Ubah Tanaman";

                var modal = new bootstrap.Modal(document.getElementById('plantModal'));

                if (action === 'edit') {
                    fetch('plant_operations.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=get&plant_id=' + plantId
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                var plant = data.data;
                                document.getElementById('plantName').value = plant.nama;
                                document.getElementById('plantType').value = plant.jenis;
                                document.getElementById('plantPrice').value = plant.harga;
                                document.getElementById('plantStock').value = plant.stok;
                                document.getElementById('plantWeight').value = plant.berat;
                                document.getElementById('plantDescription').value = plant.deskripsi;
                                modal.show();
                            }
                        });
                } else {
                    document.getElementById('plantForm').reset();
                    modal.show();
                }
            }

            window.validateForm = function () { var e = document.getElementById("plantName").value, r = document.getElementById("plantPrice").value, a = document.getElementById("plantStock").value; return "" === e.trim() ? (Swal.fire("Error", "Please enter a plant name.", "error"), !1) : isNaN(r) || r <= 0 ? (Swal.fire("Error", "Please enter a valid price.", "error"), !1) : !isNaN(a) && !(a < 0) || (Swal.fire("Error", "Please enter a valid stock number.", "error"), !1) };

            window.savePlant = function () { if (validateForm()) { var r = document.getElementById("plantForm"); fetch("plant_operations.php", { method: "POST", body: new FormData(r) }).then(r => r.json()).then(r => { r.success ? Swal.fire({ title: "Berhasil", text: r.message, icon: "success", confirmButtonText: "OK" }).then(r => { r.isConfirmed && location.reload() }) : Swal.fire("Error", r.message, "error") }).catch(r => { console.error("Error:", r), Swal.fire("Error", "An error occurred. Please try again or contact support.", "error") }) } };


            window.deletePlant = function (e) { Swal.fire({ title: "Kamu yakin?", text: "Kamu yakin ingin menghapus tanaman ini?", icon: "warning", showCancelButton: !0, confirmButtonColor: "#3085d6", cancelButtonColor: "#d33", cancelButtonText: "Batal", confirmButtonText: "Ya, hapus!" }).then(n => { n.isConfirmed && fetch("plant_operations.php", { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: "action=delete&plant_id=" + e }).then(e => e.json()).then(e => { e.success ? Swal.fire("Berhasil!", e.message, "success").then(e => { e.isConfirmed && location.reload() }) : Swal.fire("Gagal", e.message, "error") }) }) };
        });
    </script>
</body>

</html>