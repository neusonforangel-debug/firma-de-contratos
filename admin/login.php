<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

start_session();

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  rate_limit('admin_login');
  csrf_check();

  $email = strtolower(trim($_POST['email'] ?? ''));
  $pass  = (string)($_POST['password'] ?? '');

  $stmt = db()->prepare("SELECT id,email,password_hash,name, role, active FROM admin_users WHERE email=? LIMIT 1");
  $stmt->execute([$email]);
  $u = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($u) {
    // Compatibilidad: si la BD aún no tiene role/active, asigna defaults
    $role   = $u['role'] ?? 'SUPERADMIN';
    $active = (int)($u['active'] ?? 1);

    // Valida activo + password
    if ($active === 1 && password_verify($pass, $u['password_hash'])) {

      // ✅ Sesión admin completa (PASO 2)
      $_SESSION['admin_id']     = (int)$u['id'];
      $_SESSION['admin_name']   = $u['name'];
      $_SESSION['admin_email']  = $u['email'] ?? '';
      $_SESSION['admin_role']   = $role;
      $_SESSION['admin_active'] = $active;

      header('Location: ' . base_url('admin/panel.php'));
      exit;

    } elseif ($active !== 1) {
      $err = 'Tu usuario está inactivo. Contacta al administrador.';
    } else {
      $err = 'Credenciales inválidas';
    }

  } else {
    $err = 'Credenciales inválidas';
  }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin | Login</title>
<style>
  :root{--line:#e5e7eb;--muted:#6b7280}
  body{font-family:system-ui;background:linear-gradient(180deg,#faf9f7 0%,#f3f1ed 100%);color:#111;margin:0}
  .wrap{max-width:540px;margin:0 auto;padding:28px}
  .card{background:#fff;border:1px solid var(--line);border-radius:22px;padding:26px;box-shadow:0 18px 55px rgba(0,0,0,.06)}
  label{display:block;margin:.75rem 0 .35rem 0;font-size:13px;color:#4b5563;font-weight:600}
  input,button{width:100%;padding:13px 14px;border-radius:14px;border:1px solid var(--line);background:#fff;color:#111}
  input:focus{outline:none;border-color:#d1d5db;box-shadow:0 0 0 4px rgba(0,0,0,.04)}
  .btn{background:#111;border:none;font-weight:800;cursor:pointer;color:#fff;box-shadow:0 10px 24px rgba(0,0,0,.12)}
  .err{background:#fff5f5;border:1px solid #fecaca;padding:12px;border-radius:14px;margin-bottom:12px;color:#991b1b}
  .muted,a{color:#6b7280}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h2 style="margin:0 0 8px 0">Panel Admin</h2>
    <?php if ($err): ?><div class="err"><?= e($err) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <label>Email</label><input type="email" name="email" required>
      <label>Contraseña</label><input type="password" name="password" required>
      <div style="margin-top:12px"><button class="btn" type="submit">Ingresar</button></div>
    </form>
    <div style="margin-top:12px" class="muted"><a style="color:#111;font-weight:600" href="<?= e(base_url('public/index.php')) ?>">Volver</a></div>
  </div>
</div>
</body>
</html>
