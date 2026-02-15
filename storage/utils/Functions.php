<?php
// storage/utils/Functions.php

// Garante que o config esteja carregado
if (!defined('LOG_FILE')) {
    $configPath = __DIR__ . '/../data/Config.php';
    if (file_exists($configPath)) {
        require_once $configPath;
    }
}

/**
 * Classe Logger Robusta para registrar eventos no execution.log
 */
class Logger {
    public static function log($msg, $level = 'INFO') {
        // Se LOG_FILE não estiver definido, evita crash
        if (!defined('LOG_FILE')) return;

        // Garante que o diretório de logs existe
        $logDir = dirname(LOG_FILE);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $date = date('Y-m-d H:i:s');
        $pid = getmypid(); // Útil para identificar requisições simultâneas
        
        // Formato: [DATA] [PID] [NIVEL] MENSAGEM
        $logMessage = "[$date] [$pid] [$level] $msg" . PHP_EOL;
        
        // Grava no arquivo
        file_put_contents(LOG_FILE, $logMessage, FILE_APPEND);
    }

    public static function info($msg) { self::log($msg, 'INFO'); }
    public static function error($msg) { self::log($msg, 'ERROR'); }
    public static function success($msg) { self::log($msg, 'SUCCESS'); }
    public static function warning($msg) { self::log($msg, 'WARNING'); }
    public static function debug($msg) { 
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            self::log($msg, 'DEBUG'); 
        }
    }
}

// ... (Funções de renderização visual) ...

function render_button($id, $label, $onclick, $color = 'blue', $icon = null) {
    $colorClass = "bg-{$color}-600 hover:bg-{$color}-700";
    if ($color == 'red') $colorClass = "bg-red-600 hover:bg-red-700";
    if ($color == 'indigo') $colorClass = "bg-indigo-600 hover:bg-indigo-700";
    if ($color == 'green') $colorClass = "bg-green-600 hover:bg-green-700";
    if ($color == 'purple') $colorClass = "bg-purple-600 hover:bg-purple-700";
    
    $iconHtml = $icon ? "<i class='fas fa-{$icon} mr-2'></i>" : "";
    
    echo "
    <button id='{$id}' onclick=\"{$onclick}\" class='{$colorClass} text-white font-bold py-2 px-4 rounded shadow transition-all duration-200 flex items-center justify-center transform hover:scale-105'>
        {$iconHtml} {$label}
    </button>
    ";
}

function render_tournament_card($id, $title, $subtitle, $action_function) {
    // Definição das Fases e Cores
    // ESTRUTURA ATUALIZADA: 'title' agora é a SIGLA (F0, F1...) e 'label' é o NOME (Eliminatórias...)
    $phases = [];

    // CASO 1: Torneios de Rodadas (102, 118 e 106)
    if ($id == 102 || $id == 118 || $id == 106) {
        $phases = [
            ['title' => 'F6', 'label' => 'Rodada', 'color' => 'bg-purple-600 hover:bg-purple-500', 'text' => 'text-white']
        ];
    } 
    // CASO 2: Torneio 109 (Com 8° de Final)
    elseif ($id == 109) {
        $phases = [
            ['title' => 'F0', 'label' => 'Eliminatórias',  'color' => 'bg-slate-800 hover:bg-slate-700', 'text' => 'text-white'],
            ['title' => 'F2', 'label' => '8° de Final',    'color' => 'bg-green-600 hover:bg-green-500', 'text' => 'text-white'], 
            ['title' => 'F3', 'label' => '4° de Final',    'color' => 'bg-yellow-400 hover:bg-yellow-300', 'text' => 'text-yellow-900'], 
            ['title' => 'F4', 'label' => 'Semifinal',      'color' => 'bg-orange-500 hover:bg-orange-400', 'text' => 'text-white'], 
            ['title' => 'F5', 'label' => 'Final e 3°',     'color' => 'bg-red-600 hover:bg-red-500',    'text' => 'text-white']
        ];
    }
    // CASO 3: Torneio 117 (Sem 8° de Final)
    elseif ($id == 117) {
        $phases = [
            ['title' => 'F0', 'label' => 'Eliminatórias',  'color' => 'bg-slate-800 hover:bg-slate-700', 'text' => 'text-white'],
            // Pula F2 (8° de Final)
            ['title' => 'F3', 'label' => '4° de Final',    'color' => 'bg-yellow-400 hover:bg-yellow-300', 'text' => 'text-yellow-900'], 
            ['title' => 'F4', 'label' => 'Semifinal',      'color' => 'bg-orange-500 hover:bg-orange-400', 'text' => 'text-white'], 
            ['title' => 'F5', 'label' => 'Final e 3°',     'color' => 'bg-red-600 hover:bg-red-500',    'text' => 'text-white']
        ];
    }
    // CASO 4: Padrão para os demais (Sem Eliminatórias, Com F1 e F2)
    else {
        $phases = [
            // Pula F0
            ['title' => 'F1', 'label' => 'F. de Grupos',   'color' => 'bg-blue-500 hover:bg-blue-400',   'text' => 'text-white'], 
            ['title' => 'F2', 'label' => '8° de Final',    'color' => 'bg-green-600 hover:bg-green-500', 'text' => 'text-white'], 
            ['title' => 'F3', 'label' => '4° de Final',    'color' => 'bg-yellow-400 hover:bg-yellow-300', 'text' => 'text-yellow-900'], 
            ['title' => 'F4', 'label' => 'Semifinal',      'color' => 'bg-orange-500 hover:bg-orange-400', 'text' => 'text-white'], 
            ['title' => 'F5', 'label' => 'Final e 3°',     'color' => 'bg-red-600 hover:bg-red-500',    'text' => 'text-white']  
        ];
    }

    echo "
    <div class='bg-white p-4 rounded-lg shadow-md border-l-4 border-indigo-500 hover:shadow-lg transition-shadow relative'>
        <div class='flex justify-between items-start mb-2'>
            <h3 class='text-lg font-bold text-gray-800 leading-tight w-3/4'>{$title}</h3>
            <span class='text-xs font-semibold bg-indigo-100 text-indigo-800 px-2 py-1 rounded'>ID: {$id}</span>
        </div>
        <p class='text-xs text-gray-500 mb-4 h-10 overflow-hidden'>{$subtitle}</p>
        
        <!-- Botões de Fase -->
        <div class='mb-4'>
            <label class='text-xs font-bold text-gray-400 uppercase mb-1 block'>Selecione a Fase:</label>
            <div class='grid grid-cols-3 gap-2' id='phases-{$id}'>
                ";
                foreach ($phases as $idx => $p) {
                    echo "<button 
                            onclick=\"app.selectPhase({$id}, {$idx}, '{$p['label']}')\" 
                            class='phase-btn-{$id} {$p['color']} {$p['text']} text-[10px] py-2 rounded shadow-sm font-bold transition-transform active:scale-95 border-2 border-transparent hover:border-white/30 truncate'
                            title='{$p['title']}'
                            data-phase-name='{$p['label']}'>
                            {$p['label']}
                          </button>";
                }
    echo "
            </div>
            <div id='selected-phase-display-{$id}' class='text-xs text-center font-bold mt-1 text-indigo-600 h-4'></div>
        </div>

        <div class='mt-2'>
            <button id='btn-{$id}' 
                    onclick=\"app.{$action_function}({$id})\" 
                    disabled
                    class='w-full bg-gray-300 text-gray-500 cursor-not-allowed font-bold py-2 px-4 rounded shadow transition-all duration-200 flex items-center justify-center'>
                <i class='fas fa-dice mr-2'></i> Sortear
            </button>
        </div>

        <div id='result-{$id}' class='mt-3 p-2 bg-gray-50 rounded hidden text-sm border border-gray-200'></div>
    </div>
    ";
}

function render_section_header($title, $icon, $showLegend = false) {
    $legendHtml = "";
    if ($showLegend) {
        $legendHtml = "
        <div class='group relative ml-2 inline-block'>
            <i class='fas fa-circle-question text-gray-400 hover:text-indigo-600 cursor-pointer text-lg transition-colors'></i>
            <div class='absolute left-0 top-full mt-2 hidden group-hover:block w-56 bg-slate-800 text-white text-xs rounded-lg p-3 z-50 shadow-xl border border-slate-700'>
                <p class='font-bold border-b border-slate-600 pb-1 mb-2 text-indigo-400 uppercase tracking-wider'>Legenda de Fases</p>
                <ul class='space-y-1.5'>
                    <li class='flex items-center gap-2'><span class='w-6 text-center bg-slate-700 rounded text-[10px] font-bold py-0.5'>F0</span> Eliminatórias</li>
                    <li class='flex items-center gap-2'><span class='w-6 text-center bg-blue-500 rounded text-[10px] font-bold py-0.5'>F1</span> F. de Grupos</li>
                    <li class='flex items-center gap-2'><span class='w-6 text-center bg-green-600 rounded text-[10px] font-bold py-0.5'>F2</span> 8° de Final</li>
                    <li class='flex items-center gap-2'><span class='w-6 text-center bg-yellow-400 text-yellow-900 rounded text-[10px] font-bold py-0.5'>F3</span> 4° de Final</li>
                    <li class='flex items-center gap-2'><span class='w-6 text-center bg-orange-500 rounded text-[10px] font-bold py-0.5'>F4</span> Semifinal</li>
                    <li class='flex items-center gap-2'><span class='w-6 text-center bg-red-600 rounded text-[10px] font-bold py-0.5'>F5</span> Final e 3°</li>
                    <li class='flex items-center gap-2'><span class='w-6 text-center bg-purple-600 rounded text-[10px] font-bold py-0.5'>F6</span> Rodada</li>
                </ul>
                <div class='absolute -top-1 left-2 w-2 h-2 bg-slate-800 rotate-45 border-l border-t border-slate-700'></div>
            </div>
        </div>
        ";
    }

    echo "
    <div class='flex items-center mb-6 border-b pb-2'>
        <div class='p-2 bg-indigo-600 rounded-lg text-white mr-3 shadow-sm'>
            <i class='fas fa-{$icon} text-xl'></i>
        </div>
        <h2 class='text-2xl font-bold text-gray-800 flex items-center'>
            {$title}
            {$legendHtml}
        </h2>
    </div>
    ";
}

function render_nav_item($id, $label, $icon, $isActive = false) {
    $activeClass = $isActive 
        ? "text-indigo-600 border-b-2 border-indigo-600 bg-indigo-50" 
        : "text-gray-500 hover:text-indigo-500 hover:bg-gray-50";
        
    echo "
    <button onclick=\"app.switchTab('{$id}')\" id='tab-btn-{$id}' class='w-full py-4 px-1 text-center font-medium text-sm focus:outline-none transition-colors duration-200 {$activeClass}'>
        <i class='fas fa-{$icon} mb-1 block text-lg'></i>
        {$label}
    </button>
    ";
}
?>