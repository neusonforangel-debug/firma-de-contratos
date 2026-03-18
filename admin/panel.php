<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';


require_admin();                 // valida sesi¨®n + activo
require_action('contracts.view'); // m¨ªnimo: ver contratos

$pdo = db();
$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');

$sql = "SELECT c.id,c.status,c.created_at,c.expires_at,c.signed_at,c.price_cop,c.term_months,
               cl.company_name,cl.nit,cl.email,cl.legal_rep
        FROM contracts c
        JOIN clients cl ON cl.id=c.client_id ";

$where = [];
$params = [];

if ($status !== '' && in_array($status, ['PENDING','SIGNED','CANCELLED'], true)) {
  $where[] = "c.status=?";
  $params[] = $status;
}

if ($q !== '') {
  $like = '%' . $q . '%';
  $where[] = "(cl.company_name LIKE ? OR cl.nit LIKE ? OR cl.email LIKE ?)";
  $params = array_merge($params, [$like,$like,$like]);
}

if ($where) {
  $sql .= "WHERE " . implode(' AND ', $where) . " ";
}

$sql .= "ORDER BY c.id DESC LIMIT 80";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$role = $_SESSION['admin_role'] ?? 'AUDITOR';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin | Panel</title>
<style>
  body{font-family:system-ui;background:linear-gradient(180deg,#faf9f7 0%,#f3f1ed 100%);color:#171717;margin:0}
  .wrap{max-width:1120px;margin:0 auto;padding:18px}
  .card{background:#fff;border:1px solid #e5e7eb;border-radius:22px;padding:24px;box-shadow:0 18px 55px rgba(0,0,0,.06);}
  table{width:100%;border-collapse:collapse;margin-top:10px;font-size:13px}
  th,td{padding:12px;border-bottom:1px solid #e5e7eb;vertical-align:top;text-align:left}
  a{color:#111;font-weight:600}
  input,button,select{padding:11px 13px;border-radius:14px;border:1px solid #e5e7eb;background:#fff;color:#111}
  .row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .row form{flex:1}
  .pill{display:inline-block;padding:5px 10px;border-radius:999px;background:#f3f4f6;border:1px solid #e5e7eb;font-size:12px;color:#111}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="row">
      <div style="flex:1; min-width:260px">
        <h2 style="margin:0">Contratos</h2>
        <div style="opacity:.75">
          Hola, <?= e($_SESSION['admin_name'] ?? 'Admin') ?>
          <span class="pill"><?= e($role) ?></span>
          ¡¤ <a href="<?= e(base_url('admin/logout.php')) ?>">Salir</a>

          <?php if ($role === 'SUPERADMIN'): ?>
            ¡¤ <a href="<?= e(base_url('admin/users.php')) ?>">Usuarios / Roles</a>
          <?php endif; ?>
        </div>
      </div>

      <form method="get" action="" class="row" style="justify-content:flex-end; margin:0">
        <input name="q" placeholder="Buscar: empresa, nit, email" value="<?= e($q) ?>">
        <select name="status">
          <option value="">Todos</option>
          <option value="PENDING" <?= $status==='PENDING'?'selected':'' ?>>PENDING</option>
          <option value="SIGNED" <?= $status==='SIGNED'?'selected':'' ?>>SIGNED</option>
          <option value="CANCELLED" <?= $status==='CANCELLED'?'selected':'' ?>>CANCELLED</option>
        </select>
        <button>Buscar</button>
      </form>
    </div>

    <table>
      <thead>
        <tr>
          <th>ID</th><th>Cliente</th><th>Estado</th><th>Creado</th><th>Firmado</th><th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= e($r['id']) ?></td>
            <td>
              <b><?= e($r['company_name']) ?></b><br>
              NIT: <?= e($r['nit']) ?><br>
              Email: <?= e($r['email']) ?><br>
              Rep: <?= e($r['legal_rep']) ?>
            </td>
            <td>
              <b><?= e($r['status']) ?></b><br>
              Expira: <?= e($r['expires_at'] ?? '-') ?><br>
              COP <?= number_format((int)$r['price_cop'],0,',','.') ?> / mes<br>
              <?= e($r['term_months']) ?> meses
            </td>
            <td><?= e($r['created_at']) ?></td>
            <td><?= e($r['signed_at'] ?? '-') ?></td>
            <td>
              <a href="<?= e(base_url('admin/contract.php?id='.$r['id'])) ?>">Ver</a><br>

              <?php if ($r['status']==='SIGNED'): ?>
                <?php if (can('contracts.download')): ?>
                  <a href="<?= e(base_url('admin/contract_download.php?id='.$r['id'])) ?>">Descargar/Verificar</a>
                <?php endif; ?>
              <?php else: ?>
                <?php if (can('contracts.resend') || can('contracts.extend')): ?>
                  <a href="<?= e(base_url('admin/contract.php?id='.$r['id'])) ?>">Acciones</a>
                <?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (!$rows): ?>
          <tr><td colspan="6" style="opacity:.7">No hay resultados.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

  </div>
</div>
</body>
</html>
