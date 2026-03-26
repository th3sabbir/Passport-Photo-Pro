<?php
/**
 * CloudinaryUploader Class
 * Handles upload and transformation requests to Cloudinary
 */

class CloudinaryUploader {
    private $cloud_name;
    private $api_key;
    private $api_secret;
    
    public function __construct() {
        $this->cloud_name = CLOUDINARY_CLOUD_NAME;
        $this->api_key = CLOUDINARY_API_KEY;
        $this->api_secret = CLOUDINARY_API_SECRET;
        
        if (!$this->cloud_name || !$this->api_key || !$this->api_secret) {
            throw new Exception('Cloudinary credentials not configured');
        }
    }
    
    /**
     * Upload image to Cloudinary
     */
    public function upload($image_bytes, $options = []) {
        $temp_file = tempnam(sys_get_temp_dir(), 'img_');
        file_put_contents($temp_file, $image_bytes);
        
        try {
            $ch = curl_init();
            $timestamp = time();
            
            // Default options
            $upload_params = array_merge([
                'resource_type' => 'image',
                'timestamp' => $timestamp,
                'api_key' => $this->api_key,
                'quality' => 'auto'
            ], $options);
            
            // Build signature string (must be sorted alphabetically by key)
            ksort($upload_params);
            $signature_str = '';
            foreach ($upload_params as $key => $value) {
                if ($key !== 'api_key') {
                    $signature_str .= "$key=$value&";
                }
            }
            $signature_str = rtrim($signature_str, '&') . $this->api_secret;
            $signature = hash('sha1', $signature_str);
            
            $upload_params['signature'] = $signature;
            $upload_params['file'] = new CURLFile($temp_file);
            
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://api.cloudinary.com/v1_1/{$this->cloud_name}/image/upload",
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => $upload_params,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            unlink($temp_file);
            
            if ($http_code !== 200) {
                throw new Exception("Upload failed with status $http_code");
            }
            
            return json_decode($response, true);
        } catch (Exception $e) {
            @unlink($temp_file);
            throw $e;
        }
    }
    
    /**
     * Build transformation URL
     */
    public function getTransformationUrl($public_id, $transformations = []) {
        $transform_str = '';
        
        foreach ($transformations as $transform) {
            if (is_array($transform)) {
                $parts = [];
                foreach ($transform as $key => $value) {
                    $parts[] = "{$key}_{$value}";
                }
                $transform_str .= implode(',', $parts) . '/';
            } else {
                $transform_str .= $transform . '/';
            }
        }
        
        return "https://res.cloudinary.com/{$this->cloud_name}/image/upload/{$transform_str}{$public_id}";
    }
}

?>
