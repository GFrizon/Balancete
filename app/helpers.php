<?php
/**
 * Funções auxiliares globais
 */

// -------------------------------------------------------
// Sessão / Auth
// -------------------------------------------------------

function session_start_safe(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        if (empty($_SESSION['user_id'])) {
            remember_login_from_cookie();
        }
    }
}

function auth_check(): void
{
    if (empty($_SESSION['user_id'])) {
        redirect('login');
        exit;
    }

    $rememberedSession = !empty($_SESSION['remembered']);
    if (!$rememberedSession && !empty($_SESSION['last_activity']) && time() - (int)$_SESSION['last_activity'] > 1800) {
        logout_session();
        session_start_safe();
        flash('warning', 'Sessao expirada por inatividade. Entre novamente.');
        redirect('login');
    }
    $_SESSION['last_activity'] = time();

    $currentAgent = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    if (!empty($_SESSION['user_agent_hash']) && !hash_equals($_SESSION['user_agent_hash'], $currentAgent)) {
        logout_session();
        session_start_safe();
        flash('error', 'Sessao invalida. Entre novamente.');
        redirect('login');
    }

    $stmt = db()->prepare('SELECT name, role, active FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || !(int)$user['active']) {
        logout_session();
        session_start_safe();
        flash('error', 'Conta inativa ou removida. Entre em contato com o administrador.');
        redirect('login');
    }

    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
}

function auth_admin(): void
{
    auth_check();
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        flash('error', 'Acesso restrito a administradores.');
        redirect('dashboard');
        exit;
    }
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user_id(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function current_user_role(): string
{
    return $_SESSION['user_role'] ?? 'user';
}

function current_user_name(): string
{
    return $_SESSION['user_name'] ?? '';
}

// -------------------------------------------------------
// CSRF
// -------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die('Token CSRF inválido. Volte e tente novamente.');
    }
}

function csrf_rotate(): void
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function logout_session(): void
{
    remember_forget_current();

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function remember_cookie_params(): array
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    return [
        'expires' => time() + 60 * 60 * 24 * 30,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function remember_ensure_table(): bool
{
    static $done = false;
    if ($done) {
        return true;
    }

    try {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS remember_tokens (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                selector CHAR(24) NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_used_at DATETIME NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_remember_selector (selector),
                KEY idx_remember_user (user_id),
                KEY idx_remember_expires (expires_at),
                CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable) {
        return false;
    }

    $done = true;
    return true;
}

function remember_create(int $userId): void
{
    if (!remember_ensure_table()) {
        remember_clear_cookie();
        return;
    }

    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $hash = hash('sha256', $validator);

    try {
        $stmt = db()->prepare(
            'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))'
        );
        $stmt->execute([$userId, $selector, $hash]);
    } catch (Throwable) {
        remember_clear_cookie();
        return;
    }

    setcookie('remember_login', $selector . ':' . $validator, remember_cookie_params());
    $_SESSION['remembered'] = true;
}

function remember_login_from_cookie(): void
{
    $cookie = $_COOKIE['remember_login'] ?? '';
    if ($cookie === '' || !str_contains($cookie, ':')) {
        return;
    }

    if (!remember_ensure_table()) {
        remember_clear_cookie();
        return;
    }

    [$selector, $validator] = explode(':', $cookie, 2);
    if (!preg_match('/^[a-f0-9]{24}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $validator)) {
        remember_clear_cookie();
        return;
    }

    try {
        $stmt = db()->prepare(
            'SELECT rt.id, rt.user_id, rt.token_hash, u.name, u.role, u.active
               FROM remember_tokens rt
               JOIN users u ON u.id = rt.user_id
              WHERE rt.selector = ?
                AND rt.expires_at > NOW()
              LIMIT 1'
        );
        $stmt->execute([$selector]);
        $row = $stmt->fetch();
    } catch (Throwable) {
        remember_clear_cookie();
        return;
    }

    if (!$row || !(int)$row['active'] || !hash_equals((string)$row['token_hash'], hash('sha256', $validator))) {
        remember_clear_cookie();
        return;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$row['user_id'];
    $_SESSION['user_name'] = $row['name'];
    $_SESSION['user_role'] = $row['role'];
    $_SESSION['remembered'] = true;
    $_SESSION['last_activity'] = time();
    $_SESSION['user_agent_hash'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');

    try {
        db()->prepare('UPDATE remember_tokens SET last_used_at = NOW() WHERE id = ?')->execute([(int)$row['id']]);
    } catch (Throwable) {
        // Login by cookie already succeeded; failing to stamp usage should not log the user out.
    }
}

function remember_forget_current(): void
{
    $cookie = $_COOKIE['remember_login'] ?? '';
    if ($cookie !== '' && str_contains($cookie, ':')) {
        [$selector] = explode(':', $cookie, 2);
        if (preg_match('/^[a-f0-9]{24}$/', $selector)) {
            try {
                if (remember_ensure_table()) {
                    db()->prepare('DELETE FROM remember_tokens WHERE selector = ?')->execute([$selector]);
                }
            } catch (Throwable) {
                // Ignore remember-token cleanup failures during logout.
            }
        }
    }

    remember_clear_cookie();
}

function remember_clear_cookie(): void
{
    setcookie('remember_login', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => remember_cookie_params()['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function security_headers(): void
{
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

function password_errors(string $password): array
{
    $errors = [];
    if (strlen($password) < 10) {
        $errors[] = 'Senha minima de 10 caracteres.';
    }
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'Use letras maiusculas, minusculas e numeros na senha.';
    }
    return $errors;
}

// -------------------------------------------------------
// Redirecionamento / Flash
// -------------------------------------------------------

function redirect(string $route, array $params = []): void
{
    $url = url($route, $params);
    header('Location: ' . $url);
    exit;
}

/**
 * Gera URL no formato index.php?route=... para máxima compatibilidade cPanel.
 * Funciona com ou sem mod_rewrite.
 */
function url(string $route = '', array $params = []): string
{
    $base = BASE_URL . '/index.php';

    if ($route !== '') {
        $params = array_merge(['route' => $route], $params);
    }

    $qs = $params ? '?' . http_build_query($params) : '';
    return $base . $qs;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

// -------------------------------------------------------
// Views
// -------------------------------------------------------

function view(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $file = APP_ROOT . '/app/Views/' . $template . '.php';
    if (!file_exists($file)) {
        http_response_code(500);
        die("View não encontrada: {$template}");
    }
    require $file;
}

// -------------------------------------------------------
// Segurança / Output
// -------------------------------------------------------

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_response(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// -------------------------------------------------------
// Formatação
// -------------------------------------------------------

function format_brl(float $value): string
{
    return number_format($value, 2, ',', '.');
}

function format_percent(float $value, int $decimals = 1): string
{
    return number_format($value, $decimals, ',', '.') . '%';
}

function parse_brl(string $value): float
{
    $clean = str_replace(['.', ','], ['', '.'], $value);
    return (float)$clean;
}

function month_name(int $month): string
{
    return MONTHS_PT[$month] ?? '';
}

function month_short(int $month): string
{
    $names = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
    return $names[$month - 1] ?? '';
}

// -------------------------------------------------------
// Auditoria
// -------------------------------------------------------

function audit(string $action, string $entityType = '', int $entityId = 0, array $payload = []): void
{
    try {
        $pdo = db();
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, payload, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            current_user_id() ?: null,
            $action,
            $entityType,
            $entityId ?: null,
            $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    } catch (Throwable) {
        // Nunca deixar falha de auditoria derrubar a aplicação
    }
}

// -------------------------------------------------------
// Upload
// -------------------------------------------------------

function allowed_extension(string $filename): bool
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ALLOWED_EXTENSIONS, true);
}

function ensure_dir(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}
