<?php
/**
 * ImageProcessor Class
 * Handles image processing, background removal, and enhancement
 */

class ImageProcessor {
    
    /**
     * Process a single image: remove background, enhance, and return processed image resource
     */
    public function processSingleImage($image_bytes) {
        // Step 1: Remove background using remove.bg API
        $bg_removed_bytes = $this->removeBackground($image_bytes);
        
        // Load image from bytes
        $img = imagecreatefromstring($bg_removed_bytes);
        if ($img === false) {
            throw new Exception('Failed to create image from bytes');
        }
        
        // Step 2: Handle transparency - convert to white background if needed
        if ($this->hasTransparency($img)) {
            $img = $this->removeTransparency($img);
        } else {
            $img = $this->ensureRGB($img);
        }
        
        // Step 3: Upload to Cloudinary and enhance
        $enhanced_bytes = $this->enhanceViaCloudinary($bg_removed_bytes);
        
        // Load enhanced image
        $enhanced_img = imagecreatefromstring($enhanced_bytes);
        if ($enhanced_img === false) {
            throw new Exception('Failed to create enhanced image from bytes');
        }
        
        // Handle transparency in enhanced image
        if ($this->hasTransparency($enhanced_img)) {
            $enhanced_img = $this->removeTransparency($enhanced_img);
        } else {
            $enhanced_img = $this->ensureRGB($enhanced_img);
        }
        
        return $enhanced_img;
    }
    
    /**
     * Remove background using remove.bg API
     */
    private function removeBackground($image_bytes) {
        $api_key = REMOVE_BG_API_KEY;
        if (!$api_key) {
            throw new Exception('REMOVE_BG_API_KEY not configured');
        }
        
        // Create temporary file
        $temp_file = tempnam(TEMP_DIR, 'img_');
        file_put_contents($temp_file, $image_bytes);
        
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.remove.bg/v1.0/removebg',
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => [
                    'image_file' => new CURLFile($temp_file),
                    'size' => 'auto'
                ],
                CURLOPT_HTTPHEADER => [
                    'X-Api-Key: ' . $api_key
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            unlink($temp_file);
            
            if ($http_code !== 200) {
                $error_data = json_decode($response, true);
                if ($error_data && isset($error_data['errors'])) {
                    $error_code = $error_data['errors'][0]['code'] ?? 'unknown_error';
                    throw new Exception("bg_removal_failed:$error_code:$http_code");
                }
                throw new Exception("bg_removal_failed:unknown:$http_code");
            }
            
            return $response;
        } catch (Exception $e) {
            @unlink($temp_file);
            throw $e;
        }
    }
    
    /**
     * Enhance image via Cloudinary
     */
    private function enhanceViaCloudinary($image_bytes) {
        $cloud_name = CLOUDINARY_CLOUD_NAME;
        $api_key = CLOUDINARY_API_KEY;
        $api_secret = CLOUDINARY_API_SECRET;
        
        if (!$cloud_name || !$api_key || !$api_secret) {
            throw new Exception('Cloudinary credentials not configured');
        }
        
        // Create temporary file
        $temp_file = tempnam(TEMP_DIR, 'img_');
        file_put_contents($temp_file, $image_bytes);
        
        try {
            // Upload to Cloudinary
            $ch = curl_init();
            $timestamp = time();
            
            // Build signature
            $signature_str = "resource_type=image&timestamp=$timestamp" . $api_secret;
            $signature = hash('sha1', $signature_str);
            
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://api.cloudinary.com/v1_1/$cloud_name/image/upload",
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => [
                    'file' => new CURLFile($temp_file),
                    'api_key' => $api_key,
                    'timestamp' => $timestamp,
                    'signature' => $signature,
                    'quality' => 'auto'
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60
            ]);
            
            $upload_response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code !== 200) {
                throw new Exception("Cloudinary upload failed: $http_code");
            }
            
            $upload_data = json_decode($upload_response, true);
            if (!isset($upload_data['public_id'])) {
                throw new Exception('cloudinary_upload_failed');
            }
            
            $public_id = $upload_data['public_id'];
            
            // Build enhanced URL with transformations
            $enhanced_url = "https://res.cloudinary.com/$cloud_name/image/fetch/" .
                           "e_gen_restore/q_auto/f_auto/" .
                           "https://res.cloudinary.com/$cloud_name/image/upload/v" . $upload_data['version'] . "/" . $public_id;
            
            // Fetch enhanced image
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $enhanced_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30
            ]);
            
            $enhanced_bytes = curl_exec($ch);
            curl_close($ch);
            
            unlink($temp_file);
            
            return $enhanced_bytes;
        } catch (Exception $e) {
            @unlink($temp_file);
            throw $e;
        }
    }
    
    /**
     * Check if image has transparency
     */
    private function hasTransparency($image) {
        $width = imagesx($image);
        $height = imagesy($image);
        
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $color = imagecolorat($image, $x, $y);
                $alpha = ($color >> 24) & 0xFF;
                if ($alpha > 0) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Remove transparency by adding white background
     */
    private function removeTransparency($image) {
        $width = imagesx($image);
        $height = imagesy($image);
        
        $background = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($background, 255, 255, 255);
        imagefill($background, 0, 0, $white);
        
        imagecopy($background, $image, 0, 0, 0, 0, $width, $height);
        imagedestroy($image);
        
        return $background;
    }
    
    /**
     * Ensure image is RGB (not grayscale)
     */
    private function ensureRGB($image) {
        $width = imagesx($image);
        $height = imagesy($image);
        
        $rgb_image = imagecreatetruecolor($width, $height);
        imagecopy($rgb_image, $image, 0, 0, 0, 0, $width, $height);
        imagedestroy($image);
        
        return $rgb_image;
    }
    
    /**
     * Resize image to specific dimensions
     */
    public function resizeImage($image, $width, $height) {
        $resized = imagecreatetruecolor($width, $height);
        imagecopyresampled(
            $resized, $image,
            0, 0, 0, 0,
            $width, $height,
            imagesx($image), imagesy($image)
        );
        imagedestroy($image);
        return $resized;
    }
    
    /**
     * Add border to image
     */
    public function addBorder($image, $border_size, $border_color = 'black') {
        $width = imagesx($image);
        $height = imagesy($image);
        $new_width = $width + 2 * $border_size;
        $new_height = $height + 2 * $border_size;
        
        $bordered = imagecreatetruecolor($new_width, $new_height);
        
        // Allocate border color
        if ($border_color === 'black') {
            $color = imagecolorallocate($bordered, 0, 0, 0);
        } else {
            $color = imagecolorallocate($bordered, 255, 255, 255);
        }
        
        imagefill($bordered, 0, 0, $color);
        imagecopy($bordered, $image, $border_size, $border_size, 0, 0, $width, $height);
        imagedestroy($image);
        
        return $bordered;
    }
    
    /**
     * Generate PDF from image resources using ImageMagick
     */
    public function generatePDF($pages, $dpi = 300) {
        $temp_files = [];
        
        try {
            // Save each page to temporary PNG file
            foreach ($pages as $idx => $page) {
                $temp_file = tempnam(TEMP_DIR, "page_") . ".png";
                imagepng($page, $temp_file);
                $temp_files[] = $temp_file;
            }
            
            // Use ImageMagick to convert to PDF
            $output_file = tempnam(TEMP_DIR, "pdf_") . ".pdf";
            $temp_list = implode(' ', array_map('escapeshellarg', $temp_files));
            $cmd = "convert -density {$dpi} $temp_list -compress jpeg -quality 95 " . escapeshellarg($output_file);
            
            @exec($cmd, $output, $return_code);
            
            if ($return_code === 0 && file_exists($output_file)) {
                $pdf_content = file_get_contents($output_file);
                @unlink($output_file);
                
                foreach ($temp_files as $file) {
                    @unlink($file);
                }
                
                return $pdf_content;
            }
            
            // If ImageMagick fails, try alternative method
            return $this->generatePDFWithGhostscript($temp_files, $dpi, $output_file);
        } catch (Exception $e) {
            foreach ($temp_files as $file) {
                @unlink($file);
            }
            throw $e;
        }
    }
    
    /**
     * Fallback PDF generation using Ghostscript
     */
    private function generatePDFWithGhostscript($temp_files, $dpi, $output_file) {
        $temp_list = implode(' ', array_map('escapeshellarg', $temp_files));
        
        // Try Ghostscript
        $cmd = "gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dPDFFitPage -r{$dpi} -sOutputFile=" . escapeshellarg($output_file) . " $temp_list";
        
        @exec($cmd, $output, $return_code);
        
        if ($return_code === 0 && file_exists($output_file)) {
            $pdf_content = file_get_contents($output_file);
            @unlink($output_file);
            
            foreach ($temp_files as $file) {
                @unlink($file);
            }
            
            return $pdf_content;
        }
        
        // All methods failed
        throw new Exception('PDF generation failed: ImageMagick and Ghostscript not available. Install ImageMagick or Ghostscript.');
    }
}

?>
