<?php
/**
 * PWA Icon Generator
 * Generates PWA icons from association logo or default people icon
 */

require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/functions.php';

// Get size from query parameter
$size = isset($_GET['size']) ? (int)$_GET['size'] : 192;

// Validate size
$allowedSizes = [72, 96, 128, 144, 152, 192, 384, 512];
if (!in_array($size, $allowedSizes)) {
    $size = 192;
}

// Set content type
header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400'); // Cache for 1 day

// Try to get association logo
$assocInfo = getAssociationInfo();
$logoPath = $assocInfo['logo'] ?? null;

// If logo exists and is a local file, try to use it
if ($logoPath && !preg_match('/^https?:\/\//', $logoPath)) {
    $logoFullPath = __DIR__ . '/../../' . ltrim($logoPath, '/');
    if (file_exists($logoFullPath)) {
        // Create image from logo
        $ext = strtolower(pathinfo($logoFullPath, PATHINFO_EXTENSION));
        $sourceImage = null;
        
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $sourceImage = @imagecreatefromjpeg($logoFullPath);
                break;
            case 'png':
                $sourceImage = @imagecreatefrompng($logoFullPath);
                break;
            case 'gif':
                $sourceImage = @imagecreatefromgif($logoFullPath);
                break;
        }
        
        if ($sourceImage) {
            // Create a new square image with padding
            $icon = imagecreatetruecolor($size, $size);
            $bgColor = imagecolorallocate($icon, 102, 126, 234); // #667eea
            imagefill($icon, 0, 0, $bgColor);
            
            // Calculate dimensions to fit logo with padding
            $padding = (int)($size * 0.15);
            $logoSize = $size - (2 * $padding);
            
            // Get source dimensions
            $srcWidth = imagesx($sourceImage);
            $srcHeight = imagesy($sourceImage);
            
            // Calculate aspect ratio
            $aspectRatio = $srcWidth / $srcHeight;
            
            if ($aspectRatio > 1) {
                // Wider than tall
                $dstWidth = $logoSize;
                $dstHeight = (int)($logoSize / $aspectRatio);
            } else {
                // Taller than wide or square
                $dstHeight = $logoSize;
                $dstWidth = (int)($logoSize * $aspectRatio);
            }
            
            // Center the logo
            $dstX = $padding + (int)(($logoSize - $dstWidth) / 2);
            $dstY = $padding + (int)(($logoSize - $dstHeight) / 2);
            
            // Resize and copy logo
            imagecopyresampled($icon, $sourceImage, $dstX, $dstY, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);
            
            imagedestroy($sourceImage);
            imagepng($icon);
            imagedestroy($icon);
            exit;
        }
    }
}

// Default: Create icon with people icon
$icon = imagecreatetruecolor($size, $size);

// Create gradient background
$bgColor = imagecolorallocate($icon, 102, 126, 234); // #667eea
imagefill($icon, 0, 0, $bgColor);

// Draw simple people icon (three circles representing people)
$white = imagecolorallocate($icon, 255, 255, 255);

// Calculate sizes
$circleRadius = (int)($size * 0.12);
$centerY = (int)($size * 0.45);

// Draw three circles representing people
$positions = [
    (int)($size * 0.3),  // Left
    (int)($size * 0.5),  // Center
    (int)($size * 0.7)   // Right
];

foreach ($positions as $x) {
    // Head
    imagefilledellipse($icon, $x, $centerY, $circleRadius * 2, $circleRadius * 2, $white);
    
    // Body (simplified as an arc)
    $bodyY = $centerY + $circleRadius + (int)($size * 0.05);
    $bodyRadius = (int)($circleRadius * 1.5);
    imagefilledarc($icon, $x, $bodyY, $bodyRadius * 2, $bodyRadius * 2, 0, 180, $white, IMG_ARC_PIE);
}

// Output the image
imagepng($icon);
imagedestroy($icon);
