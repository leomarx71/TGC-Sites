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
            $idStr = str_replace('-history.json', '', $filename);
            $id = (int)$idStr; // Converte para inteiro para comparações seguras
            
            // Lê o histórico
            $fileContent = file_get_contents($file);
            if (!$fileContent) continue;
            
            $historyData = json_decode($fileContent, true);
            
            // Busca título do torneio
            $stateFile = $jsonPath . $idStr . '.json';
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
                    
                    // --- LÓGICA DE OBSERVAÇÃO DINÂMICA ---
                    
                    // 1. Regra Específica do Torneio 109
                    if ($id === 109) {
                        $obs = "Jogo de Ida e Volta. O desempate é jogado com o carro proibido no país subsequente.";
                    }
                    // 2. Regra Específica do Torneio 117
                    elseif ($id === 117) {
                        if ($phase === 'F5' || $phase === 'Final e 3°') {
                            $obs = "Devem ser utilizados todos os 4 carros na disputa, sendo um carro a cada 2 países e o desempate deve ser iniciado no país seguinte ao último sorteado.";
                        } else {
                            $obs = "Sorteio de Países";
                        }
                    }
                    // 3. Regra Específica do Torneio 110
                    elseif ($id === 110) {
                        if (stripos($phase, 'F1') !== false || stripos($phase, 'Grupos') !== false) {
                            $obs = "Carro LIVRE na fases de grupo (com exceção do Proibido)";
                        }
                        elseif (stripos($phase, 'F2') !== false || stripos($phase, '8') !== false) {
                            $obs = "Devem ser utilizados 2 (dois) carros na disputa, sendo um carro nas 7 primeiras pistas e outro nas 7 últimas pista (com exceção do Proibido)";
                        }
                        elseif (stripos($phase, 'F3') !== false || stripos($phase, '4') !== false) {
                            $obs = "Devem ser utilizados 2 (dois) carros na disputa, sendo um carro nas 8 primeiras pistas e outro nas 8 últimas pista (com exceção do Proibido)";
                        }
                        elseif (stripos($phase, 'F4') !== false || stripos($phase, 'Semifinal') !== false) {
                            $obs = "Devem ser utilizados 3 (três) carros na disputa, sendo um carro a cada 7 pistas (com exceção do Proibido)";
                        }
                        elseif (stripos($phase, 'F5') !== false || stripos($phase, 'Final') !== false) {
                            $obs = "Devem ser utilizados todos os 4 carros na disputa, sendo um carro a cada 6 pistas";
                        }
                    }
                    // 4. Torneios de Rodadas (102, 118, 106)
                    elseif ($id === 102 || $id === 118 || $id === 106) {
                        if (strpos($phase, 'Rodada') !== false) {
                            $obs = strtoupper($phase); 
                        } else {
                            $obs = "Sorteio de Rodadas";
                        }
                    }
                    // 5. Regra para os torneios de países
                    elseif (in_array($id, [101, 107, 112, 116])) {
                         $obs = "Sorteio de Países";
                    }
                    // 6. Outras regras baseadas em ID
                    elseif (strpos((string)$id, '3') === 0) { 
                        $obs = "Sistema Planetário";
                    } elseif (strpos((string)$id, '2') === 0) {
                        if (count($entry['drawnItems'] ?? []) == 2) {
                            $obs = "Sorteio de Países";
                        }
                    } elseif ($id === 501) {
                        $obs = ""; 
                    } elseif (strpos((string)$id, '4') === 0) {
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

    // Ordena por data
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