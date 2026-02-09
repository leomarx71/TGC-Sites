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
            // Extrai o ID do nome do arquivo (ex: 101-history.json -> 101)
            $filename = basename($file);
            $id = str_replace('-history.json', '', $filename);
            
            // Lê o histórico
            $fileContent = file_get_contents($file);
            if (!$fileContent) continue; // Pula se vazio
            
            $historyData = json_decode($fileContent, true);
            
            // Tenta ler o arquivo de estado principal para pegar o Título atualizado
            $stateFile = $jsonPath . $id . '.json';
            $tournamentTitle = "Torneio #$id"; // Fallback
            
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
                    // Formata os itens sorteados
                    $itemsString = "Nenhum item";
                    if (isset($entry['drawnItems']) && is_array($entry['drawnItems'])) {
                        $itemsString = implode(', ', $entry['drawnItems']);
                    }

                    // Define observação (Lógica para categorizar visualmente no front)
                    $obs = "";
                    
                    if (strpos($id, '3') === 0) { 
                        // IDs 300+ (Top Gear 3000)
                        $obs = "Sistema Planetário";
                    } elseif (strpos($id, '2') === 0) {
                        // IDs 200+ (Top Gear 2)
                        // Lógica específica: se tiver 2 itens, provavelmente é país + pista
                        if (count($entry['drawnItems'] ?? []) == 2) {
                            $obs = "Sorteio de Países";
                        }
                    } elseif ($id == '501') {
                        // IDs 501 (Carros Proibidos)
                        $obs = ""; // Observação vazia conforme solicitado
                    } elseif (strpos($id, '4') === 0) {
                        // IDs 400+ (Cenários)
                        $obs = "Cenário Especial";
                    }

                    $globalHistory[] = [
                        'date_raw' => $entry['date'] ?? '', // Para ordenação
                        'date_formatted' => isset($entry['date']) ? date('d/m/Y H:i', strtotime($entry['date'])) : 'N/D',
                        'tournament' => $tournamentTitle,
                        'phase' => $entry['phase'] ?? 'N/D',
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
        // Garante que datas vazias fiquem no final
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