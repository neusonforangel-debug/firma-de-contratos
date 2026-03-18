<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/contract/template.php';

// TCPDF (instalación MANUAL)
require_once __DIR__ . '/../app/lib/tcpdf/tcpdf.php';

rate_limit('submit_signature');
csrf_check();

function flatten_png_to_jpg_white(string $pngBinary, int $quality = 92): string {
    $im = imagecreatefromstring($pngBinary);
    if (!$im) throw new Exception("No se pudo leer la firma PNG.");

    $w = imagesx($im);
    $h = imagesy($im);

    $bg = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($bg, 255, 255, 255);
    imagefilledrectangle($bg, 0, 0, $w, $h, $white);

    imagecopy($bg, $im, 0, 0, 0, 0, $w, $h);

    ob_start();
    imagejpeg($bg, null, $quality);
    $jpg = ob_get_clean();

    imagedestroy($im);
    imagedestroy($bg);

    if (!$jpg) throw new Exception("No se pudo convertir la firma a JPG.");
    return $jpg;
}

// ---------- Inputs ----------
$token = (string)($_POST['token'] ?? '');
$token = strtolower(trim($token));
if (!preg_match('/^[a-f0-9]{64}$/', $token)) { http_response_code(400); exit('Token inválido'); }

$signerName  = trim($_POST['signer_name'] ?? '');
$signerId    = trim($_POST['signer_id'] ?? '');
$signerRole  = trim($_POST['signer_role'] ?? '');
$signerEmail = trim($_POST['signer_email'] ?? '');
$code        = trim($_POST['code'] ?? '');
$sigDataUrl  = (string)($_POST['signature_data'] ?? '');

if ($signerName==='' || $signerId==='' || $signerRole==='' || $signerEmail==='' || !preg_match('/^[0-9]{6}$/', $code)) {
    http_response_code(422); exit('Datos incompletos');
}

if (!filter_var($signerEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422); exit('Email inválido');
}

if (!preg_match('#^data:image/png;base64,#', $sigDataUrl)) {
    http_response_code(422); exit('Firma inválida');
}

$pdo = db();
$pdo->beginTransaction();

try {
    // Lock contract
    $stmt = $pdo->prepare("SELECT c.*, cl.company_name, cl.nit, cl.legal_rep, cl.email AS client_email, cl.phone, cl.address
                           FROM contracts c JOIN clients cl ON cl.id=c.client_id
                           WHERE (c.token_hash=? OR c.token=?) LIMIT 1 FOR UPDATE");
    $stmt->execute([token_hash($token), $token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new Exception('Contrato no encontrado');

    if (!empty($row['cancelled_at']) || ($row['status'] ?? '') === 'CANCELLED') throw new Exception('Contrato cancelado.');
    if (!empty($row['expires_at']) && strtotime($row['expires_at']) < time() && ($row['status'] ?? '') !== 'SIGNED') throw new Exception('El link de firma expiró.');

    if (($row['status'] ?? '') === 'SIGNED') {
        if ($pdo->inTransaction()) $pdo->commit();
        header('Location: ' . base_url('public/sign.php?token='.$token));
        exit;
    }

    if (strtolower(trim($row['client_email'])) !== strtolower(trim($signerEmail))) {
        throw new Exception('El email no coincide con el registrado del cliente.');
    }

    // Validate latest code
    $stmt = $pdo->prepare("SELECT id, code_hash, expires_at, consumed_at
                           FROM sign_codes
                           WHERE contract_id=?
                           ORDER BY id DESC
                           LIMIT 1 FOR UPDATE");
    $stmt->execute([(int)$row['id']]);
    $sc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sc) throw new Exception('Código no encontrado.');
    if (!empty($sc['consumed_at'])) throw new Exception('El código ya fue usado.');
    if (strtotime($sc['expires_at']) < time()) throw new Exception('El código expiró.');

    // ✅ MISMO HASH QUE EL QUE GENERA admin/contract_action.php (hash_hmac)
    $codeHash = hash_hmac('sha256', $code, APP_KEY);
    if (!hash_equals($sc['code_hash'], $codeHash)) throw new Exception('Código inválido.');

    // Decode signature PNG
    $png = base64_decode(substr($sigDataUrl, strlen('data:image/png;base64,')));
    if ($png === false || strlen($png) < 200) throw new Exception('Firma corrupta.');

    // Flatten signature to JPG
    $jpgSignature = flatten_png_to_jpg_white($png, 92);

    // Contract text (UTF-8 real)
    $contract = [
      'id'              => $row['id'],
      'token'           => $token, // ✅ siempre usar el token recibido
      'status'          => $row['status'],
      'contract_version'=> $row['contract_version'],
      'price_cop'       => $row['price_cop'],
      'term_months'     => $row['term_months'],
      'created_at'      => $row['created_at'],
    ];

    $client = [
      'company_name' => $row['company_name'],
      'nit'          => $row['nit'],
      'legal_rep'    => $row['legal_rep'],
      'email'        => $row['client_email'],
      'phone'        => $row['phone'],
      'address'      => $row['address'],
    ];

    $text = contract_text($client, $contract);

    $signedAt = now_utc();
    $evidence = [
      'token_hash'=>token_hash($token),
      'contract_version'=>$row['contract_version'],
      'price_cop'=>(int)$row['price_cop'],
      'term_months'=>(int)$row['term_months'],
      'client'=>['company_name'=>$client['company_name'],'nit'=>$client['nit'],'email'=>$client['email']],
      'signer'=>['name'=>$signerName,'id'=>$signerId,'role'=>$signerRole,'email'=>$signerEmail],
      'signed_at_utc'=>$signedAt,
      'ip'=>client_ip(),
      'ua'=>user_agent(),
      'contract_text_sha256'=>hash('sha256', $text),
      'signature_png_sha256'=>hash('sha256', $png),
    ];
    $evidenceHash = hash_evidence($evidence);

    ensure_storage_protected();

    // ---------- Generate PDF ----------
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();

    $pdf->SetFont('dejavusans', '', 10);

    $logoJpgPath = __DIR__ . '/../public/logo.jpg';
    $logoPngPath = __DIR__ . '/../public/logo.png';
    $logoPath = file_exists($logoJpgPath) ? $logoJpgPath : (file_exists($logoPngPath) ? $logoPngPath : null);

    if ($logoPath) {
        $logoW = 60;
        $logoH = 18;
        $x = (210 - $logoW) / 2;
        $pdf->Image($logoPath, $x, 12, $logoW, $logoH, '', '', '', true, 300);
        $pdf->Ln(24);
    } else {
        $pdf->Ln(6);
    }

    $pdf->SetFont('dejavusans', 'B', 13);
    $pdf->Cell(0, 7, "REDWM - Contrato Plan Anual (Firma Digital)", 0, 1, 'L');

    $pdf->SetFont('dejavusans', '', 9);
    $pdf->Cell(0, 5, "Versión: {$row['contract_version']} | Evidencia: " . substr($evidenceHash, 0, 18) . "...", 0, 1, 'L');
    $pdf->Ln(3);

    $pdf->SetFont('dejavusans', '', 10);
    $pdf->MultiCell(0, 5, $text, 0, 'L', false, 1);
    $pdf->Ln(6);

    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->Cell(0, 6, "Datos de firma", 0, 1, 'L');
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->MultiCell(0, 5, "Firmado por: {$signerName} | ID: {$signerId} | Cargo: {$signerRole}", 0, 'L', false, 1);
    $pdf->MultiCell(0, 5, "Email: {$signerEmail} | Fecha/Hora (UTC): {$signedAt} | IP: " . client_ip(), 0, 'L', false, 1);
    $pdf->Ln(2);

    $tmpSig = sys_get_temp_dir() . '/sig_' . $token . '.jpg';
    file_put_contents($tmpSig, $jpgSignature);

    $pdf->SetFont('dejavusans', '', 9);
    $pdf->Cell(0, 5, "Firma:", 0, 1, 'L');
    $y = $pdf->GetY();
    $pdf->Image($tmpSig, 15, $y, 70, 20, 'JPG', '', '', true, 300);
    $pdf->Ln(22);

    $pdfBin = $pdf->Output('', 'S');
    @unlink($tmpSig);

    // ---------- Save files (storage) ----------
    $tsSafe = gmdate('Ymd_His');
    $pdfRel = STORAGE_PDF_DIR . '/contract_' . (int)$row['id'] . '_' . $tsSafe . '.pdf';
    $sigRel = STORAGE_SIG_DIR . '/sig_' . (int)$row['id'] . '_' . $tsSafe . '.png';

    $pdfAbs = storage_path(STORAGE_PDF_DIR, basename($pdfRel));
    $sigAbs = storage_path(STORAGE_SIG_DIR, basename($sigRel));

    if (@file_put_contents($pdfAbs, $pdfBin) === false) throw new Exception('No se pudo guardar el PDF.');
    if (@file_put_contents($sigAbs, $png) === false) throw new Exception('No se pudo guardar la firma.');

    // ---------- Save in DB ----------
    $stmt = $pdo->prepare("UPDATE contracts
                           SET status='SIGNED',
                               signed_at=?,
                               signer_name=?,
                               signer_id=?,
                               signer_role=?,
                               signer_email=?,
                               ip_address=?,
                               user_agent=?,
                               evidence_hash=?,
                               signature_png_path=?,
                               contract_pdf_path=?,
                               signature_png=NULL,
                               contract_pdf=NULL
                           WHERE id=?");
    $stmt->execute([
      $signedAt,
      $signerName,
      $signerId,
      $signerRole,
      $signerEmail,
      client_ip(),
      substr(user_agent(), 0, 255),
      $evidenceHash,
      $sigRel,
      $pdfRel,
      (int)$row['id']
    ]);

    // Consume code
    $stmt = $pdo->prepare("UPDATE sign_codes SET consumed_at=? WHERE id=?");
    $stmt->execute([$signedAt, (int)$sc['id']]);

    // Audit (firma pública -> admin_user_id NULL)
    audit_log($pdo, 'CONTRACT_SIGNED', ['evidence_hash'=>$evidenceHash,'pdf'=>$pdfRel,'sig'=>$sigRel], (int)$row['id'], null);

    $pdo->commit();

    // Email confirmación
    $dl = base_url('public/download.php?token=' . $token);
    $vf = base_url('public/verify.php?token=' . $token);
    $html = "<p>✅ Contrato firmado correctamente.</p>"
          . "<p>Descargar PDF: <a href='{$dl}'>{$dl}</a></p>"
          . "<p>Verificar evidencia: <a href='{$vf}'>{$vf}</a></p>";
    try { send_mail($signerEmail, "Contrato firmado - REDWM", $html); } catch (Throwable $ignore) {}

    header('Location: ' . base_url('public/sign.php?token=' . $token));
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (defined('APP_ENV') && APP_ENV === 'development') exit('Error: ' . $e->getMessage());
    http_response_code(400);
    exit('No se pudo firmar. Verifica el código y vuelve a intentar.');
}
