<?php
// Load environment variables
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Define constants
define('BASE_URL', 'https://wedding.stephens.page');
define('PRIVATE_DIR', __DIR__);
define('PUBLIC_DIR', __DIR__ . '/../public');


