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
define('DEBUG_MODE', false); // Mantendo true durante desenvolvimento

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    // Força o PHP a usar nosso arquivo de log também
    ini_set('error_log', LOG_FILE);
}

// Versão do sistema
define('APP_VERSION', '1.3');
define('APP_NAME', 'TGC Manager - Sistema de Sorteios');

// ============================================================================
// CONFIGURAÇÕES DE SEGURANÇA
// ============================================================================

// Tempo de sessão em segundos (1 hora)
define('SESSION_TIMEOUT', 3600);

// Credenciais (Usadas no Login)
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'Tgc@2026'); // Senha atualizada

// ============================================================================
// PALETA DE CORES DAS FASES (Centralizado)
// ============================================================================

// F0 - Eliminatórias (Slate/Cinza Escuro)
define('COLOR_F0_BG', 'bg-slate-800 hover:bg-slate-700');
define('COLOR_F0_TXT', 'text-white');
define('COLOR_F0_BADGE', 'bg-slate-700');

// F1 - Fase de Grupos (Azul)
define('COLOR_F1_BG', 'bg-blue-500 hover:bg-blue-400');
define('COLOR_F1_TXT', 'text-white');
define('COLOR_F1_BADGE', 'bg-blue-500');

// F2 - 8° de Final (Verde)
define('COLOR_F2_BG', 'bg-green-600 hover:bg-green-500');
define('COLOR_F2_TXT', 'text-white');
define('COLOR_F2_BADGE', 'bg-green-600');

// F3 - 4° de Final (Amarelo)
define('COLOR_F3_BG', 'bg-yellow-400 hover:bg-yellow-300');
define('COLOR_F3_TXT', 'text-yellow-900');
define('COLOR_F3_BADGE', 'bg-yellow-400 text-yellow-900');

// F4 - Semifinal (Laranja)
define('COLOR_F4_BG', 'bg-orange-500 hover:bg-orange-400');
define('COLOR_F4_TXT', 'text-white');
define('COLOR_F4_BADGE', 'bg-orange-500');

// F5 - Final e 3° (Vermelho)
define('COLOR_F5_BG', 'bg-red-600 hover:bg-red-500');
define('COLOR_F5_TXT', 'text-white');
define('COLOR_F5_BADGE', 'bg-red-600');

// F6 - Rodada (Roxo)
define('COLOR_F6_BG', 'bg-purple-600 hover:bg-purple-500');
define('COLOR_F6_TXT', 'text-white');
define('COLOR_F6_BADGE', 'bg-purple-600');

?>