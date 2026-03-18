<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

rate_limit('resend_code_public');
csrf_check();

$token = strtolower(trim((string)($_POST['token'] ?? '')));
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
  http_response_code(400);
  exit('Token inválido');
}

$pdo = db();

try {
  $pdo->beginTransaction();

  // Traemos todo lo necesario en una sola consulta (y bloqueamos fila)
  $stmt = $pdo->prepare("
    SELECT c.id, c.status, c.expires_at, c.cancelled_at,
           cl.email, cl.legal_rep
    FROM contracts c
    JOIN clients cl ON cl.id=c.client_id
    WHERE (c.token_hash=? OR c.token=?)
    LIMIT 1
    FOR UPDATE
  ");
  $stmt->execute([token_hash($token), $token]);
  $r = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$r) throw new Exception('Contrato no encontrado');
  if (!empty($r['cancelled_at']) || $r['status'] === 'CANCELLED') throw new Exception('Contrato cancelado');
  if ($r['status'] !== 'PENDING') throw new Exception('El contrato ya fue firmado o no permite reenvío');

  if (!empty($r['expires_at']) && strtotime($r['expires_at']) < time()) {
    throw new Exception('El link de firma expiró');
  }

  // Nuevo código
  $code = (string)random_int(100000, 999999);

  // ✅ IMPORTANTE: mismo hash que en submit_signature.php y contract_action.php
  $codeHash = hash_hmac('sha256', $code, APP_KEY);

  $expires = gmdate('Y-m-d H:i:s', time() + (int)SIGN_CODE_TTL_MIN * 60);

  $ins = $pdo->prepare("INSERT INTO sign_codes(contract_id,code_hash,expires_at,created_at) VALUES(?,?,?,?)");
  $ins->execute([(int)$r['id'], $codeHash, $expires, now_utc()]);

  // Auditoría (pública)
  audit_log($pdo, 'CODE_RESENT_PUBLIC', ['expires_at'=>$expires], (int)$r['id'], null);

  $pdo->commit();

  // Enviar email (fuera de transacción)
  $link = base_url('public/sign.php?token=' . $token);

  $html = "<p>Hola <b>" . e($r['legal_rep']) . "</b>,</p>"
        . "<p>Este es tu nuevo código de firma:</p>"
        . "<h2 style='letter-spacing:2px'>" . e($code) . "</h2>"
        . "<p>Firma aquí: <a href='{$link}'>{$link}</a></p>"
        . "<p>Vence en " . (int)SIGN_CODE_TTL_MIN . " minutos.</p>";

  $sent = send_mail($r['email'], "Nuevo código de firma - REDWM", $html);

  start_session();
  $_SESSION['flash'] = $sent
    ? "✅ Código reenviado a " . $r['email']
    : "⚠️ No se pudo reenviar (revisa correo/servidor).";

  header('Location: ' . base_url('public/sign.php?token=' . $token));
  exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();

  start_session();
  $_SESSION['flash'] = "⚠️ No se pudo reenviar: " . $e->getMessage();

  header('Location: ' . base_url('public/sign.php?token=' . $token));
  exit;
}
