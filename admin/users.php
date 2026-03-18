<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';


require_role(['SUPERADMIN']); // solo SUPERADMIN puede administrar usuarios

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
  rate_limit('admin_create_user');
  csrf_check();

  $name  = trim($_POST['name'] ?? '');
  $email = strtolower(trim($_POST['email'] ?? ''));
  $role  = $_POST['role'] ?? 'AUDITOR';
  $pass  = (string)($_POST['password'] ?? '');

  if ($name === '' || $email === '' || $pass === '') {
    $err = 'Todos los campos son obligatorios.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $err = 'Email inválido.';
  } elseif (!in_array($role, ['SUPERADMIN','SOPORTE','VENTAS','AUDITOR'], true)) {
    $err = 'Rol inválido.';
  } elseif (strlen($pass) < 6) {
    $err = 'La contraseña debe tener mínimo 6 caracteres.';
  } else {
    try {
      $hash = password_hash($pass, PASSWORD_BCRYPT);
      $stmt = db()->prepare("INSERT INTO admin_users (email,password_hash,name,role,active,created_at) VALUES (?,?,?,?,1,NOW())");
      $stmt->execute([$email, $hash, $name, $role]);

      // auditoría (opcional)
      db()->prepare("INSERT INTO audit_logs (admin_user_id,event,meta,ip_address,user_agent,created_at)
                     VALUES (?, 'admin.user_created', JSON_OBJECT('email', ?, 'role', ?), ?, ?, NOW())")
         ->execute([$_SESSION['admin_id'], $email, $role, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);

      header('Location: ' . base_url('admin/users.php?ok=1'));
      exit;
    } catch (Throwable $e) {
      $err = 'No se pudo crear (¿email ya existe?).';
    }
  }
}

$users = db()->query("SELECT id,email,name,role,active,created_at FROM admin_users ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin | Usuarios</title>
<style>
  body{font-family:system-ui;background:linear-gradient(180deg,#faf9f7 0%,#f3f1ed 100%);color:#171717;margin:0}
  .wrap{max-width:1120px;margin:0 auto;padding:18px}
  .card{background:#fff;border:1px solid #e5e7eb;border-radius:22px;padding:24px;box-shadow:0 18px 55px rgba(0,0,0,.06);}
  table{width:100%;border-collapse:collapse;margin-top:10px;font-size:13px}
  th,td{padding:12px;border-bottom:1px solid #e5e7eb;vertical-align:top;text-align:left}
  a{color:#111;font-weight:600}
  input,button,select{width:100%;padding:11px 13px;border-radius:14px;border:1px solid #e5e7eb;background:#fff;color:#111}
  .row{display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap}
  .col{flex:1;min-width:360px}
  .btn{background:#111;border:none;font-weight:800;cursor:pointer;color:#fff;box-shadow:0 10px 24px rgba(0,0,0,.12)}
  .err{background:#fff5f5;border:1px solid #fecaca;padding:12px;border-radius:14px;margin-bottom:12px;color:#991b1b}
  .ok{background:#f0fdf4;border:1px solid #bbf7d0;padding:12px;border-radius:14px;margin-bottom:12px;color:#166534}
  .pill{display:inline-block;padding:5px 10px;border-radius:999px;background:#f3f4f6;border:1px solid #e5e7eb;font-size:12px;color:#111}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="row" style="align-items:center">
      <div style="flex:1">
        <h2 style="margin:0">Usuarios / Roles</h2>
        <div style="opacity:.75">
          Hola, <?= e($_SESSION['admin_name'] ?? 'Admin') ?>
          <span class="pill"><?= e($_SESSION['admin_role'] ?? 'AUDITOR') ?></span>
          · <a href="<?= e(base_url('admin/panel.php')) ?>">Panel</a>
          · <a href="<?= e(base_url('admin/logout.php')) ?>">Salir</a>
        </div>
      </div>
    </div>

    <?php if (!empty($_GET['ok'])): ?><div class="ok">Listo ✅</div><?php endif; ?>
    <?php if ($err): ?><div class="err"><?= e($err) ?></div><?php endif; ?>

    <div class="row">
      <div class="col">
        <h3 style="margin:6px 0 10px 0">Administradores</h3>
        <table>
          <thead>
            <tr>
              <th>ID</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Activo</th><th>Creado</th><th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
              <tr>
                <td><?= e($u['id']) ?></td>
                <td><?= e($u['name']) ?></td>
                <td><?= e($u['email']) ?></td>
                <td><?= e($u['role']) ?></td>
                <td><?= (int)$u['active'] === 1 ? 'Sí' : 'No' ?></td>
                <td><?= e($u['created_at']) ?></td>
                <td><a href="<?= e(base_url('admin/user_edit.php?id='.$u['id'])) ?>">Editar</a></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$users): ?>
              <tr><td colspan="7" style="opacity:.7">No hay usuarios.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="col">
        <h3 style="margin:6px 0 10px 0">Crear admin</h3>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="create">

          <label>Nombre</label>
          <input name="name" required>

          <label>Email</label>
          <input type="email" name="email" required>

          <label>Rol</label>
          <select name="role" required>
            <option value="SUPERADMIN">SUPERADMIN</option>
            <option value="SOPORTE">SOPORTE</option>
            <option value="VENTAS">VENTAS</option>
            <option value="AUDITOR">AUDITOR</option>
          </select>

          <label>Contraseña</label>
          <input type="password" name="password" required>

          <div style="margin-top:12px"><button class="btn" type="submit">Crear</button></div>
        </form>
      </div>
    </div>

  </div>
</div>
</body>
</html>
