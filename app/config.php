<?php

// ==============================
// CONFIGURACIÓN REDWM FIRMA PRO
// ==============================
// 1) Crea la BD y ejecuta /database/schema.sql
// 2) Ajusta credenciales
// 3) Sube la carpeta a public_html (cPanel)
// 4) BASE_URL al dominio/carpeta (sin slash final)
// 5) Crea admin con /install/create_admin.php?allow=1 y luego borra /install

// ---------- Entorno ----------
if (!defined('APP_ENV')) define('APP_ENV', 'development'); // production | development

// En producción NO mostrar errores en pantalla
if (APP_ENV === 'development') {
  ini_set('display_errors', '1');
  ini_set('display_startup_errors', '1');
  error_reporting(E_ALL);
} else {
  ini_set('display_errors', '0');
  ini_set('display_startup_errors', '0');
  error_reporting(E_ALL);
}

// ---------- URL base ----------
if (!defined('BASE_URL')) define('BASE_URL', 'https://redwm.org/avanza'); // sin slash final

// ---------- Base de datos ----------
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'redwm_avanza');
if (!defined('DB_USER')) define('DB_USER', 'redwm_avanza');
if (!defined('DB_PASS')) define('DB_PASS', 'Colombia20302030**');

// ---------- Correo (From debe existir en el dominio) ----------
if (!defined('MAIL_FROM')) define('MAIL_FROM', 'contratos@redwm.org');
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', 'REDWM - Firma Digital');
if (!defined('MAIL_REPLY_TO')) define('MAIL_REPLY_TO', 'contratos@redwm.org');

// ---------- SMTP (PHPMailer) ----------
if (!defined('SMTP_ENABLED')) define('SMTP_ENABLED', true);

if (!defined('SMTP_HOST')) define('SMTP_HOST', 'mail.redwm.org');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 465);           // 465 SSL o 587 TLS
if (!defined('SMTP_USER')) define('SMTP_USER', 'contratos@redwm.org');
if (!defined('SMTP_PASS')) define('SMTP_PASS', 'Colombia2023*');
if (!defined('SMTP_SECURE')) define('SMTP_SECURE', 'ssl');     // 'ssl' (465) o 'tls' (587)

// Debug SMTP: solo en desarrollo
if (!defined('SMTP_DEBUG')) define('SMTP_DEBUG', (APP_ENV === 'development'));

// ---------- Seguridad ----------
if (!defined('APP_KEY')) define('APP_KEY', 'PON_AQUI_UNA_APP_KEY_LARGA_Y_ALEATORIA_MIN_32');
if (!defined('SESSION_NAME')) define('SESSION_NAME', 'REDWM_FIRMA_SESS');

// ---------- Firma / evidencia ----------
if (!defined('SIGN_CODE_TTL_MIN')) define('SIGN_CODE_TTL_MIN', 15);
if (!defined('RATE_LIMIT_WINDOW_SEC')) define('RATE_LIMIT_WINDOW_SEC', 60);
if (!defined('RATE_LIMIT_MAX')) define('RATE_LIMIT_MAX', 10);

// ---------- Contrato ----------
if (!defined('CONTRACT_VERSION')) define('CONTRACT_VERSION', 'v2026.02');
if (!defined('CONTRACT_PRICE_COP')) define('CONTRACT_PRICE_COP', 20000);
if (!defined('CONTRACT_TERM_MONTHS')) define('CONTRACT_TERM_MONTHS', 12);


// ---------- Almacenamiento (archivos) ----------
// Recomendado: ubicar STORAGE_DIR fuera del webroot (por ejemplo: /home/USUARIO/storage_firma)
// En cPanel, si instalas en public_html/firma, puedes crear /home/USUARIO/storage_firma y apuntar aca.
if (!defined('STORAGE_DIR')) define('STORAGE_DIR', __DIR__ . '/../storage');

// Subcarpetas dentro de STORAGE_DIR
if (!defined('STORAGE_PDF_DIR')) define('STORAGE_PDF_DIR', 'pdf');
if (!defined('STORAGE_SIG_DIR')) define('STORAGE_SIG_DIR', 'sig');

// TTL del link de firma (contrato) en horas (admin puede extender)
if (!defined('SIGN_LINK_TTL_HOURS')) define('SIGN_LINK_TTL_HOURS', 48);

?>
