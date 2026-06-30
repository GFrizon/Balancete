<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? APP_NAME) ?> — <?= e(APP_NAME) ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/favicon.svg">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
  <script>window.APP_BASE_URL = '<?= BASE_URL ?>';</script>
</head>
<body>

<?php if (is_logged_in()): ?>
<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark app-navbar">
  <div class="container-fluid px-3">
    <a class="navbar-brand fw-bold" href="<?= url('dashboard') ?>">
      <i class="bi bi-bar-chart-line-fill me-2"></i><?= e(APP_NAME) ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarMain">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link <?= str_starts_with($_GET['route'] ?? '', 'dashboard') || trim($_GET['route'] ?? '') === '' ? 'active' : '' ?>"
             href="<?= url('dashboard') ?>"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= str_starts_with($_GET['route'] ?? '', 'dre') ? 'active' : '' ?>"
             href="<?= url('dre') ?>"><i class="bi bi-list-tree me-1"></i>DRE</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= str_starts_with($_GET['route'] ?? '', 'imports') ? 'active' : '' ?>"
             href="<?= url('imports') ?>"><i class="bi bi-cloud-upload me-1"></i>Importações</a>
        </li>
        <?php if (current_user_role() === 'admin'): ?>
        <li class="nav-item">
          <a class="nav-link <?= str_starts_with($_GET['route'] ?? '', 'groups') ? 'active' : '' ?>"
             href="<?= url('groups') ?>"><i class="bi bi-diagram-3 me-1"></i>Grupos</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= str_starts_with($_GET['route'] ?? '', 'users') ? 'active' : '' ?>"
             href="<?= url('users') ?>"><i class="bi bi-people me-1"></i>Usuários</a>
        </li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav ms-auto align-items-center">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle me-1"></i><?= e(current_user_name()) ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="<?= url('users/profile') ?>">
              <i class="bi bi-person me-2"></i>Meu Perfil</a></li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <form method="POST" action="<?= url('logout') ?>" class="m-0">
                <?= csrf_field() ?>
                <button type="submit" class="dropdown-item text-danger">
                  <i class="bi bi-box-arrow-right me-2"></i>Sair
                </button>
              </form>
            </li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>
<?php endif; ?>

<!-- Flash Messages -->
<?php
$flashes = get_flashes();
if ($flashes):
?>
<div class="flash-container">
  <?php foreach ($flashes as $f): ?>
  <div class="alert alert-<?= $f['type'] === 'error' ? 'danger' : e($f['type']) ?> alert-dismissible fade show mb-2" role="alert">
    <?= e($f['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<main class="<?= e($mainClass ?? 'app-main') ?>">
