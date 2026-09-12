<?php
require_once __DIR__ . '/../../vendor/autoload.php';

header('Content-Type: application/javascript');

$root = dirname(__DIR__, 2);
$dotenv = Dotenv\Dotenv::createImmutable($root);
$dotenv->safeLoad();

$apiUrl = getenv('API_URL') ?: 'http://localhost:3000/notes/backend/api/Note.php';
?>
window.APP_CONFIG = {
    API_URL: <?php echo json_encode($apiUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
};
