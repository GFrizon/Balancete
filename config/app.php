<?php
/**
 * Configuração geral da aplicação
 */

define('APP_NAME',    'Balancete DRE');
define('APP_VERSION', '1.0.0');

// Chave secreta para tokens CSRF (altere para uma string aleatória longa)
define('APP_KEY', 'troque-esta-chave-por-uma-string-aleatoria-segura-32chars');

// Fuso horário
date_default_timezone_set('America/Sao_Paulo');

// Tamanho máximo de upload (em bytes) — 20 MB
define('MAX_UPLOAD_SIZE', 20 * 1024 * 1024);

// Extensões permitidas para upload
define('ALLOWED_EXTENSIONS', ['txt', 'rtf', 'doc']);

// Caminho absoluto para a raiz do projeto (um nível acima de public/)
define('APP_ROOT', dirname(__DIR__));

// Caminho para armazenamento de arquivos
define('STORAGE_PATH', APP_ROOT . DIRECTORY_SEPARATOR . 'storage');
define('UPLOADS_PATH', STORAGE_PATH . DIRECTORY_SEPARATOR . 'uploads');
define('EXPORTS_PATH', STORAGE_PATH . DIRECTORY_SEPARATOR . 'exports');

// URL base (detectada automaticamente; sobrescreva se necessário).
// Exemplo manual para subpasta: define('BASE_URL', '/balancete/public');
if (!defined('BASE_URL')) {
    // SCRIPT_NAME = /index.php  → dirname = /  → BASE_URL = ''
    // SCRIPT_NAME = /balancete/public/index.php → dirname = /balancete/public → BASE_URL = '/balancete/public'
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $scriptDir = rtrim($scriptDir, '/');
    define('BASE_URL', $scriptDir); // pode ser '' (raiz) ou '/subpasta'
}

// Meses em português
define('MONTHS_PT', [
    1  => 'Janeiro',
    2  => 'Fevereiro',
    3  => 'Março',
    4  => 'Abril',
    5  => 'Maio',
    6  => 'Junho',
    7  => 'Julho',
    8  => 'Agosto',
    9  => 'Setembro',
    10 => 'Outubro',
    11 => 'Novembro',
    12 => 'Dezembro',
]);

define('MONTHS_PT_SHORT', [
    'JAN'=>1,'FEV'=>2,'MAR'=>3,'ABR'=>4,'MAI'=>5,'JUN'=>6,
    'JUL'=>7,'AGO'=>8,'SET'=>9,'OUT'=>10,'NOV'=>11,'DEZ'=>12,
]);
