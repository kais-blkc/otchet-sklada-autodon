<?php
require_once 'constants.php';

// HEADERS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, PUT');
header('Access-Control-Allow-Headers: Content-Type');

// PATHS
define('PROJECT_ROOT', dirname(__DIR__, 1));
define('UPLOAD_DIR', PROJECT_ROOT . '/uploads/');
define('UPLOAD_URL', '/uploads/');

// Creage directory if not
if (!file_exists(UPLOAD_DIR)) {
  mkdir(UPLOAD_DIR);
}

// Func fot connect to DB
function getDbConnection()
{
  try {
    $dns = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ];

    return new PDO($dns, DB_USER, DB_PASS, $options);
  } catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
  }
}

// Func for upload file
function uploadFile($file, $fieldName)
{
  if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
    return null;
  }

  $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
  $maxSize = 5 * 1024 * 1024; // 5MB

  if (!in_array($file['type'], $allowedTypes)) {
    return null;
  }

  if ($file['size'] > $maxSize) {
    return null;
  }

  $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
  $filename = uniqid() . '_' . time() . '.' . $extension;
  $filepath = UPLOAD_DIR . $filename;

  if (move_uploaded_file($file['tmp_name'], $filepath)) {
    return UPLOAD_URL . $filename;
  }

  return null;
}
