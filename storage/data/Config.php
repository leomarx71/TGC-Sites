<?php
/**
 * Configurações Globais do Sistema
 * * Arquivo centralizado para todas as constantes e configurações
 */

// ============================================================================
// DEFINIÇÃO DE DIRETÓRIOS
// ============================================================================

// Define o caminho base subindo dois níveis de storage/data/
define('BASE_PATH', dirname(__DIR__, 2) . '/'); 
define('STORAGE_PATH', BASE_PATH . 'storage/');
define('DATA_PATH', STORAGE_PATH . 'data/');
define('JSON_PATH', STORAGE_PATH . 'json/');
define('LOG_FILE', STORAGE_PATH . '/logs/execution.log');
define('BACKUPS_PATH', STORAGE_PATH . 'backups/');
define('UTILS_PATH', STORAGE_PATH . 'utils/');

// ============================================================================
// CONFIGURAÇÕES DE SISTEMA
// ============================================================================

// Timezone
date_default_timezone_set('America/Sao_Paulo');
ini_set('default_charset', 'UTF-8');

// Modo debug (false em produção)
define('DEBUG_MODE', true); // Mantendo true durante desenvolvimento

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    // Força o PHP a usar nosso arquivo de log também
    ini_set('error_log', LOG_FILE);
}

// Versão do sistema
define('APP_VERSION', '1.1.0');
define('APP_NAME', 'TGC Manager - Sistema de Sorteios');

// ============================================================================
// CONFIGURAÇÕES DE SEGURANÇA
// ============================================================================

// Tempo de sessão em segundos (1 hora)
define('SESSION_TIMEOUT', 3600);

// Credenciais (Usadas no Login)
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'Tgc@2026'); // Senha atualizada

?>