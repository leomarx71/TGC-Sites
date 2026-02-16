<?php
require_once __DIR__ . '/storage/data/Config.php';

$pistasDisponiveis = [
    "01 USA - Las Vegas", "02 USA - Los Angeles", "03 USA - New York", "04 USA - San Francisco",
    "05 SAM - Rio", "06 SAM - Machu Picchu", "07 SAM - Chichen Itza", "08 SAM - Rain Forest",
    "09 JAP - Tokyo", "10 JAP - Hiroshima", "11 JAP - Yokohama", "12 JAP - Kyoto",
    "13 GER - Munich", "14 GER - Cologne", "15 GER - Black Forest", "16 GER - Frankfurt",
    "17 SCN - Stockholm", "18 SCN - Copenhagen", "19 SCN - Helsinki", "20 SCN - Oslo",
    "21 FRA - Paris", "22 FRA - Nice", "23 FRA - Bordeaux", "24 FRA - Monaco",
    "25 ITA - Pisa", "26 ITA - Rome", "27 ITA - Sicily", "28 ITA - Florence",
    "29 UKG - London", "30 UKG - Sheffield", "31 UKG - Loch Ness", "32 UKG - Stonehenge"
];

$trackRows = [
    ['id' => 1, 'name' => 'Pista 1', 'side' => 'IDA'],
    ['id' => 2, 'name' => 'Pista 2', 'side' => 'IDA'],
    ['id' => 3, 'name' => 'Pista 3', 'side' => 'IDA'],
    ['id' => 4, 'name' => 'Pista 4', 'side' => 'IDA'],
    ['id' => 5, 'name' => 'Pista 1', 'side' => 'VOLTA'],
    ['id' => 6, 'name' => 'Pista 2', 'side' => 'VOLTA'],
    ['id' => 7, 'name' => 'Pista 3', 'side' => 'VOLTA'],
    ['id' => 8, 'name' => 'Pista 4', 'side' => 'VOLTA'],
];

function pistaSemNumero($pista)
{
    return preg_replace('/^\d{2}\s+/', '', $pista);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cálculo Cenário 3: Raiz vs Nutela</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f5f5f0; color: #1f1f1f; }
        .page-card { background: #ffffff; border: 1px solid #e5e7eb; box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08); }
        .input-style { background-color: #fff; border: 1px solid #cbd5e1; color: #0f172a; padding: 0.5rem; border-radius: 0.5rem; }
        .input-style:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 1px #4f46e5; }
        .table-header { background-color: #4f46e5; color: #fff; }
        .table-row-ida { background-color: #eef2ff; }
        .table-row-volta { background-color: #f8fafc; }
    </style>
</head>
<body class="p-4 md:p-8 min-h-screen">
    <div class="max-w-5xl mx-auto">
        <div class="mb-4">
            <a href="index.php" class="inline-flex items-center text-sm font-semibold text-indigo-700 hover:text-indigo-900">Voltar ao TGC Manager</a>
        </div>

        <div class="w-full page-card rounded-xl p-6 md:p-8">
            <header class="text-center mb-6">
                <h1 class="text-3xl md:text-4xl font-extrabold text-indigo-700 mb-2">Raiz vs Nutela: Cálculo de Pontuação</h1>
                <p class="text-slate-600 text-sm">Cenário 3: P1 (Manual/Raiz) vs P2 (Automático/Nutela)</p>
            </header>

            <section class="bg-slate-50 p-4 rounded-lg mb-6 border-l-4 border-red-500">
                <h2 class="text-xl font-bold text-red-700 mb-3">Tabela de Regras de Pontuação:</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm rounded-lg overflow-hidden border border-slate-300">
                        <thead>
                            <tr class="bg-red-700 text-white uppercase text-xs font-bold">
                                <th class="py-2 px-3 text-left">Condição de Vitória</th>
                                <th class="py-2 px-3 text-center">Diferença >= 10s?</th>
                                <th class="py-2 px-3 text-center">Pontos P1 (Manual)</th>
                                <th class="py-2 px-3 text-center">Pontos P2 (Auto)</th>
                            </tr>
                        </thead>
                        <tbody class="text-slate-700">
                            <tr class="bg-white border-b border-slate-200">
                                <td class="py-2 px-3 font-semibold text-amber-700">P1 (Manual) vence</td>
                                <td class="py-2 px-3 text-center">N/A</td>
                                <td class="py-2 px-3 text-center font-bold text-red-700">20 pts</td>
                                <td class="py-2 px-3 text-center">10 pts</td>
                            </tr>
                            <tr class="bg-slate-50 border-b border-slate-200">
                                <td class="py-2 px-3 font-semibold text-cyan-700">P2 (Automático) vence</td>
                                <td class="py-2 px-3 text-center font-bold text-green-700">SIM</td>
                                <td class="py-2 px-3 text-center font-bold text-amber-700">15 pts</td>
                                <td class="py-2 px-3 text-center font-bold text-cyan-700">20 pts</td>
                            </tr>
                            <tr class="bg-white">
                                <td class="py-2 px-3 font-semibold text-cyan-700">P2 (Automático) vence</td>
                                <td class="py-2 px-3 text-center font-bold text-red-700">NÃO</td>
                                <td class="py-2 px-3 text-center font-bold text-amber-700">15 pts</td>
                                <td class="py-2 px-3 text-center">10 pts</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="mb-6">
                <h2 class="text-xl font-bold text-slate-800 mb-3">Pilotos (IDA):</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="p1-ida-name" class="block text-sm font-medium text-slate-600 mb-1">P1 (Manual/Raiz)</label>
                        <input type="text" id="p1-ida-name" placeholder="Nome do Piloto 1 (IDA)" class="w-full input-style">
                    </div>
                    <div>
                        <label for="p2-ida-name" class="block text-sm font-medium text-slate-600 mb-1">P2 (Automático/Nutela)</label>
                        <input type="text" id="p2-ida-name" placeholder="Nome do Piloto 2 (IDA)" class="w-full input-style">
                    </div>
                </div>
                <p class="text-xs text-red-700 mt-2 italic">Os nomes dos pilotos na VOLTA serão preenchidos automaticamente.</p>
            </section>

            <section class="overflow-x-auto">
                <table class="min-w-full text-sm rounded-lg overflow-hidden border border-slate-200">
                    <thead>
                        <tr class="text-xs uppercase font-bold text-center table-header">
                            <th class="py-2 px-1 border-r border-indigo-500">Pista</th>
                            <th class="py-2 px-1 border-r border-indigo-500">Vencedor</th>
                            <th class="py-2 px-1 border-r border-indigo-500">Diferença >= 10s?</th>
                            <th class="py-2 px-1" colspan="2">Pontos</th>
                        </tr>
                    </thead>
                    <tbody id="pista-rows" class="text-center">
                        <?php foreach ($trackRows as $index => $track): ?>
                            <?php $isIda = $track['side'] === 'IDA'; ?>
                            <tr id="track-row-<?php echo $track['id']; ?>" class="<?php echo $isIda ? 'table-row-ida' : 'table-row-volta'; ?> border-b border-slate-200">
                                <td class="py-2 px-1 text-left text-xs md:text-sm font-bold <?php echo $isIda ? 'text-amber-700' : 'text-red-700'; ?>">
                                    <span class="block mb-1"><?php echo $track['side']; ?></span>
                                    <select
                                        id="track-select-<?php echo $track['id']; ?>"
                                        class="w-full text-xs input-style p-1 <?php echo $isIda ? '' : 'opacity-80 cursor-not-allowed'; ?>"
                                        data-track-index="<?php echo $index; ?>"
                                        onchange="handleInput(<?php echo $index; ?>)"
                                        <?php echo $isIda ? '' : 'disabled'; ?>>
                                        <option value="">-- Selecione a Pista --</option>
                                        <?php foreach ($pistasDisponiveis as $pista): ?>
                                            <?php $clean = pistaSemNumero($pista); ?>
                                            <option value="<?php echo htmlspecialchars($clean, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($clean, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="py-2 px-1">
                                    <select id="winner-<?php echo $track['id']; ?>" class="w-full text-xs input-style p-1" data-track-index="<?php echo $index; ?>" onchange="handleInput(<?php echo $index; ?>)">
                                        <option value="0" selected>--</option>
                                        <option value="P1">P1 (Manual)</option>
                                        <option value="P2">P2 (Auto)</option>
                                    </select>
                                </td>
                                <td class="py-2 px-1">
                                    <input type="checkbox" id="diff10s-<?php echo $track['id']; ?>" class="h-5 w-5 rounded form-checkbox text-indigo-600 bg-slate-100 border-slate-400 focus:ring-indigo-500" data-track-index="<?php echo $index; ?>" onchange="handleInput(<?php echo $index; ?>)">
                                </td>
                                <td id="score-p1-<?php echo $track['id']; ?>" class="py-2 px-1 font-bold text-lg text-red-700">0</td>
                                <td id="score-p2-<?php echo $track['id']; ?>" class="py-2 px-1 font-bold text-lg text-cyan-700">0</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-100 font-bold text-md">
                            <td colspan="3" class="py-2 px-1 text-right">
                                TOTAL IDA
                                <span id="total-ida-name-p1" class="text-sm font-light text-amber-700"></span> /
                                <span id="total-ida-name-p2" class="text-sm font-light text-amber-700"></span>
                            </td>
                            <td id="total-ida-p1" class="py-2 px-1 text-green-700">0</td>
                            <td id="total-ida-p2" class="py-2 px-1 text-green-700">0</td>
                        </tr>
                        <tr class="bg-slate-200 font-bold text-md">
                            <td colspan="3" class="py-2 px-1 text-right">
                                TOTAL VOLTA
                                <span id="total-volta-name-p1" class="text-sm font-light text-red-700"></span> /
                                <span id="total-volta-name-p2" class="text-sm font-light text-red-700"></span>
                            </td>
                            <td id="total-volta-p1" class="py-2 px-1 text-green-700">0</td>
                            <td id="total-volta-p2" class="py-2 px-1 text-green-700">0</td>
                        </tr>
                        <tr class="bg-indigo-600 font-bold text-xl text-white">
                            <td colspan="3" class="py-3 px-1 text-right align-middle">TOTAL GERAL</td>
                            <td class="py-3 px-1">
                                <div id="total-geral-name-p1" class="text-xs text-amber-100 font-normal mb-1 uppercase tracking-wider">P1 Manual</div>
                                <div id="total-geral-p1" class="text-2xl">0</div>
                            </td>
                            <td class="py-3 px-1">
                                <div id="total-geral-name-p2" class="text-xs text-cyan-100 font-normal mb-1 uppercase tracking-wider">P2 Auto</div>
                                <div id="total-geral-p2" class="text-2xl">0</div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </section>

            <section class="mt-6">
                <button id="send-whatsapp-btn" onclick="sendToWhatsApp()" class="bg-green-600 hover:bg-green-700 text-white font-extrabold py-3 px-6 w-full rounded-lg text-xl transition duration-300 transform hover:scale-[1.01] flex items-center justify-center shadow-lg">
                    ENVIAR RESULTADO POR WHATSAPP
                </button>
                <p id="whatsapp-status" class="text-center text-sm mt-2 text-slate-500 italic hidden">Mensagem enviada!</p>
            </section>

            <section class="mt-6 p-4 bg-slate-50 rounded-lg border border-slate-200">
                <h3 class="text-lg font-bold text-slate-800 mb-2">Legenda:</h3>
                <div id="pilot-legend" class="text-sm text-slate-600 space-y-1">
                    <p><strong>IDA:</strong> P1: <span id="legend-ida-p1" class="text-amber-700">--</span> (Manual) | P2: <span id="legend-ida-p2" class="text-amber-700">--</span> (Automático)</p>
                    <p><strong>VOLTA:</strong> P1: <span id="legend-volta-p1" class="text-red-700">--</span> (Manual) | P2: <span id="legend-volta-p2" class="text-red-700">--</span> (Automático)</p>
                </div>
            </section>
        </div>
    </div>

    <script>
        const trackData = <?php echo json_encode(array_map(function ($track) {
            return [
                'id' => $track['id'],
                'name' => $track['name'],
                'side' => $track['side'],
                'track' => '',
                'winner' => '0',
                'diff10s' => false,
                'p1Points' => 0,
                'p2Points' => 0,
                'p1PilotName' => '',
                'p2PilotName' => ''
            ];
        }, $trackRows), JSON_UNESCAPED_UNICODE); ?>;

        function calculatePoints(winner, diff10s) {
            if (winner === 'P1') return { p1: 20, p2: 10 };
            if (winner === 'P2') return diff10s ? { p1: 15, p2: 20 } : { p1: 15, p2: 10 };
            return { p1: 0, p2: 0 };
        }

        function syncVoltaTrackFromIda(track) {
            if (track.side !== 'IDA') return;
            const voltaId = track.id + 4;
            const voltaIndex = trackData.findIndex(t => t.id === voltaId);
            if (voltaIndex === -1) return;

            const voltaSelect = document.getElementById(`track-select-${voltaId}`);
            if (!voltaSelect) return;

            voltaSelect.value = track.track || '';
            trackData[voltaIndex].track = voltaSelect.value;
        }

        function updateTotals() {
            let totalIdaP1 = 0, totalIdaP2 = 0, totalVoltaP1 = 0, totalVoltaP2 = 0;
            trackData.forEach(track => {
                if (track.side === 'IDA') {
                    totalIdaP1 += track.p1Points;
                    totalIdaP2 += track.p2Points;
                } else {
                    totalVoltaP1 += track.p1Points;
                    totalVoltaP2 += track.p2Points;
                }
            });

            document.getElementById('total-ida-p1').textContent = totalIdaP1;
            document.getElementById('total-ida-p2').textContent = totalIdaP2;
            document.getElementById('total-volta-p1').textContent = totalVoltaP1;
            document.getElementById('total-volta-p2').textContent = totalVoltaP2;
            document.getElementById('total-geral-p1').textContent = totalIdaP1 + totalVoltaP2;
            document.getElementById('total-geral-p2').textContent = totalIdaP2 + totalVoltaP1;
        }

        function handleInput(trackIndex) {
            const track = trackData[trackIndex];
            const winnerSelect = document.getElementById(`winner-${track.id}`);
            const diffCheckbox = document.getElementById(`diff10s-${track.id}`);
            const trackSelect = document.getElementById(`track-select-${track.id}`);

            track.winner = winnerSelect ? winnerSelect.value : '0';
            track.diff10s = track.winner === 'P2' && diffCheckbox ? diffCheckbox.checked : false;
            track.track = trackSelect ? trackSelect.value : '';

            if (track.side === 'IDA') {
                syncVoltaTrackFromIda(track);
            }

            const points = calculatePoints(track.winner, track.diff10s);
            track.p1Points = points.p1;
            track.p2Points = points.p2;

            document.getElementById(`score-p1-${track.id}`).textContent = track.p1Points;
            document.getElementById(`score-p2-${track.id}`).textContent = track.p2Points;
            updateTotals();
        }

        function setupPilotNameListeners() {
            const p1IdaInput = document.getElementById('p1-ida-name');
            const p2IdaInput = document.getElementById('p2-ida-name');

            const updateNames = () => {
                const nameA = p1IdaInput.value || 'P1 (Manual/Raiz)';
                const nameB = p2IdaInput.value || 'P2 (Automático/Nutela)';

                document.getElementById('legend-ida-p1').textContent = nameA;
                document.getElementById('legend-ida-p2').textContent = nameB;
                document.getElementById('legend-volta-p1').textContent = nameB;
                document.getElementById('legend-volta-p2').textContent = nameA;

                document.getElementById('total-geral-name-p1').textContent = nameA;
                document.getElementById('total-geral-name-p2').textContent = nameB;
                document.getElementById('total-ida-name-p1').textContent = `(${nameA})`;
                document.getElementById('total-ida-name-p2').textContent = `(${nameB})`;
                document.getElementById('total-volta-name-p1').textContent = `(${nameB})`;
                document.getElementById('total-volta-name-p2').textContent = `(${nameA})`;

                for (let i = 0; i < 4; i++) {
                    trackData[i].p1PilotName = nameA;
                    trackData[i].p2PilotName = nameB;
                }
                for (let i = 4; i < 8; i++) {
                    trackData[i].p1PilotName = nameB;
                    trackData[i].p2PilotName = nameA;
                }

                updateTotals();
            };

            p1IdaInput.addEventListener('input', updateNames);
            p2IdaInput.addEventListener('input', updateNames);
            updateNames();
        }

        function sendToWhatsApp() {
            const nameA = document.getElementById('p1-ida-name').value || 'P1 (Manual/Raiz)';
            const nameB = document.getElementById('p2-ida-name').value || 'P2 (Automático/Nutela)';
            const totalGeralP1 = document.getElementById('total-geral-p1').textContent;
            const totalGeralP2 = document.getElementById('total-geral-p2').textContent;

            const idaTracks = trackData.filter(t => t.side === 'IDA' && t.winner !== '0' && t.track);
            const voltaTracks = trackData.filter(t => t.side === 'VOLTA' && t.winner !== '0' && t.track);

            const buildTrackBlock = (track) => {
                const diffEl = document.getElementById(`diff10s-${track.id}`);
                const diffText = track.winner === 'P2' && diffEl && diffEl.checked
                    ? "Dif >10s: SIM"
                    : (track.winner === 'P2' ? "Dif >10s: NÃO" : "Dif >10s: N/A");
                const winnerText = track.winner === 'P1'
                    ? `${track.p1PilotName} (P1/Manual) venceu`
                    : `${track.p2PilotName} (P2/Auto) venceu`;

                return `\n🏎️ ${track.name} - ${track.track}\n` +
                    `   🏁 ${winnerText}\n` +
                    `   ⏱️ ${diffText}\n` +
                    `   🔴 P1 ${track.p1PilotName}: ${track.p1Points} pts | 🔵 P2 ${track.p2PilotName}: ${track.p2Points} pts\n`;
            };

            let report = `🎮 RESULTADO FINAL - CENARIO 3\n`;
            report += `⚡ Raiz vs Nutela | Manual vs Auto\n\n`;
            report += `📊 PLACAR GERAL\n`;
            report += `🔴 ${nameA}: ${totalGeralP1} pts\n`;
            report += `🔵 ${nameB}: ${totalGeralP2} pts\n`;

            report += `\n════════ IDA ════════\n`;
            report += `🎯 P1 (Manual): ${nameA}\n`;
            report += `🎯 P2 (Auto): ${nameB}\n`;
            if (idaTracks.length === 0) {
                report += `Sem dados preenchidos na IDA.\n`;
            } else {
                idaTracks.forEach(track => { report += buildTrackBlock(track); });
            }

            report += `\n════════ VOLTA ════════\n`;
            report += `🎯 P1 (Manual): ${nameB}\n`;
            report += `🎯 P2 (Auto): ${nameA}\n`;
            if (voltaTracks.length === 0) {
                report += `Sem dados preenchidos na VOLTA.\n`;
            } else {
                voltaTracks.forEach(track => { report += buildTrackBlock(track); });
            }

            window.open(`https://wa.me/?text=${encodeURIComponent(report)}`, '_blank');
            const statusDisplay = document.getElementById('whatsapp-status');
            statusDisplay.textContent = 'Enviando relatório...';
            statusDisplay.classList.remove('hidden');
            setTimeout(() => statusDisplay.classList.add('hidden'), 5000);
        }

        window.handleInput = handleInput;
        window.sendToWhatsApp = sendToWhatsApp;
        window.addEventListener('load', () => {
            setupPilotNameListeners();
            updateTotals();
        });
    </script>
</body>
</html>
