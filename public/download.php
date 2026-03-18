<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

rate_limit('download_pdf');
$token = $_GET['token'] ?? '';
if (!preg_match('/^[a-f0-9]{64}$/', $token)) { http_response_code(400); exit('Token inválido'); }

$pdo = db();
$stmt = $pdo->prepare("SELECT status, contract_pdf_path, contract_pdf FROM contracts WHERE (token_hash=? OR token=?) LIMIT 1");
$stmt->execute([token_hash($token), $token]);
$row = $stmt->fetch();
if (!$row) { http_response_code(404); exit('No encontrado'); }
if ($row['status'] !== 'SIGNED') { http_response_code(403); exit('Aún no está firmado'); }

$filename = 'contrato_redwm_' . $token . '.pdf';

// Preferir archivo en storage
if (!empty($row['contract_pdf_path'])) {
    $abs = rtrim(STORAGE_DIR, '/') . '/' . ltrim($row['contract_pdf_path'], '/');
    if (!is_file($abs)) { http_response_code(500); exit('Archivo no disponible'); }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($abs));
    readfile($abs);
    exit;
}

// Fallback legacy (BLOB)
if (empty($row['contract_pdf'])) { http_response_code(500); exit('Archivo no disponible'); }
$pdf = $row['contract_pdf'];
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;

?>
