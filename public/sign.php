<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/contract/template.php';

rate_limit('sign_page');

$token = (string)($_GET['token'] ?? '');
$token = strtolower(trim($token));

// Validación básica del token (64 hex)
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
  http_response_code(400);
  exit('Token inválido');
}

$pdo = db();
$stmt = $pdo->prepare("
  SELECT c.*, cl.company_name, cl.nit, cl.legal_rep, cl.email AS client_email, cl.phone, cl.address
  FROM contracts c
  JOIN clients cl ON cl.id=c.client_id
  WHERE (c.token_hash=? OR c.token=?)
  LIMIT 1
");
$stmt->execute([token_hash($token), $token]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
  http_response_code(404);
  exit('Contrato no encontrado');
}

// Bloqueos por estado
if (!empty($row['cancelled_at']) || ($row['status'] ?? '') === 'CANCELLED') {
  http_response_code(410);
  exit('Este contrato fue cancelado.');
}

if (!empty($row['expires_at']) && strtotime($row['expires_at']) < time() && ($row['status'] ?? '') !== 'SIGNED') {
  http_response_code(410);
  exit('El link de firma expiró. Solicita un nuevo enlace.');
}

$contract = [
  'id'               => $row['id'],
  'token'            => $token, // ✅ usamos SIEMPRE el token de la URL (no dependemos de c.token)
  'status'           => $row['status'],
  'contract_version' => $row['contract_version'],
  'price_cop'        => $row['price_cop'],
  'term_months'      => $row['term_months'],
  'created_at'       => $row['created_at'],
  'signed_at'        => $row['signed_at']
];

$client = [
  'company_name' => $row['company_name'],
  'nit'          => $row['nit'],
  'legal_rep'    => $row['legal_rep'],
  'email'        => $row['client_email'],
  'phone'        => $row['phone'],
  'address'      => $row['address'],
];

$text = contract_text($client, $contract);

// FLASH
start_session();
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Firmar contrato | REDWM</title>
  <style>
    :root{--bg:#f6f4f1;--card:#ffffff;--text:#171717;--muted:#6b7280;--line:#e5e7eb;--soft:#fafafa;--accent:#111111;}
    *{box-sizing:border-box}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,'Helvetica Neue',Arial;background:linear-gradient(180deg,#faf9f7 0%,#f3f1ed 100%);color:var(--text);margin:0;}
    .wrap{max-width:1100px;margin:0 auto;padding:24px;}
    .card{background:var(--card);border:1px solid var(--line);border-radius:22px;padding:22px;box-shadow:0 18px 55px rgba(0,0,0,.06);}
    .grid{display:grid;grid-template-columns:1.15fr .85fr;gap:16px}
    pre{white-space:pre-wrap;background:#fff;padding:18px;border-radius:18px;border:1px solid var(--line);height:520px;overflow:auto;color:#222;line-height:1.6;box-shadow:inset 0 1px 0 rgba(255,255,255,.8)}
    label{display:block;margin:.65rem 0 .35rem 0;font-size:13px;color:#4b5563;font-weight:600}
    input,button,select{width:100%;padding:13px 14px;border-radius:14px;border:1px solid var(--line);background:#fff;color:#111}
    input:focus,select:focus{outline:none;border-color:#d2d6db;box-shadow:0 0 0 4px rgba(0,0,0,.04)}
    .btn{background:var(--accent);border:none;font-weight:800;cursor:pointer;color:#fff;box-shadow:0 10px 24px rgba(0,0,0,.12)}
    .btn:hover{filter:brightness(1.02)}
    .muted{font-size:12px;color:var(--muted)}
    canvas{background:#fff;border-radius:14px;width:100%;height:160px;border:1px dashed #d1d5db;touch-action:none;box-shadow:inset 0 0 0 1px rgba(255,255,255,.8)}
    .row{display:flex;gap:10px}.row>*{flex:1}
    @media(max-width:860px){.grid{grid-template-columns:1fr} pre{height:auto}}
  </style>
  <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h2 style="margin:0 0 6px 0">Firma Digital — Contrato</h2>
    <div class="muted">Estado: <b><?= e($contract['status']) ?></b></div>

    <div class="grid" style="margin-top:12px">

      <div>
        <pre><?= e($text) ?></pre>
        <div class="muted" style="margin-top:8px">
          Evidencia: IP, fecha/hora UTC, agente de usuario y hash criptográfico.
        </div>
      </div>

      <div>
        <?php if ($contract['status'] === 'SIGNED'): ?>

          <div class="card" style="background:#fbfbfb;border:1px solid #e5e7eb;box-shadow:none">
            <h3 style="margin:0 0 6px 0">✅ Contrato firmado</h3>
            <div class="muted">Fecha de firma (UTC): <?= e($contract['signed_at'] ?? '-') ?></div>
            <div style="margin-top:12px" class="row">
              <a class="btn" style="display:block;text-align:center;text-decoration:none;padding:12px;border-radius:14px;color:#fff;background:#111"
                 href="<?= e(base_url('public/download.php?token='.$token)) ?>">Descargar PDF</a>
              <a style="display:block;text-align:center;text-decoration:none;padding:12px;border-radius:14px;border:1px solid #d1d5db;color:#111;background:#fff"
                 href="<?= e(base_url('public/verify.php?token='.$token)) ?>">Verificar</a>
            </div>
          </div>

        <?php else: ?>

          <?php if ($flash): ?>
            <div style="background:#fff;border:1px solid #e5e7eb;padding:12px;border-radius:14px;margin:10px 0;color:#111">
              <?= e($flash) ?>
            </div>
          <?php endif; ?>

          <form method="post" action="<?= e(base_url('actions/submit_signature.php')) ?>" id="signForm">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <input type="hidden" name="signature_data" id="signature_data">

            <label>Nombre de quien firma</label>
            <input name="signer_name" required value="<?= e($client['legal_rep']) ?>">

            <div class="row">
              <div>
                <label>Cédula / ID</label>
                <input name="signer_id" required>
              </div>
              <div>
                <label>Cargo</label>
                <input name="signer_role" required placeholder="Representante Legal">
              </div>
            </div>

            <label>Email (debe coincidir)</label>
            <input name="signer_email" type="email" required value="<?= e($client['email']) ?>">

            <label>Código enviado al correo</label>
            <input name="code" inputmode="numeric" pattern="[0-9]{6}" placeholder="6 dígitos" required>

            <div style="margin-top:10px">
              <button type="button"
                onclick="document.getElementById('resendForm').submit();"
                style="width:100%;padding:12px;border-radius:14px;border:1px solid #d1d5db;background:#fff;color:#111;cursor:pointer">
                No me llegó el correo — Reenviar código
              </button>
            </div>

            <label style="margin-top:10px">Firma (dibuja en el recuadro)</label>
            <canvas id="pad"></canvas>

            <div class="row" style="margin-top:10px">
              <button type="button" onclick="pad.clear()">Limpiar</button>
              <button class="btn" type="submit">Firmar y generar PDF</button>
            </div>

            <div class="muted" style="margin-top:10px">
              Al firmar, aceptas el contrato y autorizas el tratamiento de datos (Habeas Data).
            </div>
          </form>

          <form id="resendForm" method="post" action="<?= e(base_url('actions/resend_code_public.php')) ?>" style="display:none">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="token" value="<?= e($token) ?>">
          </form>

        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<script>
  const canvas = document.getElementById('pad');
  canvas.style.touchAction = 'none';
  canvas.addEventListener('touchstart', (e) => e.preventDefault(), { passive: false });
  canvas.addEventListener('touchmove',  (e) => e.preventDefault(), { passive: false });

  function resizeCanvas() {
    const ratio = Math.max(window.devicePixelRatio || 1, 1);
    const rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * ratio;
    canvas.height = rect.height * ratio;
    canvas.getContext("2d").scale(ratio, ratio);
  }

  resizeCanvas();
  window.addEventListener('resize', () => { resizeCanvas(); pad.clear(); });

  const pad = new SignaturePad(canvas, { minWidth: 0.8, maxWidth: 2.2 });

  const form = document.getElementById('signForm');
  if (form) {
    form.addEventListener('submit', (e) => {
      if (pad.isEmpty()) {
        e.preventDefault();
        alert('Por favor firma en el recuadro.');
        return;
      }
      document.getElementById('signature_data').value = pad.toDataURL('image/png');
    });
  }
</script>
</body>
</html>
