<?php
declare(strict_types=1);

/**
 * Front controller — ponto de entrada único da aplicação
 */

// -------------------------------------------------------
// Bootstrap
// -------------------------------------------------------
define('APP_START', microtime(true));

$appRoot = dirname(__DIR__);
require_once $appRoot . '/config/app.php';
require_once $appRoot . '/config/database.php';
require_once $appRoot . '/app/helpers.php';

security_headers();

// Carrega controllers
foreach (glob($appRoot . '/app/Controllers/*.php') as $file) {
    require_once $file;
}
// Carrega services
foreach (glob($appRoot . '/app/Services/*.php') as $file) {
    require_once $file;
}

session_start_safe();

// -------------------------------------------------------
// Roteamento simples
// -------------------------------------------------------
$route = trim($_GET['route'] ?? '', '/');

// Normaliza separadores (alguns servidores enviam barras extras)
$route = preg_replace('#/+#', '/', $route);

// Método HTTP
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// -------------------------------------------------------
// Tabela de rotas
// -------------------------------------------------------
$routes = [
    // [método, padrão regex, controller, action]
    ['GET',  '',                         'AuthController',       'showLogin'],
    ['GET',  'login',                    'AuthController',       'showLogin'],
    ['POST', 'login',                    'AuthController',       'doLogin'],
    ['POST', 'logout',                   'AuthController',       'doLogout'],

    ['GET',  'setup',                    'AuthController',       'showSetup'],
    ['POST', 'setup',                    'AuthController',       'doSetup'],

    ['GET',  'dashboard',                'DashboardController',  'index'],

    ['GET',  'imports',                  'ImportController',     'index'],
    ['GET',  'imports/create',           'ImportController',     'create'],
    ['POST', 'imports/create',           'ImportController',     'store'],
    ['GET',  'imports/(\d+)/preview',    'ImportController',     'preview'],
    ['POST', 'imports/(\d+)/confirm',    'ImportController',     'confirm'],
    ['POST', 'imports/(\d+)/delete',     'ImportController',     'destroy'],

    ['GET',  'dre',                      'DreController',        'index'],
    ['GET',  'dre/details',              'DreController',        'details'],
    ['GET',  'dre/export',               'DreController',        'export'],

    ['GET',  'groups',                   'GroupController',      'index'],
    ['POST', 'groups/store',             'GroupController',      'store'],
    ['POST', 'groups/update',            'GroupController',      'update'],
    ['POST', 'groups/delete',            'GroupController',      'destroy'],

    ['GET',  'users',                    'UserController',       'index'],
    ['POST', 'users/store',              'UserController',       'store'],
    ['POST', 'users/update',             'UserController',       'update'],
    ['POST', 'users/delete',             'UserController',       'destroy'],
    ['GET',  'users/profile',            'UserController',       'profile'],
    ['POST', 'users/profile',            'UserController',       'updateProfile'],
];

// -------------------------------------------------------
// Dispatch
// -------------------------------------------------------
$matched   = false;
$routeArgs = [];

foreach ($routes as [$rMethod, $rPattern, $controller, $action]) {
    if ($method !== $rMethod) {
        continue;
    }
    $regex = '#^' . $rPattern . '$#';
    if (preg_match($regex, $route, $matches)) {
        $matched   = true;
        $routeArgs = array_slice($matches, 1);
        break;
    }
}

if (!$matched) {
    http_response_code(404);
    view('errors/404');
    exit;
}

if (!class_exists($controller)) {
    http_response_code(500);
    die("Controller {$controller} não encontrado.");
}

$ctrl = new $controller();

if (!method_exists($ctrl, $action)) {
    http_response_code(500);
    die("Ação {$action} não encontrada em {$controller}.");
}

call_user_func_array([$ctrl, $action], $routeArgs);
