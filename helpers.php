<?php
/**
 * Helper Functions and Utilities
 */

/**
 * Validate image file
 */
function validateImageFile($file) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['valid' => false, 'error' => 'no_image_uploaded'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'upload_error'];
    }
    
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['valid' => false, 'error' => 'file_too_large'];
    }
    
    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, ALLOWED_IMAGE_TYPES)) {
        return ['valid' => false, 'error' => 'invalid_image_type'];
    }
    
    // Validate file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return ['valid' => false, 'error' => 'invalid_image_type'];
    }
    
    return ['valid' => true];
}

/**
 * Clean up temporary file
 */
function cleanupTempFile($file_path) {
    if (is_file($file_path) && strpos($file_path, TEMP_DIR) === 0) {
        @unlink($file_path);
    }
}

/**
 * Clean up temporary directory
 */
function cleanupTempDirectory() {
    if (is_dir(TEMP_DIR)) {
        $files = glob(TEMP_DIR . '/*');
        $now = time();
        
        foreach ($files as $file) {
            // Remove files older than 1 hour
            if (is_file($file) && $now - filemtime($file) > 3600) {
                @unlink($file);
            }
        }
    }
}

/**
 * Format bytes to human readable
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Get image dimensions safely
 */
function getImageDimensions($image_bytes) {
    $temp_file = tempnam(TEMP_DIR, 'img_');
    file_put_contents($temp_file, $image_bytes);
    
    $size = getimagesizefromstring($image_bytes);
    @unlink($temp_file);
    
    if ($size === false) {
        return null;
    }
    
    return ['width' => $size[0], 'height' => $size[1]];
}

/**
 * Log debug message
 */
function logDebug($message) {
    error_log('[DEBUG] ' . $message);
}

/**
 * Log info message
 */
function logInfo($message) {
    error_log('[INFO] ' . $message);
}

/**
 * Log warning message
 */
function logWarning($message) {
    error_log('[WARNING] ' . $message);
}

/**
 * Log error message
 */
function logError($message) {
    error_log('[ERROR] ' . $message);
}

/**
 * Check if ImageMagick is available
 */
function hasImageMagick() {
    $output = [];
    $return_code = 0;
    
    @exec('which convert', $output, $return_code);
    
    return $return_code === 0;
}

/**
 * Check if Ghostscript is available
 */
function hasGhostscript() {
    $output = [];
    $return_code = 0;
    
    @exec('which gs', $output, $return_code);
    
    return $return_code === 0;
}

/**
 * Get system requirements status
 */
function getSystemStatus() {
    return [
        'php_version' => phpversion(),
        'php_version_ok' => version_compare(phpversion(), '7.4', '>='),
        'gd_enabled' => extension_loaded('gd'),
        'curl_enabled' => extension_loaded('curl'),
        'fileinfo_enabled' => extension_loaded('fileinfo'),
        'imagemagick_available' => hasImageMagick(),
        'ghostscript_available' => hasGhostscript(),
        'temp_dir_writable' => is_writable(TEMP_DIR),
        'upload_dir_writable' => is_writable(UPLOAD_DIR)
    ];
}

?>
