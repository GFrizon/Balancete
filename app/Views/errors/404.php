<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Página não encontrada</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">
<div class="text-center">
  <h1 class="display-1 text-muted">404</h1>
  <h4 class="mb-3">Página não encontrada</h4>
  <a href="<?= defined('BASE_URL') ? BASE_URL . '/dashboard' : '/dashboard' ?>" class="btn btn-primary">
    Voltar ao início
  </a>
</div>
</body>
</html>
