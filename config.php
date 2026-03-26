<?php
/**
 * Configuration file for Passport Photo Pro
 * Load environment variables and set up defaults
 */

// Load .env file
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Skip comments
        if (strpos($line, '=') === false) continue;
        
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        
        if (!isset($_SERVER[$key])) {
            putenv("$key=$value");
        }
    }
}

// Configuration constants
define('REMOVE_BG_API_KEY', getenv('REMOVE_BG_API_KEY'));
define('CLOUDINARY_CLOUD_NAME', getenv('CLOUDINARY_CLOUD_NAME'));
define('CLOUDINARY_API_KEY', getenv('CLOUDINARY_API_KEY'));
define('CLOUDINARY_API_SECRET', getenv('CLOUDINARY_API_SECRET'));

// Temporary directory for processing
define('TEMP_DIR', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'passport_photos_' . getenv('USER' ?: 'www-data'));
if (!is_dir(TEMP_DIR)) {
    mkdir(TEMP_DIR, 0755, true);
}

// Upload directory
define('UPLOAD_DIR', __DIR__ . '/uploads');
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Set allowed image types
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/jpg', 'image/png', 'image/webp']);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);

// Set max upload size (50MB)
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024);

?>
