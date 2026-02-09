<?php
// api/ProcessDraw.php
header('Content-Type: application/json');

require_once '../storage/data/Config.php';
require_once '../storage/data/Data.php';
require_once '../storage/utils/Functions.php';
require_once '../storage/utils/FileManager.php';

// Ativa debug se necessário
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// Verifica Método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);
    exit;
}

// Lê Input
$input = json_decode(file_get_contents('php://input'), true);
$tournamentId = $input['tournamentId'] ?? null;
// A fase que vem do front (ex: "Rodada") será sobrescrita pela lógica do contador server-side
$inputPhaseLabel = $input['phase'] ?? 'Rodada'; 
$title = $input['title'] ?? "Torneio $tournamentId";

if (!$tournamentId) {
    echo json_encode(['status' => 'error', 'message' => 'ID do torneio obrigatório']);
    exit;
}

Logger::info("ProcessDraw: Iniciando sorteio para ID $tournamentId");

try {
    // 1. Carrega Estado Atual
    $stateFile = $tournamentId . '.json';
    $currentState = FileManager::readJson($stateFile);
    
    // --- LÓGICA DO CONTADOR DE RODADAS ---
    $currentRound = $currentState['roundCounter'] ?? 0;
    $nextRound = $currentRound + 1;
    
    if ($nextRound > 50) {
        $nextRound = 1; // Reseta após 50 ou mantém? Assumindo reset cíclico.
    }
    
    // Define o nome oficial da fase para exibição e histórico
    $officialPhaseName = "Rodada $nextRound";
    
    $usedItems = $currentState['usedItems'] ?? [];

    // 2. Define Pote Base e Regras
    $basePool = [];
    $drawCount = 0;
    $specialRules = false;
    $stonehengeRule = false;
    
    // Configuração baseada no ID
    if ($tournamentId == 102 || $tournamentId == 118) {
        global $tracks_tg1; // Do Data.php
        $basePool = $tracks_tg1;
        $drawCount = 12;
        $specialRules = true; // Regra de países
        $stonehengeRule = true; // Regra Stonehenge 12ª posição
    } else {
        // Fallback genérico
         global $tracks_tg1;
         $basePool = $tracks_tg1;
         $drawCount = 12; 
    }

    // 3. Processamento do Pote (Lógica Cíclica Ajustada)
    $availablePool = array_diff($basePool, $usedItems);
    $drawResult = [];
    
    // Variável para rastrear quais itens devem ser persistidos como "usados" no novo estado
    // Se não houver reset, serão os usados antigos + novos sorteados
    // Se houver reset, serão APENAS os novos sorteados do novo ciclo
    $nextUsedItemsState = $usedItems; 
    
    // Se o pote disponível é menor que o necessário, usa o resto e reseta
    if (count($availablePool) < $drawCount) {
        Logger::info("ProcessDraw: Pote cíclico ativado (Disponível: " . count($availablePool) . ", Necessário: $drawCount)");
        
        // Pega tudo que sobrou do ciclo antigo
        $residue = $availablePool;
        foreach ($residue as $item) {
            $drawResult[] = $item;
        }
        
        // --- PONTO CRÍTICO: RESET DO CICLO ---
        // O ciclo antigo fechou. O novo estado de "usados" começará do zero apenas com o que for tirado do pote novo.
        $nextUsedItemsState = []; 
        
        // Reseta pool disponível para o total completo
        $availablePool = $basePool;
        
        // Remove do pote novo o que já foi pego no resíduo para não repetir NA MESMA RODADA
        // (Mas isso não afeta a persistência do próximo ciclo, apenas o sorteio atual)
        $availablePool = array_diff($availablePool, $residue);
    }

    // 4. Sorteio dos itens restantes (considerando regras especiais)
    $slotsRemaining = $drawCount - count($drawResult);
    $itemsDrawnFromNewPool = []; // Rastreia o que saiu do pote novo/atual
    
    if ($specialRules && $slotsRemaining > 0) {
        // Regra de Países
        $requiredCountries = ['USA', 'SAM', 'JAP', 'GER', 'SCN', 'FRA', 'ITA', 'UKG'];
        $presentCountries = [];

        foreach ($drawResult as $track) {
            $parts = explode(' - ', $track);
            if (isset($parts[1])) $presentCountries[] = $parts[1];
        }

        $missingCountries = array_diff($requiredCountries, $presentCountries);
        
        foreach ($missingCountries as $country) {
            if ($slotsRemaining <= 0) break;

            $countryTracks = array_filter($availablePool, function($t) use ($country) {
                return strpos($t, " - $country - ") !== false;
            });

            if (!empty($countryTracks)) {
                $pick = $countryTracks[array_rand($countryTracks)];
                $drawResult[] = $pick;
                $itemsDrawnFromNewPool[] = $pick; // Marca como novo
                
                $availablePool = array_diff($availablePool, [$pick]);
                $slotsRemaining--;
            }
        }
    }

    // 5. Completa os slots restantes com aleatórios
    if ($slotsRemaining > 0) {
        $availablePool = array_values($availablePool);
        if (count($availablePool) >= $slotsRemaining) {
            $randomKeys = array_rand($availablePool, $slotsRemaining);
            if (!is_array($randomKeys)) $randomKeys = [$randomKeys];
            
            foreach ($randomKeys as $key) {
                $pick = $availablePool[$key];
                $drawResult[] = $pick;
                $itemsDrawnFromNewPool[] = $pick; // Marca como novo
            }
        } else {
            // Caso extremo
            foreach($availablePool as $pick) {
                $drawResult[] = $pick;
                $itemsDrawnFromNewPool[] = $pick;
            }
        }
    }

    // 6. Regra Stonehenge
    if ($stonehengeRule) {
        $targetTrack = "32 - UKG - Stonehenge";
        $foundIndex = -1;
        foreach ($drawResult as $idx => $track) {
            if ($track === $targetTrack) {
                $foundIndex = $idx;
                break;
            }
        }
        if ($foundIndex !== -1) {
            unset($drawResult[$foundIndex]);
            $drawResult = array_values($drawResult);
            $drawResult[] = $targetTrack;
        }
    }

    // Corte final para garantir tamanho exato
    $drawResult = array_slice($drawResult, 0, $drawCount);

    // 7. Atualiza lista de itens usados para persistência
    // Se nextUsedItemsState foi zerado (reset cíclico), adicionamos apenas o que veio do NOVO pool ($itemsDrawnFromNewPool)
    // Se não foi zerado, adicionamos tudo que foi sorteado agora (pois tudo veio do mesmo pool corrente)
    if (empty($nextUsedItemsState) && count($residue) > 0) {
        // Houve reset: Persiste APENAS o que saiu do pote novo
        $nextUsedItemsState = $itemsDrawnFromNewPool;
    } else {
        // Fluxo normal: Acumula o sorteio atual
        $nextUsedItemsState = array_merge($nextUsedItemsState, $drawResult);
    }
    
    $nextUsedItemsState = array_values(array_unique($nextUsedItemsState));

    // 8. Salva usando FileManager
    // Prepara dados para salvar no JSON de estado
    $drawData = [
        'title' => $title,
        'phase' => $officialPhaseName, // Usa "Rodada X"
        'drawnItems' => $drawResult
    ];
    
    // Injeta o contador de rodada no array que será salvo
    // O método saveGranularDraw precisará ser ligeiramente "enganado" ou melhorado, 
    // mas como ele lê o estado atual e faz merge, vamos passar o contador via um método auxiliar ou modificar o FileManager.
    // Para não alterar o FileManager drasticamente agora, vamos ler, modificar e salvar manualmente aqui ou passar via drawData se o FileManager suportasse.
    
    // Melhor abordagem: Usar FileManager::writeJson diretamente para ter controle total do estado
    // Mas precisamos atualizar histórico também. Vamos usar o saveGranularDraw e depois atualizar o contador separadamente ou passar um array de estado completo.
    
    // Vamos atualizar o saveGranularDraw no FileManager (na memória aqui, mas o arquivo físico é outro).
    // Como não posso editar 2 arquivos no mesmo bloco de código se não solicitado, vou usar a lógica de:
    // Salvar o sorteio normal -> Ler o arquivo -> Adicionar contador -> Salvar de novo. É ineficiente mas seguro sem editar FileManager.
    // OU: Passo o contador dentro de $nextUsedItemsState? Não, suja o array.
    
    // Solução: O FileManager::saveGranularDraw aceita $updatedUsedItems.
    // Vou fazer o seguinte: Salvo normalmente. O FileManager salva `usedItems`.
    // Depois, reabro o arquivo JSON e injeto o `roundCounter`.
    
    FileManager::saveGranularDraw($tournamentId, $drawData, $nextUsedItemsState);
    
    // Injeção do Contador (Pós-processamento)
    $finalState = FileManager::readJson($stateFile);
    $finalState['roundCounter'] = $nextRound;
    FileManager::writeJson($stateFile, $finalState);

    Logger::success("ProcessDraw: Sorteio ID $tournamentId (Rodada $nextRound) finalizado.");

    // 9. Retorna Resultado
    echo json_encode([
        'status' => 'success', 
        'data' => $drawResult,
        'phase' => $officialPhaseName, // Retorna o nome correto para o front atualizar
        'used_count' => count($nextUsedItemsState)
    ]);

} catch (Exception $e) {
    Logger::error("ProcessDraw Erro: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>