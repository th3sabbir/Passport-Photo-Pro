<?php
/**
 * Passport Photo Pro - PHP Backend
 * Image processing, background removal, enhancement, and PDF generation
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/classes/ApiResponse.php';
require_once __DIR__ . '/classes/ImageProcessor.php';
require_once __DIR__ . '/classes/CloudinaryUploader.php';

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Routing
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$request_method = $_SERVER['REQUEST_METHOD'];

// Handle subdirectory installation (Apache via /any-folder-name/)
// Dynamically detect the folder name and strip it if present
$base_folder = basename(__DIR__);
$base_path = '/' . $base_folder . '/';
if (strpos($request_uri, $base_path) === 0) {
    $request_uri = substr($request_uri, strlen($base_path) - 1);
}
// Ensure root request is just "/"
if (empty($request_uri) || $request_uri === '/' . $base_folder) {
    $request_uri = '/';
}

try {
    if ($request_uri === '/' && $request_method === 'GET') {
        // Serve index.html
        $html_file = __DIR__ . '/public/index.html';
        if (file_exists($html_file)) {
            header('Content-Type: text/html; charset=UTF-8');
            readfile($html_file);
        } else {
            ApiResponse::error('file_not_found', 'Frontend file not found', 404);
        }
    } elseif ($request_uri === '/api/status' && $request_method === 'GET') {
        // Return system status
        $status = getSystemStatus();
        ApiResponse::success($status, 'System status retrieved');
    } elseif ($request_uri === '/api/health' && $request_method === 'GET') {
        // Health check endpoint
        $health = [
            'status' => 'healthy',
            'timestamp' => time(),
            'version' => '1.0.0'
        ];
        ApiResponse::success($health, 'Healthy');
    } elseif ($request_uri === '/process' && $request_method === 'POST') {
        // Process images and generate PDF
        handleProcessRequest();
    } else {
        ApiResponse::error('not_found', 'Endpoint not found', 404);
    }
} catch (Exception $e) {
    handleException($e);
}

/**
 * Handle image processing and PDF generation
 */
function handleProcessRequest() {
    try {
        $processor = new ImageProcessor();
        
        // Layout settings
        $passport_width = (int)($_POST['width'] ?? 390);
        $passport_height = (int)($_POST['height'] ?? 480);
        $border = (int)($_POST['border'] ?? 2);
        $spacing = (int)($_POST['spacing'] ?? 10);
        $margin_x = 10;
        $margin_y = 10;
        $horizontal_gap = 10;
        $a4_w = 2480;
        $a4_h = 3508;
        
        // Collect images and their copy counts
        $images_data = [];
        
        // Multi-image mode (image_0, image_1, ...)
        $i = 0;
        while (isset($_FILES["image_$i"])) {
            $file = $_FILES["image_$i"];
            $validation = validateImageFile($file);
            if (!$validation['valid']) {
                ApiResponse::error($validation['error'], null, 400);
            }
            
            $copies = (int)($_POST["copies_$i"] ?? 6);
            $images_data[] = [
                'data' => file_get_contents($file['tmp_name']),
                'name' => $file['name'],
                'copies' => max(1, min($copies, 50))
            ];
            $i++;
        }
        
        // Fallback to single image mode
        if (empty($images_data) && isset($_FILES['image'])) {
            $file = $_FILES['image'];
            $validation = validateImageFile($file);
            if (!$validation['valid']) {
                ApiResponse::error($validation['error'], null, 400);
            }
            
            $copies = (int)($_POST['copies'] ?? 6);
            $images_data[] = [
                'data' => file_get_contents($file['tmp_name']),
                'name' => $file['name'],
                'copies' => max(1, min($copies, 50))
            ];
        }
        
        if (empty($images_data)) {
            ApiResponse::error('no_image_uploaded', null, 400);
        }
        
        logInfo("Processing " . count($images_data) . " image(s)");
        
        // Process all images
        $passport_images = [];
        foreach ($images_data as $idx => $img_data) {
            logInfo("Processing image " . ($idx + 1) . " with " . $img_data['copies'] . " copies");
            
            $processed_img = $processor->processSingleImage($img_data['data']);
            
            // Resize to passport dimensions
            $processed_img = $processor->resizeImage($processed_img, $passport_width, $passport_height);
            
            // Add border
            $final_img = $processor->addBorder($processed_img, $border, 'black');
            
            $passport_images[] = [
                'image' => $final_img,
                'copies' => $img_data['copies']
            ];
        }
        
        // Build PDF pages
        $paste_w = $passport_width + 2 * $border;
        $paste_h = $passport_height + 2 * $border;
        
        $pages = [];
        $current_page = imagecreatetruecolor($a4_w, $a4_h);
        $white = imagecolorallocate($current_page, 255, 255, 255);
        imagefill($current_page, 0, 0, $white);
        
        $x = $margin_x;
        $y = $margin_y;
        
        foreach ($passport_images as $passport_data) {
            for ($copy = 0; $copy < $passport_data['copies']; $copy++) {
                // Move to next row if needed
                if ($x + $paste_w > $a4_w - $margin_x) {
                    $x = $margin_x;
                    $y += $paste_h + $spacing;
                }
                
                // Move to next page if needed
                if ($y + $paste_h > $a4_h - $margin_y) {
                    $pages[] = $current_page;
                    $current_page = imagecreatetruecolor($a4_w, $a4_h);
                    imagefill($current_page, 0, 0, $white);
                    $x = $margin_x;
                    $y = $margin_y;
                }
                
                // Paste image
                imagecopy($current_page, $passport_data['image'], $x, $y, 0, 0, imagesx($passport_data['image']), imagesy($passport_data['image']));
                logDebug("Placed at x=$x, y=$y");
                
                $x += $paste_w + $horizontal_gap;
            }
        }
        
        $pages[] = $current_page;
        logInfo("Total pages: " . count($pages));
        
        // Generate PDF
        $pdf_content = $processor->generatePDF($pages, 300);
        
        // Clean up images
        foreach ($pages as $page) {
            imagedestroy($page);
        }
        foreach ($passport_images as $img_data) {
            imagedestroy($img_data['image']);
        }
        
        logInfo("Returning PDF to client");
        ApiResponse::file($pdf_content, 'passport-sheet.pdf', 'application/pdf');
        
    } catch (Exception $e) {
        logError($e->getMessage());
        
        // Parse error from message
        $message = $e->getMessage();
        if (strpos($message, 'bg_removal_failed') !== false) {
            if (strpos($message, '410') !== false || strpos($message, 'face') !== false) {
                ApiResponse::error('face_detection_failed', 'No face detected in image', 410);
            } elseif (strpos($message, '429') !== false || strpos($message, 'quota') !== false) {
                ApiResponse::error('quota_exceeded', 'API quota exceeded', 429);
            } else {
                ApiResponse::error('bg_removal_failed', 'Failed to remove background', 400);
            }
        } else {
            ApiResponse::error('processing_failed', $message, 500);
        }
    }
}

?>
