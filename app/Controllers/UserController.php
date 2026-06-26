<?php
declare(strict_types=1);

class UserController
{
    public function index(): void
    {
        auth_admin();
        $users = db()->query(
            'SELECT id, name, email, role, active, created_at FROM users ORDER BY name'
        )->fetchAll();
        view('users/index', compact('users'));
    }

    public function store(): void
    {
        auth_admin();
        csrf_verify();

        $name     = trim($_POST['name'] ?? '');
        $email    = mb_strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'user';

        $errors = [];
        if ($name === '')                              $errors[] = 'Nome obrigatório.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail inválido.';
        if (!in_array($role, ['admin', 'user'], true)) $errors[] = 'Perfil invalido.';
        $errors = array_merge($errors, password_errors($password));

        // E-mail único
        $dup = db()->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
        $dup->execute([$email]);
        if ($dup->fetch()) $errors[] = 'E-mail já cadastrado.';

        if ($errors) {
            foreach ($errors as $e) flash('error', $e);
            redirect('users');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        db()->prepare(
            'INSERT INTO users (name, email, password_hash, role, force_change_password) VALUES (?,?,?,?,1)'
        )->execute([$name, $email, $hash, $role]);

        audit('user_created', 'user', 0, ['email' => $email]);
        flash('success', 'Usuário criado. Ele deverá trocar a senha no primeiro acesso.');
        redirect('users');
    }

    public function update(): void
    {
        auth_admin();
        csrf_verify();

        $id     = (int)($_POST['id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $email  = mb_strtolower(trim($_POST['email'] ?? ''));
        $role   = $_POST['role'] ?? 'user';
        $active = (int)($_POST['active'] ?? 1);

        $errors = [];
        if (!in_array($role, ['admin', 'user'], true)) $errors[] = 'Perfil invalido.';
        if ($name === '')                              $errors[] = 'Nome obrigatório.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail inválido.';

        // E-mail único (exceto o próprio)
        $dup = db()->prepare('SELECT id FROM users WHERE email=? AND id!=? LIMIT 1');
        $dup->execute([$email, $id]);
        if (!empty($_POST['new_password'])) $errors = array_merge($errors, password_errors($_POST['new_password']));
        if ($dup->fetch()) $errors[] = 'E-mail já utilizado por outro usuário.';

        if ($errors) {
            foreach ($errors as $e) flash('error', $e);
            redirect('users');
        }

        db()->prepare('UPDATE users SET name=?, email=?, role=?, active=? WHERE id=?')
             ->execute([$name, $email, $role, $active, $id]);

        // Se enviou nova senha
        if (!empty($_POST['new_password']) && !password_errors($_POST['new_password'])) {
            $hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            db()->prepare('UPDATE users SET password_hash=?, force_change_password=0 WHERE id=?')
                 ->execute([$hash, $id]);
        }

        audit('user_updated', 'user', $id);
        flash('success', 'Usuário atualizado.');
        redirect('users');
    }

    public function destroy(): void
    {
        auth_admin();
        csrf_verify();

        $id = (int)($_POST['id'] ?? 0);

        if ($id === current_user_id()) {
            flash('error', 'Você não pode excluir sua própria conta.');
            redirect('users');
        }

        db()->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
        audit('user_deleted', 'user', $id);
        flash('success', 'Usuário removido.');
        redirect('users');
    }

    public function profile(): void
    {
        auth_check();
        $user = db()->prepare('SELECT id, name, email, role FROM users WHERE id=?');
        $user->execute([current_user_id()]);
        $user = $user->fetch();
        view('users/profile', compact('user'));
    }

    public function updateProfile(): void
    {
        auth_check();
        csrf_verify();

        $name        = trim($_POST['name'] ?? '');
        $email       = mb_strtolower(trim($_POST['email'] ?? ''));
        $oldPassword = $_POST['old_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirm     = $_POST['confirm_password'] ?? '';
        $userId      = current_user_id();

        $errors = [];
        $dup = db()->prepare('SELECT id FROM users WHERE email=? AND id!=? LIMIT 1');
        $dup->execute([$email, $userId]);
        if ($dup->fetch()) $errors[] = 'E-mail ja utilizado por outro usuario.';
        if ($name === '')                              $errors[] = 'Nome obrigatório.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail inválido.';

        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id=?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if ($newPassword !== '') {
            if (!password_verify($oldPassword, $user['password_hash'])) {
                $errors[] = 'Senha atual incorreta.';
            }
            $errors = array_merge($errors, password_errors($newPassword));
            if ($newPassword !== $confirm) {
                $errors[] = 'Confirmação de senha não confere.';
            }
        }

        if ($errors) {
            foreach ($errors as $e) flash('error', $e);
            redirect('users/profile');
        }

        db()->prepare('UPDATE users SET name=?, email=? WHERE id=?')
             ->execute([$name, $email, $userId]);

        if ($newPassword !== '') {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            db()->prepare('UPDATE users SET password_hash=?, force_change_password=0 WHERE id=?')
                 ->execute([$hash, $userId]);
        }

        $_SESSION['user_name'] = $name;

        audit('profile_updated', 'user', $userId);
        flash('success', 'Perfil atualizado com sucesso.');
        redirect('users/profile');
    }
}
