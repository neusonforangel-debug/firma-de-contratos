<?php
require_once __DIR__ . '/../app/helpers.php';
start_session();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>REDWM | Firma Digital</title>
  <style>
    :root{--bg:#f6f4f1;--card:#ffffff;--text:#171717;--muted:#6b7280;--line:#e5e7eb;--soft:#f8f8f8;--accent:#111111;}
    *{box-sizing:border-box}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,'Helvetica Neue',Arial;background:linear-gradient(180deg,#faf9f7 0%,#f4f2ee 100%);color:var(--text);margin:0;}
    .wrap{max-width:1020px;margin:0 auto;padding:34px 22px;}
    .card{background:var(--card);border:1px solid var(--line);border-radius:22px;padding:28px;box-shadow:0 18px 55px rgba(0,0,0,.06);}
    .logo{display:flex;justify-content:center;margin:4px 0 18px}
    .logo img{max-width:220px;width:100%;height:auto;display:block}
    h1{margin:0 0 8px 0;font-size:28px;text-align:center;letter-spacing:-.02em;color:#111}
    p{color:#4b5563;line-height:1.6;text-align:center;margin:0 0 16px 0}
    label{display:block;margin:.2rem 0 .45rem 0;font-size:13px;color:#4b5563;font-weight:600}
    input,button{width:100%;padding:14px 14px;border-radius:14px;border:1px solid var(--line);background:#fff;color:#111;font-size:14px}
    input:focus{outline:none;border-color:#cfcfcf;box-shadow:0 0 0 4px rgba(0,0,0,.04)}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    .btn{background:var(--accent);border:none;color:#fff;font-weight:700;cursor:pointer;box-shadow:0 10px 24px rgba(0,0,0,.12)}
    .btn:hover{filter:brightness(1.02)}
    .muted{font-size:12px;color:var(--muted);margin-top:12px;text-align:center}
    @media(max-width:720px){.grid{grid-template-columns:1fr}.card{padding:22px}}
  </style>
</head>
<body>
<div class="wrap">
  <div class="card">

    <!-- ✅ LOGO ARRIBA -->
    <div class="logo">
      <img src="<?= e(base_url('public/logo.png')) ?>" alt="REDWM">
    </div>

    <h1>Firma Digital — Contrato Plan Anual</h1>
    <p>Completa los datos del cliente para generar el contrato y enviar el código de firma al correo.</p>

    <form method="post" action="<?= e(base_url('actions/create_client.php')) ?>">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <div class="grid">
        <div>
          <label>Empresa / Razón social</label>
          <input name="company_name" required>
        </div>
        <div>
          <label>NIT</label>
          <input name="nit" required>
        </div>
        <div>
          <label>Representante legal</label>
          <input name="legal_rep" required>
        </div>
        <div>
          <label>Email (para firma)</label>
          <input type="email" name="email" required>
        </div>
        <div>
          <label>Teléfono</label>
          <input name="phone">
        </div>
        <div>
          <label>Dirección</label>
          <input name="address">
        </div>
      </div>

      <div style="margin-top:14px">
        <button class="btn" type="submit">Generar contrato y enviar código</button>
      </div>

      <div class="muted">Al continuar, aceptas el tratamiento de datos para la gestión del contrato.</div>
    </form>

    <!-- ❌ Eliminado: ¿Eres administrador? Ingresar al panel -->

  </div>
</div>
</body>
</html>
