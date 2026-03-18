<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';


require_role(['SUPERADMIN']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { die('ID inválido'); }

$stmt = db()->prepare("SELECT id,email,name,role,active,created_at FROM admin_users WHERE id=? LIMIT 1");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { die('Usuario no existe'); }

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $action = $_POST['action'] ?? '';

  if ($action === 'save') {
    $name = trim($_POST['name'] ?? '');
    $role = $_POST['role'] ?? 'AUDITOR';
    $active = isset($_POST['active']) ? 1 : 0;

    if ($name === '') {
      $err = 'Nombre requerido.';
    } elseif (!in_array($role, ['SUPERADMIN','SOPORTE','VENTAS','AUDITOR'], true)) {
      $err = 'Rol inválido.';
    } else {
      db()->prepare("UPDATE admin_users SET name=?, role=?, active=? WHERE id=?")->execute([$name, $role, $active, $id]);

      db()->prepare("INSERT INTO audit_logs (admin_user_id,event,meta,ip_address,user_agent,created_at)
                     VALUES (?, 'admin.user_updated', JSON_OBJECT('target_id', ?, 'role', ?, 'active', ?), ?, ?, NOW())")
         ->execute([$_SESSION['admin_id'], $id, $role, $active, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);

      header('Location: ' . base_url('admin/user_edit.php?id='.$id.'&ok=1'));
      exit;
    }
  }

  if ($action === 'reset_password') {
    $pass = (string)($_POST['password'] ?? '');
    if (strlen($pass) < 6) {
      $err = 'Contraseña muy corta (mínimo 6).';
    } else {
      $hash = password_hash($pass, PASSWORD_BCRYPT);
      db()->prepare("UPDATE admin_users SET password_hash=? WHERE id=?")->execute([$hash, $id]);

      db()->prepare("INSERT INTO audit_logs (admin_user_id,event,meta,ip_address,user_agent,created_at)
                     VALUES (?, 'admin.user_password_reset', JSON_OBJECT('target_id', ?), ?, ?, NOW())")
         ->execute([$_SESSION['admin_id'], $id, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);

      header('Location: ' . base_url('admin/user_edit.php?id='.$id.'&ok=1'));
      exit;
    }
  }
}

$stmt = db()->prepare("SELECT id,email,name,role,active,created_at FROM admin_users WHERE id=? LIMIT 1");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin | Editar Usuario</title>
<style>
  body{font-family:system-ui;background:linear-gradient(180deg,#faf9f7 0%,#f3f1ed 100%);color:#171717;margin:0}
  .wrap{max-width:820px;margin:0 auto;padding:18px}
  .card{background:#fff;border:1px solid #e5e7eb;border-radius:22px;padding:24px;box-shadow:0 18px 55px rgba(0,0,0,.06);}
  input,button,select{width:100%;padding:11px 13px;border-radius:14px;border:1px solid #e5e7eb;background:#fff;color:#111}
  a{color:#111;font-weight:600}
  .btn{background:#111;border:none;font-weight:800;cursor:pointer;color:#fff;box-shadow:0 10px 24px rgba(0,0,0,.12)}
  .row{display:flex;gap:14px;flex-wrap:wrap}
  .col{flex:1;min-width:320px}
  .err{background:#fff5f5;border:1px solid #fecaca;padding:12px;border-radius:14px;margin-bottom:12px;color:#991b1b}
  .ok{background:#f0fdf4;border:1px solid #bbf7d0;padding:12px;border-radius:14px;margin-bottom:12px;color:#166534}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h2 style="margin:0 0 8px 0">Editar Admin #<?= e($user['id']) ?></h2>
    <div style="opacity:.75;margin-bottom:10px">
      <a href="<?= e(base_url('admin/users.php')) ?>">← Usuarios</a> ·
      <a href="<?= e(base_url('admin/panel.php')) ?>">Panel</a> ·
      <a href="<?= e(base_url('admin/logout.php')) ?>">Salir</a>
    </div>

    <?php if (!empty($_GET['ok'])): ?><div class="ok">Listo ✅</div><?php endif; ?>
    <?php if ($err): ?><div class="err"><?= e($err) ?></div><?php endif; ?>

    <div class="row">
      <div class="col">
        <h3 style="margin:6px 0 10px 0">Datos</h3>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="save">

          <label>Nombre</label>
          <input name="name" value="<?= e($user['name']) ?>" required>

          <label>Email (solo lectura)</label>
          <input value="<?= e($user['email']) ?>" disabled>

          <label>Rol</label>
          <select name="role" required>
            <?php foreach (['SUPERADMIN','SOPORTE','VENTAS','AUDITOR'] as $r): ?>
              <option value="<?= e($r) ?>" <?= $user['role']===$r?'selected':'' ?>><?= e($r) ?></option>
            <?php endforeach; ?>
          </select>

          <label style="display:flex;gap:8px;align-items:center;margin-top:8px">
            <input style="width:auto" type="checkbox" name="active" value="1" <?= (int)$user['active']===1?'checked':'' ?>>
            Activo
          </label>

          <div style="margin-top:12px"><button class="btn" type="submit">Guardar</button></div>
        </form>
      </div>

      <div class="col">
        <h3 style="margin:6px 0 10px 0">Resetear contraseña</h3>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="reset_password">

          <label>Nueva contraseña</label>
          <input type="password" name="password" required>

          <div style="margin-top:12px"><button class="btn" type="submit">Actualizar</button></div>
        </form>
      </div>
    </div>

  </div>
</div>
</body>
</html>
