<?php
require_once __DIR__ . '/config.php';

function e(string $s): string { 
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); 
}

function base_url(string $path=''): string {
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

function now_utc(): string { 
    return gmdate('Y-m-d H:i:s'); 
}

function client_ip(): string {
    $keys = ['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'];
    foreach ($keys as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = explode(',', $_SERVER[$k])[0];
            return trim($ip);
        }
    }
    return '0.0.0.0';
}

function user_agent(): string { 
    return $_SERVER['HTTP_USER_AGENT'] ?? ''; 
}

function start_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
}

function csrf_token(): string {
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void {
    start_session();
    $ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']);
    if (!$ok) {
        http_response_code(403);
        exit('CSRF inválido');
    }
}

function hash_evidence(array $data): string {
    $payload = json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    return hash_hmac('sha256', $payload, APP_KEY);
}

function rate_limit(string $key): void {
    start_session();
    $t = time();
    if (!isset($_SESSION['rl'])) $_SESSION['rl'] = [];
    if (!isset($_SESSION['rl'][$key])) $_SESSION['rl'][$key] = ['t'=>$t,'c'=>0];

    $w = &$_SESSION['rl'][$key];
    if (($t - $w['t']) > RATE_LIMIT_WINDOW_SEC) {
        $w = ['t'=>$t,'c'=>0];
    }

    $w['c']++;
    if ($w['c'] > RATE_LIMIT_MAX) {
        http_response_code(429);
        exit('Demasiadas solicitudes. Intenta en un momento.');
    }
}

/* ======================================================
   SMTP PRO CON PHPMailer
   ====================================================== */

function send_mail(string $to, string $subject, string $html, string $text=''): bool {

    if ($text === '') {
        $text = strip_tags($html);
    }

    // Si SMTP no está activo, usa mail() como respaldo
    if (!defined('SMTP_ENABLED') || SMTP_ENABLED !== true) {

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
        $headers .= "Reply-To: " . MAIL_REPLY_TO . "\r\n";

        return mail($to, $subject, $html, $headers);
    }

    // Cargar PHPMailer
    $composer = __DIR__ . '/../vendor/autoload.php';

    if (file_exists($composer)) {
        require_once $composer;
    } else {
        require_once __DIR__ . '/lib/PHPMailer/src/Exception.php';
        require_once __DIR__ . '/lib/PHPMailer/src/PHPMailer.php';
        require_once __DIR__ . '/lib/PHPMailer/src/SMTP.php';
    }

    try {

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        if (defined('SMTP_DEBUG') && SMTP_DEBUG) {
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = function($str, $level) {
                error_log("SMTP_DEBUG[$level]: " . $str);
            };
        }

        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE; // ssl o tls
        $mail->Port       = SMTP_PORT;

        $mail->CharSet = 'UTF-8';

        // IMPORTANTE: FROM debe coincidir con el usuario SMTP
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addReplyTo(MAIL_REPLY_TO);
        $mail->addAddress($to);

        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = $html;
        $mail->AltBody = $text;

        return $mail->send();

    } catch (\Throwable $e) {

        error_log("SMTP_ERROR to={$to} subject={$subject} err=" . $e->getMessage());

        start_session();
        $_SESSION['flash'] = "SMTP ERROR: " . $e->getMessage();

        return false;
    }
}


// ======================================================
// Helpers Firma PRO (token hash + storage + auditoria)
// ======================================================

function token_hash(string $token): string {
    return hash('sha256', $token);
}

function storage_path(string $subdir, string $filename): string {
    $base = rtrim(STORAGE_DIR, '/');
    $dir = $base . '/' . trim($subdir, '/');
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir . '/' . $filename;
}

function ensure_storage_protected(): void {
    $base = rtrim(STORAGE_DIR, '/');
    if (!is_dir($base)) {
        @mkdir($base, 0755, true);
    }
    $ht = $base . '/.htaccess';
    if (!file_exists($ht)) {
        @file_put_contents($ht, "Deny from all\n");
    }
}

function admin_role(): string {
    start_session();
    return $_SESSION['admin_role'] ?? 'SUPERADMIN';
}

function require_role(array $allowed): void {
    $r = admin_role();
    if (!in_array($r, $allowed, true)) {
        http_response_code(403);
        exit('No autorizado');
    }
}

/**
 * Permisos por acción (RBAC PRO)
 */
function can(string $action): bool {
    $role = admin_role();

    $matrix = [
        'SUPERADMIN' => ['*'],

        'SOPORTE' => [
            'contracts.view',
            'contracts.resend',
            'contracts.extend',
            'contracts.download',
            'audit.view',
        ],

        'VENTAS' => [
            'contracts.view',
            'contracts.create',
            'contracts.resend',
            'contracts.extend',
            'contracts.download',
        ],

        'AUDITOR' => [
            'contracts.view',
            'contracts.download',
            'audit.view',
        ],
    ];

    $allowed = $matrix[$role] ?? [];
    return in_array('*', $allowed, true) || in_array($action, $allowed, true);
}

function require_action(string $action): void {
    require_admin();
    if (!can($action)) {
        http_response_code(403);
        exit('No autorizado');
    }
}

function audit_log(PDO $pdo, string $event, array $meta = [], ?int $contractId = null, ?int $adminUserId = null): void {

    // ✅ FIX FK: evita enviar 0/negativos a admin_user_id (FK -> admin_users.id)
    if ($adminUserId !== null && (int)$adminUserId <= 0) {
        $adminUserId = null;
    }

    $ip = client_ip();
    $ua = substr(user_agent(), 0, 255);
    $created = now_utc();

    $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

    static $cols = null;
    if ($cols === null) {
        $cols = [];
        try {
            $q = $pdo->query("SHOW COLUMNS FROM audit_logs");
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $c) $cols[$c['Field']] = true;
        } catch (Throwable $e) {
            $cols = [];
        }
    }

    $hasContract = isset($cols['contract_id']);
    $hasAdmin = isset($cols['admin_user_id']);

    if ($hasContract || $hasAdmin) {
        $stmt = $pdo->prepare("INSERT INTO audit_logs(event, contract_id, admin_user_id, meta, ip_address, user_agent, created_at)
                               VALUES(?,?,?,?,?,?,?)");
        $stmt->execute([
            $event,
            $hasContract ? $contractId : null,
            $hasAdmin ? $adminUserId : null,
            $metaJson,
            $ip,
            $ua,
            $created
        ]);
        return;
    }

    $stmt = $pdo->prepare("INSERT INTO audit_logs(event,meta,ip_address,user_agent,created_at) VALUES(?,?,?,?,?)");
    $stmt->execute([$event, $metaJson, $ip, $ua, $created]);
}

function require_admin(): void {
    start_session();
    if (empty($_SESSION['admin_id'])) {
        header('Location: ' . base_url('admin/login.php'));
        exit;
    }

    if (isset($_SESSION['admin_active']) && (int)$_SESSION['admin_active'] !== 1) {
        session_destroy();
        header('Location: ' . base_url('admin/login.php?disabled=1'));
        exit;
    }
}
?>
