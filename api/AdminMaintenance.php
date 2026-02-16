<?php
header('Content-Type: application/json; charset=UTF-8');

require_once '../storage/data/Config.php';
require_once '../storage/utils/Functions.php';
require_once './BackupManager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Metodo nao permitido'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

$action = isset($input['action']) ? trim((string)$input['action']) : '';
$adminId = isset($input['admin_id']) ? trim((string)$input['admin_id']) : 'admin-panel';

if ($action === '') {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Acao nao informada'
    ]);
    exit;
}

try {
    $result = null;

    switch ($action) {
        case 'create_backup':
            $result = BackupManager::createBackupSnapshot($adminId);
            break;

        case 'list_backups':
            $result = BackupManager::listBackups();
            break;

        case 'clear_logs':
            if (($input['confirm'] ?? false) !== true || ($input['confirmation_text'] ?? '') !== 'LIMPAR_LOGS') {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Confirmacao obrigatoria para limpar logs'
                ]);
                exit;
            }
            $result = BackupManager::clearLogs($adminId);
            break;

        case 'reset_season':
            if (($input['confirm'] ?? false) !== true || ($input['confirmation_text'] ?? '') !== 'RESETAR_TEMPORADA') {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Confirmacao obrigatoria para resetar a temporada'
                ]);
                exit;
            }
            $result = BackupManager::resetSeason($adminId);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Acao invalida'
            ]);
            exit;
    }

    if (!is_array($result)) {
        throw new RuntimeException('Resposta invalida do servico de manutencao');
    }

    if (empty($result['success'])) {
        http_response_code(500);
        echo json_encode(array_merge(['status' => 'error'], $result));
        exit;
    }

    echo json_encode(array_merge(['status' => 'success'], $result));
} catch (Throwable $e) {
    Logger::error('AdminMaintenance erro: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}