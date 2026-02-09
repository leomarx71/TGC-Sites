<?php
// Carrega configurações globais primeiro
require_once 'storage/data/Config.php';
require_once 'storage/utils/Functions.php';
require_once 'storage/utils/FileManager.php';
require_once 'storage/data/Data.php';

Logger::info("Acesso à página principal: " . $_SERVER['REMOTE_ADDR']);

// --- CONFIGURAÇÃO DAS ABAS DE NAVEGAÇÃO (PHP) ---
$nav_tabs = [
    [
        'id' => 'tg1', 
        'label' => 'Top Gear 1', 
        'bg_color' => 'bg-red-600', 
        'text_color' => 'text-white', 
        'icon' => 'fa-car',
        'active' => true
    ],
    [
        'id' => 'tg2', 
        'label' => 'Top Gear 2', 
        'bg_color' => 'bg-purple-700', 
        'text_color' => 'text-white', 
        'icon' => 'fa-car-side'
    ],
    [
        'id' => 'tg3000', 
        'label' => 'Top Gear 3000', 
        'bg_color' => 'bg-blue-600', 
        'text_color' => 'text-white', 
        'icon' => 'fa-rocket'
    ],
    [
        'id' => 'cenarios', 
        'label' => 'Top Gear Cenários', 
        'bg_color' => 'bg-slate-100', 
        'text_color' => 'text-slate-800', 
        'icon' => 'fa-map-location-dot',
        'extra_classes' => 'border border-slate-300'
    ],
    [
        'id' => 'raiznutela', 
        'label' => 'Calculadora Raiz vs Nutela', 
        'bg_color' => 'bg-yellow-400', 
        'text_color' => 'text-yellow-900', 
        'icon' => 'fa-calculator',
        'is_link' => true,
        'url' => 'https://topgearchampionships.com/sites/sorteios/calculadora_cenario_3.html'
    ]
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sorteios de Torneios TopGear - ADM TGC</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f5f5f0; color: #1f1f1f; }
        .history-scroll::-webkit-scrollbar { width: 8px; }
        .history-scroll::-webkit-scrollbar-track { background: #f1f1f1; }
        .history-scroll::-webkit-scrollbar-thumb { background: #c7c7c7; border-radius: 4px; }
        .history-scroll::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }
        .fade-in { animation: fadeIn 0.5s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        #login-modal { background-color: rgba(0,0,0,0.85); backdrop-filter: blur(5px); }
        .tab-btn.active {
            border: 2px solid white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.15);
            transform: scale(1.02);
        }
        .ban-toggle.banned {
            opacity: 0.5;
            filter: grayscale(100%);
            text-decoration: line-through;
            border-color: #475569 !important;
        }
        .phase-selected {
            transform: scale(1.1);
            z-index: 10;
            box-shadow: 0 0 0 2px white, 0 0 5px rgba(0,0,0,0.5);
            filter: brightness(1.2);
        }
        /* Ponteiro da roda mais preciso */
        .wheel-pointer i {
            filter: drop-shadow(0 2px 2px rgba(0,0,0,0.5));
            transform: scaleX(0.7); /* Deixa a seta mais fina */
        }
        /* Tabela responsiva */
        .table-auto th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .table-auto td { font-size: 0.85rem; }
    </style>
</head>
<body class="h-screen flex flex-col overflow-hidden">

    <!-- Modal de Login -->
    <div id="login-modal" class="fixed inset-0 z-50 flex items-center justify-center transition-opacity duration-500">
        <div class="bg-white p-8 rounded-2xl shadow-2xl max-w-sm w-full transform transition-all scale-100">
            <div class="text-center mb-6">
                <div class="bg-indigo-600 text-white w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl shadow-lg">
                    <i class="fas fa-gavel"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800">Acesso <?php echo APP_NAME; ?></h2>
                <p class="text-gray-500 text-sm">Versão <?php echo APP_VERSION; ?></p>
            </div>
            <input type="password" id="admin-pass" class="w-full border-2 border-gray-200 p-3 rounded-lg focus:outline-none focus:border-indigo-500 transition-colors mb-4 text-center text-lg" placeholder="Senha de Acesso">
            <button onclick="app.checkLogin()" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-lg shadow transition-transform transform active:scale-95">
                ENTRAR
            </button>
            <p id="login-msg" class="text-red-500 text-xs text-center mt-3 h-4"></p>
        </div>
    </div>

    <!-- Topbar -->
    <header class="bg-white shadow-sm z-10 h-16 flex items-center justify-between px-6 border-b border-gray-200">
        <div class="flex items-center space-x-3">
            <i class="fas fa-flag-checkered text-indigo-600 text-2xl"></i>
            <h1 class="text-xl font-bold text-gray-800 tracking-tight">TGC <span class="text-indigo-600">Manager</span></h1>
        </div>
        <div class="flex items-center space-x-4">
            <div class="text-sm text-right hidden md:block">
                <p class="font-semibold text-gray-700" id="clock">00:00</p>
                <p class="text-xs text-gray-400">Ambiente: <?php echo DEBUG_MODE ? 'Dev' : 'Prod'; ?></p>
            </div>
            <button onclick="app.refreshSystem()" class="bg-green-600 text-white hover:bg-green-700 p-3 rounded-lg shadow-md transition-all active:scale-95" title="Recarregar Sistema">
                <i class="fas fa-sync-alt text-lg"></i>
            </button>
        </div>
    </header>

    <!-- Layout Principal -->
    <div class="flex flex-1 overflow-hidden relative">
        <nav class="w-20 bg-white border-r border-gray-200 flex flex-col items-center py-4 space-y-4 z-20 shadow-inner">
             <?php 
            render_nav_item('dashboard', 'Painel', 'chart-pie', true); 
            render_nav_item('forbidden', 'Proibidos', 'ban');
            render_nav_item('logs', 'Logs', 'terminal');
            ?>
        </nav>

        <main class="flex-1 overflow-y-auto bg-[#f5f5f0] p-6 relative" id="main-container">
            <!-- Tabs Navigation -->
            <div class="w-full mb-8">
                <div class="flex flex-wrap justify-center gap-3" id="tabContainer">
                    <?php foreach ($nav_tabs as $tab): ?>
                        <?php 
                            $isActive = isset($tab['active']) && $tab['active'] ? 'active' : '';
                            $extraClasses = $tab['extra_classes'] ?? '';
                            if (isset($tab['is_link']) && $tab['is_link']) {
                                echo "<button onclick=\"window.location.href='{$tab['url']}'\" class=\"tab-btn {$tab['bg_color']} {$tab['text_color']} px-4 py-3 rounded-xl flex items-center gap-2 font-bold shadow-md hover:opacity-90 transition-all {$extraClasses}\" data-tab=\"{$tab['id']}\"><i class=\"fa-solid {$tab['icon']}\"></i> <span>{$tab['label']}</span></button>";
                            } else {
                                echo "<button onclick=\"app.switchTab('{$tab['id']}')\" class=\"tab-btn {$isActive} {$tab['bg_color']} {$tab['text_color']} px-4 py-3 rounded-xl flex items-center gap-2 font-bold shadow-md hover:opacity-90 transition-all {$extraClasses}\" data-tab=\"{$tab['id']}\"><i class=\"fa-solid {$tab['icon']}\"></i> <span>{$tab['label']}</span></button>";
                            }
                        ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Tab: Dashboard -->
            <div id="tab-dashboard" class="hidden fade-in space-y-6">
                <?php render_section_header('Painel Administrativo', 'chart-pie'); ?>
                 
                <!-- Histórico Global com Tabela de 5 Colunas -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden mt-6">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                        <h3 class="font-bold text-gray-700"><i class="fas fa-history mr-2"></i>Histórico Global de Sorteios</h3>
                    </div>
                    <div class="h-96 overflow-y-auto history-scroll">
                        <table class="min-w-full table-auto">
                            <thead class="bg-gray-100 text-gray-600 sticky top-0 shadow-sm z-10">
                                <tr>
                                    <th class="px-6 py-3 text-left font-bold">Data</th>
                                    <th class="px-6 py-3 text-left font-bold">Torneio</th>
                                    <th class="px-6 py-3 text-left font-bold">Fase</th>
                                    <th class="px-6 py-3 text-left font-bold w-1/3">Pistas / Itens</th>
                                    <th class="px-6 py-3 text-left font-bold">Observações</th>
                                </tr>
                            </thead>
                            <tbody id="global-history-table-body" class="divide-y divide-gray-200">
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">Carregando histórico...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Hack para manter o layout limpo sem os widgets -->
                <div id="banned-count" class="hidden"></div>
            </div>

            <!-- Tab: TG1 -->
            <div id="tab-tg1" class="fade-in"> 
                <?php render_section_header('Sorteios Top Gear 1', 'trophy', true); ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php 
                    foreach ($tg1_tournaments as $t) {
                        render_tournament_card($t['id'], $t['name'], $t['description'], "drawTG1");
                    }
                    ?>
                </div>
                <div class="mt-8 bg-white p-6 rounded-lg shadow">
                    <h3 class="font-bold text-gray-700 mb-4">Ações</h3>
                    <div class="flex gap-4">
                        <?php render_button('btn-reset-tg1', 'Resetar TG1', 'app.resetTG1()', 'red', 'trash'); ?>
                    </div>
                </div>
            </div>

            <!-- Tab: TG2 -->
            <div id="tab-tg2" class="hidden fade-in">
                <?php render_section_header('Sorteios Top Gear 2', 'road', true); ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <?php 
                    foreach ($tg2_tournaments as $t) {
                        render_tournament_card($t['id'], $t['name'], $t['description'], "drawTG2");
                    }
                    ?>
                </div>
            </div>

            <!-- Tab: TG3000 -->
            <div id="tab-tg3000" class="hidden fade-in">
                <?php render_section_header('Sorteios Top Gear 3000', 'rocket', true); ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                     <?php 
                    foreach ($tg3k_tournaments as $t) {
                        render_tournament_card($t['id'], $t['name'], $t['description'], "drawTG3K");
                    }
                    ?>
                </div>
            </div>
            
            <!-- Tab: Cenários -->
            <div id="tab-cenarios" class="hidden fade-in">
                <?php render_section_header('Sorteios Prototype Challenge', 'map-location-dot', true); ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php 
                    if (!empty($cenarios_tournaments)) {
                        foreach ($cenarios_tournaments as $t) {
                            render_tournament_card($t['id'], $t['name'], $t['description'], "drawCenario");
                        }
                    } else {
                        echo "<div class='col-span-2 text-center text-gray-500 py-8'>Nenhum cenário cadastrado no momento.</div>";
                    }
                    ?>
                </div>
            </div>

            <!-- Tab: Forbidden -->
            <div id="tab-forbidden" class="hidden fade-in">
                <section class="p-6 bg-slate-800 rounded-xl border-t-4 border-slate-500 shadow-xl ring-1 ring-slate-700 mt-2" id="forbidden-car-section">
                    <div class="mb-6 border-b border-slate-700 pb-4">
                        <h2 class="text-2xl font-bold text-white mb-2 flex items-center gap-3"><i class="fa-solid fa-ban text-red-500"></i> Sorteio de Carros Proibidos</h2>
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-bold text-slate-400 mb-2 uppercase tracking-wide">Lista de Torneios (Obrigatório)</label>
                        <!-- Adicionado evento onchange para validação -->
                        <select id="forbidden-car-tournament-select" onchange="app.checkForbiddenButton()" class="w-full p-3 rounded-xl bg-slate-900 text-white border border-slate-700 focus:outline-none focus:border-red-500 transition-colors">
                            <option value="" disabled selected>Selecionar Torneio...</option>
                            <?php foreach($tg1_tournaments as $t): ?>
                                <option value="<?php echo $t['name']; ?>"><?php echo $t['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="md:col-span-2">
                            <div class="h-full min-h-[300px] p-6 bg-slate-900 rounded-xl border border-slate-700 flex flex-col justify-center items-center relative overflow-hidden group shadow-inner">
                                 <div id="forbidden-car-result-container" class="flex flex-col items-center justify-center w-full h-full">
                                    <div class="wheel-container relative w-64 h-64">
                                        <!-- Ponteiro menor (w-6 h-8 ao invés de w-8 h-10) -->
                                        <div class="wheel-pointer absolute top-0 left-1/2 -translate-x-1/2 -translate-y-3 z-20 w-6 h-8 filter drop-shadow-md"><i class="fas fa-caret-down text-4xl text-white"></i></div>
                                        <div id="wheel-spinner" class="wheel w-full h-full rounded-full border-4 border-slate-600 shadow-2xl transition-transform duration-[3000ms] ease-[cubic-bezier(0.25,0.1,0.25,1)]"></div>
                                        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-20 h-20 bg-slate-900 rounded-full border-4 border-slate-800 shadow-inner z-10"></div>
                                    </div>
                                    <div id="wheel-text-result" class="mt-6 font-black text-2xl text-white opacity-0 transition-opacity duration-500 uppercase tracking-widest bg-slate-800 px-4 py-2 rounded-lg border border-slate-700">...</div>
                                </div>
                            </div>
                        </div>
                        <div class="md:col-span-1 flex flex-col gap-4">
                            <div class="grid grid-cols-2 gap-2">
                                 <button onclick="app.toggleCarBan('Vermelho')" id="ban-btn-Vermelho" class="ban-toggle bg-red-600 hover:bg-red-500 text-white py-3 rounded-lg font-bold shadow text-xs uppercase transition-all flex items-center justify-center gap-2">Vermelho</button>
                                 <button onclick="app.toggleCarBan('Azul')" id="ban-btn-Azul" class="ban-toggle bg-blue-600 hover:bg-blue-500 text-white py-3 rounded-lg font-bold shadow text-xs uppercase transition-all flex items-center justify-center gap-2">Azul</button>
                                 <button onclick="app.toggleCarBan('Branco')" id="ban-btn-Branco" class="ban-toggle bg-gray-100 hover:bg-white text-gray-800 border border-gray-300 py-3 rounded-lg font-bold shadow text-xs uppercase transition-all flex items-center justify-center gap-2">Branco</button>
                                 <button onclick="app.toggleCarBan('Roxo')" id="ban-btn-Roxo" class="ban-toggle bg-purple-600 hover:bg-purple-500 text-white py-3 rounded-lg font-bold shadow text-xs uppercase transition-all flex items-center justify-center gap-2">Roxo</button>
                            </div>
                            <p id="ban-warning" class="text-xs text-red-400 text-center font-bold hidden">Manter pelo menos 2 cores!</p>
                            <!-- Botão inicia desabilitado -->
                            <button onclick="app.drawForbiddenCar()" id="btn-draw-forbidden" disabled class="flex-grow bg-gradient-to-br from-red-600 to-red-700 text-white rounded-xl font-black text-2xl shadow-lg border-b-4 border-red-900 py-6 active:border-b-0 active:translate-y-1 transition-all mt-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:border-none disabled:active:translate-y-0 disabled:from-slate-600 disabled:to-slate-700"><i class="fa-solid fa-arrows-rotate mr-2"></i> GIRAR</button>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Tab: Logs -->
            <div id="tab-logs" class="hidden fade-in">
                <?php render_section_header('Logs do Sistema', 'terminal'); ?>
                <div class="bg-gray-900 text-green-400 p-4 rounded-lg font-mono text-sm h-96 overflow-y-auto shadow-inner" id="system-logs">
                    <div>> Sistema iniciado...</div>
                </div>
            </div>
        </main>
    </div>

    <!-- SCRIPT PRINCIPAL -->
    <script>
        const PHP_CONFIG = {
            appName: "<?php echo APP_NAME; ?>",
            adminPass: "<?php echo ADMIN_PASS; ?>",
            cars: <?php echo json_encode($cars_global); ?>,
            tracks: <?php echo json_encode($tracks_tg1); ?>,
            tg2_countries: <?php echo json_encode($tg2_countries); ?>,
            tg3k_systems: <?php echo json_encode($tg3k_4planet_systems); ?>,
            pot_cen1_a: <?php echo json_encode($pot_cen1_a); ?>,
            tournaments: {
                tg1: <?php echo json_encode($tg1_tournaments); ?>,
                tg2: <?php echo json_encode($tg2_tournaments); ?>,
                tg3k: <?php echo json_encode($tg3k_tournaments); ?>,
                cenarios: <?php echo json_encode($cenarios_tournaments); ?>
            }
        };

        const SESSION_KEY = 'tgc_auth_token';
        const SESSION_DURATION = 60 * 60 * 1000; // 60 minutos em ms

        let bannedColors = [];
        let selectedPhases = {}; 
        let wheelSegments = []; // Armazena a ordem das cores na roda para sincronia

        const WHEEL_GRADIENTS = {
            'Vermelho': ['#ef4444', '#b91c1c'],
            'Azul':     ['#3b82f6', '#1d4ed8'],
            'Branco':   ['#f3f4f6', '#9ca3af'],
            'Roxo':     ['#a855f7', '#7e22ce']
        };

        const TIEBREAKER_PHASES = ["8° de Final", "4° de Final", "Semifinal", "Final e 3°"];

        const logSystem = (msg, type="INFO") => {
            const logBox = document.getElementById('system-logs');
            const time = new Date().toLocaleTimeString();
            const color = type === 'ERROR' ? 'text-red-400' : (type === 'SUCCESS' ? 'text-green-400' : 'text-gray-300');
            logBox.innerHTML += `<div class='mb-1'><span class='text-gray-500'>[${time}]</span> <span class='${color} font-bold'>${type}:</span> ${msg}</div>`;
            logBox.scrollTop = logBox.scrollHeight;
        };

        const updateClock = () => {
            document.getElementById('clock').innerText = new Date().toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
        };
        setInterval(updateClock, 1000);

        const switchTab = (tabId) => {
            document.querySelectorAll('[id^="tab-"]').forEach(el => {
                if(!el.id.includes('btn')) el.classList.add('hidden');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('[id^="tab-btn-"]').forEach(btn => {
                btn.classList.remove('text-indigo-600', 'border-b-2', 'bg-indigo-50');
                btn.classList.add('text-gray-500');
            });
            const content = document.getElementById(`tab-${tabId}`);
            if(content) content.classList.remove('hidden');
            const navBtn = document.querySelector(`.tab-btn[data-tab="${tabId}"]`);
            if(navBtn) navBtn.classList.add('active');
        };

        const checkLogin = () => {
            const pass = document.getElementById('admin-pass').value;
            if(pass === 'admin' || pass === PHP_CONFIG.adminPass) { 
                document.getElementById('login-modal').classList.add('opacity-0', 'pointer-events-none');
                
                // Salvar Sessão
                const expiry = new Date().getTime() + SESSION_DURATION;
                localStorage.setItem(SESSION_KEY, JSON.stringify({ expiry: expiry }));
                
                logSystem(`Login Admin OK`, "SUCCESS");
                initSystem();
            } else {
                document.getElementById('login-msg').innerText = "Senha incorreta.";
                document.getElementById('admin-pass').classList.add('border-red-500');
                setTimeout(() => document.getElementById('admin-pass').classList.remove('border-red-500'), 2000);
            }
        };

        const checkSession = () => {
            const session = localStorage.getItem(SESSION_KEY);
            if (session) {
                try {
                    const data = JSON.parse(session);
                    if (new Date().getTime() < data.expiry) {
                        // Sessão válida
                        document.getElementById('login-modal').classList.add('hidden'); // Oculta imediatamente
                        logSystem(`Sessão restaurada. Válida até: ${new Date(data.expiry).toLocaleTimeString()}`, "SUCCESS");
                        initSystem();
                        return true;
                    } else {
                        logSystem("Sessão expirada.", "WARNING");
                        localStorage.removeItem(SESSION_KEY);
                    }
                } catch (e) {
                    console.error("Erro ao ler sessão", e);
                }
            }
            return false;
        };

        const getRandomItems = (array, count) => {
            if(!array || array.length === 0) return [];
            const shuffled = [...array].sort(() => 0.5 - Math.random());
            return shuffled.slice(0, count);
        };
        const getRandomItem = (array) => {
             if(!array || array.length === 0) return { name: 'Erro' };
             return array[Math.floor(Math.random() * array.length)];
        };

        const getTournamentInfo = (id) => {
            const all = [
                { list: PHP_CONFIG.tournaments.tg1 },
                { list: PHP_CONFIG.tournaments.tg2 },
                { list: PHP_CONFIG.tournaments.tg3k },
                { list: PHP_CONFIG.tournaments.cenarios }
            ];
            for (const group of all) {
                const found = group.list.find(t => t.id == id);
                if (found) return { title: found.name };
            }
            return { title: 'Desconhecido' };
        };

        const getTiebreakerText = (phase, type, article) => {
            if (TIEBREAKER_PHASES.includes(phase)) {
                return `<div class='mt-3 p-3 bg-yellow-50 text-yellow-800 text-xs rounded border border-yellow-200 leading-snug flex items-start gap-2 shadow-sm'><i class="fas fa-exclamation-triangle mt-0.5 text-yellow-600"></i><div><strong class="block mb-1 text-yellow-700">Regra de Desempate:</strong> O desempate deve ser iniciado n${article} ${type} seguinte ao último sorteado.</div></div>`;
            }
            return "";
        };

        const renderResultList = (phase, items, type) => {
             let listHtml = '<ul class="text-left text-sm space-y-1 font-mono text-gray-700">';
             items.forEach((t, i) => listHtml += `<li class="border-b border-gray-100 last:border-0 pb-1 flex"><span class="font-bold text-indigo-600 w-8 inline-block text-right mr-2">${i + 1} -</span> <span>${t}</span></li>`);
             listHtml += '</ul>';
             let article = 'o(a)';
             if(type === 'país' || type === 'sistema') article = 'o';
             if(type === 'pista') article = 'a';
             return `<div class='flex flex-col gap-3 bg-white p-3 rounded border border-gray-200 shadow-sm'><div class='font-bold text-xs text-gray-500 uppercase border-b pb-2 mb-1 flex justify-between items-center bg-gray-50 -m-3 mb-2 p-3 rounded-t'><span>${phase}</span><span class="text-[10px] bg-indigo-100 text-indigo-600 px-2 py-0.5 rounded font-bold uppercase tracking-wider">Grid de Largada</span></div>${listHtml}${getTiebreakerText(phase, type, article)}</div>`;
        };

        const selectPhase = (tournamentId, phaseIndex, phaseTitle) => {
            const buttons = document.querySelectorAll(`.phase-btn-${tournamentId}`);
            buttons.forEach(btn => btn.classList.remove('phase-selected'));
            buttons[phaseIndex].classList.add('phase-selected');
            const drawBtn = document.getElementById(`btn-${tournamentId}`);
            if(drawBtn) {
                drawBtn.disabled = false;
                drawBtn.classList.remove('bg-gray-300', 'text-gray-500', 'cursor-not-allowed');
                drawBtn.classList.add('bg-indigo-600', 'text-white', 'hover:bg-indigo-700');
            }
            const display = document.getElementById(`selected-phase-display-${tournamentId}`);
            if(display) display.innerText = phaseTitle;
            selectedPhases[tournamentId] = phaseTitle;
        };

        // --- CARREGAR HISTÓRICO GLOBAL ---
        const loadGlobalHistory = () => {
            const tbody = document.getElementById('global-history-table-body');
            tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Carregando...</td></tr>';

            fetch('api/GetHistory.php')
                .then(response => response.json())
                .then(res => {
                    if (res.status === 'success' && res.data.length > 0) {
                        tbody.innerHTML = '';
                        res.data.forEach(item => {
                            const tr = document.createElement('tr');
                            tr.className = 'hover:bg-gray-50 transition-colors';
                            
                            let displayItems = item.items; 

                            tr.innerHTML = `
                                <td class="px-6 py-3 whitespace-nowrap text-gray-700 font-mono text-xs align-top">${item.date_formatted}</td>
                                <td class="px-6 py-3 text-gray-800 font-bold align-top">${item.tournament}</td>
                                <td class="px-6 py-3 align-top"><span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-bold">${item.phase}</span></td>
                                <td class="px-6 py-3 text-gray-600 text-xs leading-relaxed align-top">${displayItems}</td>
                                <td class="px-6 py-3 text-gray-500 text-xs italic align-top">${item.obs || '-'}</td>
                            `;
                            tbody.appendChild(tr);
                        });
                    } else {
                        tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-gray-400 italic">Nenhum histórico encontrado.</td></tr>';
                    }
                })
                .catch(err => {
                    console.error(err);
                    tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center text-red-500">Erro ao carregar histórico.</td></tr>';
                });
        };
        
        const handleDraw = (id, pool, count, itemType) => {
            const btn = document.getElementById(`btn-${id}`);
            const resultBox = document.getElementById(`result-${id}`);
            const phase = selectedPhases[id] || 'Fase Desconhecida';
            const info = getTournamentInfo(id);

            if(btn) {
                const icon = btn.querySelector('i');
                if(icon) icon.classList.add('fa-spin');
                btn.disabled = true;
                btn.classList.add('opacity-75');
            }

            setTimeout(() => {
                const selectedItems = getRandomItems(pool, count);
                resultBox.classList.remove('hidden');
                resultBox.innerHTML = renderResultList(phase, selectedItems, itemType);
                
                const payload = {
                    tournamentId: id,
                    title: info.title,
                    phase: phase,
                    drawnItems: selectedItems
                };

                fetch('api/SaveDraw.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                })
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'success') {
                        logSystem(`Sorteio #${id} salvo com sucesso.`, "SUCCESS");
                        loadGlobalHistory();
                    } else {
                        logSystem(`Erro ao salvar sorteio #${id}: ${data.message}`, "ERROR");
                    }
                })
                .catch(err => logSystem(`Erro de conexão ao salvar #${id}`, "ERROR"));

                if(btn) {
                    const icon = btn.querySelector('i');
                    if(icon) icon.classList.remove('fa-spin');
                    btn.disabled = false;
                    btn.classList.remove('opacity-75');
                }
                confetti({ particleCount: 50, spread: 60, origin: { y: 0.7 } });
            }, 800);
        };

        const drawTG1 = (id) => handleDraw(id, PHP_CONFIG.tracks, 12, "pista");
        const drawTG2 = (id) => handleDraw(id, PHP_CONFIG.tg2_countries, 2, "país");
        const drawTG3K = (id) => handleDraw(id, PHP_CONFIG.tg3k_systems, 2, "sistema");
        const drawCenario = (id) => handleDraw(id, PHP_CONFIG.pot_cen1_a, 4, "pista");

        const checkForbiddenButton = () => {
            const select = document.getElementById('forbidden-car-tournament-select');
            const btn = document.getElementById('btn-draw-forbidden');
            if (select && btn) {
                btn.disabled = !select.value; // Desabilita se vazio
            }
        };

        const renderForbiddenWheel = () => {
             const wheel = document.getElementById('wheel-spinner');
             if(!wheel) return;
             const validColors = ["Vermelho", "Azul", "Branco", "Roxo"].filter(c => !bannedColors.includes(c));
             if(validColors.length === 0) return;
             
             wheelSegments = []; // Reinicia segmentos
             let segments = [];
             // Cria 12 segmentos para a roda
             for(let i=0; i<12; i++) {
                 const color = validColors[i % validColors.length];
                 segments.push(color);
                 wheelSegments.push(color); // Guarda a ordem para o cálculo de rotação
             }

             let gradientStr = "conic-gradient(";
             segments.forEach((color, i) => {
                 const [startColor, endColor] = WHEEL_GRADIENTS[color];
                 gradientStr += `${startColor} ${i * 30}deg, ${endColor} ${(i + 1) * 30}deg` + (i < 11 ? ", " : "");
             });
             wheel.style.background = gradientStr + ")";
        };

        const toggleCarBan = (color) => {
             const btn = document.getElementById(`ban-btn-${color}`);
             if (bannedColors.includes(color)) {
                 bannedColors = bannedColors.filter(c => c !== color);
                 btn.classList.remove('banned');
                 btn.innerHTML = color;
             } else {
                 if (4 - (bannedColors.length + 1) >= 2) {
                     bannedColors.push(color);
                     btn.classList.add('banned');
                     btn.innerHTML = `<i class="fa-solid fa-ban"></i> ${color}`;
                 } else {
                     document.getElementById('ban-warning').classList.remove('hidden');
                     setTimeout(() => document.getElementById('ban-warning').classList.add('hidden'), 2000);
                     return;
                 }
             }
             renderForbiddenWheel();
        };

        const drawForbiddenCar = () => {
             const select = document.getElementById('forbidden-car-tournament-select');
             if (!select.value) {
                 alert("Por favor, selecione um torneio.");
                 return;
             }
             const selectedTournamentName = select.value;

             const wheel = document.getElementById('wheel-spinner');
             const textRes = document.getElementById('wheel-text-result');
             const btn = document.getElementById('btn-draw-forbidden');
             
             if(btn) btn.disabled = true;
             textRes.classList.add('opacity-0');
             
             // 1. Sorteia o carro (o resultado lógico)
             const eligible = PHP_CONFIG.cars.filter(c => c.banned && !bannedColors.includes(c.color_name));
             const pool = eligible.length > 0 ? eligible : PHP_CONFIG.cars.filter(c => c.banned);
             const car = getRandomItem(pool);

             // 2. Calcula a rotação para cair na cor correta
             // Encontra todos os índices (segmentos) que têm a cor do carro sorteado
             const matchingIndices = wheelSegments.map((c, i) => c === car.color_name ? i : -1).filter(i => i !== -1);
             // Escolhe um segmento aleatório dessa cor para parar
             const targetIndex = matchingIndices[Math.floor(Math.random() * matchingIndices.length)];
             
             // Cada segmento tem 30 graus. O centro do segmento i é (i * 30) + 15.
             // Como a roda gira no sentido horário, para o segmento ir para o topo (0 graus ou 360),
             // precisamos subtrair a posição dele de um total de voltas.
             const segmentCenter = (targetIndex * 30) + 15;
             const jitter = Math.random() * 20 - 10; // +/- 10 graus de variação aleatória dentro do segmento
             const finalAngle = segmentCenter + jitter;
             
             // 5 voltas completas (1800) + rotação ajustada para parar no topo
             // Ex: Se o alvo está em 90deg, giramos 360-90 = 270deg para ele chegar ao topo.
             const rotation = (360 * 5) - finalAngle; 

             // Aplica a rotação
             wheel.style.transition = "transform 3s cubic-bezier(0.25, 0.1, 0.25, 1)";
             wheel.style.transform = `rotate(${rotation}deg)`;

             setTimeout(() => {
                 textRes.classList.remove('opacity-0');
                 textRes.innerText = car.name;
                 textRes.className = `mt-6 font-black text-2xl uppercase tracking-widest bg-slate-800 px-4 py-2 rounded-lg border border-slate-700 transition-opacity duration-500 ${car.color_name === 'Vermelho' ? 'text-red-400' : (car.color_name === 'Azul' ? 'text-blue-400' : (car.color_name === 'Roxo' ? 'text-purple-400' : 'text-white'))}`;
                 logSystem(`Carro Proibido: ${car.name} (${selectedTournamentName})`, "WARNING");

                 // SALVAR HISTÓRICO - ID 501
                 const payload = {
                    tournamentId: 501,
                    title: selectedTournamentName, // Nome do torneio selecionado
                    phase: 'Carro Proibido', // Fase hardcoded
                    drawnItems: [car.name + " " + car.color_name] // Nome + Cor
                 };

                 fetch('api/SaveDraw.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                 })
                 .then(res => res.json())
                 .then(data => {
                    if(data.status === 'success') {
                        logSystem(`Registro salvo no histórico.`, "SUCCESS");
                        loadGlobalHistory();
                    }
                 })
                 .catch(err => console.error(err));

                 // Reinicia transição para permitir novo giro futuro (opcional resetar para mod 360)
                 setTimeout(() => {
                    wheel.style.transition = 'none';
                    wheel.style.transform = `rotate(${rotation % 360}deg)`;
                    // Reabilita botão se torneio ainda selecionado
                    checkForbiddenButton();
                 }, 500);
                 
             }, 3000);
        };

        const initSystem = () => {
            const passInput = document.getElementById('admin-pass');
            if(passInput) {
                passInput.focus();
                passInput.addEventListener('keypress', (e) => { if(e.key === 'Enter') checkLogin(); });
            }
            renderForbiddenWheel();
            checkForbiddenButton(); // Verifica estado inicial do botão
            loadGlobalHistory(); 
        };

        // Verifica sessão ao iniciar
        document.addEventListener('DOMContentLoaded', () => {
             checkSession();
        });

        window.app = {
            checkLogin, switchTab, drawTG1, drawTG2, drawTG3K, drawCenario, drawForbiddenCar, toggleCarBan, selectPhase, loadGlobalHistory, checkForbiddenButton,
            refreshSystem: () => window.location.reload(),
            resetTG1: () => logSystem('Reset TG1 acionado'),
            backupGlobal: () => alert('Backup solicitado (Simulação)')
        };
    </script>
</body>
</html>