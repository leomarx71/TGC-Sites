<?php

class BackupManager
{
    public static function createBackupSnapshot(string $adminId = 'system'): array
    {
        if (!class_exists('ZipArchive')) {
            return [
                'success' => false,
                'message' => 'ZipArchive nao esta disponivel no servidor'
            ];
        }

        if (!defined('JSON_PATH') || !defined('BACKUPS_PATH')) {
            return [
                'success' => false,
                'message' => 'Constantes de caminho nao configuradas'
            ];
        }

        try {
            self::ensureDirectory(BACKUPS_PATH);

            $sourceDir = realpath(JSON_PATH);
            if ($sourceDir === false || !is_dir($sourceDir)) {
                return [
                    'success' => false,
                    'message' => 'Diretorio JSON_PATH nao encontrado'
                ];
            }

            $sourceDir = rtrim($sourceDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.zip';
            $zipPath = BACKUPS_PATH . $filename;

            if (file_exists($zipPath)) {
                $filename = 'backup_' . date('Y-m-d_H-i-s') . '_' . uniqid('', true) . '.zip';
                $zipPath = BACKUPS_PATH . $filename;
            }

            $tmpZipPath = $zipPath . '.tmp';
            if (file_exists($tmpZipPath)) {
                @unlink($tmpZipPath);
            }

            $zip = new ZipArchive();
            if ($zip->open($tmpZipPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                return [
                    'success' => false,
                    'message' => 'Nao foi possivel criar o arquivo ZIP'
                ];
            }

            $added = 0;
            $failedFiles = [];

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }

                $absolutePath = $fileInfo->getPathname();
                if (!is_readable($absolutePath)) {
                    $failedFiles[] = $absolutePath;
                    continue;
                }

                $relativePath = substr($absolutePath, strlen($sourceDir));
                $entryPath = 'json/' . ltrim(str_replace('\\', '/', $relativePath), '/');

                if ($zip->addFile($absolutePath, $entryPath)) {
                    $added++;
                } else {
                    $failedFiles[] = $absolutePath;
                }
            }

            if (defined('LOG_FILE') && is_file(LOG_FILE) && is_readable(LOG_FILE)) {
                if ($zip->addFile(LOG_FILE, 'logs/execution.log')) {
                    $added++;
                } else {
                    $failedFiles[] = LOG_FILE;
                }
            }

            if (!$zip->close()) {
                @unlink($tmpZipPath);
                return [
                    'success' => false,
                    'message' => 'Falha ao finalizar o arquivo ZIP'
                ];
            }

            if ($added === 0) {
                @unlink($tmpZipPath);
                return [
                    'success' => false,
                    'message' => 'Nenhum arquivo encontrado em JSON_PATH para backup'
                ];
            }

            if (!rename($tmpZipPath, $zipPath)) {
                @unlink($tmpZipPath);
                return [
                    'success' => false,
                    'message' => 'Falha ao mover backup para o destino final'
                ];
            }

            self::safeLog('success', "Backup criado por {$adminId}: {$filename} ({$added} arquivos)");

            return [
                'success' => true,
                'message' => 'Backup criado com sucesso',
                'filename' => $filename,
                'files_count' => $added,
                'failed_files' => count($failedFiles),
                'backup_path' => $zipPath
            ];
        } catch (Throwable $e) {
            self::safeLog('error', 'BackupManager createBackupSnapshot: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public static function listBackups(): array
    {
        if (!defined('BACKUPS_PATH')) {
            return [
                'success' => false,
                'message' => 'Constante BACKUPS_PATH nao configurada',
                'backups' => []
            ];
        }

        self::ensureDirectory(BACKUPS_PATH);

        $files = glob(BACKUPS_PATH . '*.zip');
        if (!is_array($files)) {
            $files = [];
        }

        usort($files, static function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });

        $backups = [];
        foreach ($files as $file) {
            $filename = basename($file);
            $mtime = @filemtime($file);
            $size = @filesize($file);

            $backups[] = [
                'filename' => $filename,
                'created_at' => $mtime ? date('d/m/Y H:i:s', $mtime) : 'N/D',
                'size_bytes' => $size !== false ? (int)$size : 0,
                'size_human' => self::formatBytes($size !== false ? (int)$size : 0),
                'download_url' => 'storage/backups/' . rawurlencode($filename)
            ];
        }

        return [
            'success' => true,
            'message' => 'Lista de backups carregada',
            'total' => count($backups),
            'backups' => $backups
        ];
    }

    public static function clearLogs(string $adminId = 'system'): array
    {
        if (!defined('LOG_FILE')) {
            return [
                'success' => false,
                'message' => 'Constante LOG_FILE nao configurada'
            ];
        }

        try {
            self::ensureDirectory(dirname(LOG_FILE));

            $previousSize = is_file(LOG_FILE) ? (int)filesize(LOG_FILE) : 0;
            if (@file_put_contents(LOG_FILE, '', LOCK_EX) === false) {
                return [
                    'success' => false,
                    'message' => 'Falha ao limpar arquivo de log'
                ];
            }

            self::safeLog('warning', "Logs limpos por {$adminId}");

            return [
                'success' => true,
                'message' => 'Logs limpos com sucesso',
                'bytes_removed' => $previousSize
            ];
        } catch (Throwable $e) {
            self::safeLog('error', 'BackupManager clearLogs: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public static function resetSeason(string $adminId = 'system'): array
    {
        if (!defined('JSON_PATH')) {
            return [
                'success' => false,
                'message' => 'Constante JSON_PATH nao configurada'
            ];
        }

        $backupResult = self::createBackupSnapshot($adminId);
        if (empty($backupResult['success'])) {
            return [
                'success' => false,
                'message' => 'Falha ao criar backup antes do reset: ' . ($backupResult['message'] ?? 'erro desconhecido')
            ];
        }

        $stateResetCount = 0;
        $historyResetCount = 0;
        $errors = [];

        try {
            $iterator = new DirectoryIterator(JSON_PATH);
            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }

                $filename = $fileInfo->getFilename();
                if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'json') {
                    continue;
                }

                $fullPath = $fileInfo->getPathname();

                if (self::endsWith($filename, '-history.json')) {
                    if (@file_put_contents($fullPath, "[]\n", LOCK_EX) !== false) {
                        $historyResetCount++;
                    } else {
                        $errors[] = $filename;
                    }
                    continue;
                }

                $stateData = self::readJsonFile($fullPath);
                $resetData = self::buildResetState($stateData, $filename);
                $json = json_encode($resetData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                if ($json === false || @file_put_contents($fullPath, $json, LOCK_EX) === false) {
                    $errors[] = $filename;
                    continue;
                }

                $stateResetCount++;
            }

            self::safeLog('warning', "Temporada resetada por {$adminId}: {$stateResetCount} estados, {$historyResetCount} historicos");

            return [
                'success' => empty($errors),
                'message' => empty($errors)
                    ? 'Temporada resetada com sucesso'
                    : 'Temporada resetada com pendencias em alguns arquivos',
                'backup_file' => $backupResult['filename'] ?? null,
                'states_reset' => $stateResetCount,
                'histories_cleared' => $historyResetCount,
                'failed_files' => $errors
            ];
        } catch (Throwable $e) {
            self::safeLog('error', 'BackupManager resetSeason: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private static function buildResetState(array $state, string $filename): array
    {
        $id = isset($state['id']) ? (int)$state['id'] : self::extractIdFromFilename($filename);
        $title = isset($state['title']) && is_string($state['title']) && $state['title'] !== ''
            ? $state['title']
            : ($id > 0 ? 'Torneio #' . $id : pathinfo($filename, PATHINFO_FILENAME));

        if (empty($state)) {
            return [
                'id' => $id,
                'title' => $title,
                'lastUpdate' => '',
                'latestResult' => [
                    'phase' => 'Rodada 0',
                    'drawnItems' => [],
                    'date' => ''
                ],
                'usedItems' => ['NULL'],
                'roundCounter' => 0
            ];
        }

        $state['id'] = $id;
        $state['title'] = $title;
        $state['lastUpdate'] = '';
        $state['latestResult'] = [
            'phase' => 'Rodada 0',
            'drawnItems' => [],
            'date' => ''
        ];
        $state['usedItems'] = ['NULL'];
        $state['roundCounter'] = 0;

        foreach ($state as $key => $value) {
            if (strpos($key, 'usedItems') === 0 && $key !== 'usedItems') {
                $state[$key] = [];
                continue;
            }

            if ($key !== 'roundCounter' && self::endsWith($key, 'Counter')) {
                $state[$key] = 0;
            }
        }

        return $state;
    }

    private static function readJsonFile(string $path): array
    {
        $raw = @file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function extractIdFromFilename(string $filename): int
    {
        if (preg_match('/^(\d+)/', $filename, $matches)) {
            return (int)$matches[1];
        }

        return 0;
    }

    private static function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !@mkdir($path, 0777, true) && !is_dir($path)) {
            throw new RuntimeException('Nao foi possivel criar diretorio: ' . $path);
        }
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = (int)floor(log($bytes, 1024));
        $pow = min($pow, count($units) - 1);
        $value = $bytes / (1024 ** $pow);

        return number_format($value, $pow === 0 ? 0 : 2, ',', '.') . ' ' . $units[$pow];
    }

    private static function endsWith(string $value, string $suffix): bool
    {
        if ($suffix === '') {
            return true;
        }

        return substr($value, -strlen($suffix)) === $suffix;
    }

    private static function safeLog(string $level, string $message): void
    {
        if (!class_exists('Logger')) {
            return;
        }

        switch (strtolower($level)) {
            case 'error':
                Logger::error($message);
                break;
            case 'success':
                Logger::success($message);
                break;
            case 'warning':
                Logger::warning($message);
                break;
            default:
                Logger::info($message);
                break;
        }
    }
}