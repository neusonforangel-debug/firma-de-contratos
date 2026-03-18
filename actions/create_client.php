<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

rate_limit('create_client');
csrf_check();

$company = trim($_POST['company_name'] ?? '');
$nit     = trim($_POST['nit'] ?? '');
$rep     = trim($_POST['legal_rep'] ?? '');
$email   = trim($_POST['email'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');

if ($company==='' || $nit==='' || $rep==='' || $email==='') {
    http_response_code(422);
    exit('Faltan campos obligatorios');
}

$pdo = db();
$pdo->beginTransaction();

try {
    // create/get client
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE nit=? AND email=? LIMIT 1");
    $stmt->execute([$nit, $email]);
    $clientId = $stmt->fetchColumn();

    if (!$clientId) {
        $stmt = $pdo->prepare("INSERT INTO clients(company_name,nit,legal_rep,email,phone,address,created_at) VALUES(?,?,?,?,?,?,?)");
        $stmt->execute([$company,$nit,$rep,$email,$phone,$address,now_utc()]);
        $clientId = (int)$pdo->lastInsertId();
    }

    // contract
    $token = bin2hex(random_bytes(32));
    $tokenHash = token_hash($token);
    $expiresAt = gmdate('Y-m-d H:i:s', time() + SIGN_LINK_TTL_HOURS*3600);
    $stmt = $pdo->prepare("INSERT INTO contracts(client_id,token,token_hash,status,contract_version,price_cop,term_months,created_at,expires_at) VALUES(?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$clientId,$token,$tokenHash,'PENDING',CONTRACT_VERSION,CONTRACT_PRICE_COP,CONTRACT_TERM_MONTHS,now_utc(),$expiresAt]);
    $contractId = (int)$pdo->lastInsertId();

    // code
    $code = (string)random_int(100000, 999999);
    $codeHash = hash('sha256', $code . APP_KEY);
    $expires = gmdate('Y-m-d H:i:s', time() + SIGN_CODE_TTL_MIN*60);
    $stmt = $pdo->prepare("INSERT INTO sign_codes(contract_id,code_hash,expires_at,created_at) VALUES(?,?,?,?)");
    $stmt->execute([$contractId, $codeHash, $expires, now_utc()]);

    // audit
    audit_log($pdo, 'CONTRACT_CREATED', ['client_id'=>$clientId], $contractId, (int)($_SESSION['admin_id'] ?? 0));

    $pdo->commit();

    // email
    $link = base_url('public/sign.php?token=' . $token);
    $html = "<p>Hola <b>" . e($rep) . "</b>,</p>"
          . "<p>Tu contrato está listo para firmar. Usa este código:</p>"
          . "<h2 style='letter-spacing:2px'>" . e($code) . "</h2>"
          . "<p>Entra aquí para firmar: <a href='{$link}'>{$link}</a></p>"
          . "<p>El código vence en " . SIGN_CODE_TTL_MIN . " minutos.</p>";
    send_mail($email, "Código de firma - REDWM", $html);

    header('Location: ' . $link);
    exit;

} catch (Throwable $e) {
    $pdo->rollBack();
    if (APP_ENV === 'development') {
        exit('Error: ' . $e->getMessage());
    }
    http_response_code(500);
    exit('Error al crear el contrato.');
}
?>