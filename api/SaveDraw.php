<?php
// api/SaveDraw.php
header('Content-Type: application/json');

// 1. Carrega Configuração
require_once '../storage/data/Config.php';
// 2. Carrega Functions (Logger)
require_once '../storage/utils/Functions.php';
// 3. Carrega FileManager
require_once '../storage/utils/FileManager.php';

// Log inicial da requisição
Logger::info("API [SaveDraw]: Requisição recebida de " . $_SERVER['REMOTE_ADDR']);

// Ativa exibição de erros na resposta da API para debug, se configurado
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Logger::warning("API: Tentativa de acesso via método inválido: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!$input || !isset($input['tournamentId'])) {
    Logger::error("API: Input inválido ou ID ausente. Raw: " . substr($rawInput, 0, 50) . "...");
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID do torneio não fornecido']);
    exit;
}

$tournamentId = $input['tournamentId'];
Logger::info("API: Processando dados para Torneio ID: $tournamentId");

$drawData = [
    'title' => $input['title'] ?? 'Torneio ' . $tournamentId,
    'phase' => $input['phase'] ?? 'Fase Desconhecida',
    'drawnItems' => $input['drawnItems'] ?? []
];

try {
    FileManager::saveGranularDraw($tournamentId, $drawData);
    Logger::success("API: Sucesso no salvamento do ID $tournamentId");
    echo json_encode(['status' => 'success', 'message' => "Sorteio $tournamentId salvo em " . JSON_PATH]);
} catch (Exception $e) {
    Logger::error("API: Erro fatal capturado: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'debug_path' => JSON_PATH]);
}
?>