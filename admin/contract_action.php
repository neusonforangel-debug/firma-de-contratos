<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

require_admin();

csrf_check();
rate_limit('admin_contract_action');

$pdo = db();

$id     = (int)($_POST['id'] ?? 0);
$action = (string)($_POST['action'] ?? '');
$adminId = (int)($_SESSION['admin_id'] ?? 0);

if ($id <= 0) { http_response_code(400); exit('ID inválido'); }

// Permisos por acción (RBAC PRO)
if ($action === 'resend_code') {
  require_action('contracts.resend');
} elseif ($action === 'extend') {
  require_action('contracts.extend');
} elseif ($action === 'regen_token') {
  // si quieres restringirlo más, cambia por require_action('contracts.extend') o crea uno nuevo
  require_role(['SUPERADMIN','SOPORTE','VENTAS']);
} elseif ($action === 'cancel') {
  // ✅ recomendado: solo superadmin
  require_role(['SUPERADMIN']);
} else {
  http_response_code(400);
  exit('Acción inválida');
}

try {
  $pdo->beginTransaction();

  // Bloqueo de fila para evitar carreras
  $stmt = $pdo->prepare("
    SELECT c.*, cl.email AS client_email, cl.legal_rep
    FROM contracts c
    JOIN clients cl ON cl.id=c.client_id
    WHERE c.id=? LIMIT 1
    FOR UPDATE
  ");
  $stmt->execute([$id]);
  $c = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$c) throw new Exception('Contrato no encontrado');

  // Helper: crear OTP
  $createOtp = function() use ($pdo, $id): array {
    $code = (string)random_int(100000, 999999);
    // hash correcto y consistente (no guardes el código en claro)
    $codeHash = hash_hmac('sha256', $code, APP_KEY);
    $expires = gmdate('Y-m-d H:i:s', time() + (int)SIGN_CODE_TTL_MIN * 60);

    $ins = $pdo->prepare("INSERT INTO sign_codes(contract_id,code_hash,expires_at,created_at) VALUES(?,?,?,?)");
    $ins->execute([$id, $codeHash, $expires, now_utc()]);

    return [$code, $expires];
  };

  if ($action === 'resend_code') {
    if ($c['status'] !== 'PENDING') throw new Exception('Solo disponible para PENDING');

    [$code, $expires] = $createOtp();

    audit_log($pdo, 'CODE_RESENT_ADMIN', ['expires_at'=>$expires], $id, $adminId);

    $pdo->commit();

    // Email (fuera de la transacción)
    $token = $c['token'] ?? null;
    if (!$token) {
      // si no hay token legacy, tu public/sign.php debería aceptar token_hash,
      // pero como aquí enviamos link, necesitamos token real.
      // Si tu sistema ya trabaja con token real siempre, esto no pasará.
      throw new Exception('No hay token para enviar enlace');
    }

    $link = base_url('public/sign.php?token=' . $token);

    $html = "<p>Hola <b>" . e($c['legal_rep']) . "</b>,</p>"
          . "<p>Este es tu nuevo código de firma:</p>"
          . "<h2 style='letter-spacing:2px'>" . e($code) . "</h2>"
          . "<p>Firma aquí: <a href='{$link}'>{$link}</a></p>"
          . "<p>Vence en " . (int)SIGN_CODE_TTL_MIN . " minutos.</p>";

    send_mail($c['client_email'], "Nuevo código de firma - REDWM", $html);

    header('Location: ' . base_url('admin/contract.php?id=' . $id));
    exit;
  }

  if ($action === 'extend') {
    if ($c['status'] !== 'PENDING') throw new Exception('Solo disponible para PENDING');

    $h = (int)($_POST['extend_hours'] ?? 48);
    if ($h < 1) $h = 1;
    if ($h > 720) $h = 720;

    $newExp = gmdate('Y-m-d H:i:s', time() + $h * 3600);

    $up = $pdo->prepare("UPDATE contracts SET expires_at=? WHERE id=?");
    $up->execute([$newExp, $id]);

    audit_log($pdo, 'CONTRACT_EXTENDED', ['hours'=>$h,'expires_at'=>$newExp], $id, $adminId);

    $pdo->commit();

    header('Location: ' . base_url('admin/contract.php?id=' . $id));
    exit;
  }

  if ($action === 'regen_token') {
    if ($c['status'] !== 'PENDING') throw new Exception('Solo disponible para PENDING');

    $newToken = bin2hex(random_bytes(32));
    $newHash  = token_hash($newToken);
    $newExp   = gmdate('Y-m-d H:i:s', time() + (int)SIGN_LINK_TTL_HOURS * 3600);

    $up = $pdo->prepare("UPDATE contracts SET token=?, token_hash=?, expires_at=? WHERE id=?");
    $up->execute([$newToken, $newHash, $newExp, $id]);

    // Genera también OTP nuevo dentro de la misma transacción
    [$code, $expires] = $createOtp();

    audit_log($pdo, 'TOKEN_REGENERATED', ['expires_at'=>$newExp, 'otp_expires'=>$expires], $id, $adminId);

    $pdo->commit();

    // Email fuera de la transacción
    $link = base_url('public/sign.php?token=' . $newToken);

    $html = "<p>Hola <b>" . e($c['legal_rep']) . "</b>,</p>"
          . "<p>Generamos un nuevo enlace y código de firma:</p>"
          . "<h2 style='letter-spacing:2px'>" . e($code) . "</h2>"
          . "<p>Firmar aquí: <a href='{$link}'>{$link}</a></p>"
          . "<p>El código vence en " . (int)SIGN_CODE_TTL_MIN . " minutos.</p>";

    send_mail($c['client_email'], "Nuevo enlace de firma - REDWM", $html);

    header('Location: ' . base_url('admin/contract.php?id=' . $id));
    exit;
  }

  if ($action === 'cancel') {
    if ($c['status'] === 'SIGNED') throw new Exception('No se puede cancelar un contrato ya firmado');

    $ts = now_utc();

    $up = $pdo->prepare("UPDATE contracts SET status='CANCELLED', cancelled_at=? WHERE id=?");
    $up->execute([$ts, $id]);

    audit_log($pdo, 'CONTRACT_CANCELLED', ['cancelled_at'=>$ts], $id, $adminId);

    $pdo->commit();

    header('Location: ' . base_url('admin/contract.php?id=' . $id));
    exit;
  }

  throw new Exception('Acción inválida');

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(400);
  echo 'Error: ' . e($e->getMessage());
}
