<?php
// Simple filter generator script
// Run this script to create basic filter images

$filtersDir = __DIR__ . '/../filters/';

if (!is_dir($filtersDir)) {
    mkdir($filtersDir, 0755, true);
}

// Create simple colored overlay filters
$filters = [
    'heart_eyes.png' => [255, 182, 193, 50], // Light pink
    'sunglasses.png' => [0, 0, 0, 80], // Black
    'mustache.png' => [139, 69, 19, 60], // Brown
    'crown.png' => [255, 215, 0, 70], // Gold
    'dog_ears.png' => [210, 180, 140, 50], // Tan
    'cat_ears.png' => [128, 128, 128, 50], // Gray
    'santa_hat.png' => [220, 20, 60, 60], // Crimson
    'frame_border.png' => [75, 0, 130, 40], // Indigo
];

foreach ($filters as $filename => $color) {
    list($r, $g, $b, $alpha) = $color;
    
    // Create a 200x200 transparent image
    $image = imagecreatetruecolor(200, 200);
    
    // Enable alpha blending
    imagealphablending($image, false);
    imagesavealpha($image, true);
    
    // Make background transparent
    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefill($image, 0, 0, $transparent);
    
    // Create the filter color
    $filterColor = imagecolorallocatealpha($image, $r, $g, $b, $alpha);
    
    // Draw different shapes based on filter
    if (strpos($filename, 'heart') !== false) {
        // Draw heart shape (simplified as circle)
        imagefilledellipse($image, 100, 100, 80, 80, $filterColor);
    } elseif (strpos($filename, 'sunglasses') !== false) {
        // Draw sunglasses (rectangle)
        imagefilledrectangle($image, 50, 80, 150, 120, $filterColor);
    } elseif (strpos($filename, 'mustache') !== false) {
        // Draw mustache (ellipse)
        imagefilledellipse($image, 100, 140, 120, 30, $filterColor);
    } elseif (strpos($filename, 'crown') !== false) {
        // Draw crown (polygon approximation)
        $points = [100, 50, 120, 90, 160, 90, 130, 110, 140, 150, 100, 130, 60, 150, 70, 110, 40, 90, 80, 90];
        imagefilledpolygon($image, $points, 10, $filterColor);
    } elseif (strpos($filename, 'ears') !== false) {
        // Draw ears (two circles)
        imagefilledellipse($image, 70, 60, 40, 60, $filterColor);
        imagefilledellipse($image, 130, 60, 40, 60, $filterColor);
    } elseif (strpos($filename, 'hat') !== false) {
        // Draw hat (triangle + rectangle)
        $points = [80, 80, 120, 80, 100, 40];
        imagefilledpolygon($image, $points, 3, $filterColor);
        imagefilledrectangle($image, 90, 80, 110, 90, $filterColor);
    } elseif (strpos($filename, 'frame') !== false) {
        // Draw frame border
        imagerectangle($image, 10, 10, 190, 190, $filterColor);
        imagerectangle($image, 15, 15, 185, 185, $filterColor);
        imagerectangle($image, 20, 20, 180, 180, $filterColor);
    } else {
        // Default: filled circle
        imagefilledellipse($image, 100, 100, 80, 80, $filterColor);
    }
    
    // Save the image
    $filepath = $filtersDir . $filename;
    imagepng($image, $filepath);
    imagedestroy($image);
    
    echo "Created filter: $filename\n";
}

echo "All filter images created successfully!\n";
?>
