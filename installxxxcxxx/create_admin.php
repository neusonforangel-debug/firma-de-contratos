<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

if (APP_ENV === 'production' && !isset($_GET['allow'])) {
  exit('Bloqueado. Para usar en producción, agrega ?allow=1 y luego elimina /install.');
}

$err = ''; $ok = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $email = trim($_POST['email'] ?? '');
  $name  = trim($_POST['name'] ?? 'Admin');
  $pass  = $_POST['password'] ?? '';
  if ($email==='' || $pass==='') $err = 'Faltan datos';
  else {
    $hash = password_hash($pass, PASSWORD_DEFAULT);

    // Compatibilidad: si la tabla tiene role/active, los setea; si no, hace el insert clásico
    $pdo = db();
    try {
      $stmt = $pdo->prepare("INSERT INTO admin_users(email,password_hash,name,role,active,created_at) VALUES(?,?,?,?,?,?)");
      $stmt->execute([$email,$hash,$name,'SUPERADMIN',1,now_utc()]);
      $ok = 'Admin creado. Ahora borra la carpeta /install por seguridad.';
    } catch (Throwable $e) {
      try {
        $stmt = $pdo->prepare("INSERT INTO admin_users(email,password_hash,name,created_at) VALUES(?,?,?,?)");
        $stmt->execute([$email,$hash,$name,now_utc()]);
        $ok = 'Admin creado. Ahora borra la carpeta /install por seguridad.';
      } catch (Throwable $e2) {
        $err = 'No se pudo crear (¿ya existe?).';
      }
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install | Crear Admin</title>
<style>
body{font-family:system-ui;background:#0b1220;color:#e7ecff;margin:0}
.wrap{max-width:520px;margin:0 auto;padding:22px}
.card{background:#121b2f;border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:18px}
label{display:block;margin:.6rem 0 .25rem 0;font-size:13px;opacity:.9}
input,button{width:100%;padding:12px;border-radius:12px;border:1px solid rgba(255,255,255,.12);background:#0f1730;color:#e7ecff}
.btn{background:#2b6cff;border:none;font-weight:800;cursor:pointer}
.msg{padding:10px;border-radius:12px;margin-bottom:10px}
.err{background:#3b1b1b;border:1px solid rgba(255,90,90,.35)}
.ok{background:#16331d;border:1px solid rgba(90,255,140,.25)}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h2 style="margin:0 0 8px 0">Crear Admin</h2>
    <?php if($err): ?><div class="msg err"><?= e($err) ?></div><?php endif; ?>
    <?php if($ok): ?><div class="msg ok"><?= e($ok) ?></div><?php endif; ?>
    <form method="post">
      <label>Nombre</label><input name="name" value="Admin REDWM" required>
      <label>Email</label><input type="email" name="email" required>
      <label>Contraseña</label><input type="password" name="password" required>
      <div style="margin-top:12px"><button class="btn">Crear</button></div>
    </form>
  </div>
</div>
</body>
</html>
