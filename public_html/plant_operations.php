<?php
require_once 'config/db_connect.php';
require_once 'auth_middleware.php';

ob_start();

header('Content-Type: application/json');
function handleError($message)
{
    ob_clean();
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

$user = authenticate();

if (!$user || $user['role'] !== 'petani') {
    handleError('Unauthorized access');
}

$user_id = $user['user_id'];

// Verify that the petani exists in the database
$stmt = $pdo->prepare("SELECT petani_id FROM petani WHERE user_id = ?");
$stmt->execute([$user_id]);
$petani = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$petani) {
    handleError('Invalid petani account');
}
$petani_id = $petani['petani_id'];

// function to convert image to webp
function convertToWebP($source, $destination, $quality = 80)
{
    if (!extension_loaded('gd')) {
        error_log('GD library is not installed or not enabled.');
        return false;
    }
    $info = getimagesize($source);
    if ($info === false) {
        error_log("Unable to get image info: $source");
        return false;
    }
    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($source);
            break;
        case 'image/gif':
            $image = @imagecreatefromgif($source);
            break;
        case 'image/webp':
            // If it's already WebP, just copy the file
            return copy($source, $destination);
        default:
            error_log("Unsupported image type: $mime");
            return false;
    }

    if ($image === false) {
        error_log("Failed to create image resource from file: $source");
        return false;
    }
    if (function_exists('imagewebp')) {
        $result = imagewebp($image, $destination, $quality);
        if (!$result) {
            error_log("Failed to save WebP image: $destination");
        }
    } else {
        error_log("WebP support is not available in GD library. Saving original format.");
        switch ($mime) {
            case 'image/jpeg':
                $result = imagejpeg($image, $destination, $quality);
                break;
            case 'image/png':
                $result = imagepng($image, $destination, 9);
                break;
            case 'image/gif':
                $result = imagegif($image, $destination);
                break;
            default:
                $result = false;
        }
        if (!$result) {
            error_log("Failed to save image in original format: $destination");
        }
    }
    imagedestroy($image);
    return $result;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action == 'add' || $action == 'edit') {
        $nama = $_POST['nama'] ?? '';
        $jenis = $_POST['jenis'] ?? '';
        $harga = $_POST['harga'] ?? 0;
        $stok = $_POST['stok'] ?? 0;
        $berat = $_POST['berat'] ?? 0;
        $deskripsi = $_POST['deskripsi'] ?? '';

        if (empty($nama))
            handleError('Plant name is required.');
        if (!in_array($jenis, ['indoor', 'outdoor']))
            handleError('Invalid plant type.');
        if (!is_numeric($harga) || $harga <= 0)
            handleError('Invalid price.');
        if (!is_numeric($stok) || $stok < 0)
            handleError('Invalid stock number.');
        if (!is_numeric($berat) || $berat < 0)
            handleError('Invalid weight value.');

        $gambar = '';
        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($_FILES['gambar']['type'], $allowed_types)) {
                handleError('Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.');
            }
            $target_dir = "assets/img/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_extension = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '.' . $file_extension;
            $target_file = $target_dir . $file_name;

            error_log('Uploaded file details: ' . print_r($_FILES['gambar'], true));
            if (convertToWebP($_FILES["gambar"]["tmp_name"], $target_file)) {
                $gambar = $target_file;
            } else {
                error_log('Image conversion/save failed for file: ' . $_FILES["gambar"]["name"]);
                handleError('Failed to save image. Please try again or contact support.');
            }
        }

        try {
            if ($action == 'add') {
                $stmt = $pdo->prepare("INSERT INTO plants (petani_id, nama, jenis, harga, stok, berat, deskripsi, gambar, is_deleted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
                $stmt->execute([$petani_id, $nama, $jenis, $harga, $stok, $berat, $deskripsi, $gambar]);
                echo json_encode(['success' => true, 'message' => 'Tanaman berhasil ditambahkan']);
            } else {
                $plant_id = $_POST['plant_id'];
                $stmt = $pdo->prepare("UPDATE plants SET nama = ?, jenis = ?, harga = ?, stok = ?, berat = ?, deskripsi = ? WHERE plant_id = ? AND petani_id = ? AND is_deleted = 0");
                $stmt->execute([$nama, $jenis, $harga, $stok, $berat, $deskripsi, $plant_id, $petani_id]);
                if ($gambar) {
                    $stmt = $pdo->prepare("UPDATE plants SET gambar = ? WHERE plant_id = ? AND is_deleted = 0");
                    $stmt->execute([$gambar, $plant_id]);
                }
                echo json_encode(['success' => true, 'message' => 'Tanaman berhasil diperbarui']);
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            handleError('A database error occurred. Please try again or contact support.');
        }
    } elseif ($action == 'delete') {
        $plant_id = $_POST['plant_id'];
        if (!is_numeric($plant_id) || $plant_id <= 0) {
            handleError('Invalid plant ID.');
        }
        try {
            $stmt = $pdo->prepare("UPDATE plants SET is_deleted = 1 WHERE plant_id = ? AND petani_id = ?");
            $stmt->execute([$plant_id, $petani_id]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Tanaman berhasil dihapus']);
            } else {
                handleError('Tanaman tidak ditemukan atau Anda tidak memiliki izin untuk menghapusnya.');
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            handleError('Terjadi kesalahan saat menghapus tanaman. Silakan coba lagi atau hubungi dukungan teknis.');
        }
    } elseif ($action == 'get') {
        $plant_id = $_POST['plant_id'];
        if (!is_numeric($plant_id) || $plant_id <= 0)
            handleError('Invalid plant ID.');
        try {
            $stmt = $pdo->prepare("SELECT * FROM plants WHERE plant_id = ? AND petani_id = ? AND is_deleted = 0");
            $stmt->execute([$plant_id, $petani_id]);
            $plant = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($plant) {
                echo json_encode(['success' => true, 'data' => $plant]);
            } else {
                handleError('Plant not found.');
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            handleError('A database error occurred while fetching the plant. Please try again or contact support.');
        }
    } else {
        handleError('Invalid action.');
    }
} else {
    handleError('Invalid request method');
}

ob_end_flush();