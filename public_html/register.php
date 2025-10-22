<?php

require 'auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    $phone_number = $_POST['phone_number'];
    $latitude = $_POST['latitude'];
    $longitude = $_POST['longitude'];
    $alamat_pengiriman = $_POST['alamat_pengiriman'];

    $error = null;

    try {
        $pdo->beginTransaction();

        // Check if username or email sudah digunakan
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing_user) {
            if ($existing_user['username'] == $username) {
                throw new Exception("Username sudah digunakan");
            } else {
                throw new Exception("Email sudah digunakan");
            }
        }

        // Check if phone number sudah digunakan
        $stmt = $pdo->prepare("SELECT 'customer' as type FROM customers WHERE no_hp = ?
                               UNION ALL
                               SELECT 'petani' as type FROM petani WHERE no_hp = ?");
        $stmt->execute([$phone_number, $phone_number]);
        if ($stmt->rowCount() > 0) {
            throw new Exception("Nomor Telepon sudah digunakan");
        }

        // Check password match
        if ($password !== $confirm_password) {
            throw new Exception("Passwords do not match");
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $hashed_password, $email, $role]);
        $user_id = $pdo->lastInsertId();

        if ($role == 'pembeli') {
            $stmt = $pdo->prepare("INSERT INTO customers (user_id, nama_lengkap, no_hp, latitude, longitude, alamat_pengiriman) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $first_name . ' ' . $last_name, $phone_number, $latitude, $longitude, $alamat_pengiriman]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO petani (user_id, nama_lengkap, no_hp, latitude, longitude) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $first_name . ' ' . $last_name, $phone_number, $latitude, $longitude]);
        }

        $pdo->commit();



        // Insert login record
        $stmt = $pdo->prepare("INSERT INTO login (user_id) VALUES (?)");
        $stmt->execute([$user_id]);

        header("Location: login.php");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Registration error: " . $e->getMessage());
        $error = "Registration failed: " . $e->getMessage();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
    ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Register - Plant Inventory Jabon Mekar</title>
    <link href="css/styles.css" rel="stylesheet" />
    <link rel="stylesheet" href="css/custom-styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/js/all.min.js"
        integrity="sha512-2bMhOkE/ACz21dJT8zBOMgMecNxx0d37NND803ExktKiKdSzdwn+L7i9fdccw/3V06gM/DBWKbYmQvKMdAA9Nw=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Afacad+Flux:wght@100..1000&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <script src="https://js.api.here.com/v3/3.1/mapsjs-core.js" type="text/javascript" charset="utf-8"></script>
    <script src="https://js.api.here.com/v3/3.1/mapsjs-service.js" type="text/javascript" charset="utf-8"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.14.0/sweetalert2.min.css" integrity="sha512-WxRv0maH8aN6vNOcgNFlimjOhKp+CUqqNougXbz0E+D24gP5i+7W/gcc5tenxVmr28rH85XHF5eXehpV2TQhRg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <style>
       .alert,.card-footer,.card-header{text-align:center}.row,body{display:-webkit-box;display:-ms-flexbox;display:flex}*{margin:0;padding:0;-webkit-box-sizing:border-box;box-sizing:border-box}body{font-family:Poppins,sans-serif;background:-o-linear-gradient(315deg,#4caf50,#2196f3);background:linear-gradient(135deg,#4caf50,#2196f3);min-height:100vh;-webkit-box-align:center;-ms-flex-align:center;align-items:center;-webkit-box-pack:center;-ms-flex-pack:center;justify-content:center;padding:20px 0}.container{max-width:450px;width:90%;padding:20px}.card{margin-top:200px;background-color:rgba(255,255,255,.9);border-radius:20px;-webkit-box-shadow:0 10px 30px rgba(0,0,0,.1);box-shadow:0 10px 30px rgba(0,0,0,.1);overflow:hidden;-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);-webkit-transition:-webkit-transform .3s;transition:-webkit-transform .3s;-o-transition:transform .3s;transition:transform .3s;transition:transform .3s, -webkit-transform .3s}.card:hover{-webkit-transform:translateY(-5px);-ms-transform:translateY(-5px);transform:translateY(-5px)}.card-header{background-color:transparent;border-bottom:none;padding:30px 0 20px}.card-header h3{color:#333;font-weight:600;font-size:1.5rem;margin-bottom:5px}.card-header h5{color:#666;font-weight:400;font-size:1rem}.card-body{padding:20px 30px}.form-floating{margin-bottom:15px;position:relative}.form-floating label,.password-toggle{position:absolute;top:50%;-webkit-transform:translateY(-50%);-ms-transform:translateY(-50%);transform:translateY(-50%)}.form-control{width:100%;padding:15px;font-size:1rem;border:1px solid #ddd;border-radius:10px;-webkit-transition:border-color .3s;-o-transition:border-color .3s;transition:border-color .3s}.form-control:focus{outline:0;border-color:#4caf50;-webkit-box-shadow:0 0 0 .2rem rgba(76,175,80,.25);box-shadow:0 0 0 .2rem rgba(76,175,80,.25)}.btn-primary:hover,.logo{-webkit-box-shadow:0 5px 15px rgba(0,0,0,.1);box-shadow:0 5px 15px rgba(0,0,0,.1)}.form-floating label{left:15px;-webkit-transition:.3s;-o-transition:.3s;transition:.3s;color:#999;pointer-events:none}.form-floating>.form-control:not(:-moz-placeholder-shown)~label{opacity:0}.form-floating>.form-control:not(:-ms-input-placeholder)~label{opacity:0}.form-floating>.form-control:focus~label,.form-floating>.form-control:not(:placeholder-shown)~label{opacity:0}.btn-primary{background-color:#4caf50;border:none;border-radius:10px;padding:12px;font-weight:600;font-size:1rem;color:#fff;cursor:pointer;-webkit-transition:.3s;-o-transition:.3s;transition:.3s;width:100%}.btn-primary:hover{background-color:#45a049;-webkit-transform:translateY(-2px);-ms-transform:translateY(-2px);transform:translateY(-2px)}.card-footer{background-color:transparent;border-top:none;padding:20px 30px}.card-footer a{color:#4caf50;text-decoration:none;font-weight:500;-webkit-transition:color .3s;-o-transition:color .3s;transition:color .3s}.card-footer a:hover{color:#45a049;text-decoration:underline}.alert{background-color:#f8d7da;color:#721c24;padding:10px;border-radius:5px;margin-bottom:20px}.row{-ms-flex-wrap:wrap;flex-wrap:wrap;margin:-5px}.col-6{-webkit-box-flex:0;-ms-flex:0 0 50%;flex:0 0 50%;max-width:50%;padding:5px}.form-check-inline{display:inline-block;margin-right:15px}.form-check-input{margin-right:5px}@media (max-width:576px){.container{width:100%;padding:10px}.card{border-radius:0}.card-body{padding:15px}.col-6{-webkit-box-flex:0;-ms-flex:0 0 100%;flex:0 0 100%;max-width:100%}}.logo{width:80px;height:80px;margin:0 auto 20px;background-color:#4caf50;border-radius:50%;display:-webkit-box;display:-ms-flexbox;display:flex;-webkit-box-align:center;-ms-flex-align:center;align-items:center;-webkit-box-pack:center;-ms-flex-pack:center;justify-content:center}.logo::after{content:'🌱';font-size:40px}.password-toggle{right:10px;background:0 0;border:none;cursor:pointer;font-size:1rem;color:#999}.password-toggle:focus{outline:0}.form-floating .password-toggle~.form-control{padding-right:40px}@-webkit-keyframes float{0%,100%{-webkit-transform:translateY(0);transform:translateY(0)}50%{-webkit-transform:translateY(-10px);transform:translateY(-10px)}}@keyframes float{0%,100%{-webkit-transform:translateY(0);transform:translateY(0)}50%{-webkit-transform:translateY(-10px);transform:translateY(-10px)}}.floating{-webkit-animation:3s ease-in-out infinite float;animation:3s ease-in-out infinite float}
    </style>
    <script src="js/matomo.js"> </script>
</head>

<body>
    <div class="container">
        <div class="card shadow-lg">
            <div class="card-header">
                <div class="logo floating"></div>
                <h3>Plant Inventory Jabon Mekar</h3>
                <h5>Buat Akun</h5>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form method="post" id="registrationForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">
                    <div class="row">
                        <div class="col-6">
                            <div class="form-floating">
                                <input class="form-control" id="inputFirstName" type="text" name="first_name"
                                    placeholder=" " required />
                                <label for="inputFirstName">Nama Depan</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-floating">
                                <input class="form-control" id="inputLastName" type="text" name="last_name"
                                    placeholder=" " required />
                                <label for="inputLastName">Nama Belakang</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-floating">
                        <input class="form-control" id="inputUsername" type="text" name="username" placeholder=" "
                            required />
                        <label for="inputUsername">Username</label>
                    </div>
                    <div class="form-floating">
                        <input class="form-control" id="inputEmail" type="email" name="email" placeholder=" "
                            required />
                        <label for="inputEmail">Email</label>
                    </div>
                    <div class="form-floating">
                        <input class="form-control" id="inputPhoneNumber" type="tel" name="phone_number" placeholder=" "
                            required />
                        <label for="inputPhoneNumber">No. HP</label>
                        <small id="phoneError" class="text-danger" style="display: none;">Mohon masukkan nomor yang
                            valid</small>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-floating">
                                <input class="form-control" id="inputPassword" type="password" name="password"
                                    minlength="8" placeholder=" " required />
                                <label for="inputPassword">Password</label>
                                <button type="button" class="password-toggle" data-target="inputPassword">👁️</button>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-floating">
                                <input class="form-control" id="inputPasswordConfirm" type="password" minlength="8"
                                    name="confirm_password" placeholder=" " required />
                                <label for="inputPasswordConfirm">Konfirmasi</label>
                                <button type="button" class="password-toggle"
                                    data-target="inputPasswordConfirm">👁️</button>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="role" id="rolePembeli" value="pembeli"
                                required>
                            <label class="form-check-label" for="rolePembeli">Pembeli</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="role" id="rolePetani" value="petani"
                                required>
                            <label class="form-check-label" for="rolePetani">Petani</label>
                        </div>
                    </div>
                    <input type="hidden" id="latitude" name="latitude">
                    <input type="hidden" id="longitude" name="longitude">
                    <input type="hidden" id="alamat_pengiriman" name="alamat_pengiriman">
                    <div class="d-grid mt-4">
                        <button class="btn btn-primary" type="submit">Buat Akun</button>
                    </div>
                </form>
            </div>
            <div class="card-footer">
                <div class="small"><a href="login.php">Sudah punya akun? Masuk disini</a></div>
            </div>
        </div>
    </div>
  
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/js/bootstrap.bundle.min.js"
        integrity="sha512-i9cEfJwUwViEPFKdC1enz4ZRGBj8YQo6QByFTF92YXHi7waCqyexvRD75S5NVTsSiTv7rKWqG9Y5eFxmRsOn0A=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.14.0/sweetalert2.all.min.js" integrity="sha512-vHKpHh3VBF4B8QqZ1ppqnNb8zoTBceER6pyGb5XQyGtkCmeGwxDi5yyCmFLZA4Xuf9Jn1LBoAnx9sVvy+MFjNg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="js/scripts.js"></script>
    <script>
       document.addEventListener("DOMContentLoaded", function () {
    // Password toggle functionality
    let passwordToggles = document.querySelectorAll(".password-toggle");
    let phoneNumberInput = document.getElementById("inputPhoneNumber");
    let phoneErrorMessage = document.getElementById("phoneError");
    let registrationForm = document.getElementById("registrationForm");

    // Function to validate Indonesian phone numbers
    function isValidIndonesianPhoneNumber(phoneNumber) {
        return /^(\+62|62)?[\s-]?0?8[1-9]{1}\d{1}[\s-]?\d{4}[\s-]?\d{2,5}$/.test(phoneNumber);
    }

    // Password toggle event listeners
    passwordToggles.forEach(toggle => {
        toggle.addEventListener("click", function () {
            let targetId = this.getAttribute("data-target");
            let targetInput = document.getElementById(targetId);
            if (targetInput.type === "password") {
                targetInput.type = "text";
                this.textContent = "\uD83D\uDD12"; // Locked icon
            } else {
                targetInput.type = "password";
                this.textContent = "\uD83D\uDC41️"; // Eye icon
            }
        });
    });

    // Phone number validation
    phoneNumberInput.addEventListener("input", function () {
        if (isValidIndonesianPhoneNumber(this.value)) {
            phoneErrorMessage.style.display = "none";
            this.setCustomValidity("");
        } else {
            phoneErrorMessage.style.display = "block";
            this.setCustomValidity("Nomor telepon invalid");
        }
    });

    // Geolocation functionality
    let hereApiKey = '';

    function initializeHereMaps() {
        fetch('get_here_api_key.php')
            .then(response => response.text())
            .then(key => {
                hereApiKey = key;
                requestLocationPermission();
            })
            .catch(error => {
                console.error('Error fetching HERE API key:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Unable to initialize location services. Please try again later.'
                });
            });
    }

    function requestLocationPermission() {
        if (navigator.geolocation) {
            Swal.fire({
                icon: 'info',
                title: 'Izin Lokasi Diperlukan',
                text: 'Kami memerlukan akses ke lokasi Anda untuk melanjutkan pendaftaran.',
                confirmButtonText: 'Berikan Izin Lokasi',
                showCancelButton: true,
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    requestLocationManually();
                }
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Geolocation tidak didukung',
                text: 'Browser Anda tidak mendukung geolocation.'
            });
        }
    }
      
       function requestLocationManually() {
    navigator.permissions.query({name:'geolocation'}).then(function(result) {
        if (result.state === 'granted') {
            getLocation();
        } else if (result.state === 'prompt') {
            navigator.geolocation.getCurrentPosition(showPosition, showError, {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            });
        } else if (result.state === 'denied') {
            showError({code: 1}); // PERMISSION_DENIED
        }
    });
}

     function getLocation() {
        navigator.geolocation.getCurrentPosition(showPosition, showError, {
            enableHighAccuracy: true,
            timeout: 5000,
            maximumAge: 0
        });
    }

   function showPosition(position) {
        document.getElementById('latitude').value = position.coords.latitude;
        document.getElementById('longitude').value = position.coords.longitude;
        reverseGeocode(position.coords.latitude, position.coords.longitude);
    }

    function showError(error) {
    switch (error.code) {
        case error.PERMISSION_DENIED:
            Swal.fire({
                icon: 'warning',
                title: 'Izin Lokasi Ditolak',
                html: `
                    <p>Anda telah menolak akses ke lokasi. Untuk melanjutkan pendaftaran, mohon izinkan akses lokasi dengan mengikuti langkah-langkah berikut:</p>
                    <ol style="text-align: left; margin-top: 10px;">
                        <li>Klik ikon <i class="fa-solid fa-location-dot"></i> atau <i class="fa-solid fa-sliders"></i> di sebelah kiri URL di address bar browser Anda.</li>
                        <li>Cari pengaturan untuk 'Location' atau 'Lokasi'.</li>
                        <li>Ubah pengaturan menjadi 'Allow' atau 'Izinkan'.</li>
                        <li>Refresh halaman ini dan coba lagi.</li>
                    </ol>
                `,
                confirmButtonText: 'Saya sudah mengizinkan, Coba Lagi',
                showCancelButton: true,
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    requestLocationManually();
                }
            });
            break;
            case error.POSITION_UNAVAILABLE:
                Swal.fire({
                    icon: 'error',
                    title: 'Informasi Lokasi Tidak Tersedia',
                    text: 'Informasi lokasi tidak tersedia.'
                });
                break;
            case error.TIMEOUT:
                Swal.fire({
                    icon: 'error',
                    title: 'Waktu Permintaan Habis',
                    text: 'Permintaan untuk mendapatkan lokasi pengguna habis waktu.'
                });
                break;
            case error.UNKNOWN_ERROR:
                Swal.fire({
                    icon: 'error',
                    title: 'Error Tidak Diketahui',
                    text: 'Terjadi error yang tidak diketahui.'
                });
                break;
        }
    }

    function reverseGeocode(lat, lng) {
        var platform = new H.service.Platform({
            'apikey': hereApiKey
        });
        var service = platform.getSearchService();

        service.reverseGeocode({
            at: lat + ',' + lng
        }, (result) => {
            if (result.items.length > 0) {
                document.getElementById('alamat_pengiriman').value = result.items[0].address.label;
            }
        }, alert);
    }

    // Initialize HereMaps when the page loads
    initializeHereMaps();

    // Form submission validation
    registrationForm.addEventListener('submit', function (e) {
        if (!isValidIndonesianPhoneNumber(phoneNumberInput.value)) {
            e.preventDefault();
            phoneErrorMessage.style.display = "block";
            phoneNumberInput.setCustomValidity("Nomor telepon invalid");
        }

        if (!document.getElementById('latitude').value || !document.getElementById('longitude').value) {
            e.preventDefault();
            requestLocationPermission();
        }
    });
});
    </script>

</body>

</html>