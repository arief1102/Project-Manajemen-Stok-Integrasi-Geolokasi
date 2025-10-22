<?php
require_once 'vendor/autoload.php';

try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    if (isset($_ENV['HERE_API_KEY'])) {
        echo $_ENV['HERE_API_KEY'];
    } else {
        throw new Exception('HERE_API_KEY not found in .env file');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
