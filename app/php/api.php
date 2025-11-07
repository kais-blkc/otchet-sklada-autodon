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

function toArray($value)
{
  return is_array($value) ? $value : [$value];
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

      case 'delete_all':
        if ($method === 'POST') {
          deleteAllReportsForEmployee($pdo);
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
    $datesToDuplicate = [];

    foreach ($employees as $employeeId => $employee) {
      // put saved files path in employees
      if (isset($uploadedFiles[$employeeId])) {
        foreach ($uploadedFiles[$employeeId] as $fieldName => $fileArray) {
          $employee[$fieldName] = is_array($fileArray)
            ? json_encode($fileArray, JSON_UNESCAPED_SLASHES)
            : $fileArray;
        }
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

      $datesToDuplicate[$employee['date']] = true;
    }

    $pdo->commit();

    duplicateReportsToNextDays($pdo, array_keys($datesToDuplicate));

    echo json_encode(['success' => true, 'message' => 'Reports saved successfully']);
  } catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
  }
}

function duplicateReportsToNextDays($pdo, $sourceDates)
{
  if (empty($sourceDates)) {
    return;
  }

  $sourceDates = array_unique($sourceDates);
  sort($sourceDates);

  $pdo->beginTransaction();

  try {
    $insertSql = "INSERT INTO employee_reports (
      report_date, employee_name,
      pre_lunch_photo_plan, pre_lunch_comment_plan,
      pre_lunch_photo_fact, pre_lunch_comment_fact, pre_lunch_status,
      after_lunch_photo_plan, after_lunch_comment_plan,
      after_lunch_photo_fact, after_lunch_comment_fact, after_lunch_status
    ) VALUES (
      :date, :name,
      NULL, '', NULL, '', '?',
      NULL, '', NULL, '', '?'
    )
    ON DUPLICATE KEY UPDATE id = id;";

    $insertStmt = $pdo->prepare($insertSql);
    foreach ($sourceDates as $sourceDate) {
      $source = new DateTime($sourceDate);
      $endOfMonth = new DateTime($source->format('Y-m-t'));

      $select = $pdo->prepare("SELECT employee_name FROM employee_reports WHERE report_date = :date");
      $select->execute([':date' => $sourceDate]);
      $employees = $select->fetchAll(PDO::FETCH_COLUMN);

      if (empty($employees)) continue;

      $current = clone $source;
      $current->modify('+1 day');

      while ($current <= $endOfMonth) {
        $currentDate = $current->format('Y-m-d');
        foreach ($employees as $name) {
          $insertStmt->execute([
            ':date' => $currentDate,
            ':name' => $name
          ]);
        }
        $current->modify('+1 day');
      }
    }

    $pdo->commit();
  } catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
  }
}

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

function deleteAllReportsForEmployee($pdo)
{
  $id = $_POST['id'] ?? null;
  if (!$id) {
    throw new Exception('Report ID is required');
  }

  // Get report by id 
  $sql = "SELECT report_date, employee_name FROM employee_reports WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':id' => $id]);
  $report = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$report) {
    throw new Exception('Report not found');
  }

  $employeeName = $report['employee_name'];
  $sourceDate = new DateTime($report['report_date']);
  $endOfMonth = new DateTime($sourceDate->format('Y-m-t'));

  // Find all reports for employee
  $sql = "SELECT 
      id,
      pre_lunch_photo_plan, 
      pre_lunch_photo_fact,
      after_lunch_photo_plan,
      after_lunch_photo_fact
    FROM employee_reports
    WHERE employee_name = :name 
      AND report_date BETWEEN :from AND :to";
  $stmt = $pdo->prepare($sql);

  $stmt->execute([
    ':name' => $employeeName,
    ':from' => $sourceDate->format('Y-m-d'),
    ':to' => $endOfMonth->format('Y-m-d')
  ]);
  $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (empty($reports)) {
    throw new Exception('No reports found for this employee in the selected period');
  }

  // Delete files
  foreach ($reports as $rep) {
    foreach (['pre_lunch_photo_plan', 'pre_lunch_photo_fact', 'after_lunch_photo_plan', 'after_lunch_photo_fact'] as $field) {
      $imgPath = $rep[$field] ?? null;
      if (!$imgPath) continue;

      $imgLocal = str_replace(UPLOAD_URL, '', $imgPath);
      $imgLocal = UPLOAD_DIR . $imgLocal;

      if (file_exists($imgLocal)) {
        unlink($imgLocal);
      }
    }
  }

  // Delete reports
  $deleteSql = "DELETE FROM employee_reports 
                WHERE employee_name = :name 
                AND report_date BETWEEN :from AND :to";
  $deleteStmt = $pdo->prepare($deleteSql);
  $deleteStmt->execute([
    ':name' => $employeeName,
    ':from' => $sourceDate->format('Y-m-d'),
    ':to' => $endOfMonth->format('Y-m-d')
  ]);

  echo json_encode([
    'success' => true,
    'message' => "All reports for employee '$employeeName' from {$sourceDate->format('Y-m-d')} to end of month deleted successfully",
    'deleted' => count($reports)
  ]);
}


function saveFiles()
{
  if (!isset($_FILES['employees'])) {
    return;
  }

  $uploadedFiles = [];

  foreach ($_FILES['employees']['name'] as $employeeId => $fields) {
    foreach ($fields as $fieldName => $originalName) {
      // If multiple — process an array of files
      $names = toArray($_FILES['employees']['name'][$employeeId][$fieldName]);
      $tmpNames = toArray($_FILES['employees']['tmp_name'][$employeeId][$fieldName]);
      $types = toArray($_FILES['employees']['type'][$employeeId][$fieldName]);
      $errors = toArray($_FILES['employees']['error'][$employeeId][$fieldName]);

      foreach ($names as $i => $name) {
        if ($errors[$i] !== UPLOAD_ERR_OK || empty($name)) {
          continue;
        }
        if (!in_array($types[$i], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
          continue;
        }

        // Construct new file name
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $datePrefix = date('Y-m-d');
        $newFileName = uniqid("{$datePrefix}_emp{$employeeId}_{$fieldName}_") . '.' . $ext;
        $destination = UPLOAD_DIR . $newFileName;

        // Compress image
        $success = compressImage($tmpNames[$i], $destination);

        // Save file result
        if ($success) {
          $uploadedFiles[$employeeId][$fieldName][] = UPLOAD_URL . $newFileName;
        }
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

  $reportId = $_POST['reportId'] ?? null;
  if (!$reportId) {
    throw new Exception('Report "reportId" is required');
  }

  $allowedFields = ['pre_lunch_photo_plan', 'pre_lunch_photo_fact', 'after_lunch_photo_plan', 'after_lunch_photo_fact'];
  if (!in_array($fieldName, $allowedFields)) {
    throw new Exception("Недопустимое поле");
  }

  $imgLocal = str_replace(UPLOAD_URL, '', $img);
  $imgLocal = UPLOAD_DIR . $imgLocal;
  if (file_exists($imgLocal)) {
    unlink($imgLocal);
  }

  $sql = "SELECT $fieldName FROM employee_reports WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':id' => $reportId]);
  $current = $stmt->fetchColumn();

  if (!$current) {
    echo json_encode(['success' => true, 'message' => 'No files to update']);
    return;
  }

  // Convert JSON to array
  $files = json_decode($current, true);
  if (!is_array($files)) {
    $files = [];
  }

  // Remove the file from the array
  $updatedFiles = array_values(
    array_filter($files, fn($url) => $url !== $img)
  );

  // Update the report in the database
  if (empty($updatedFiles)) {
    $sql = "UPDATE employee_reports SET $fieldName = NULL WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $reportId]);
  } else {
    $sql = "UPDATE employee_reports SET $fieldName = :data WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      ':id' => $reportId,
      ':data' => json_encode($updatedFiles, JSON_UNESCAPED_SLASHES)
    ]);
  }

  echo json_encode(['success' => true, 'message' => 'File deleted successfully']);
}
