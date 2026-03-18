<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';
require_admin();
rate_limit('resend_code');

$token = $_GET['token'] ?? '';
if (!preg_match('/^[a-f0-9]{64}$/', $token)) { http_response_code(400); exit('Token inválido'); }

$pdo = db();
$pdo->beginTransaction();
try {
  $stmt = $pdo->prepare("SELECT c.id,c.status,c.token,cl.email,cl.legal_rep FROM contracts c JOIN clients cl ON cl.id=c.client_id WHERE c.token=? LIMIT 1 FOR UPDATE");
  $stmt->execute([$token]);
  $r = $stmt->fetch();
  if (!$r) throw new Exception('No encontrado');
  if ($r['status'] !== 'PENDING') throw new Exception('Ya está firmado o cancelado');

  $code = (string)random_int(100000,999999);
  $codeHash = hash('sha256', $code . APP_KEY);
  $expires = gmdate('Y-m-d H:i:s', time() + SIGN_CODE_TTL_MIN*60);

  $stmt = $pdo->prepare("INSERT INTO sign_codes(contract_id,code_hash,expires_at,created_at) VALUES(?,?,?,?)");
  $stmt->execute([(int)$r['id'], $codeHash, $expires, now_utc()]);

  $stmt = $pdo->prepare("INSERT INTO audit_logs(event,meta,ip_address,user_agent,created_at) VALUES(?,?,?,?,?)");
  $meta = json_encode(['contract_id'=>(int)$r['id'],'action'=>'RESEND_CODE'], JSON_UNESCAPED_UNICODE);
  $stmt->execute(['CODE_RESENT',$meta,client_ip(),substr(user_agent(),0,255),now_utc()]);

  $pdo->commit();

  $link = base_url('public/sign.php?token=' . $token);
  $html = "<p>Hola <b>" . e($r['legal_rep']) . "</b>,</p>"
        . "<p>Este es tu nuevo código de firma:</p>"
        . "<h2 style='letter-spacing:2px'>" . e($code) . "</h2>"
        . "<p>Firma aquí: <a href='{$link}'>{$link}</a></p>"
        . "<p>Vence en " . SIGN_CODE_TTL_MIN . " minutos.</p>";
  send_mail($r['email'], "Nuevo código de firma - REDWM", $html);

  header('Location: ' . base_url('admin/panel.php'));
  exit;

} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(400);
  exit('No se pudo reenviar.');
}
?>