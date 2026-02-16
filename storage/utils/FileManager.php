<?php
// storage/utils/FileManager.php

// 1. Carrega Configura��o (Se n�o estiver definida)
if (!defined('JSON_PATH')) {
    require_once __DIR__ . '/../data/Config.php';
}
// 2. Carrega Functions para usar o Logger
require_once __DIR__ . '/Functions.php';

class FileManager {

    private static function init() {
        if (!defined('JSON_PATH')) {
            $msg = "FATAL: Constante JSON_PATH n�o definida.";
            Logger::error($msg);
            throw new Exception("Erro de configura��o do sistema: JSON_PATH ausente.");
        }

        // Tenta criar pasta se n�o existir
        if (!is_dir(JSON_PATH)) {
            Logger::info("Iniciando cria��o do diret�rio: " . JSON_PATH);
            if (!mkdir(JSON_PATH, 0777, true)) {
                $error = error_get_last();
                $msg = "FATAL: Falha ao criar diret�rio " . JSON_PATH . ". Causa: " . $error['message'];
                Logger::error($msg);
                throw new Exception("Permiss�o negada ao criar pasta de dados.");
            }
            Logger::success("Diret�rio criado com sucesso.");
        }

        // Verifica permiss�o de escrita
        if (!is_writable(JSON_PATH)) {
            $msg = "FATAL: Diret�rio " . JSON_PATH . " n�o tem permiss�o de escrita.";
            Logger::error($msg);
            throw new Exception("Diret�rio de dados sem permiss�o de escrita.");
        }
    }

    /**
     * L� um arquivo JSON e retorna array associativo
     */
    public static function readJson($filename) {
        self::init();
        $filepath = JSON_PATH . $filename;

        if (!file_exists($filepath)) {
            Logger::debug("Arquivo n�o encontrado (novo?): $filename");
            return [];
        }

        $content = file_get_contents($filepath);
        if ($content === false) {
            Logger::error("Falha ao ler conte�do de: $filepath");
            return [];
        }

        $data = json_decode($content, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            Logger::error("JSON inv�lido em $filename: " . json_last_error_msg());
            return [];
        }

        return $data ?: [];
    }

    /**
     * Escreve array em arquivo JSON
     */
    public static function writeJson($filename, $data) {
        self::init();
        $filepath = JSON_PATH . $filename;

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            Logger::error("Falha no json_encode para $filename: " . json_last_error_msg());
            return false;
        }

        $result = file_put_contents($filepath, $json);

        if ($result === false) {
            Logger::error("FALHA CR�TICA ao escrever em $filepath. Verifique permiss�es.");
            return false;
        }

        Logger::info("Arquivo gravado: $filename (" . strlen($json) . " bytes)");
        return true;
    }

    /**
     * Salva o estado do sorteio e atualiza hist�rico
     */
    public static function saveGranularDraw($tournamentId, $drawData, $updatedUsedItems = null) {
        Logger::info("FileManager: Iniciando saveGranularDraw para ID $tournamentId");

        $stateFile = $tournamentId . '.json';
        $historyFile = $tournamentId . '-history.json';

        try {
            // 1. Processar Arquivo de Estado
            $currentState = self::readJson($stateFile);

            // Se updatedUsedItems for passado (vindo do ProcessDraw), usa ele.
            // Caso contr�rio, calcula merge simples (comportamento legado para outros sorteios)
            if ($updatedUsedItems !== null) {
                $finalUsedItems = $updatedUsedItems;
            } else {
                $previousUsed = $currentState['usedItems'] ?? [];
                $newItems = $drawData['drawnItems'] ?? [];
                $finalUsedItems = array_values(array_unique(array_merge($previousUsed, $newItems)));
            }

            $newState = [
                'id' => $tournamentId,
                'title' => $drawData['title'],
                'lastUpdate' => date('Y-m-d H:i:s'),
                'latestResult' => [
                    'phase' => $drawData['phase'],
                    'drawnItems' => $drawData['drawnItems'],
                    'date' => date('Y-m-d H:i:s')
                ],
                'usedItems' => $finalUsedItems
            ];

            if (!self::writeJson($stateFile, $newState)) {
                throw new Exception("Falha ao gravar arquivo de estado $stateFile");
            }

            // 2. Processar Hist�rico
            $history = self::readJson($historyFile);
            if (!is_array($history)) $history = [];

            array_unshift($history, $newState['latestResult']);

            if (!self::writeJson($historyFile, $history)) {
                throw new Exception("Falha ao gravar arquivo de hist�rico $historyFile");
            }

            Logger::success("FileManager: Opera��o conclu�da para ID $tournamentId");
            return true;

        } catch (Exception $e) {
            Logger::error("FileManager Exception: " . $e->getMessage());
            throw $e;
        }
    }
}
?>