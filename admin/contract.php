<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

require_admin();
require_action('contracts.view');

$pdo = db();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit('ID inválido'); }

$stmt = $pdo->prepare("SELECT c.*, cl.company_name, cl.nit, cl.legal_rep, cl.email AS client_email, cl.phone, cl.address
                       FROM contracts c JOIN clients cl ON cl.id=c.client_id
                       WHERE c.id=? LIMIT 1");
$stmt->execute([$id]);
$c = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$c) { http_response_code(404); exit('No encontrado'); }

// último código
$q = $pdo->prepare("SELECT id, expires_at, consumed_at, created_at FROM sign_codes WHERE contract_id=? ORDER BY id DESC LIMIT 1");
$q->execute([$id]);
$sc = $q->fetch(PDO::FETCH_ASSOC);

// auditoría (últimos 60) SOLO si el rol puede verla
$logs = [];
if (can('audit.view')) {
  try {
    $q = $pdo->prepare("SELECT event, created_at, ip_address, user_agent, meta
                        FROM audit_logs WHERE contract_id=? ORDER BY id DESC LIMIT 60");
    $q->execute([$id]);
    $logs = $q->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    $logs = [];
  }
}

function badge(string $s): string {
  $map = ['PENDING'=>'#f5b942','SIGNED'=>'#3ddc97','CANCELLED'=>'#ff6b6b'];
  $c = $map[$s] ?? '#9db7ff';
  return '<span style="display:inline-block;padding:4px 10px;border-radius:999px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12)"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:'.$c.';margin-right:8px"></span>'.e($s).'</span>';
}

// permisos por acción
$canResend   = can('contracts.resend');
$canExtend   = can('contracts.extend');
$canDownload = can('contracts.download');
$canCancel   = (admin_role() === 'SUPERADMIN'); // cancelar solo superadmin
$canWriteAny = ($canResend || $canExtend || (admin_role()==='SUPERADMIN') || (admin_role()==='VENTAS')); // para mostrar bloque acciones

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin | Contrato #<?= e((string)$id) ?></title>
<style>
  body{font-family:system-ui;background:linear-gradient(180deg,#faf9f7 0%,#f3f1ed 100%);color:#171717;margin:0}
  .wrap{max-width:1120px;margin:0 auto;padding:18px}
  .card{background:#fff;border:1px solid #e5e7eb;border-radius:22px;padding:24px;box-shadow:0 18px 55px rgba(0,0,0,.06);}
  a{color:#111;font-weight:600}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .muted{color:#6b7280;font-size:13px;line-height:1.6}
  input,button,select{padding:11px 13px;border-radius:14px;border:1px solid #e5e7eb;background:#fff;color:#111}
  button{cursor:pointer}
  .btn{background:#111;border:none;font-weight:800;color:#fff;box-shadow:0 10px 24px rgba(0,0,0,.12)}
  .btnDanger{background:#991b1b;border:none;font-weight:800;color:#fff}
  table{width:100%;border-collapse:collapse;margin-top:10px;font-size:13px}
  th,td{padding:12px;border-bottom:1px solid #e5e7eb;vertical-align:top;text-align:left}
  code{display:block;background:#fff;padding:12px;border-radius:14px;overflow:auto;border:1px solid #e5e7eb;color:#111}
  .pill{display:inline-block;padding:5px 10px;border-radius:999px;background:#f3f4f6;border:1px solid #e5e7eb;font-size:12px;color:#111}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div style="display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap">
      <div>
        <h2 style="margin:0 0 6px 0">Contrato #<?= e((string)$id) ?> <?= badge($c['status']) ?></h2>
        <div class="muted">
          <?= e($c['company_name']) ?> · NIT <?= e($c['nit']) ?> · Email <?= e($c['client_email']) ?>
          · <span class="pill"><?= e(admin_role()) ?></span>
          · <a href="<?= e(base_url('admin/panel.php')) ?>">Volver</a>
          · <a href="<?= e(base_url('admin/logout.php')) ?>">Salir</a>
        </div>
      </div>

      <div style="text-align:right">
        <?php if ($c['status']==='SIGNED' && $canDownload): ?>
          <a class="btn" style="display:inline-block;text-decoration:none;padding:11px 15px;border-radius:14px;color:#fff;background:#111" href="<?= e(base_url('admin/contract_download.php?id='.$id)) ?>">Descargar PDF</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="grid" style="margin-top:12px">
      <div class="card" style="background:#fbfbfb;border:1px solid #e5e7eb;box-shadow:none">
        <h3 style="margin:0 0 8px 0">Datos</h3>
        <div class="muted">Creado: <b><?= e($c['created_at']) ?></b></div>
        <div class="muted">Expira: <b><?= e($c['expires_at'] ?? '-') ?></b></div>
        <div class="muted">Firmado: <b><?= e($c['signed_at'] ?? '-') ?></b></div>
        <div class="muted">Cancelado: <b><?= e($c['cancelled_at'] ?? '-') ?></b></div>

        <div style="margin-top:10px" class="muted">Evidencia hash:</div>
        <code><?= e($c['evidence_hash'] ?? '-') ?></code>
      </div>

      <div class="card" style="background:#fbfbfb;border:1px solid #e5e7eb;box-shadow:none">
        <h3 style="margin:0 0 8px 0">Último código</h3>
        <?php if ($sc): ?>
          <div class="muted">Generado: <b><?= e($sc['created_at']) ?></b></div>
          <div class="muted">Expira: <b><?= e($sc['expires_at']) ?></b></div>
          <div class="muted">Consumido: <b><?= e($sc['consumed_at'] ?? '-') ?></b></div>
        <?php else: ?>
          <div class="muted">No hay códigos aún.</div>
        <?php endif; ?>

        <?php if ($c['status']==='PENDING' && ($canResend || $canExtend || $canCancel || admin_role()==='VENTAS')): ?>
          <h3 style="margin:14px 0 8px 0">Acciones</h3>

          <form method="post" action="<?= e(base_url('admin/contract_action.php')) ?>" style="display:grid;gap:10px">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= e((string)$id) ?>">

            <?php if ($canResend): ?>
              <button class="btn" name="action" value="resend_code" type="submit">Reenviar código</button>
            <?php endif; ?>

            <?php if ($canExtend): ?>
              <div style="display:flex;gap:10px;align-items:center">
                <input name="extend_hours" type="number" min="1" max="720" value="48" style="flex:1" placeholder="Horas">
                <button name="action" value="extend" type="submit">Extender expiración</button>
              </div>
            <?php endif; ?>

            <?php if (admin_role()==='SUPERADMIN' || admin_role()==='VENTAS' || admin_role()==='SOPORTE'): ?>
              <button name="action" value="regen_token" type="submit">Regenerar link (nuevo token)</button>
            <?php endif; ?>

            <?php if ($canCancel): ?>
              <button class="btnDanger" name="action" value="cancel" type="submit" onclick="return confirm('¿Cancelar este contrato?')">Cancelar contrato</button>
            <?php endif; ?>
          </form>
        <?php else: ?>
          <div class="muted" style="margin-top:10px">No hay acciones disponibles para tu rol o para el estado actual.</div>
        <?php endif; ?>
      </div>
    </div>

    <?php if (can('audit.view')): ?>
      <div class="card" style="background:#0f1730;margin-top:12px">
        <h3 style="margin:0 0 8px 0">Auditoría (últimos 60)</h3>
        <?php if (!$logs): ?>
          <div class="muted">Aún no hay logs (o falta la migración de audit_logs.contract_id).</div>
        <?php else: ?>
          <table>
            <thead><tr><th>Fecha</th><th>Evento</th><th>IP</th><th>Meta</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $l): ?>
              <tr>
                <td><?= e($l['created_at']) ?></td>
                <td><b><?= e($l['event']) ?></b><br><span class="muted"><?= e($l['user_agent'] ?? '-') ?></span></td>
                <td><?= e($l['ip_address'] ?? '-') ?></td>
                <td><code><?= e($l['meta'] ?? '') ?></code></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </div>
</div>
</body>
</html>

