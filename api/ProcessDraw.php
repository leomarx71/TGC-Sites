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
// A fase selecionada no botão (frontend)
$inputPhaseLabel = $input['phase'] ?? 'Fase Desconhecida'; 
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
    $usedItems = $currentState['usedItems'] ?? [];

    // --- LÓGICA DO CONTADOR DE RODADAS E NOME DA FASE ---
    $phaseToSave = $inputPhaseLabel;
    $shouldUpdateRoundCounter = false;
    $nextRound = 0;

    // Apenas para 102, 118 e 106 aplicamos a lógica de "Rodada X"
    if ($tournamentId == 102 || $tournamentId == 118 || $tournamentId == 106) {
        $currentRound = $currentState['roundCounter'] ?? 0;
        $nextRound = $currentRound + 1;
        
        if ($nextRound > 50) {
            $nextRound = 1; // Reseta após 50
        }
        
        $phaseToSave = "Rodada $nextRound";
        $shouldUpdateRoundCounter = true;
    }

    // 2. Define Pote Base e Regras
    $basePool = [];
    $drawCount = 0;
    $specialRules = false;
    $stonehengeRule = false;
    $formatAsRounds = false; 
    $drawResult = []; // Inicializa array de resultado
    $customLogicApplied = false; // Flag para indicar se lógica customizada foi usada (ex: 110)
    
    // Configuração baseada no ID
    if ($tournamentId == 102 || $tournamentId == 118) {
        global $tracks_tg1; 
        $basePool = $tracks_tg1;
        $drawCount = 12;
        $specialRules = true; // Regra de países obrigatórios
        $stonehengeRule = true; // Regra Stonehenge 12ª posição
    } 
    elseif ($tournamentId == 106) {
        global $tracks_tg1; 
        $basePool = $tracks_tg1;
        $drawCount = 8; 
        $specialRules = true; 
        $stonehengeRule = true; 
    }
    elseif ($tournamentId == 109) {
        global $countries_tg1; 
        $basePool = $countries_tg1;
        
        if (stripos($inputPhaseLabel, 'F0') !== false || stripos($inputPhaseLabel, 'Eliminat') !== false) {
            $drawCount = 2;
        }
        elseif (stripos($inputPhaseLabel, 'F5') !== false || stripos($inputPhaseLabel, 'Final e 3') !== false) {
            $drawCount = 4;
        }
        else {
            $drawCount = 3;
        }
    }
    // --- LÓGICA ESPECÍFICA TORNEIO 117 ---
    elseif ($tournamentId == 117) {
        global $countries_tg1;
        $basePool = $countries_tg1;
        $customLogicApplied = false;

        // Eliminatórias (F0): 3 países
        if (stripos($inputPhaseLabel, 'Eliminat') !== false || stripos($inputPhaseLabel, 'F0') !== false) {
            $drawCount = 3;
        }
        // 4° de Final (F3): 4 países
        elseif (stripos($inputPhaseLabel, '4') !== false || stripos($inputPhaseLabel, 'F3') !== false) {
            $drawCount = 4;
        }
        // Semifinal (F4): 4 países
        elseif (stripos($inputPhaseLabel, 'Semi') !== false || stripos($inputPhaseLabel, 'F4') !== false) {
            $drawCount = 4;
        }
        // Final e 3° (F5): 8 países Sequenciais (Ciclo)
        elseif (stripos($inputPhaseLabel, 'Final') !== false || stripos($inputPhaseLabel, 'F5') !== false) {
            $customLogicApplied = true;
            $totalCountries = count($basePool); // Deve ser 8
            
            // Sorteia um índice de início aleatório (0 a 7)
            $startIndex = rand(0, $totalCountries - 1);
            
            // Gera a lista sequencial a partir do índice sorteado (wrap-around)
            for ($i = 0; $i < $totalCountries; $i++) {
                $idx = ($startIndex + $i) % $totalCountries;
                $drawResult[] = $basePool[$idx];
            }
            $drawCount = 8;
        }
        else {
            $drawCount = 3; // Fallback para outras fases não mapeadas
        }
    }
    elseif ($tournamentId == 110) {
        // --- REGRA EXCLUSIVA TORNEIO 110 ---
        $customLogicApplied = true;
        global $tracks_110_pit, $tracks_110_nopit;
        
        if (stripos($inputPhaseLabel, 'F1') !== false || stripos($inputPhaseLabel, 'Grupos') !== false) {
            $poolPit = $tracks_110_pit; shuffle($poolPit); $selectedPit = array_slice($poolPit, 0, 5);
            $poolNoPit = $tracks_110_nopit; shuffle($poolNoPit); $selectedNoPit = array_slice($poolNoPit, 0, 5);
            for ($i = 0; $i < 5; $i++) {
                if (isset($selectedPit[$i])) $drawResult[] = $selectedPit[$i] . " <span class='ml-2 text-[10px] bg-red-100 text-red-700 px-1.5 py-0.5 rounded border border-red-200 font-bold'>COM_PIT</span>";
                if (isset($selectedNoPit[$i])) $drawResult[] = $selectedNoPit[$i] . " <span class='ml-2 text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded border border-green-200 font-bold'>SEM_PIT</span>";
            }
            $drawCount = 10;
        } 
        elseif (stripos($inputPhaseLabel, 'F2') !== false || stripos($inputPhaseLabel, '8') !== false) {
            $poolPit = $tracks_110_pit; shuffle($poolPit); $poolNoPit = $tracks_110_nopit; shuffle($poolNoPit);
            $group1 = (function($p, $np) { $r=[]; $m=max(count($p),count($np)); for($i=0;$i<$m;$i++){ if(isset($p[$i])) $r[]=$p[$i]." <span class='ml-2 text-[10px] bg-red-100 text-red-700 px-1.5 py-0.5 rounded border border-red-200 font-bold'>COM_PIT</span>"; if(isset($np[$i])) $r[]=$np[$i]." <span class='ml-2 text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded border border-green-200 font-bold'>SEM_PIT</span>"; } return $r; })(array_slice($poolPit,0,5), array_slice($poolNoPit,0,2));
            $group2 = (function($p, $np) { $r=[]; $m=max(count($p),count($np)); for($i=0;$i<$m;$i++){ if(isset($p[$i])) $r[]=$p[$i]." <span class='ml-2 text-[10px] bg-red-100 text-red-700 px-1.5 py-0.5 rounded border border-red-200 font-bold'>COM_PIT</span>"; if(isset($np[$i])) $r[]=$np[$i]." <span class='ml-2 text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded border border-green-200 font-bold'>SEM_PIT</span>"; } return $r; })(array_slice($poolPit,5,4), array_slice($poolNoPit,2,3));
            $drawResult = array_merge($group1, $group2);
            $drawCount = 14;
        } 
        elseif (stripos($inputPhaseLabel, 'F3') !== false || stripos($inputPhaseLabel, '4') !== false) {
            $poolPit = $tracks_110_pit; shuffle($poolPit); $poolNoPit = $tracks_110_nopit; shuffle($poolNoPit);
            $group1 = (function($p, $np) { $r=[]; $m=max(count($p),count($np)); for($i=0;$i<$m;$i++){ if(isset($p[$i])) $r[]=$p[$i]." <span class='ml-2 text-[10px] bg-red-100 text-red-700 px-1.5 py-0.5 rounded border border-red-200 font-bold'>COM_PIT</span>"; if(isset($np[$i])) $r[]=$np[$i]." <span class='ml-2 text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded border border-green-200 font-bold'>SEM_PIT</span>"; } return $r; })(array_slice($poolPit,0,5), array_slice($poolNoPit,0,3));
            $group2 = (function($p, $np) { $r=[]; $m=max(count($p),count($np)); for($i=0;$i<$m;$i++){ if(isset($p[$i])) $r[]=$p[$i]." <span class='ml-2 text-[10px] bg-red-100 text-red-700 px-1.5 py-0.5 rounded border border-red-200 font-bold'>COM_PIT</span>"; if(isset($np[$i])) $r[]=$np[$i]." <span class='ml-2 text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded border border-green-200 font-bold'>SEM_PIT</span>"; } return $r; })(array_slice($poolPit,5,5), array_slice($poolNoPit,3,3));
            $drawResult = array_merge($group1, $group2);
            $drawCount = 16;
        }
        elseif (stripos($inputPhaseLabel, 'F4') !== false || stripos($inputPhaseLabel, 'Semifinal') !== false) {
            $poolPit = $tracks_110_pit; shuffle($poolPit); $poolNoPit = $tracks_110_nopit; shuffle($poolNoPit);
            $interleave = function($p, $np) { $r=[]; $m=max(count($p),count($np)); for($i=0;$i<$m;$i++){ if(isset($p[$i])) $r[]=$p[$i]." <span class='ml-2 text-[10px] bg-red-100 text-red-700 px-1.5 py-0.5 rounded border border-red-200 font-bold'>COM_PIT</span>"; if(isset($np[$i])) $r[]=$np[$i]." <span class='ml-2 text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded border border-green-200 font-bold'>SEM_PIT</span>"; } return $r; };
            $drawResult = array_merge($interleave(array_slice($poolPit,0,4), array_slice($poolNoPit,0,3)), $interleave(array_slice($poolPit,4,4), array_slice($poolNoPit,3,3)), $interleave(array_slice($poolPit,8,4), array_slice($poolNoPit,6,3)));
            $drawCount = 21;
        }
        elseif (stripos($inputPhaseLabel, 'F5') !== false || stripos($inputPhaseLabel, 'Final') !== false) {
            $poolPit = $tracks_110_pit; shuffle($poolPit); $poolNoPit = $tracks_110_nopit; shuffle($poolNoPit);
            $interleave = function($p, $np) { $r=[]; $m=max(count($p),count($np)); for($i=0;$i<$m;$i++){ if(isset($p[$i])) $r[]=$p[$i]." <span class='ml-2 text-[10px] bg-red-100 text-red-700 px-1.5 py-0.5 rounded border border-red-200 font-bold'>COM_PIT</span>"; if(isset($np[$i])) $r[]=$np[$i]." <span class='ml-2 text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded border border-green-200 font-bold'>SEM_PIT</span>"; } return $r; };
            $drawResult = array_merge($interleave(array_slice($poolPit,0,3), array_slice($poolNoPit,0,3)), $interleave(array_slice($poolPit,3,3), array_slice($poolNoPit,3,3)), $interleave(array_slice($poolPit,6,3), array_slice($poolNoPit,6,3)), $interleave(array_slice($poolPit,9,4), array_slice($poolNoPit,9,2)));
            $drawCount = 24;
        }
        else {
            global $tracks_tg1; $basePool = $tracks_tg1; $drawCount = 12; $customLogicApplied = false;
        }
    }
    elseif (in_array($tournamentId, [101, 107, 112, 116])) {
        global $countries_tg1; 
        $basePool = $countries_tg1;
        
        if (stripos($inputPhaseLabel, 'F1') !== false || stripos($inputPhaseLabel, 'Grupos') !== false) {
            $drawCount = 6; 
            $formatAsRounds = true;
        } 
        elseif (stripos($inputPhaseLabel, 'F5') !== false || stripos($inputPhaseLabel, 'Final e 3') !== false) {
            $drawCount = 4;
        }
        else {
            $drawCount = 3; 
        }
    }
    else {
        // Fallback genérico
         global $tracks_tg1;
         $basePool = $tracks_tg1;
         $drawCount = 12; 
    }

    // --- LÓGICA DE SORTEIO PADRÃO (Se não foi customizada acima) ---
    $nextUsedItemsState = $usedItems; 
    $residue = [];

    if (!$customLogicApplied) {
        $availablePool = array_diff($basePool, $usedItems);
        
        // Lógica Cíclica
        if (count($availablePool) < $drawCount) {
            Logger::info("ProcessDraw: Pote cíclico ativado");
            $residue = $availablePool;
            foreach ($residue as $item) {
                $drawResult[] = $item;
            }
            $nextUsedItemsState = []; 
            $availablePool = $basePool;
            $availablePool = array_diff($availablePool, $residue);
        }

        $slotsRemaining = $drawCount - count($drawResult);
        $itemsDrawnFromNewPool = []; 
        
        // Regras Especiais (Países)
        if ($specialRules && $slotsRemaining > 0) {
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
                    $itemsDrawnFromNewPool[] = $pick;
                    $availablePool = array_diff($availablePool, [$pick]);
                    $slotsRemaining--;
                }
            }
        }

        // Completa aleatórios
        if ($slotsRemaining > 0) {
            $availablePool = array_values($availablePool);
            if (count($availablePool) >= $slotsRemaining) {
                $randomKeys = array_rand($availablePool, $slotsRemaining);
                if (!is_array($randomKeys)) $randomKeys = [$randomKeys];
                foreach ($randomKeys as $key) {
                    $pick = $availablePool[$key];
                    $drawResult[] = $pick;
                    $itemsDrawnFromNewPool[] = $pick;
                }
            } else {
                foreach($availablePool as $pick) {
                    $drawResult[] = $pick;
                    $itemsDrawnFromNewPool[] = $pick;
                }
            }
        }

        // Regra Stonehenge
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

        // Corte Final
        $drawResult = array_slice($drawResult, 0, $drawCount);

        // Atualiza Estado de Usados
        if (empty($nextUsedItemsState) && count($residue) > 0) {
            $nextUsedItemsState = $itemsDrawnFromNewPool;
        } else {
            $nextUsedItemsState = array_merge($nextUsedItemsState, $drawResult);
        }
        $nextUsedItemsState = array_values(array_unique($nextUsedItemsState));
    } 
    else {
        // Lógica customizada (110 e 117-F5) não persiste ciclo de usados da mesma forma
    }

    // 8. Formatação para Exibição
    $displayItems = $drawResult;
    if ($formatAsRounds && count($drawResult) === 6) {
        $displayItems = [
            "Rodada 1 - " . $drawResult[0] . " e " . $drawResult[1],
            "Rodada 2 - " . $drawResult[2] . " e " . $drawResult[3],
            "Rodada 3 - " . $drawResult[4] . " e " . $drawResult[5]
        ];
    }

    // 9. Salva
    $drawData = [
        'title' => $title,
        'phase' => $phaseToSave,
        'drawnItems' => $displayItems
    ];
    
    FileManager::saveGranularDraw($tournamentId, $drawData, $nextUsedItemsState);
    
    if ($shouldUpdateRoundCounter) {
        $finalState = FileManager::readJson($stateFile);
        $finalState['roundCounter'] = $nextRound;
        FileManager::writeJson($stateFile, $finalState);
    }

    Logger::success("ProcessDraw: Sorteio ID $tournamentId ($phaseToSave) finalizado.");

    echo json_encode([
        'status' => 'success', 
        'data' => $displayItems,
        'phase' => $phaseToSave,
        'used_count' => count($nextUsedItemsState)
    ]);

} catch (Exception $e) {
    Logger::error("ProcessDraw Erro: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>