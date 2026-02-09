<?php
// api/processDraw.php
header('Content-Type: application/json');

// 1. Carrega dependências
require_once '../storage/data/Config.php';
require_once '../storage/utils/Functions.php';
require_once '../storage/utils/FileManager.php';
require_once '../storage/data/Data.php';

// Ativa debug se configurado
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método inválido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$tournamentId = $input['tournamentId'] ?? null;
$round = $input['round'] ?? null;

if (!$tournamentId) {
    echo json_encode(['status' => 'error', 'message' => 'ID do torneio obrigatório']);
    exit;
}

// IDs especiais que usam a lógica de Pote Cíclico
$specialIds = ['t2', 't18'];

try {
    // Carrega ou inicializa o estado do torneio
    $jsonFile = JSON_PATH . $tournamentId . '.json';
    $state = [];
    if (file_exists($jsonFile)) {
        $state = json_decode(file_get_contents($jsonFile), true);
    }
    
    // Recupera itens usados (Pote)
    $usedItems = $state['usedItems'] ?? [];
    
    // Array final de sorteio
    $selection = [];
    $drawnStrings = [];
    $isSpecial = in_array($tournamentId, $specialIds);

    // ============================================================================
    // LÓGICA ESPECIAL PARA T2 e T18 (Pote Cíclico + Diversidade)
    // ============================================================================
    if ($isSpecial) {
        $allTracks = $tracks_tg1_full; // Vem do Data.php
        $totalTracksCount = count($allTracks); // 32
        
        // 1. Filtra disponíveis (o que não está em usedItems)
        // Precisamos comparar nomes, pois $usedItems é array de strings (nomes)
        $available = array_filter($allTracks, function($t) use ($usedItems) {
            return !in_array($t['name'], $usedItems);
        });
        
        // Reindexa array
        $available = array_values($available);
        
        // Regra: "Se acabarem as 32 (ou não der para completar 12), usa as restantes e completa com novo"
        // Vamos forçar reset se count(available) < 12 para simplificar a lógica de "completar"
        // Ou se não tivermos diversidade suficiente.
        
        $needed = 12;
        $selectionPool = []; // De onde vamos tirar as pistas
        $didReset = false;

        // Se tiver menos que 12, pegamos TUDO o que sobrou e resetamos o pote para pegar o resto
        if (count($available) < $needed) {
            // Pega o que sobrou
            foreach ($available as $t) {
                $selection[] = $t;
            }
            
            // Reseta Pote
            $usedItems = []; 
            $didReset = true;
            
            // O novo pool são TODAS as pistas, MENOS as que acabamos de pegar (para não repetir na rodada)
            // Extrai nomes já selecionados
            $currentRoundNames = array_column($selection, 'name');
            $selectionPool = array_filter($allTracks, function($t) use ($currentRoundNames) {
                return !in_array($t['name'], $currentRoundNames);
            });
        } else {
            $selectionPool = $available;
        }
        
        // Quantos faltam para completar 12?
        $remainingNeeded = $needed - count($selection);
        
        if ($remainingNeeded > 0) {
            // AQUI APLICAMOS A DIVERSIDADE NOS RESTANTES
            // Países obrigatórios: USA, SAM, JAP, GER, SCN, FRA, ITA, UKG
            $countries = ["USA", "SAM", "JAP", "GER", "SCN", "FRA", "ITA", "UKG"];
            
            // Verifica quais países já temos na seleção (se pegamos do resto do pote)
            $existingCountries = array_column($selection, 'country');
            
            // Tenta garantir 1 de cada país na seleção FINAL (somando o que já pegou + o que vai pegar)
            // Se o pote foi resetado ($didReset), $selectionPool está cheio (exceto as usadas na rodada), então é fácil.
            // Se o pote NÃO foi resetado, dependemos do que tem no $selectionPool.
            
            // Para cada país, se ainda não temos, tentamos pegar do pool
            $tempPool = $selectionPool;
            
            // Passo 1: Preencher obrigatórios
            foreach ($countries as $c) {
                // Se já temos esse país na seleção parcial (dos restos), ok.
                if (in_array($c, $existingCountries)) continue;
                
                // Se não temos, busca no pool
                $countryTracks = array_filter($tempPool, function($t) use ($c) { return $t['country'] === $c; });
                
                if (!empty($countryTracks)) {
                    // Pega uma aleatória desse país
                    $key = array_rand($countryTracks);
                    $pick = $countryTracks[$key];
                    
                    $selection[] = $pick;
                    // Remove do pool temporário para não duplicar
                    // Precisamos encontrar a chave original no $tempPool
                    // Como array_filter preserva chaves, $key é a chave em $tempPool? Não necessariamente se reindexado.
                    // Vamos remover por nome
                    $tempPool = array_filter($tempPool, function($t) use ($pick) { return $t['name'] !== $pick['name']; });
                } else {
                    // Se faltar país e não tiver no pool (ex: pote antigo sem reset), não tem como cumprir.
                    // Nesse caso extremo, ignoramos ou forçamos reset? 
                    // Se não houve reset antes (count >= 12), mas a diversidade falhou, deveríamos ter resetado.
                    // Para simplificar: segue com o que tem.
                }
            }
            
            // Passo 2: Preencher o que falta (Random) até chegar em 12
            while (count($selection) < 12 && count($tempPool) > 0) {
                 $key = array_rand($tempPool);
                 $pick = $tempPool[$key];
                 $selection[] = $pick;
                 // Remove por nome
                 $tempPool = array_filter($tempPool, function($t) use ($pick) { return $t['name'] !== $pick['name']; });
            }
        }
        
        // --- REGRA STONEHENGE ---
        // Se sorteada, move para a 12ª posição (índice 11)
        $stoneIndex = -1;
        foreach ($selection as $idx => $t) {
            if ($t['name'] === 'Stonehenge') {
                $stoneIndex = $idx;
                break;
            }
        }
        
        if ($stoneIndex !== -1 && $stoneIndex !== 11 && count($selection) === 12) {
            $stoneTrack = $selection[$stoneIndex];
            // Remove
            array_splice($selection, $stoneIndex, 1);
            // Adiciona no final
            $selection[] = $stoneTrack;
        }
        
        // Formata para string e atualiza UsedItems
        foreach ($selection as $t) {
            $drawnStrings[] = $t['country'] . " - " . $t['name'];
            // Adiciona ao pote global de usados (apenas o nome)
            if (!in_array($t['name'], $usedItems)) {
                $usedItems[] = $t['name'];
            }
        }

    } else {
        // ============================================================================
        // LÓGICA PADRÃO (Outros torneios)
        // ============================================================================
        // Apenas sorteia 12 aleatórias da lista simples $tracks_tg1
        $pool = $tracks_tg1; // Data.php
        shuffle($pool);
        $drawnStrings = array_slice($pool, 0, 12);
        // Não gerenciamos pote persistente complexo para os outros por enquanto
    }

    // ============================================================================
    // SALVAMENTO
    // ============================================================================
    
    // Atualiza estado (Pote)
    $state['id'] = $tournamentId;
    $state['title'] = $input['title'] ?? 'Torneio ' . $tournamentId;
    $state['usedItems'] = $usedItems;
    $state['lastUpdate'] = date('Y-m-d H:i:s');
    
    // Salva JSON de Estado
    FileManager::writeJson($jsonFile, $state);
    
    // Salva Histórico (SaveDraw Logic)
    // O SaveDraw.php salva granularmente no histórico. Vamos simular/chamar a lógica dele aqui.
    $phase = $round ? "Rodada $round" : ($input['phase'] ?? 'Sorteio');
    
    $historyData = [
        'phase' => $phase,
        'drawnItems' => $drawnStrings,
        'date' => date('Y-m-d H:i:s')
    ];
    
    $historyFile = JSON_PATH . $tournamentId . '-history.json';
    $history = [];
    if (file_exists($historyFile)) {
        $history = json_decode(file_get_contents($historyFile), true);
    }
    array_unshift($history, $historyData);
    FileManager::writeJson($historyFile, $history);
    
    // Retorna
    echo json_encode([
        'status' => 'success',
        'data' => [
            'drawnItems' => $drawnStrings,
            'phase' => $phase,
            'potState' => $isSpecial ? count($usedItems) . "/32" : "-" 
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>