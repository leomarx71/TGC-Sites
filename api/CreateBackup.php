<?php
header('Content-Type: application/json');

require_once '../storage/data/Config.php';
require_once '../storage/utils/Functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);
    exit;
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Extensão ZipArchive não disponível no servidor']);
    exit;
}

try {
    if (!is_dir(BACKUPS_PATH)) {
        if (!mkdir(BACKUPS_PATH, 0777, true) && !is_dir(BACKUPS_PATH)) {
            throw new Exception('Falha ao criar diretório de backups');
        }
    }

    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.zip';
    $zipPath = BACKUPS_PATH . $filename;

    if (file_exists($zipPath)) {
        // Evita sobrescrever caso duas requisições ocorram no mesmo segundo.
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.zip';
        $zipPath = BACKUPS_PATH . $filename;
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
        throw new Exception('Não foi possível criar o arquivo ZIP');
    }

    $base = rtrim(str_replace('\\', '/', BASE_PATH), '/') . '/';
    $excludeDir = rtrim(str_replace('\\', '/', BACKUPS_PATH), '/') . '/';
    $added = 0;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(BASE_PATH, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }
        $path = str_replace('\\', '/', $fileInfo->getPathname());
        if (strpos($path, $excludeDir) === 0) {
            continue;
        }
        if (strtolower($fileInfo->getExtension()) !== 'json') {
            continue;
        }

        $relativePath = ltrim(substr($path, strlen($base)), '/');
        if ($zip->addFile($path, $relativePath)) {
            $added++;
        }
    }

    // Inclui log atual como apoio de manutenção.
    if (defined('LOG_FILE') && is_file(LOG_FILE)) {
        $logPath = str_replace('\\', '/', LOG_FILE);
        $relativeLog = ltrim(substr($logPath, strlen($base)), '/');
        if ($zip->addFile(LOG_FILE, $relativeLog)) {
            $added++;
        }
    }

    $zip->close();

    if ($added === 0) {
        @unlink($zipPath);
        throw new Exception('Nenhum arquivo JSON encontrado para backup');
    }

    Logger::success("Backup criado: $filename ($added arquivos)");
    echo json_encode([
        'status' => 'success',
        'message' => 'Backup criado com sucesso',
        'filename' => $filename,
        'files_count' => $added
    ]);
} catch (Exception $e) {
    Logger::error('CreateBackup erro: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>