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
$laLigaSixTournamentIds = [103, 104, 105, 113, 114, 115];
$isLaLigaSix = in_array((int)$tournamentId, $laLigaSixTournamentIds, true);
$isTg3000Tournament = ((int)$tournamentId === 301);
$isTg3000CyclePhase = false;
$isCenario401Tournament = ((int)$tournamentId === 401);
$isCenario402Tournament = ((int)$tournamentId === 402);
$isCenario403Tournament = ((int)$tournamentId === 403);
$isCenario404Or406Tournament = in_array((int)$tournamentId, [404, 406], true);
$usedItemsPotA = [];
$usedItemsPotB = [];
$usedItemsPotC = [];
$usedItemsPot402 = [];
$usedItemsPot403 = [];
$usedItemsPotFull = [];

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
    elseif ($isLaLigaSix && (stripos($inputPhaseLabel, 'F1') !== false || stripos($inputPhaseLabel, 'Grupos') !== false)) {
        $phaseToSave = "Fase de Grupos";
    }

    // 2. Define Pote Base e Regras
    $basePool = [];
    $drawCount = 0;
    $specialRules = false;
    $stonehengeRule = false;
    $formatAsRounds = false; 
    $drawResult = []; // Inicializa array de resultado
    $customLogicApplied = false; // Flag para indicar se lógica customizada foi usada (ex: 110)
    $allowCyclicPot = true;
    $shouldTrackUsedItems = true;

    // Regra nova para La Liga (103, 104, 105, 113, 114 e 115):
    // o ciclo de usados é exclusivo para mata-mata (F3/F4/F5).
    if ($isLaLigaSix) {
        $usedItems = $currentState['usedItemsKnockout'] ?? [];
    }
    if ($isTg3000Tournament) {
        $usedItems = $currentState['usedItemsTg3000Cycle'] ?? [];
    }
    if ($isCenario401Tournament) {
        $usedItemsPotA = $currentState['usedItemsPotA'] ?? [];
        $usedItemsPotB = $currentState['usedItemsPotB'] ?? [];
        $usedItemsPotC = $currentState['usedItemsPotC'] ?? [];
    }
    if ($isCenario402Tournament) {
        $usedItemsPot402 = $currentState['usedItemsPot402'] ?? [];
    }
    if ($isCenario403Tournament) {
        $usedItemsPot403 = $currentState['usedItemsPot403'] ?? [];
    }
    if ($isCenario404Or406Tournament) {
        $usedItemsPotFull = $currentState['usedItemsPotFull'] ?? [];
    }
    
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
    elseif ($tournamentId == 401) {
        global $pot_cen1_a, $pot_cen1_b, $pot_cen1_c, $pot_cenarios_full;
        $customLogicApplied = true;
        $shouldTrackUsedItems = false;
        $drawResult = [];
        $normalizePot = function(array $pot) {
            $keys = array_keys($pot);
            $isIndexedList = true;
            foreach ($keys as $k) {
                if (!is_int($k)) {
                    $isIndexedList = false;
                    break;
                }
            }
            return $isIndexedList ? array_values($pot) : array_values($keys);
        };
        $potAList = $normalizePot($pot_cen1_a);
        $potBList = $normalizePot($pot_cen1_b);
        $potCList = $normalizePot($pot_cen1_c);

        if (
            stripos($inputPhaseLabel, 'F1') === false &&
            stripos($inputPhaseLabel, 'F2') === false &&
            stripos($inputPhaseLabel, 'F3') === false &&
            stripos($inputPhaseLabel, 'F4') === false &&
            stripos($inputPhaseLabel, 'F5') === false &&
            stripos($inputPhaseLabel, 'Grupos') === false &&
            stripos($inputPhaseLabel, '8') === false &&
            stripos($inputPhaseLabel, '4') === false &&
            stripos($inputPhaseLabel, 'Semi') === false &&
            stripos($inputPhaseLabel, 'Final') === false
        ) {
            throw new Exception('Fase não suportada para o torneio 401.');
        }

        $drawFromPotWithCycle = function(array $pot, array $used, int $count) {
            $selected = [];
            $usedState = array_values(array_unique($used));

            for ($i = 0; $i < $count; $i++) {
                $available = array_values(array_diff($pot, $usedState, $selected));

                if (empty($available)) {
                    // Reinicia ciclo do pote ao esgotar.
                    $usedState = [];
                    $available = array_values(array_diff($pot, $selected));
                }

                if (empty($available)) {
                    break;
                }

                $pick = $available[array_rand($available)];
                $selected[] = $pick;
                $usedState[] = $pick;
                $usedState = array_values(array_unique($usedState));
            }

            return [$selected, $usedState];
        };

        $getLapsForTrack = function($track, $primaryPot) use ($pot_cenarios_full) {
            // Pote no formato mapa: pista => voltas
            if (isset($primaryPot[$track]) && is_numeric($primaryPot[$track])) {
                return (int)$primaryPot[$track];
            }
            // Fallback para mapa completo
            if (isset($pot_cenarios_full[$track]) && is_numeric($pot_cenarios_full[$track])) {
                return (int)$pot_cenarios_full[$track];
            }
            return 0;
        };
        $formatTrackWithLaps = function($track, $laps) {
            $lapsText = ($laps === 1) ? '1 Volta' : ($laps . ' Voltas');
            return $track . " <span class='ml-2 text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded border border-amber-200 font-bold'>" . $lapsText . "</span>";
        };

        // Ordem fixa obrigatória: A, A, B, C.
        [$selectedA, $usedItemsPotA] = $drawFromPotWithCycle($potAList, $usedItemsPotA, 2);
        [$selectedB, $usedItemsPotB] = $drawFromPotWithCycle($potBList, $usedItemsPotB, 1);
        [$selectedC, $usedItemsPotC] = $drawFromPotWithCycle($potCList, $usedItemsPotC, 1);

        if (count($selectedA) < 2 || count($selectedB) < 1 || count($selectedC) < 1) {
            throw new Exception('Não foi possível completar o sorteio do torneio 401 com os potes configurados.');
        }

        $drawResult = [
            $formatTrackWithLaps($selectedA[0], $getLapsForTrack($selectedA[0], $pot_cen1_a)),
            $formatTrackWithLaps($selectedA[1], $getLapsForTrack($selectedA[1], $pot_cen1_a)),
            $formatTrackWithLaps($selectedB[0], $getLapsForTrack($selectedB[0], $pot_cen1_b)),
            $formatTrackWithLaps($selectedC[0], $getLapsForTrack($selectedC[0], $pot_cen1_c))
        ];
    }
    elseif ($tournamentId == 402) {
        global $pot_cen402, $pot_cenarios_full;
        $customLogicApplied = true;
        $shouldTrackUsedItems = false;
        $drawResult = [];

        if (
            stripos($inputPhaseLabel, 'F1') === false &&
            stripos($inputPhaseLabel, 'F2') === false &&
            stripos($inputPhaseLabel, 'F3') === false &&
            stripos($inputPhaseLabel, 'F4') === false &&
            stripos($inputPhaseLabel, 'F5') === false &&
            stripos($inputPhaseLabel, 'Grupos') === false &&
            stripos($inputPhaseLabel, '8') === false &&
            stripos($inputPhaseLabel, '4') === false &&
            stripos($inputPhaseLabel, 'Semi') === false &&
            stripos($inputPhaseLabel, 'Final') === false
        ) {
            throw new Exception('Fase não suportada para o torneio 402.');
        }

        $pot402List = array_values(array_keys($pot_cen402));

        $drawFromPotWithCycle = function(array $pot, array $used, int $count) {
            $selected = [];
            $usedState = array_values(array_unique($used));

            for ($i = 0; $i < $count; $i++) {
                $available = array_values(array_diff($pot, $usedState, $selected));

                if (empty($available)) {
                    // Reinicia ciclo ao esgotar o pote.
                    $usedState = [];
                    $available = array_values(array_diff($pot, $selected));
                }

                if (empty($available)) {
                    break;
                }

                $pick = $available[array_rand($available)];
                $selected[] = $pick;
                $usedState[] = $pick;
                $usedState = array_values(array_unique($usedState));
            }

            return [$selected, $usedState];
        };

        $getLapsForTrack = function($track) use ($pot_cen402, $pot_cenarios_full) {
            if (isset($pot_cen402[$track]) && is_numeric($pot_cen402[$track])) {
                return (int)$pot_cen402[$track];
            }
            if (isset($pot_cenarios_full[$track]) && is_numeric($pot_cenarios_full[$track])) {
                return (int)$pot_cenarios_full[$track];
            }
            return 0;
        };
        $formatTrackWithLaps = function($track, $laps) {
            $lapsText = ($laps === 1) ? '1 Volta' : ($laps . ' Voltas');
            return $track . " <span class='ml-2 text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded border border-amber-200 font-bold'>" . $lapsText . "</span>";
        };

        [$selected, $usedItemsPot402] = $drawFromPotWithCycle($pot402List, $usedItemsPot402, 6);
        if (count($selected) < 6) {
            throw new Exception('Não foi possível completar o sorteio do torneio 402 com o pote configurado.');
        }

        foreach ($selected as $track) {
            $drawResult[] = $formatTrackWithLaps($track, $getLapsForTrack($track));
        }
    }
    elseif ($tournamentId == 403) {
        global $pot_cen402, $pot_cenarios_full;
        $customLogicApplied = true;
        $shouldTrackUsedItems = false;
        $drawResult = [];

        if (
            stripos($inputPhaseLabel, 'F1') === false &&
            stripos($inputPhaseLabel, 'F2') === false &&
            stripos($inputPhaseLabel, 'F3') === false &&
            stripos($inputPhaseLabel, 'F4') === false &&
            stripos($inputPhaseLabel, 'F5') === false &&
            stripos($inputPhaseLabel, 'Grupos') === false &&
            stripos($inputPhaseLabel, '8') === false &&
            stripos($inputPhaseLabel, '4') === false &&
            stripos($inputPhaseLabel, 'Semi') === false &&
            stripos($inputPhaseLabel, 'Final') === false
        ) {
            throw new Exception('Fase não suportada para o torneio 403.');
        }

        $pot403List = array_values(array_keys($pot_cen402));

        $drawFromPotWithCycle = function(array $pot, array $used, int $count) {
            $selected = [];
            $usedState = array_values(array_unique($used));

            for ($i = 0; $i < $count; $i++) {
                $available = array_values(array_diff($pot, $usedState, $selected));

                if (empty($available)) {
                    // Reinicia ciclo ao esgotar o pote.
                    $usedState = [];
                    $available = array_values(array_diff($pot, $selected));
                }

                if (empty($available)) {
                    break;
                }

                $pick = $available[array_rand($available)];
                $selected[] = $pick;
                $usedState[] = $pick;
                $usedState = array_values(array_unique($usedState));
            }

            return [$selected, $usedState];
        };

        $getLapsForTrack = function($track) use ($pot_cen402, $pot_cenarios_full) {
            if (isset($pot_cen402[$track]) && is_numeric($pot_cen402[$track])) {
                return (int)$pot_cen402[$track];
            }
            if (isset($pot_cenarios_full[$track]) && is_numeric($pot_cenarios_full[$track])) {
                return (int)$pot_cenarios_full[$track];
            }
            return 0;
        };
        $formatTrackWithLaps = function($track, $laps) {
            $lapsText = ($laps === 1) ? '1 Volta' : ($laps . ' Voltas');
            return $track . " <span class='ml-2 text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded border border-amber-200 font-bold'>" . $lapsText . "</span>";
        };

        [$selected, $usedItemsPot403] = $drawFromPotWithCycle($pot403List, $usedItemsPot403, 4);
        if (count($selected) < 4) {
            throw new Exception('Não foi possível completar o sorteio do torneio 403 com o pote configurado.');
        }

        foreach ($selected as $track) {
            $drawResult[] = $formatTrackWithLaps($track, $getLapsForTrack($track));
        }
    }
    elseif ($isCenario404Or406Tournament) {
        global $pot_cenarios_full;
        $customLogicApplied = true;
        $shouldTrackUsedItems = false;
        $drawResult = [];

        if (
            stripos($inputPhaseLabel, 'F1') === false &&
            stripos($inputPhaseLabel, 'F2') === false &&
            stripos($inputPhaseLabel, 'F3') === false &&
            stripos($inputPhaseLabel, 'F4') === false &&
            stripos($inputPhaseLabel, 'F5') === false &&
            stripos($inputPhaseLabel, 'Grupos') === false &&
            stripos($inputPhaseLabel, '8') === false &&
            stripos($inputPhaseLabel, '4') === false &&
            stripos($inputPhaseLabel, 'Semi') === false &&
            stripos($inputPhaseLabel, 'Final') === false
        ) {
            throw new Exception('Fase não suportada para este torneio de cenário.');
        }

        $potFullList = array_values(array_keys($pot_cenarios_full));

        $drawFromPotWithCycle = function(array $pot, array $used, int $count) {
            $selected = [];
            $usedState = array_values(array_unique($used));

            for ($i = 0; $i < $count; $i++) {
                $available = array_values(array_diff($pot, $usedState, $selected));

                if (empty($available)) {
                    // Reinicia ciclo ao esgotar o pote.
                    $usedState = [];
                    $available = array_values(array_diff($pot, $selected));
                }

                if (empty($available)) {
                    break;
                }

                $pick = $available[array_rand($available)];
                $selected[] = $pick;
                $usedState[] = $pick;
                $usedState = array_values(array_unique($usedState));
            }

            return [$selected, $usedState];
        };

        [$selected, $usedItemsPotFull] = $drawFromPotWithCycle($potFullList, $usedItemsPotFull, 4);
        if (count($selected) < 4) {
            throw new Exception('Não foi possível completar o sorteio deste torneio de cenário.');
        }

        $drawResult = $selected;
    }
    else {
        // Fallback genérico
         global $tracks_tg1;
         $basePool = $tracks_tg1;
         $drawCount = 12; 
    }

    if ($isTg3000Tournament) {
        global $tg3k_4planet_systems, $tg3k_all_systems;
        $specialRules = false;
        $stonehengeRule = false;
        $formatAsRounds = false;

        if (stripos($inputPhaseLabel, 'F1') !== false || stripos($inputPhaseLabel, 'Grupos') !== false) {
            // F1 independente, sem pote cíclico.
            $basePool = $tg3k_4planet_systems;
            $drawCount = 2;
            $allowCyclicPot = false;
            $shouldTrackUsedItems = false;
            $usedItems = [];
        }
        elseif (stripos($inputPhaseLabel, 'F2') !== false || stripos($inputPhaseLabel, '8') !== false) {
            $basePool = $tg3k_all_systems;
            $drawCount = 3;
            $allowCyclicPot = true;
            $shouldTrackUsedItems = true;
            $isTg3000CyclePhase = true;
        }
        elseif (stripos($inputPhaseLabel, 'F3') !== false || stripos($inputPhaseLabel, '4') !== false) {
            $basePool = $tg3k_all_systems;
            $drawCount = 3;
            $allowCyclicPot = true;
            $shouldTrackUsedItems = true;
            $isTg3000CyclePhase = true;
        }
        elseif (stripos($inputPhaseLabel, 'F4') !== false || stripos($inputPhaseLabel, 'Semi') !== false) {
            $basePool = $tg3k_all_systems;
            $drawCount = 3;
            $allowCyclicPot = true;
            $shouldTrackUsedItems = true;
            $isTg3000CyclePhase = true;
        }
        elseif (stripos($inputPhaseLabel, 'F5') !== false || stripos($inputPhaseLabel, 'Final') !== false) {
            $basePool = $tg3k_all_systems;
            $drawCount = 4;
            $allowCyclicPot = true;
            $shouldTrackUsedItems = true;
            $isTg3000CyclePhase = true;
        }
        else {
            throw new Exception('Fase não suportada para o TG3000.');
        }
    }

    if ($isLaLigaSix) {
        global $tracks_tg1;
        $basePool = $tracks_tg1;
        $specialRules = true;
        $stonehengeRule = true;

        if (stripos($inputPhaseLabel, 'F1') !== false || stripos($inputPhaseLabel, 'Grupos') !== false) {
            // Fase de grupos: processa as 9 rodadas no backend com balanceamento estatístico.
            $customLogicApplied = true;
            $shouldTrackUsedItems = false;
            $drawResult = [];
            $requiredCountries = ['USA', 'SAM', 'JAP', 'GER', 'SCN', 'FRA', 'ITA', 'UKG'];
            $targetTrack = "32 - UKG - Stonehenge";
            $maxAppearancesPerTrack = 3; // 90 seleções / 32 pistas => alvo ~2-3
            $usageMap = [];
            foreach ($basePool as $track) {
                $usageMap[$track] = 0;
            }

            // Índice por país para facilitar seleção balanceada.
            $tracksByCountry = [];
            foreach ($requiredCountries as $country) {
                $tracksByCountry[$country] = array_values(array_filter($basePool, function($t) use ($country) {
                    return strpos($t, " - $country - ") !== false;
                }));
            }

            $prevRoundTracks = [];
            $pickBalancedTrack = function(array $candidates, array $usageMap, array $prevRoundTracks, bool $preferNotPrevRound = true) {
                if (empty($candidates)) {
                    return null;
                }

                if ($preferNotPrevRound) {
                    $notPrev = array_values(array_filter($candidates, function($t) use ($prevRoundTracks) {
                        return !in_array($t, $prevRoundTracks, true);
                    }));
                    if (!empty($notPrev)) {
                        $candidates = $notPrev;
                    }
                }

                // Peso por uso: menos usadas têm chance muito maior.
                $weights = [];
                $totalWeight = 0;
                foreach ($candidates as $track) {
                    $u = $usageMap[$track] ?? 0;
                    if ($u <= 0) {
                        $weight = 120;
                    } elseif ($u === 1) {
                        $weight = 40;
                    } elseif ($u === 2) {
                        $weight = 12;
                    } else {
                        $weight = 2;
                    }

                    // Penaliza repetição na rodada imediatamente anterior.
                    if (in_array($track, $prevRoundTracks, true)) {
                        $weight = max(1, (int)floor($weight * 0.2));
                    }

                    $weights[] = $weight;
                    $totalWeight += $weight;
                }

                if ($totalWeight <= 0) {
                    return $candidates[array_rand($candidates)];
                }

                $roll = mt_rand(1, $totalWeight);
                $acc = 0;
                foreach ($candidates as $idx => $track) {
                    $acc += $weights[$idx];
                    if ($roll <= $acc) {
                        return $track;
                    }
                }

                return $candidates[array_rand($candidates)];
            };

            for ($round = 1; $round <= 9; $round++) {
                $roundDraw = [];

                // 1) Garante 1 pista por país, priorizando menos usadas e não repetidas da rodada anterior.
                foreach ($requiredCountries as $country) {
                    $countryPool = $tracksByCountry[$country] ?? [];
                    $countryCandidates = array_values(array_filter($countryPool, function($t) use ($usageMap, $maxAppearancesPerTrack, $roundDraw) {
                        return ($usageMap[$t] ?? 0) < $maxAppearancesPerTrack && !in_array($t, $roundDraw, true);
                    }));

                    if (empty($countryCandidates)) {
                        $countryCandidates = array_values(array_filter($countryPool, function($t) use ($roundDraw) {
                            return !in_array($t, $roundDraw, true);
                        }));
                    }

                    $pick = $pickBalancedTrack($countryCandidates, $usageMap, $prevRoundTracks, true);
                    if ($pick !== null) {
                        $roundDraw[] = $pick;
                        $usageMap[$pick] = ($usageMap[$pick] ?? 0) + 1;
                    }
                }

                // 2) Completa até 10 com o mesmo critério de balanceamento global.
                while (count($roundDraw) < 10) {
                    $globalCandidates = array_values(array_filter($basePool, function($t) use ($usageMap, $maxAppearancesPerTrack, $roundDraw) {
                        return ($usageMap[$t] ?? 0) < $maxAppearancesPerTrack && !in_array($t, $roundDraw, true);
                    }));

                    if (empty($globalCandidates)) {
                        $globalCandidates = array_values(array_filter($basePool, function($t) use ($roundDraw) {
                            return !in_array($t, $roundDraw, true);
                        }));
                    }

                    if (empty($globalCandidates)) {
                        break;
                    }

                    $pick = $pickBalancedTrack($globalCandidates, $usageMap, $prevRoundTracks, true);
                    if ($pick === null) {
                        break;
                    }

                    $roundDraw[] = $pick;
                    $usageMap[$pick] = ($usageMap[$pick] ?? 0) + 1;
                }

                // Stonehenge sempre por último, se sorteada.
                $foundIndex = -1;
                foreach ($roundDraw as $idx => $track) {
                    if ($track === $targetTrack) {
                        $foundIndex = $idx;
                        break;
                    }
                }
                if ($foundIndex !== -1) {
                    unset($roundDraw[$foundIndex]);
                    $roundDraw = array_values($roundDraw);
                    $roundDraw[] = $targetTrack;
                }

                $roundDraw = array_slice($roundDraw, 0, 10);
                $drawResult[] = "Rodada $round - " . implode(', ', $roundDraw);
                $prevRoundTracks = $roundDraw;
            }
        }
        elseif (stripos($inputPhaseLabel, 'F3') !== false || stripos($inputPhaseLabel, '4') !== false) {
            $drawCount = 12;
        }
        elseif (stripos($inputPhaseLabel, 'F4') !== false || stripos($inputPhaseLabel, 'Semi') !== false) {
            $drawCount = 12;
        }
        elseif (stripos($inputPhaseLabel, 'F5') !== false || stripos($inputPhaseLabel, 'Final') !== false) {
            $drawCount = 16;
        }
        else {
            throw new Exception('Fase não suportada para este torneio. Use Fase de Grupos, 4° de Final, Semifinal ou Final e 3°.');
        }
    }

    // --- LÓGICA DE SORTEIO PADRÃO (Se não foi customizada acima) ---
    $nextUsedItemsState = $usedItems; 
    $residue = [];

    if (!$customLogicApplied) {
        $availablePool = array_diff($basePool, $usedItems);
        
        // Lógica Cíclica
        if ($allowCyclicPot && count($availablePool) < $drawCount) {
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
        if ($shouldTrackUsedItems) {
            if (empty($nextUsedItemsState) && count($residue) > 0) {
                $nextUsedItemsState = $itemsDrawnFromNewPool;
            } else {
                $nextUsedItemsState = array_merge($nextUsedItemsState, $drawResult);
            }
            $nextUsedItemsState = array_values(array_unique($nextUsedItemsState));
        } elseif ($isTg3000Tournament && !$isTg3000CyclePhase) {
            $nextUsedItemsState = $currentState['usedItemsTg3000Cycle'] ?? ($currentState['usedItems'] ?? []);
        } else {
            $nextUsedItemsState = $currentState['usedItemsKnockout'] ?? ($currentState['usedItems'] ?? []);
        }
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

    if ($isLaLigaSix) {
        $finalState = FileManager::readJson($stateFile);
        $finalState['usedItemsKnockout'] = $nextUsedItemsState;
        FileManager::writeJson($stateFile, $finalState);
    }

    if ($isTg3000Tournament && $isTg3000CyclePhase) {
        $finalState = FileManager::readJson($stateFile);
        $finalState['usedItemsTg3000Cycle'] = $nextUsedItemsState;
        FileManager::writeJson($stateFile, $finalState);
    }

    if ($isCenario401Tournament) {
        $finalState = FileManager::readJson($stateFile);
        $finalState['usedItemsPotA'] = $usedItemsPotA;
        $finalState['usedItemsPotB'] = $usedItemsPotB;
        $finalState['usedItemsPotC'] = $usedItemsPotC;
        FileManager::writeJson($stateFile, $finalState);
    }

    if ($isCenario402Tournament) {
        $finalState = FileManager::readJson($stateFile);
        $finalState['usedItemsPot402'] = $usedItemsPot402;
        FileManager::writeJson($stateFile, $finalState);
    }
    if ($isCenario403Tournament) {
        $finalState = FileManager::readJson($stateFile);
        $finalState['usedItemsPot403'] = $usedItemsPot403;
        FileManager::writeJson($stateFile, $finalState);
    }

    if ($isCenario404Or406Tournament) {
        $finalState = FileManager::readJson($stateFile);
        $finalState['usedItemsPotFull'] = $usedItemsPotFull;
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
