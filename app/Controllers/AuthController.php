<?php
declare(strict_types=1);

class AuthController
{
    public function showLogin(): void
    {
        if (is_logged_in()) {
            redirect('dashboard');
        }

        $canSetup = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
        view('auth/login', compact('canSetup'));
    }

    public function doLogin(): void
    {
        csrf_verify();

        $email    = mb_strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            flash('error', 'Preencha e-mail e senha.');
            redirect('login');
        }

        if ($this->isRateLimited($email)) {
            audit('login_blocked', 'user', 0, ['email' => $email]);
            flash('error', 'Muitas tentativas de login. Aguarde alguns minutos e tente novamente.');
            redirect('login');
        }

        $stmt = db()->prepare(
            'SELECT id, name, email, password_hash, role, force_change_password, active
             FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            flash('error', 'E-mail ou senha incorretos.');
            audit('login_failed', 'user', 0, ['email' => $email]);
            redirect('login');
        }

        if (!$user['active']) {
            flash('error', 'Conta desativada. Contate o administrador.');
            redirect('login');
        }

        // Regenerar sessão
        session_regenerate_id(true);
        csrf_rotate();

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['last_activity'] = time();
        $_SESSION['user_agent_hash'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');

        if (!empty($_POST['remember_me'])) {
            remember_create((int)$user['id']);
        }

        audit('login', 'user', (int)$user['id']);

        if ($user['force_change_password']) {
            flash('warning', 'Por segurança, altere sua senha antes de continuar.');
            redirect('users/profile');
        }

        redirect('dashboard');
    }

    public function doLogout(): void
    {
        csrf_verify();
        $userId = current_user_id();
        audit('logout', 'user', $userId);

        logout_session();
        redirect('login');
    }

    // -------------------------------------------------------
    // Setup inicial (cria admin se users estiver vazio)
    // -------------------------------------------------------

    public function showSetup(): void
    {
        $count = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count > 0) {
            flash('error', 'Setup já realizado. Faça login normalmente.');
            redirect('login');
        }
        view('auth/setup');
    }

    public function doSetup(): void
    {
        $count = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count > 0) {
            redirect('login');
        }

        csrf_verify();

        $name     = trim($_POST['name'] ?? '');
        $email    = mb_strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm'] ?? '';

        $errors = [];
        if ($name === '')                     $errors[] = 'Nome obrigatório.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail inválido.';
        $errors = array_merge($errors, password_errors($password));
        if ($password !== $confirm)           $errors[] = 'Senhas não conferem.';

        if ($errors) {
            foreach ($errors as $e) flash('error', $e);
            redirect('setup');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = db()->prepare(
            'INSERT INTO users (name, email, password_hash, role, force_change_password) VALUES (?, ?, ?, ?, 0)'
        );
        $stmt->execute([$name, $email, $hash, 'admin']);

        flash('success', 'Administrador criado com sucesso! Faça login.');
        redirect('login');
    }

    private function isRateLimited(string $email): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $stmt = db()->prepare(
            "SELECT COUNT(*)
               FROM audit_logs
              WHERE action IN ('login_failed', 'login_blocked')
                AND ip_address = ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        );
        $stmt->execute([$ip]);
        if ((int)$stmt->fetchColumn() >= 8) {
            return true;
        }

        $stmt = db()->prepare(
            "SELECT COUNT(*)
               FROM audit_logs
              WHERE action = 'login_failed'
                AND payload LIKE ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        );
        $stmt->execute(['%' . $email . '%']);
        return (int)$stmt->fetchColumn() >= 5;
    }
}
