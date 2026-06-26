<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Setup Inicial — <?= e(APP_NAME) ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/favicon.svg">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
</head>
<body class="login-bg d-flex align-items-center justify-content-center min-vh-100">
<div class="login-card card shadow-lg">
  <div class="card-body p-5">
    <div class="text-center mb-4">
      <div class="login-icon mb-3"><i class="bi bi-gear-fill"></i></div>
      <h2 class="fw-bold">Setup Inicial</h2>
      <p class="text-muted">Crie a conta de administrador</p>
    </div>
    <?php foreach (get_flashes() as $f): ?>
    <div class="alert alert-<?= $f['type'] === 'error' ? 'danger' : e($f['type']) ?> py-2 small"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <form method="POST" action="<?= url('setup') ?>">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label fw-semibold">Nome completo</label>
        <input type="text" name="name" class="form-control" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold">E-mail</label>
        <input type="email" name="email" class="form-control" required autocomplete="username">
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold">Senha (minimo 10 caracteres, com letras e numeros)</label>
        <input type="password" name="password" class="form-control" required minlength="10" autocomplete="new-password">
      </div>
      <div class="mb-4">
        <label class="form-label fw-semibold">Confirmar senha</label>
        <input type="password" name="confirm" class="form-control" required minlength="10" autocomplete="new-password">
      </div>
      <button type="submit" class="btn btn-success btn-lg w-100">
        <i class="bi bi-check-circle me-2"></i>Criar administrador
      </button>
    </form>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
