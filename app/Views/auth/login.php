<?php $pageTitle = 'Login'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — <?= e(APP_NAME) ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/favicon.svg">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
</head>
<body class="login-bg d-flex align-items-center justify-content-center min-vh-100">

<div class="login-card card shadow-lg">
  <div class="card-body p-5">
    <div class="text-center mb-4">
      <div class="login-icon mb-3">
        <i class="bi bi-bar-chart-line-fill"></i>
      </div>
      <h2 class="fw-bold"><?= e(APP_NAME) ?></h2>
      <p class="text-muted mb-0">DRE gerada diretamente do balancete</p>
    </div>

    <?php foreach (get_flashes() as $f): ?>
    <div class="alert alert-<?= $f['type'] === 'error' ? 'danger' : e($f['type']) ?> py-2 small">
      <?= e($f['message']) ?>
    </div>
    <?php endforeach; ?>

    <form method="POST" action="<?= url('login') ?>">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label fw-semibold">E-mail</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-envelope"></i></span>
          <input type="email" name="email" class="form-control form-control-lg"
                 placeholder="seu@email.com" required autofocus autocomplete="username"
                 value="<?= e($_POST['email'] ?? '') ?>">
        </div>
      </div>
      <div class="mb-4">
        <label class="form-label fw-semibold">Senha</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input type="password" name="password" id="pwdInput" class="form-control form-control-lg" autocomplete="current-password"
                 placeholder="••••••••" required>
          <button type="button" class="btn btn-outline-secondary" onclick="togglePwd()">
            <i class="bi bi-eye" id="pwdIcon"></i>
          </button>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-lg w-100">
        <i class="bi bi-box-arrow-in-right me-2"></i>Entrar
      </button>
    </form>

    <?php if (!empty($canSetup)): ?>
      <div class="text-center mt-4">
        <small class="text-muted">
          Primeiro acesso? <a href="<?= url('setup') ?>">Configure o administrador</a>
        </small>
      </div>
    <?php endif; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePwd() {
  const i = document.getElementById('pwdInput');
  const ic = document.getElementById('pwdIcon');
  if (i.type === 'password') {
    i.type = 'text';
    ic.className = 'bi bi-eye-slash';
  } else {
    i.type = 'password';
    ic.className = 'bi bi-eye';
  }
}
</script>
</body>
</html>
