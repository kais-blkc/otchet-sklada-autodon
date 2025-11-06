<?php
require_once 'config.php';
require_once 'compress_img.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

routeRequest();

function showData($data)
{
  echo '<pre>';
  print_r($data);
  echo '</pre>';
}

function routeRequest()
{
  global $action;
  global $method;

  try {
    $pdo = getDbConnection();

    switch ($action) {
      case 'save':
        if ($method === 'POST') {
          saveReports($pdo);
        }
        break;

      case 'get':
        if ($method === 'GET') {
          getReports($pdo);
        }
        break;

      case 'delete':
        if ($method === 'POST') {
          deleteReport($pdo);
        }
        break;

      case 'delete_img':
        if ($method === 'POST') {
          deleteFile($pdo);
        }
        break;

      default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
  }
}



// Func for get reports
function getReports($pdo)
{
  $date = $_GET['date'];
  if (!$date) {
    throw new Exception('Date is required');
  }

  $sql = "SELECT * FROM employee_reports WHERE report_date = :date ORDER BY id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':date' => $date]);

  $reports = $stmt->fetchAll();
  echo json_encode(['success' => true, 'data' => $reports, 'y-date' => $date]);
}

// Func save data reports to DB
function saveReports($pdo)
{
  $employees = $_POST['employees'] ?? [];
  if (empty($employees)) {
    throw new Exception('Employees data is required');
  }
  if (!is_array($employees)) {
    throw new Exception('Employees data must be an array');
  }

  // save in DB
  $pdo->beginTransaction();

  try {
    $sql = "INSERT INTO employee_reports (
        id,
        report_date, employee_name,
        pre_lunch_photo_plan, pre_lunch_comment_plan,
        pre_lunch_photo_fact, pre_lunch_comment_fact, pre_lunch_status,
        after_lunch_photo_plan, after_lunch_comment_plan,
        after_lunch_photo_fact, after_lunch_comment_fact, after_lunch_status
    ) VALUES (
        :id,
        :date, :name,
        :pre_lunch_photo_plan, :pre_lunch_comment_plan,
        :pre_lunch_photo_fact, :pre_lunch_comment_fact, :pre_lunch_status,
        :after_lunch_photo_plan, :after_lunch_comment_plan,
        :after_lunch_photo_fact, :after_lunch_comment_fact, :after_lunch_status
    ) ON DUPLICATE KEY UPDATE
        report_date = VALUES(report_date),
        employee_name = VALUES(employee_name),
        pre_lunch_photo_plan = COALESCE(VALUES(pre_lunch_photo_plan), pre_lunch_photo_plan),
        pre_lunch_comment_plan = VALUES(pre_lunch_comment_plan),
        pre_lunch_photo_fact = COALESCE(VALUES(pre_lunch_photo_fact), pre_lunch_photo_fact),
        pre_lunch_comment_fact = VALUES(pre_lunch_comment_fact),
        pre_lunch_status = VALUES(pre_lunch_status),
        after_lunch_photo_plan = COALESCE(VALUES(after_lunch_photo_plan), after_lunch_photo_plan),
        after_lunch_comment_plan = VALUES(after_lunch_comment_plan),
        after_lunch_photo_fact = COALESCE(VALUES(after_lunch_photo_fact), after_lunch_photo_fact),
        after_lunch_comment_fact = VALUES(after_lunch_comment_fact),
        after_lunch_status = VALUES(after_lunch_status);";
    $stmt = $pdo->prepare($sql);

    $uploadedFiles = saveFiles();
    foreach ($employees as $employeeId => $employee) {
      // put saved files path in employees
      if (isset($uploadedFiles[$employeeId])) {
        $employee = array_merge($employee, $uploadedFiles[$employeeId]);
      }

      // save to DB
      $stmt->execute([
        ':id' => $employee['id'] ?? null,
        ':date' => $employee['date'],
        ':name' => $employee['employee_name'],
        ':pre_lunch_photo_plan' => $employee['pre_lunch_photo_plan'] ?? null,
        ':pre_lunch_comment_plan' => $employee['pre_lunch_comment_plan'] ?? '',
        ':pre_lunch_photo_fact' => $employee['pre_lunch_photo_fact'] ?? null,
        ':pre_lunch_comment_fact' => $employee['pre_lunch_comment_fact'] ?? '',
        ':pre_lunch_status' => $employee['pre_lunch_status'] ?? '?',
        ':after_lunch_photo_plan' => $employee['after_lunch_photo_plan'] ?? null,
        ':after_lunch_comment_plan' => $employee['after_lunch_comment_plan'] ?? '',
        ':after_lunch_photo_fact' => $employee['after_lunch_photo_fact'] ?? null,
        ':after_lunch_comment_fact' => $employee['after_lunch_comment_fact'] ?? '',
        ':after_lunch_status' => $employee['after_lunch_status'] ?? '?'
      ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Reports saved successfully']);
  } catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
  }
}

// Func for delete report
function deleteReport($pdo)
{
  $id = $_POST['id'] ?? null;
  if (!$id) {
    throw new Exception('Report ID is required');
  }

  // get cur files
  $sql = "SELECT 
      pre_lunch_photo_plan, 
      pre_lunch_photo_fact,
      after_lunch_photo_plan,
      after_lunch_photo_fact 
    FROM employee_reports 
    WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':id' => $id]);
  $report = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$report) {
    throw new Exception('Report not found');
  }

  // delete cur images
  foreach ($report as $imgIndex => $imgPath) {
    if (!$imgPath) continue;
    $imgLocal = str_replace(UPLOAD_URL, '', $imgPath);
    $imgLocal = UPLOAD_DIR . $imgLocal;

    if ($imgLocal && file_exists($imgLocal)) {
      unlink($imgLocal);
    }
  }

  $sql = "DELETE FROM employee_reports WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':id' => $id]);

  echo json_encode(['success' => true, 'message' => 'Report deleted successfully']);
}

function saveFiles()
{
  if (!isset($_FILES['employees'])) {
    return;
  }
  $uploadedFiles = [];

  foreach ($_FILES['employees']['name'] as $employeeId => $fields) {
    foreach ($fields as $fieldName => $originalName) {
      $error = $_FILES['employees']['error'][$employeeId][$fieldName];
      if ($error !== UPLOAD_ERR_OK && empty($originalName)) {
        continue;
      }

      // Check type is image
      $type = $_FILES['employees']['type'][$employeeId][$fieldName];
      if (!in_array($type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
        continue;
      }

      // Construct new file name
      $tmpName = $_FILES['employees']['tmp_name'][$employeeId][$fieldName];
      $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
      $datePrefix = date('Y-m-d');
      $newFileName = uniqid("{$datePrefix}_emp{$employeeId}_{$fieldName}_") . '.' . $ext;
      $destination = UPLOAD_DIR . $newFileName;

      // Compress image
      $success = compressImage($tmpName, $destination);

      // Save file result
      if ($success) {
        $uploadedFiles[$employeeId][$fieldName] = UPLOAD_URL . $newFileName;
      } else {
        $uploadedFiles[$employeeId][$fieldName] = null;
      }
    }
  }

  return $uploadedFiles;
}

function deleteFile($pdo)
{
  $img = $_POST['img'] ?? null;
  if (!$img) {
    throw new Exception('Report "img" is required');
  }

  $fieldName = $_POST['fieldName'] ?? null;
  if (!$fieldName) {
    throw new Exception('Report "fieldName" is required');
  }

  $allowedFields = ['pre_lunch_photo_plan', 'pre_lunch_photo_fact', 'after_lunch_photo_plan', 'after_lunch_photo_fact'];
  if (!in_array($fieldName, $allowedFields)) {
    throw new Exception("Недопустимое поле");
  }

  $imgLocal = str_replace(UPLOAD_URL, '', $img);
  $imgLocal = UPLOAD_DIR . $imgLocal;
  if (!file_exists($imgLocal)) {
    echo json_encode(['success' => true, 'message' => 'File not found']);
    return;
  }

  $success_delete = unlink($imgLocal);
  if ($success_delete) {
    /* $sql = "DELETE FROM employee_reports WHERE $fieldName = :img"; */
    $sql = "UPDATE employee_reports SET $fieldName = NULL WHERE $fieldName = :img";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':img' => $img]);

    echo json_encode(['success' => true, 'message' => 'File deleted successfully']);
  }
}
