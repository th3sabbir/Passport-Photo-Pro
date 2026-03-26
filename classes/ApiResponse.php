<?php
/**
 * API Response Handler - Standardized JSON responses
 */

class ApiResponse {
    
    /**
     * Send success response with data
     */
    public static function success($data = null, $message = 'Success', $http_code = 200) {
        http_response_code($http_code);
        header('Content-Type: application/json');
        
        $response = [
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ];
        
        echo json_encode($response);
        exit;
    }
    
    /**
     * Send error response
     */
    public static function error($error_code, $message = null, $http_code = 400) {
        http_response_code($http_code);
        header('Content-Type: application/json');
        
        $default_messages = [
            'no_image_uploaded' => 'No image uploaded',
            'invalid_image_type' => 'Invalid image type',
            'file_too_large' => 'File is too large (max 50MB)',
            'face_detection_failed' => 'No face detected in image',
            'bg_removal_failed' => 'Failed to remove background',
            'image_processing_failed' => 'Failed to process image',
            'pdf_generation_failed' => 'Failed to generate PDF',
            'cloudinary_upload_failed' => 'Failed to upload to Cloudinary',
            'api_credentials_missing' => 'API credentials not configured',
            'quota_exceeded' => 'API quota exceeded',
            'server_error' => 'Internal server error'
        ];
        
        $response = [
            'status' => 'error',
            'error_code' => $error_code,
            'message' => $message ?? $default_messages[$error_code] ?? 'Unknown error'
        ];
        
        echo json_encode($response);
        exit;
    }
    
    /**
     * Send file response (PDF, image, etc.)
     */
    public static function file($content, $filename, $mime_type = 'application/pdf') {
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo $content;
        exit;
    }
}

/**
 * Global error handler
 */
function handleException($exception) {
    error_log('Exception: ' . $exception->getMessage() . ' at ' . $exception->getFile() . ':' . $exception->getLine());
    
    // Determine appropriate HTTP code
    $code = $exception->getCode() ?? 500;
    if ($code < 100 || $code >= 600) {
        $code = 500;
    }
    
    $message = $exception->getMessage();
    
    // Parse error code from message if present
    if (strpos($message, ':') !== false) {
        $parts = explode(':', $message, 2);
        $error_code = $parts[0];
        $message = $parts[1] ?? $error_code;
        
        ApiResponse::error($error_code, $message, $code);
    } else {
        ApiResponse::error('server_error', $message, $code);
    }
}

set_exception_handler('handleException');

?>
