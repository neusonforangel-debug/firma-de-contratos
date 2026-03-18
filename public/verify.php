<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

rate_limit('verify');

$token = $_GET['token'] ?? '';
if (!preg_match('/^[a-f0-9]{64}$/', $token)) { http_response_code(400); exit('Token inválido'); }

$pdo = db();
$stmt = $pdo->prepare("SELECT c.status,c.signed_at,c.signer_name,c.signer_id,c.signer_role,c.signer_email,c.ip_address,c.user_agent,c.evidence_hash,
                              cl.company_name,cl.nit,cl.email AS client_email
                       FROM contracts c JOIN clients cl ON cl.id=c.client_id
                       WHERE (c.token_hash=? OR c.token=?) LIMIT 1");
$stmt->execute([token_hash($token), $token]);
$row = $stmt->fetch();
if (!$row) { http_response_code(404); exit('No encontrado'); }

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Verificación | REDWM</title>
<style>
  :root{--bg:#f6f4f1;--card:#fff;--text:#171717;--muted:#6b7280;--line:#e5e7eb}
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,'Helvetica Neue',Arial;background:linear-gradient(180deg,#faf9f7 0%,#f3f1ed 100%);color:var(--text);margin:0}
  .wrap{max-width:940px;margin:0 auto;padding:24px}
  .card{background:#fff;border:1px solid var(--line);border-radius:22px;padding:24px;box-shadow:0 18px 55px rgba(0,0,0,.06)}
  code{display:block;background:#fff;padding:14px;border-radius:14px;overflow:auto;border:1px solid var(--line);color:#111}
  .muted{color:var(--muted);line-height:1.6}
  a{color:#111;font-weight:600}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h2 style="margin:0 0 6px 0">Verificación de Contrato</h2>
    <div class="muted">Estado: <b><?= e($row['status']) ?></b></div>

    <h3>Cliente</h3>
    <div class="muted"><?= e($row['company_name']) ?> · NIT <?= e($row['nit']) ?> · Email <?= e($row['client_email']) ?></div>

    <h3>Firma</h3>
    <div class="muted">Firmado por: <b><?= e($row['signer_name'] ?? '-') ?></b> (<?= e($row['signer_role'] ?? '-') ?>)</div>
    <div class="muted">ID: <?= e($row['signer_id'] ?? '-') ?> · Email: <?= e($row['signer_email'] ?? '-') ?></div>
    <div class="muted">Fecha/Hora (UTC): <?= e($row['signed_at'] ?? '-') ?></div>
    <div class="muted">IP: <?= e($row['ip_address'] ?? '-') ?></div>

    <h3>Hash de evidencia</h3>
    <code><?= e($row['evidence_hash'] ?? '-') ?></code>

    <div class="muted" style="margin-top:12px">
      Este hash sirve para demostrar integridad (si cambian los datos o el texto del contrato, el hash ya no coincide con la evidencia registrada).
    </div>

    <div style="margin-top:14px">
      <a style="color:#111;font-weight:600" href="<?= e(base_url('public/download.php?token='.$token)) ?>">Descargar PDF</a>
    </div>
  </div>
</div>
</body>
</html>
