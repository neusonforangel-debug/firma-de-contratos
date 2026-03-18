<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';
require_admin();

$pdo = db();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit('ID inválido'); }

$stmt = $pdo->prepare("SELECT status, contract_pdf_path, contract_pdf FROM contracts WHERE id=? LIMIT 1");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) { http_response_code(404); exit('No encontrado'); }
if ($row['status'] !== 'SIGNED') { http_response_code(403); exit('Aún no está firmado'); }

$filename = 'contrato_redwm_' . $id . '.pdf';

if (!empty($row['contract_pdf_path'])) {
  $abs = rtrim(STORAGE_DIR, '/') . '/' . ltrim($row['contract_pdf_path'], '/');
  if (!is_file($abs)) { http_response_code(500); exit('Archivo no disponible'); }
  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  header('Content-Length: ' . filesize($abs));
  readfile($abs);
  exit;
}

if (empty($row['contract_pdf'])) { http_response_code(500); exit('Archivo no disponible'); }
$pdf = $row['contract_pdf'];
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
