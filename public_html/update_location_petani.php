<?php
require_once 'config/db_connect.php';

// Check if user is logged in and is a pembeli
require_once 'auth_middleware.php';
$user = requireRole('petani');

$user_id = $user['user_id'];
$role = $user['role'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM petani WHERE user_id = ?");
$stmt->execute([$user_id]);
$petani = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $latitude = $_POST['latitude'];
    $longitude = $_POST['longitude'];

    $stmt = $pdo->prepare("UPDATE petani SET latitude = ?, longitude = ? WHERE user_id = ?");
    if ($stmt->execute([$latitude, $longitude, $user_id])) {
        $success_message = "Lokasi berhasil diperbarui.";

        // Fetch the latest user data after form submission
        $stmt = $pdo->prepare("SELECT * FROM petani WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $petani = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $error_message = "An error occurred while updating your location.";
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
    <title>Pilih Lokasi - Plant Inventory Jabon Mekar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/css/bootstrap.min.css"
        integrity="sha512-SbiR/eusphKoMVVXysTKG/7VseWii+Y3FdHrt0EpKgpToZeemhqHeZeLWLhJutz/2ut2Vw1uQEj2MbRF+TVBUA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="css/styles.css" rel="stylesheet" />
    <link rel="stylesheet" href="css/custom-styles.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.14.0/sweetalert2.min.css"
        integrity="sha512-WxRv0maH8aN6vNOcgNFlimjOhKp+CUqqNougXbz0E+D24gP5i+7W/gcc5tenxVmr28rH85XHF5eXehpV2TQhRg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/js/all.min.js"
        integrity="sha512-2bMhOkE/ACz21dJT8zBOMgMecNxx0d37NND803ExktKiKdSzdwn+L7i9fdccw/3V06gM/DBWKbYmQvKMdAA9Nw=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <style>
        #mapContainer {
            width: 100%;
            height: 400px
        }

        .H_ib_body {
            width: 275px
        }

        .autocomplete-container {
            position: relative
        }

        .autocomplete-items {
            position: absolute;
            border: 1px solid #d4d4d4;
            border-top: none;
            z-index: 99;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 150px;
            overflow-y: auto;
            background-color: #fff
        }

        .autocomplete-items div {
            padding: 10px;
            cursor: pointer;
            background-color: #fff;
            border-bottom: 1px solid #d4d4d4
        }

        .autocomplete-items div:hover {
            background-color: #e9e9e9
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
                    <h1 class="mt-4">Pilih Lokasi</h1>

                    <div class="card mb-4">
                        <div class="card-body">
                            <?php if (isset($success_message)): ?>
                                <div class="alert alert-success"><?php echo $success_message; ?></div>
                            <?php endif; ?>
                            <?php if (isset($error_message)): ?>
                                <div class="alert alert-danger"><?php echo $error_message; ?></div>
                            <?php endif; ?>
                            <form method="POST" id="locationForm">
                                <div class="mb-3 autocomplete-container">
                                    <label for="address" class="form-label">Alamat</label>
                                    <input type="text" class="form-control" id="address" name="address"
                                        placeholder="Masukkan alamat toko anda">
                                </div>
                                <div class="mb-3">
                                    <label for="mapContainer" class="form-label">Pilih lokasi pada map</label>
                                    <div id="mapContainer"></div>
                                </div>
                                <input type="hidden" id="latitude" name="latitude"
                                    value="<?php echo $petani['latitude'] ?? ''; ?>" required>
                                <input type="hidden" id="longitude" name="longitude"
                                    value="<?php echo $petani['longitude'] ?? ''; ?>" required>
                                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i></button>
                            </form>
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
        // Function to load script asynchronously
        function loadScript(src, callback) {
            var script = document.createElement('script');
            script.src = src;
            script.async = true;
            script.onload = callback;
            document.body.appendChild(script);
        }

        // Function to load CSS asynchronously
        function loadCSS(href) {
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            document.head.appendChild(link);
        }

        var platform, map, behavior, ui, searchService, marker;

        function initMap(apiKey) {
            platform = new H.service.Platform({
                'apikey': apiKey
            });

            var defaultLayers = platform.createDefaultLayers();

            map = new H.Map(
                document.getElementById('mapContainer'),
                defaultLayers.vector.normal.map, {
                zoom: 10,
                center: {
                    lat: <?php echo $petani['latitude'] ?? -6.2088; ?>,
                    lng: <?php echo $petani['longitude'] ?? 106.8456; ?>
                }
            });

            behavior = new H.mapevents.Behavior(new H.mapevents.MapEvents(map));
            ui = H.ui.UI.createDefault(map, defaultLayers);

            searchService = platform.getSearchService();

            map.addEventListener('tap', function (evt) {
                var coord = map.screenToGeo(evt.currentPointer.viewportX, evt.currentPointer.viewportY);
                setMarker(coord);
                reverseGeocode(coord);
            });

            // Set initial marker and address if coordinates are available
            if (<?php echo ($petani['latitude'] && $petani['longitude']) ? 'true' : 'false'; ?>) {
                setMarker({
                    lat: <?php echo $petani['latitude'] ?? 0; ?>,
                    lng: <?php echo $petani['longitude'] ?? 0; ?>
                });
                reverseGeocode({
                    lat: <?php echo $petani['latitude'] ?? 0; ?>,
                    lng: <?php echo $petani['longitude'] ?? 0; ?>
                });
            }

            initAutocomplete();
        }

        function setMarker(e) { marker && map.removeObject(marker), marker = new H.map.Marker(e), map.addObject(marker), document.getElementById("latitude").value = e.lat, document.getElementById("longitude").value = e.lng, map.setCenter(e) } function reverseGeocode(e) { searchService.reverseGeocode({ at: e.lat + "," + e.lng }, e => { e.items.length > 0 && (document.getElementById("address").value = e.items[0].address.label) }, alert) } function initAutocomplete() { var e, t = document.getElementById("address"), n = document.createElement("div"); n.className = "autocomplete-items", t.parentNode.appendChild(n), t.addEventListener("input", function () { clearTimeout(e), e = setTimeout(() => { this.value.length > 2 ? searchService.geocode({ q: this.value, in: "countryCode:IDN" }, e => { n.innerHTML = "", e.items.forEach(e => { var r = document.createElement("div"); r.innerHTML = e.address.label, r.addEventListener("click", function () { t.value = e.address.label, setMarker(e.position), n.innerHTML = "" }), n.appendChild(r) }) }, alert) : n.innerHTML = "" }, 300) }), document.addEventListener("click", function (e) { e.target !== t && e.target !== n && (n.innerHTML = "") }) } loadCSS("https://js.api.here.com/v3/3.1/mapsjs-ui.css"), loadScript("https://js.api.here.com/v3/3.1/mapsjs-core.js", function () { loadScript("https://js.api.here.com/v3/3.1/mapsjs-service.js", function () { loadScript("https://js.api.here.com/v3/3.1/mapsjs-ui.js", function () { loadScript("https://js.api.here.com/v3/3.1/mapsjs-mapevents.js", function () { fetch("get_here_api_key.php").then(e => { if (!e.ok) throw Error("Network response was not ok"); return e.text() }).then(e => { initMap(e) }).catch(e => { console.error("Error fetching API key:", e), alert("Failed to initialize map. Please try again later.") }) }) }) }) }), document.addEventListener("DOMContentLoaded", function () { var e = document.getElementById("locationForm"); e.addEventListener("submit", function (t) { t.preventDefault(); var n = new FormData(e); fetch(window.location.href, { method: "POST", body: n }).then(e => e.text()).then(e => { var t = new DOMParser().parseFromString(e, "text/html"), n = t.querySelector(".alert-success"), r = t.querySelector(".alert-danger"); n ? Swal.fire({ icon: "success", title: " Berhasil!", text: n.textContent, confirmButtonText: "OK" }) : r && Swal.fire({ icon: "error", title: "Error!", text: r.textContent, confirmButtonText: "OK" }); var a = t.getElementById("latitude"), i = t.getElementById("longitude"); a && a.value && (document.getElementById("latitude").value = a.value), i && i.value && (document.getElementById("longitude").value = i.value); var o = parseFloat(document.getElementById("latitude").value), l = parseFloat(document.getElementById("longitude").value); isNaN(o) || isNaN(l) || (setMarker({ lat: o, lng: l }), map.setCenter({ lat: o, lng: l })) }).catch(e => { console.error("Error:", e), Swal.fire({ icon: "error", title: "Error!", text: "An error occurred while updating the location. Please try again.", confirmButtonText: "OK" }) }) }) });
    </script>
</body>

</html>