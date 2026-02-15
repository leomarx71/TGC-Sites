<?php
// storage/data/Data.php - Armazena todas as configurações e listas do sistema

// ============================================================================
// LISTAS DE CARROS E PISTAS GLOBAIS
// ============================================================================

// Lista Global de Carros (Apenas os 4 Proibidos, multiplicados por 3 = 12 entradas)
$cars_global = [
    // Grupo 1
    ['id' => 'p1_1', 'name' => 'Cannibal', 'class' => 'X', 'banned' => true, 'color' => 'text-red-600', 'color_name' => 'Vermelho'],
    ['id' => 'p2_1', 'name' => 'Weasel', 'class' => 'X', 'banned' => true, 'color' => 'text-blue-600', 'color_name' => 'Azul'],
    ['id' => 'p3_1', 'name' => 'Razor', 'class' => 'X', 'banned' => true, 'color' => 'text-purple-600', 'color_name' => 'Roxo'],
    ['id' => 'p4_1', 'name' => 'Sidewinder', 'class' => 'X', 'banned' => true, 'color' => 'text-gray-400', 'color_name' => 'Branco'],
    
    // Grupo 2
    ['id' => 'p1_2', 'name' => 'Cannibal', 'class' => 'X', 'banned' => true, 'color' => 'text-red-600', 'color_name' => 'Vermelho'],
    ['id' => 'p2_2', 'name' => 'Weasel', 'class' => 'X', 'banned' => true, 'color' => 'text-blue-600', 'color_name' => 'Azul'],
    ['id' => 'p3_2', 'name' => 'Razor', 'class' => 'X', 'banned' => true, 'color' => 'text-purple-600', 'color_name' => 'Roxo'],
    ['id' => 'p4_2', 'name' => 'Sidewinder', 'class' => 'X', 'banned' => true, 'color' => 'text-gray-400', 'color_name' => 'Branco'],

    // Grupo 3
    ['id' => 'p1_3', 'name' => 'Cannibal', 'class' => 'X', 'banned' => true, 'color' => 'text-red-600', 'color_name' => 'Vermelho'],
    ['id' => 'p2_3', 'name' => 'Weasel', 'class' => 'X', 'banned' => true, 'color' => 'text-blue-600', 'color_name' => 'Azul'],
    ['id' => 'p3_3', 'name' => 'Razor', 'class' => 'X', 'banned' => true, 'color' => 'text-purple-600', 'color_name' => 'Roxo'],
    ['id' => 'p4_3', 'name' => 'Sidewinder', 'class' => 'X', 'banned' => true, 'color' => 'text-gray-400', 'color_name' => 'Branco'],
];

// ============================================================================
// LISTAS DE POTES PARA O TOP GEAR 1
// ============================================================================

// Pistas TG1 (32 Pistas) - Atualizado conforme solicitação (Ordem ITA ajustada)
$tracks_tg1 = [
    '01 - USA - Las Vegas', '02 - USA - Los Angeles', '03 - USA - New York', '04 - USA - San Francisco',
    '05 - SAM - Rio de Janeiro', '06 - SAM - Machu Picchu', '07 - SAM - Chichén Itza', '08 - SAM - Floresta A.',
    '09 - JAP - Tokyo', '10 - JAP - Hiroshima', '11 - JAP - Yokohama', '12 - JAP - Kyoto',
    '13 - GER - Munich', '14 - GER - Cologne', '15 - GER - Black Forest', '16 - GER - Frankfurt',
    '17 - SCN - Stockholm', '18 - SCN - Copenhagen', '19 - SCN - Helsinki', '20 - SCN - Oslo',
    '21 - FRA - Paris', '22 - FRA - Nice', '23 - FRA - Bordeaux', '24 - FRA - Monaco',
    '25 - ITA - Pisa', '26 - ITA - Rome', '27 - ITA - Sicily', '28 - ITA - Florence',
    '29 - UKG - London', '30 - UKG - Sheffield', '31 - UKG - Loch Ness', '32 - UKG - Stonehenge'
];


$countries_tg1 = [
    'USA','SAM','JAP','GER','SCN','FRA','ITA','UKG'
];

$tracks_110_pit = [
    "04 USA - San Francisco", "05 SAM - Rio", "07 SAM - Chichen Itza", 
    "09 JAP - Tokyo", "10 JAP - Hiroshima", "12 JAP - Kyoto", 
    "13 GER - Munich", "15 GER - Black Forest", "16 GER - Frankfurt", 
    "22 FRA - Nice", "23 FRA - Bordeaux", "26 ITA - Rome", "29 UKG - London"
];

$tracks_110_nopit = [
    "01 USA - Las Vegas", "02 USA - Los Angeles", "03 USA - New York", 
    "06 SAM - Machu Picchu", "08 SAM - Rain Forest", "11 JAP - Yokohama", 
    "14 GER - Cologne", "17 SCN - Stockholm", "18 SCN - Copenhagen", 
    "19 SCN - Helsinki", "20 SCN - Oslo", "21 FRA - Paris", 
    "24 FRA - Monaco", "25 ITA - Pisa", "27 ITA - Sicily", 
    "28 ITA - Florence", "30 UKG - Sheffield", "31 UKG - Loch Ness", 
    "32 UKG - Stonehenge"
];

// ============================================================================
// LISTAS DE POTES PARA O TOP GEAR 2
// ============================================================================

// Top Gear 2 - Países e Pares
$tg2_countries = [
    "AUS - Australasia", "UKG - Britain", "CAN - Canada", "EGY - Egypt", 
    "FRA - France", "GER - Germany", "GRE - Greece", "IND - India", 
    "IRL - Ireland", "ITA - Italy", "JAP - Japan", "SCN - Scandinavia", 
    "SAM - South America", "ESP - Spain", "SUI - Switzerland", "USA - United States"
];

$tg2_country_pairs = [ 
    ["UKG - Britain", "FRA - France"], ["UKG - Britain", "JAP - Japan"], ["UKG - Britain", "SAM - South America"], 
    ["UKG - Britain", "ITA - Italy"], ["UKG - Britain", "USA - United States"], ["UKG - Britain", "GER - Germany"], 
    ["UKG - Britain", "SCN - Scandinavia"], ["FRA - France", "JAP - Japan"], ["FRA - France", "SAM - South America"], 
    ["FRA - France", "ITA - Italy"], ["FRA - France", "USA - United States"], ["FRA - France", "GER - Germany"], 
    ["FRA - France", "SCN - Scandinavia"], ["JAP - Japan", "SAM - South America"], ["JAP - Japan", "ITA - Italy"], 
    ["JAP - Japan", "USA - United States"], ["JAP - Japan", "GER - Germany"], ["JAP - Japan", "SCN - Scandinavia"], 
    ["SAM - South America", "ITA - Italy"], ["SAM - South America", "USA - United States"], ["SAM - South America", "GER - Germany"], 
    ["SAM - South America", "SCN - Scandinavia"], ["ITA - Italy", "USA - United States"], ["ITA - Italy", "GER - Germany"], 
    ["ITA - Italy", "SCN - Scandinavia"], ["USA - United States", "GER - Germany"], ["USA - United States", "SCN - Scandinavia"], 
    ["GER - Germany", "SCN - Scandinavia"] 
];

// ============================================================================
// LISTAS DE POTES PARA O TOP GEAR 3000
// ============================================================================

// Sistemas TG3000
$tg3k_all_systems = ["Merak", "Zosmar", "Sarin", "Alderam", "Kajam", "Lesath", "Miram", "Subrat", "Toygeta", "Vega_5", "Naosphein", "Kraz"];
// Sistemas TG3000 COM 4 pistas
$tg3k_4planet_systems = ["Zosmar", "Sarin", "Alderam", "Lesath", "Miram", "Subrat", "Vega_5"];

// ============================================================================
// LISTAS DE POTES PARA O TOP GEAR CENÁRIOS
// ============================================================================

// Potes Cenário 1
$pot_cenarios_full = [
    '01 - USA - Las Vegas' => 6,
    '02 - USA - Los Angeles' => 3,
    '03 - USA - New York' => 3,
    '04 - USA - San Francisco' => 7,
    '05 - SAM - Rio de Janeiro' => 7,
    '06 - SAM - Machu Pichu' => 1,
    '07 - SAM - Chichén Itzá' => 8,
    '08 - SAM - Floresta A' => 3,
    '09 - JAP - Tóquio' => 9,
    '10 - JAP - Hiroshima' => 6,
    '11 - JAP - Yokohama' => 3,
    '12 - JAP - Quioto' => 7,
    '13 - GER - Munique' => 9,
    '14 - GER - Cologne' => 1,
    '15 - GER - Black Forest' => 10,
    '16 - GER - Frankfurt' => 10,
    '17 - SCN - StockHolm' => 1,
    '18 - SCN - Copen' => 3,
    '19 - SCN - Helsinque' => 1,
    '20 - SCN - Oslo' => 3,
    '21 - FRA - Paris' => 1,
    '22 - FRA - Nice' => 10,
    '23 - FRA - Bordeaux' => 4,
    '24 - FRA - Monaco' => 3,
    '25 - ITA - Pisa' => 1,
    '26 - ITA - Roma' => 10,
    '27 - ITA - Sicília' => 3,
    '28 - ITA - Florença' => 3,
    '29 - UKG - Londres' => 10,
    '30 - UKG - Sheffield' => 3,
    '31 - UKG - Loch Ness' => 1,
    '32 - UKG - Stonehenge' => 3
];

$pot_cen1_a = [
    '06 - SAM - Machu Pichu' => 1,
    '14 - GER - Cologne' => 1,
    '17 - SCN - StockHolm' => 1,
    '19 - SCN - Helsinque' => 1,
    '21 - FRA - Paris' => 1,
    '25 - ITA - Pisa' => 1,
    '31 - UKG - Loch Ness' => 1,
    '32 - UKG - Stonehenge' => 3
];

$pot_cen1_b = [
    '23 - FRA - Bordeaux' => 4,
    '01 - USA - Las Vegas' => 6,
    '10 - JAP - Hiroshima' => 6,
    '04 - USA - San Francisco' => 7,
    '05 - SAM - Rio de Janeiro' => 7,
    '12 - JAP - Quioto' => 7
];

$pot_cen1_c = [
    '07 - SAM - Chichén Itzá' => 8,
    '09 - JAP - Tóquio' => 9,
    '13 - GER - Munique' => 9,
    '15 - GER - Black Forest' => 10,
    '16 - GER - Frankfurt' => 10,
    '22 - FRA - Nice' => 10,
    '26 - ITA - Roma' => 10,
    '29 - UKG - Londres' => 10
];

$pot_cen402 = [
    '01 - USA - Las Vegas' => 6,
    '04 - USA - San Francisco' => 7,
    '05 - SAM - Rio de Janeiro' => 7,
    '07 - SAM - Chichén Itzá' => 8,
    '09 - JAP - Tóquio' => 9,
    '10 - JAP - Hiroshima' => 6,
    '12 - JAP - Quioto' => 7,
    '13 - GER - Munique' => 9,
    '15 - GER - Black Forest' => 10,
    '16 - GER - Frankfurt' => 10,
    '22 - FRA - Nice' => 10,
    '23 - FRA - Bordeaux' => 4,
    '26 - ITA - Roma' => 10,
    '29 - UKG - Londres' => 10
];

$pot_cen405_a = [
    '06 - SAM - Machu Pichu' => 1,
    '14 - GER - Cologne' => 1,
    '17 - SCN - StockHolm' => 1,
    '19 - SCN - Helsinque' => 1,
    '21 - FRA - Paris' => 1,
    '25 - ITA - Pisa' => 1,
    '31 - UKG - Loch Ness' => 1
];

$pot_cen405_b = [
    '02 - USA - Los Angeles' => 3,
    '03 - USA - New York' => 3,
    '08 - SAM - Floresta A' => 3,
    '11 - JAP - Yokohama' => 3,
    '18 - SCN - Copen' => 3,
    '20 - SCN - Oslo' => 3,
    '24 - FRA - Monaco' => 3,
    '27 - ITA - Sicília' => 3,
    '28 - ITA - Florença' => 3,
    '30 - UKG - Sheffield' => 3,
    '32 - UKG - Stonehenge' => 3
];

$pot_cen405_c = [
    '23 - FRA - Bordeaux' => 4,
    '01 - USA - Las Vegas' => 6,
    '10 - JAP - Hiroshima' => 6,
    '04 - USA - San Francisco' => 7,
    '05 - SAM - Rio de Janeiro' => 7,
    '12 - JAP - Quioto' => 7
];

$pot_cen405_d = [
    '07 - SAM - Chichén Itzá' => 8,
    '09 - JAP - Tóquio' => 9,
    '13 - GER - Munique' => 9,
    '15 - GER - Black Forest' => 10,
    '16 - GER - Frankfurt' => 10,
    '22 - FRA - Nice' => 10,
    '26 - ITA - Roma' => 10,
    '29 - UKG - Londres' => 10
];


// ============================================================================
// DESCRIÇÕES DOS TORNEIOS
// ============================================================================

$tg1_descriptions_map = [
    "T1" => "O calor intenso do verão exige resistência máxima. Sorteio de Países.",
    "T2" => "Corridas de longa duração em formato de liga inspiradas no clássico americano. Rodadas contínuas com diversidade de pistas.",
    "T3" => "A elite do automobilismo virtual em disputa acirrada.",
    "T4" => "Competidores famintos buscando a ascensão para a elite.",
    "T5" => "Onde nascem as lendas: a porta de entrada do La Liga.",
    "T6" => "Quem larga na frente tem vantagem, mas a constância de não bater vence. Rodadas de tempo contínuas de 8 pistas.",
    "T7" => "Folhas caindo e pistas escorregadias no desafio de outono. Sorteio de Países.",
    "T8" => "A base para os futuros campeões. Copa dos novatos.",
    "T9" => "A copa mais cobiçada do circuito TGC. Duelos de Ida e Volta com Carro Livre ou Proibido.",
    "T10" => "Um desafio matemático e estratégia onde cada posição conta. Regras especiais de Pit Stop e Carro Proibido.",
    "T11" => "Protótipos velozes testando os limites da engenharia a 767 Km/h.",
    "T12" => "Gelo, neve e controle absoluto no rali ártico. Derrapou morreu! Sorteio de Países.",
    "T13" => "A batalha pelo ouro recomeça com novos rivais. Formato Liga.",
    "T14" => "A prata brilha, mas o objetivo é o topo. Formato Liga.",
    "T15" => "Bronze com sabor de vitória para os iniciantes. Formato Liga.",
    "T16" => "As flores desabrocham enquanto os motores rugem na primavera da Itália. Sorteio de Países.",
    "T17" => "Apenas os campeões têm lugar neste grid exclusivo. Sorteio de Países.",
    "T18" => "O desafio do oriente testando reflexos dos menos afortunados na tabela do Mundial. Rodadas contínuas com diversidade de pistas."
];

$tg2_descriptions_map = [
    201 => "Corrida de resistência e velocidade inspirada na Nascar.",
    202 => "A liga definitiva de turismo para os pilotos mais experientes.",
    203 => "Sem regras, apenas velocidade. Onde o vale-tudo impera.",
    204 => "Desafios temáticos e pistas insanas estilo Hot Wheels.",
    205 => "A taça dos campeões. Apenas a elite sobrevive aqui."
];

$tg3k_description_text = "Corridas interplanetárias no ano 3000. O futuro da velocidade.";

// ============================================================================
// CONFIGURAÇÃO DOS TORNEIOS (COM DESCRIÇÕES INJETADAS)
// ============================================================================

// --- TORNEIOS TG1 (101 a 118) ---
$tg1_tournaments = [
    ['id' => 101, 'name' => 'T1 - Torneio de Verão: Dakar Series', 'class' => 'C', 'slots' => 8],
    ['id' => 102, 'name' => 'T2 - American LeMans Series', 'class' => 'B', 'slots' => 12],
    ['id' => 103, 'name' => 'T3 - La Liga - Série Ouro', 'class' => 'S', 'slots' => 16],
    ['id' => 104, 'name' => 'T4 - La Liga - Série Prata', 'class' => 'A', 'slots' => 16],
    ['id' => 105, 'name' => 'T5 - La Liga - Série Bronze', 'class' => 'B', 'slots' => 16],
    ['id' => 106, 'name' => 'T6 - TGC Pole Position', 'class' => 'A', 'slots' => 8],
    ['id' => 107, 'name' => 'T7 - Torneio de Outono: Acropolis Cup', 'class' => 'C', 'slots' => 8],
    ['id' => 108, 'name' => 'T8 - F1 Academy', 'class' => 'S', 'slots' => 20],
    ['id' => 109, 'name' => 'T9 - Copa TGC', 'class' => 'A', 'slots' => 32],
    ['id' => 110, 'name' => 'T10 - TGC Numerado', 'class' => 'B', 'slots' => 10],
    ['id' => 111, 'name' => 'T11 - TGC Prototype Challenge', 'class' => 'S', 'slots' => 12],
    ['id' => 112, 'name' => 'T12 - Torneio de Inverno: Arctic Rally', 'class' => 'C', 'slots' => 8],
    ['id' => 113, 'name' => 'T13 - La Liga - Série Ouro', 'class' => 'S', 'slots' => 16],
    ['id' => 114, 'name' => 'T14 - La Liga - Série Prata', 'class' => 'A', 'slots' => 16],
    ['id' => 115, 'name' => 'T15 - La Liga - Série Bronze', 'class' => 'B', 'slots' => 16],
    ['id' => 116, 'name' => 'T16 - Torneio de Primavera: Targa Florio', 'class' => 'B', 'slots' => 12],
    ['id' => 117, 'name' => 'T17 - Champions Cup', 'class' => 'S', 'slots' => 8],
    ['id' => 118, 'name' => 'T18 - Asia LeMans Series', 'class' => 'A', 'slots' => 14],
];

// Injetar descrições no TG1
foreach ($tg1_tournaments as &$t) {
    preg_match('/^(T\d+)/', $t['name'], $matches);
    if (isset($matches[1]) && isset($tg1_descriptions_map[$matches[1]])) {
        $t['description'] = $tg1_descriptions_map[$matches[1]];
    } else {
        $t['description'] = 'Descrição não disponível.';
    }
}
unset($t);

// --- TORNEIOS TG2 (201 a 205) ---
$tg2_tournaments = [
    ['id' => 201, 'name' => 'Hot Wheels Top Gear 2 Cenários', 'type' => 'Special'],
    ['id' => 202, 'name' => 'TG2 Stock Car Vale Tudo', 'type' => 'Stock'],
    ['id' => 203, 'name' => 'Nascar Mestres do Top Gear 2', 'type' => 'Nascar'],
    ['id' => 204, 'name' => 'Gran Turismo League TG2', 'type' => 'GT League'],
    ['id' => 205, 'name' => 'Champions Cup', 'type' => 'Elite'],
];

// Injetar descrições no TG2
foreach ($tg2_tournaments as &$t) {
    if (isset($tg2_descriptions_map[$t['id']])) {
        $t['description'] = $tg2_descriptions_map[$t['id']];
    } else {
        $t['description'] = 'Descrição não disponível.';
    }
}
unset($t);

// --- TORNEIOS TG3000 (301) ---
$tg3k_tournaments = [
    ['id' => 301, 'name' => 'Top Gear 3026 - O Futuro é Agora!!!', 'style' => 'Futuristic', 'description' => $tg3k_description_text],
];

// --- CENÁRIOS (401 a 406) ---
$cenarios_tournaments = [
    ['id' => 401, 'name' => 'Cenário 1: Novas Estratégias', 'difficulty' => 'Estratégico', 'description' => 'Desafio estratégico de pit stops.'],
    ['id' => 402, 'name' => 'Cenário 2: Velozes e Furiosos 2092', 'difficulty' => 'Velocidade', 'description' => 'Velocidade máxima sem limites.'],
    ['id' => 403, 'name' => 'Cenário 3: Raiz vs Nutela', 'difficulty' => 'Clássico', 'description' => 'A batalha de gerações. Manual vs Automático'],
    ['id' => 404, 'name' => 'Cenário 4: Estoy Facilito e Sajirito', 'difficulty' => 'Fun', 'description' => 'Cenário divertido e imprevisível. Onde cada nitro de sobra é uma bônus'],
    ['id' => 405, 'name' => 'Cenário 5: Efeito Borboleta', 'difficulty' => 'Caos', 'description' => 'A vida só pode ser compreendida olhando para trás, mas só pode ser vivida olhando para frente.'],
    ['id' => 406, 'name' => 'Cenário 6: Nitrar na Largada é Pecado', 'difficulty' => 'Regra', 'description' => 'Teste de paciência e controle dos impulsivos.'],
];
?>
