<?php
/**
 * Asset serving script - serves photos and videos from private directory
 * This allows serving private assets through PHP for security
 */
require_once __DIR__ . '/../private/config.php';

$type = $_GET['type'] ?? '';
$path = $_GET['path'] ?? '';

// Security: Only allow photo and video types
if (!in_array($type, ['photo', 'video'])) {
    http_response_code(400);
    die('Invalid asset type');
}

// Security: Prevent directory traversal
$path = str_replace('..', '', $path);
$path = ltrim($path, '/');

// Determine base directory
if ($type === 'photo') {
    $baseDir = PRIVATE_DIR . '/photos/';
} else {
    $baseDir = PRIVATE_DIR . '/videos/';
}

$filePath = $baseDir . $path;

// Security: Ensure file exists and is within allowed directory
if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    die('File not found');
}

// Security: Ensure file is within the base directory (prevent directory traversal)
$realPath = realpath($filePath);
$realBaseDir = realpath($baseDir);
if (strpos($realPath, $realBaseDir) !== 0) {
    http_response_code(403);
    die('Access denied');
}

// Determine MIME type
$mimeType = mime_content_type($filePath);
if (!$mimeType) {
    // Fallback MIME types
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'mov' => 'video/quicktime',
        'mp4' => 'video/mp4',
    ];
    $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
}

// Set headers and output file
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: public, max-age=31536000'); // Cache for 1 year

readfile($filePath);
exit;





