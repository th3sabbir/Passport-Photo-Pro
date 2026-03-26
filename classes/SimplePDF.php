<?php
/**
 * SimplePDF - A lightweight PDF generator for passport photos
 * Generates PDF from image resources without external dependencies
 */

class SimplePDF {
    private $images = [];
    private $dpi = 300;
    private $width;  // in pixels
    private $height; // in pixels
    
    public function __construct($dpi = 300) {
        $this->dpi = $dpi;
    }
    
    /**
     * Add image to PDF
     */
    public function addImage($image_resource, $width, $height) {
        $this->images[] = [
            'resource' => $image_resource,
            'width' => $width,
            'height' => $height
        ];
        
        if (!$this->width) {
            $this->width = $width;
            $this->height = $height;
        }
    }
    
    /**
     * Generate PDF content (simplified PDF structure)
     */
    public function generate() {
        $temp_files = [];
        
        try {
            // Save images to temporary PNG files
            foreach ($this->images as $idx => $img_data) {
                $temp_file = tempnam(sys_get_temp_dir(), "pdf_img_") . ".png";
                imagepng($img_data['resource'], $temp_file);
                $temp_files[] = $temp_file;
            }
            
            // Try to use ImageMagick if available
            if ($this->hasImageMagick()) {
                return $this->generateWithImageMagick($temp_files);
            }
            
            // Fallback to simple PDF structure
            return $this->generateSimplePDF($temp_files);
        } finally {
            // Cleanup
            foreach ($temp_files as $file) {
                @unlink($file);
            }
        }
    }
    
    /**
     * Generate PDF using ImageMagick
     */
    private function generateWithImageMagick($png_files) {
        $output_file = tempnam(sys_get_temp_dir(), "pdf_output_") . ".pdf";
        $files_str = implode(' ', array_map('escapeshellarg', $png_files));
        $density = $this->dpi;
        
        $cmd = "convert -density {$density} {$files_str} -compress jpeg -quality 95 " . escapeshellarg($output_file);
        
        exec($cmd, $output, $return_code);
        
        if ($return_code === 0 && file_exists($output_file)) {
            $content = file_get_contents($output_file);
            @unlink($output_file);
            return $content;
        }
        
        throw new Exception("ImageMagick PDF generation failed");
    }
    
    /**
     * Generate simple PDF using built-in functions
     */
    private function generateSimplePDF($png_files) {
        // Basic PDF structure for single or multiple images
        $pdf = "%PDF-1.4\n";
        $objects = [];
        $object_offsets = [];
        
        // Create objects for each image
        foreach ($png_files as $idx => $png_file) {
            $image_data = file_get_contents($png_file);
            
            // Image XObject (object number = 2 + idx*2)
            $img_obj_num = 2 + $idx * 2;
            $metadata_obj_num = 3 + $idx * 2;
            
            // Store offset for xref
            $object_offsets[$img_obj_num] = strlen($pdf);
            
            $pdf .= "$img_obj_num 0 obj\n";
            $pdf .= "<< /Type /XObject /Subtype /Image /Width " . imagesx(imagecreatefromstring($image_data)) . 
                    " /Height " . imagesy(imagecreatefromstring($image_data)) . 
                    " /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length " . strlen($image_data) . " >>\n";
            $pdf .= "stream\n" . $image_data . "\nendstream\n";
            $pdf .= "endobj\n";
        }
        
        // Create pages
        $pages_kids = "[ ";
        for ($i = 0; $i < count($png_files); $i++) {
            $pages_kids .= ($5 + $i) . " 0 R ";
        }
        $pages_kids .= "]";
        
        // Pages object
        $object_offsets[4] = strlen($pdf);
        $pdf .= "4 0 obj\n";
        $pdf .= "<< /Type /Pages /Kids $pages_kids /Count " . count($png_files) . " >>\n";
        $pdf .= "endobj\n";
        
        // Create individual page objects
        for ($i = 0; $i < count($png_files); $i++) {
            $page_obj_num = 5 + $i;
            $img_obj_num = 2 + $i * 2;
            
            $object_offsets[$page_obj_num] = strlen($pdf);
            $pdf .= "$page_obj_num 0 obj\n";
            $pdf .= "<< /Type /Page /Parent 4 0 R /Resources << /XObject << /Image$img_obj_num $img_obj_num 0 R >> >> ";
            $pdf .= "/MediaBox [ 0 0 595.27 841.89 ] /Contents " . ($page_obj_num + 100) . " 0 R >>\n";
            $pdf .= "endobj\n";
            
            // Content stream
            $object_offsets[$page_obj_num + 100] = strlen($pdf);
            $pdf .= ($page_obj_num + 100) . " 0 obj\n";
            $pdf .= "<< /Length 45 >>\n";
            $pdf .= "stream\n";
            $pdf .= "q 595.27 0 0 841.89 0 0 cm /Image$img_obj_num Do Q\n";
            $pdf .= "endstream\n";
            $pdf .= "endobj\n";
        }
        
        // Catalog object
        $object_offsets[1] = strlen($pdf);
        $pdf .= "1 0 obj\n";
        $pdf .= "<< /Type /Catalog /Pages 4 0 R >>\n";
        $pdf .= "endobj\n";
        
        // Info object
        $object_offsets[3] = strlen($pdf);
        $info_text = "Passport Photo Pro - Generated " . date('Y-m-d H:i:s');
        $pdf .= "3 0 obj\n";
        $pdf .= "<< /Producer (Passport Photo Pro PHP) /CreationDate (D:" . date('YmdHis') . ") /Title (passport-sheet.pdf) >>\n";
        $pdf .= "endobj\n";
        
        // Cross-reference table
        $xref_offset = strlen($pdf);
        $pdf .= "xref\n";
        $pdf .= "0 " . (count($object_offsets) + 1) . "\n";
        $pdf .= sprintf("%010d %05d f\n", 0, 65535);
        
        foreach (range(1, max(array_keys($object_offsets))) as $obj_num) {
            if (isset($object_offsets[$obj_num])) {
                $pdf .= sprintf("%010d %05d n\n", $object_offsets[$obj_num], 0);
            } else {
                $pdf .= sprintf("%010d %05d f\n", 0, 65535);
            }
        }
        
        // Trailer
        $pdf .= "trailer\n";
        $pdf .= "<< /Size " . (max(array_keys($object_offsets)) + 1) . " /Root 1 0 R /Info 3 0 R >>\n";
        $pdf .= "startxref\n";
        $pdf .= "$xref_offset\n";
        $pdf .= "%%EOF\n";
        
        return $pdf;
    }
    
    /**
     * Check if ImageMagick is available
     */
    private function hasImageMagick() {
        $output = @shell_exec('which convert 2>/dev/null || command -v convert 2>nul');
        return !empty($output);
    }
}

?>
