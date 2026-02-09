<?php
// api/GetHistory.php
header('Content-Type: application/json');

// 1. Carrega Configuração
require_once '../storage/data/Config.php';

if (defined('DEBUG_MODE') && DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

try {
    $jsonPath = defined('JSON_PATH') ? JSON_PATH : __DIR__ . '/../storage/json/';
    
    // Busca todos os arquivos de histórico
    $files = glob($jsonPath . '*-history.json');
    $globalHistory = [];

    if ($files) {
        foreach ($files as $file) {
            $filename = basename($file);
            $id = str_replace('-history.json', '', $filename);
            
            // Lê o histórico
            $fileContent = file_get_contents($file);
            if (!$fileContent) continue;
            
            $historyData = json_decode($fileContent, true);
            
            // Busca título do torneio
            $stateFile = $jsonPath . $id . '.json';
            $tournamentTitle = "Torneio #$id"; 
            
            if (file_exists($stateFile)) {
                $stateContent = file_get_contents($stateFile);
                if ($stateContent) {
                    $stateData = json_decode($stateContent, true);
                    if (isset($stateData['title'])) {
                        $tournamentTitle = $stateData['title'];
                    }
                }
            }

            if (is_array($historyData)) {
                foreach ($historyData as $entry) {
                    $itemsString = "Nenhum item";
                    if (isset($entry['drawnItems']) && is_array($entry['drawnItems'])) {
                        $itemsString = implode(', ', $entry['drawnItems']);
                    }

                    $phase = $entry['phase'] ?? 'N/D';
                    $obs = $entry['obs'] ?? ""; // Observação original se existir
                    
                    // Lógica de Observação Dinâmica
                    if ($id == 102 || $id == 118) {
                        // Se a fase contém "Rodada", destaca isso na observação também
                        if (strpos($phase, 'Rodada') !== false) {
                            $obs = strtoupper($phase); // Ex: "RODADA 5"
                        } else {
                            $obs = "Sorteio de Rodadas";
                        }
                    } elseif (strpos($id, '3') === 0) { 
                        $obs = "Sistema Planetário";
                    } elseif (strpos($id, '2') === 0) {
                        if (count($entry['drawnItems'] ?? []) == 2) {
                            $obs = "Sorteio de Países";
                        }
                    } elseif ($id == '501') {
                        $obs = ""; 
                    } elseif (strpos($id, '4') === 0) {
                        $obs = "Cenário Especial";
                    }

                    $globalHistory[] = [
                        'date_raw' => $entry['date'] ?? '',
                        'date_formatted' => isset($entry['date']) ? date('d/m/Y H:i', strtotime($entry['date'])) : 'N/D',
                        'tournament' => $tournamentTitle,
                        'phase' => $phase,
                        'items' => $itemsString,
                        'items_count' => isset($entry['drawnItems']) ? count($entry['drawnItems']) : 0,
                        'obs' => $obs
                    ];
                }
            }
        }
    }

    // Ordena por data (Mais recente primeiro)
    usort($globalHistory, function ($a, $b) {
        if (empty($a['date_raw'])) return 1;
        if (empty($b['date_raw'])) return -1;
        return strtotime($b['date_raw']) - strtotime($a['date_raw']);
    });

    echo json_encode(['status' => 'success', 'data' => $globalHistory]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>