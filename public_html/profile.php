<?php
require_once 'includes/color_scheme.php';
require_once 'auth_middleware.php';
require_once 'config/db_connect.php';
require 'controller/profile_controller.php';

// Check if user is logged in
$user = authenticate();
if (!$user) {
    header("Location: login.php");
    exit();
}

$user_id = $user['user_id'];
$role = $user['role'];

$colorScheme = getColorScheme($role);

// Fetch user data
$user_data = getUserData($user_id, $role);
$additional_data = getAdditionalData($user_id, $role);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>User Profile - Plant Inventory Jabon Mekar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/simple-datatables/7.1.2/style.min.css"
        integrity="sha512-dcdQkw+lfchKGZD+YmuSMwHBoR8AgJGrHXtBVXaxo2sMhlSKB0r04F2W9+BXCfdDjmP75EEl7oVNaHn2FTVNpQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="css/styles.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/js/all.min.js"
        integrity="sha512-2bMhOkE/ACz21dJT8zBOMgMecNxx0d37NND803ExktKiKdSzdwn+L7i9fdccw/3V06gM/DBWKbYmQvKMdAA9Nw=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.14.0/sweetalert2.min.css"
        integrity="sha512-WxRv0maH8aN6vNOcgNFlimjOhKp+CUqqNougXbz0E+D24gP5i+7W/gcc5tenxVmr28rH85XHF5eXehpV2TQhRg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .profile-header {
            background: linear-gradient(135deg,
                    <?php echo ($role === 'pembeli') ? '#4caf50, #2196f3' : (($role === 'petani') ? '#f4a460, #d2691e' : '#343a40, #6c757d'); ?>
                );
            color: #fff;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 50% 50%/20px
        }

        .profile-img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 5px solid #fff;
            box-shadow: 0 0 20px rgba(0, 0, 0, .2)
        }

        .profile-name {
            font-size: 2.5rem;
            font-weight: 700;
            margin-top: 1rem
        }

        .profile-role {
            font-size: 1.2rem;
            opacity: .8
        }

        .profile-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 30px rgba(0, 0, 0, .1)
        }

        .profile-card .card-header {
            background-color: #f8f9fa;
            border-bottom: none;
            padding: 1.5rem
        }

        .profile-card .card-body {
            padding: 2rem
        }

        .btn,
        .form-control {
            border-radius: 25px
        }

        .btn-primary {
            padding: 10px 30px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px
        }

        .is-invalid,
        .is-valid {
            padding-right: calc(1.5em + .75rem);
            background-repeat: no-repeat;
            background-position: right calc(.375em + .1875rem) center;
            background-size: calc(.75em + .375rem) calc(.75em + .375rem)
        }

        .is-invalid {
            border-color: #dc3545;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e")
        }

        .is-valid {
            border-color: #198754;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e")
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
                <div class="profile-header text-center">
                    <?php
                    $profileImage = ($role === 'petani') ? 'assets/img/farmer-profile.png' :
                        (($role === 'pembeli') ? 'assets/img/customer-profile.png' :
                            'assets/img/default-profile.png');
                    ?>
                    <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Profile Image" class="profile-img">
                    <h1 class="profile-name"><?php echo htmlspecialchars($user_data['username']); ?></h1>
                    <p class="profile-role"><?php echo ucfirst($role); ?></p>
                </div>
                <div class="container-fluid px-4">
                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            <div class="card profile-card mb-4">
                                <div class="card-header">
                                    <h2 class="mb-0"><i class="fas fa-user-edit me-2"></i>Data Profil</h2>
                                </div>
                                <div class="card-body">
                                    <form id="profileForm" method="POST" action="">
                                        <div class="mb-4">
                                            <label for="username" class="form-label">Username</label>
                                            <input type="text" class="form-control" id="username" name="username"
                                                value="<?php echo htmlspecialchars($user_data['username']); ?>"
                                                required>
                                        </div>
                                        <div class="mb-4">
                                            <label for="email" class="form-label">Alamat e-mail</label>
                                            <input type="email" class="form-control" id="email" name="email"
                                                value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                                        </div>
                                        <div class="mb-4">
                                            <label for="no_hp" class="form-label">Nomor Telepon</label>
                                            <input type="text" class="form-control" id="no_hp" name="no_hp"
                                                value="<?php echo htmlspecialchars($additional_data['no_hp']); ?>"
                                                required>
                                            <div id="phoneError" class="invalid-feedback">
                                                Mohon masukkan nomor yang valid
                                            </div>
                                        </div>
                                        <div class="mb-4">
                                            <label for="new_password" class="form-label">Password Baru</label>
                                            <input type="password" class="form-control" id="new_password"
                                                name="new_password"
                                                placeholder="Kosongkan jika tidak ingin mengganti password">
                                        </div>
                                        <div class="mb-4">
                                            <label for="confirm_password" class="form-label">Konfirmasi Password
                                                Baru</label>
                                            <input type="password" class="form-control" id="confirm_password"
                                                name="confirm_password" placeholder="Konfirmasi Password Baru">
                                        </div>
                                        <div class="text-center">
                                            <button type="submit" class="btn btn-primary btn-lg">Perbarui</button>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.14.0/sweetalert2.all.min.js"
        integrity="sha512-vHKpHh3VBF4B8QqZ1ppqnNb8zoTBceER6pyGb5XQyGtkCmeGwxDi5yyCmFLZA4Xuf9Jn1LBoAnx9sVvy+MFjNg=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/js/bootstrap.bundle.min.js"
        integrity="sha512-i9cEfJwUwViEPFKdC1enz4ZRGBj8YQo6QByFTF92YXHi7waCqyexvRD75S5NVTsSiTv7rKWqG9Y5eFxmRsOn0A=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="js/scripts.js"></script>
    <script>
        $(document).ready(function () { function e(e) { return /^(\+62|62)?[\s-]?0?8[1-9]{1}\d{1}[\s-]?\d{4}[\s-]?\d{2,5}$/.test(e) } $("#no_hp").on("input", function () { let r = $(this).val(); e(r) ? ($(this).removeClass("is-invalid").addClass("is-valid"), $("#phoneError").hide()) : ($(this).removeClass("is-valid").addClass("is-invalid"), $("#phoneError").show()) }), $("#profileForm").on("submit", function (r) { let i = $("#no_hp").val(); if (!e(i)) return r.preventDefault(), $("#no_hp").removeClass("is-valid").addClass("is-invalid"), $("#phoneError").show(), !1; r.preventDefault(), $.ajax({ type: "POST", url: "update_profile.php", data: $(this).serialize(), dataType: "json", success: function (e) { e.success ? Swal.fire({ title: "Success!", text: e.message, icon: "success", confirmButtonText: "OK" }).then(r => { r.isConfirmed && (e.requires_reauth ? window.location.href = "login.php" : e.password_updated && (window.location.href = "login.php")) }) : Swal.fire({ title: "Error!", html: e.message, icon: "error", confirmButtonText: "OK" }) }, error: function () { Swal.fire({ title: "Error!", text: "An unexpected error occurred. Please try again.", icon: "error", confirmButtonText: "OK" }) } }) }) });
    </script>
</body>

</html>