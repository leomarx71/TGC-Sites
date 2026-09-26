<?php
/**
 * TOP GEAR CHAMPIONSHIPS - TGC 2026
 * Portal de Competição de Tempos
 * Arquivo único com backend + frontend integrados
 */

date_default_timezone_set('America/Sao_Paulo');
session_start();

function projectEnv() {
    static $config = null;
    if ($config !== null) return $config;

    $config = [];
    $envFile = __DIR__ . '/.env';
    if (is_file($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) throw new Exception('Não foi possível ler a configuração do servidor.');
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            $separator = strpos($line, '=');
            if ($separator === false) continue;
            $key = trim(substr($line, 0, $separator));
            $value = trim(substr($line, $separator + 1));
            if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
                $value = substr($value, 1, -1);
            }
            $config[$key] = $value;
        }
    }

    return $config;
}

function projectEnvValue($key) {
    $environmentValue = getenv($key);
    if ($environmentValue !== false && $environmentValue !== '') return $environmentValue;
    $config = projectEnv();
    if (array_key_exists($key, $config)) return $config[$key] !== '' ? $config[$key] : null;
    return null;
}

function verifyAdminPassword($password) {
    $hash = projectEnvValue('TGC_ADMIN_PASSWORD_HASH');
    if (!is_string($hash) || $hash === '' || !is_string($password)) return false;
    $info = password_get_info($hash);
    $defaultInfo = password_get_info(password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT));
    return !empty($info['algo']) && $info['algoName'] === $defaultInfo['algoName'] && password_verify($password, $hash);
}

function adminSessionActive() {
    if (empty($_SESSION['tgc_admin_authenticated']) || (int) ($_SESSION['tgc_admin_last_activity'] ?? 0) < time() - 1800) {
        unset($_SESSION['tgc_admin_authenticated'], $_SESSION['tgc_admin_last_activity']);
        return false;
    }
    $_SESSION['tgc_admin_last_activity'] = time();
    return true;
}

function isAdminAuthorized() {
    return adminSessionActive()
        && isset($_SESSION['tgc_admin_csrf'], $_POST['admin_csrf'])
        && hash_equals($_SESSION['tgc_admin_csrf'], (string) $_POST['admin_csrf']);
}

function adminCsrfToken() {
    if (empty($_SESSION['tgc_admin_csrf'])) $_SESSION['tgc_admin_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['tgc_admin_csrf'];
}

function maskEmailForDisplay($email) {
    $parts = explode('@', (string) $email, 2);
    if (count($parts) !== 2) return '';
    $local = $parts[0];
    $maskLength = strlen($local) <= 10 ? 4 : (strlen($local) <= 15 ? 5 : 7);
    $maskLength = min(strlen($local), $maskLength);
    $start = (int) floor((strlen($local) - $maskLength) / 2);
    return substr($local, 0, $start) . str_repeat('*', $maskLength) . substr($local, $start + $maskLength) . '@' . $parts[1];
}

function getResetEligiblePilots($pilots) {
    $eligible = array_values(array_filter($pilots, function ($pilot) {
        return ($pilot['activePilot'] ?? false) === true && trim((string) ($pilot['email'] ?? '')) !== '';
    }));
    usort($eligible, function ($a, $b) { return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')); });
    return $eligible;
}

// ============================================================================
// VALIDAÇÃO DE PIN VIA API (FRONTEND)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['api_action']) && $_POST['api_action'] === 'validate_pin') {
    header('Content-Type: application/json');

    try {
        $pilotManager = new PilotManager();
        $valid = $pilotManager->validatePIN($_POST['phoneNumberID'] ?? '', $_POST['pin'] ?? '');
        echo json_encode(['success' => $valid]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

    exit;
}

// ============================================================================
// CLASSES DO BACKEND
// ============================================================================

class TimeConverter {
    public static function toMilliseconds($time) {
        list($minutes, $seconds, $centiseconds) = explode(':', $time);
        return (intval($minutes) * 60000) + (intval($seconds) * 1000) + (intval($centiseconds) * 10);
    }

    public static function toReadable($ms) {
        if ($ms === 0) return "0:00:000";
        $minutes = floor($ms / 60000);
        $seconds = floor(($ms % 60000) / 1000);
        $centiseconds = floor(($ms % 1000) / 10);
        return sprintf("%02d:%02d:%03d", $minutes, $seconds, $centiseconds * 10);
    }
}

class PointsCalculator {
    private static $pointsTable = [
            1 => 20, 2 => 15, 3 => 12, 4 => 10, 5 => 8,
            6 => 6, 7 => 5, 8 => 4, 9 => 3, 10 => 2
    ];

    public static function getPoints($position) {
        if ($position === 0) return 0;
        if (isset(self::$pointsTable[$position])) {
            return self::$pointsTable[$position];
        }
        return 1; // 11º em diante recebe 1 ponto
    }

    public static function getColorForPoints($points) {
        if ($points <= 0) return 'rgba(239, 68, 68, 0.2)';
        if ($points < 8) return 'rgba(239, 68, 68, 0.5)';
        if ($points < 15) return 'rgba(234, 179, 8, 0.5)';
        return 'rgba(34, 197, 94, 0.5)';
    }
}

class PilotManager {
    private $dataFile;
    private $mailerInstance;

    public function __construct($dataFile = null, $mailer = null) {
        $this->dataFile = $dataFile ?: __DIR__ . '/data/pilots.json';
        $this->mailerInstance = $mailer;
    }

    public function generatePIN() {
        $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        return [
                'pin' => $pin,
                'pin_b64' => base64_encode($pin)
        ];
    }

    public function validatePIN($phoneNumberID, $pin) {
        $phoneNumberID = filter_var($phoneNumberID, FILTER_VALIDATE_INT);
        $pin = trim((string) $pin);

        if ($phoneNumberID === false || !preg_match('/^[0-9]{6}$/', $pin)) return false;

        $pilots = $this->loadPilots();

        foreach ($pilots as $pilot) {
            if (!isset($pilot['phoneNumberID']) || (int) $pilot['phoneNumberID'] !== $phoneNumberID) continue;
            if (($pilot['activePilot'] ?? false) !== true) return false;

            $storedPIN = base64_decode((string) ($pilot['pinB64'] ?? ''), true);

            if ($storedPIN === false || !preg_match('/^[0-9]{6}$/', $storedPIN)) return false;

            return hash_equals($storedPIN, $pin);
        }

        return false;
    }

    public function getPilotByPhone($phoneNumberID) {
        $phoneNumberID = filter_var($phoneNumberID, FILTER_VALIDATE_INT);

        if ($phoneNumberID === false) throw new Exception('Phone ID inválido.');

        $pilots = $this->loadPilots();

        foreach ($pilots as $pilot) {
            if (isset($pilot['phoneNumberID']) && (int) $pilot['phoneNumberID'] === $phoneNumberID) {
                if (($pilot['activePilot'] ?? false) !== true) throw new Exception('Este piloto está inativo.');
                return $pilot;
            }
        }

        throw new Exception('Piloto não encontrado pelo Phone ID.');
    }

    public function registerPilot($data) {
        $name = trim((string) ($data['name'] ?? ''));
        $nickname = trim((string) ($data['nicknameTGC'] ?? ''));
        $phoneNumberID = trim((string) ($data['phoneNumberID'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));

        if ($name === '' || strlen($name) > 120 || $nickname === '' || strlen($nickname) > 60 || strlen($email) > 254 || !preg_match('/^[0-9]{8,15}$/', $phoneNumberID) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new Exception('Preencha nome, nickname, telefone com DDI e e-mail válidos.');
        }

        $storedPhone = (string) (int) $phoneNumberID === $phoneNumberID ? (int) $phoneNumberID : $phoneNumberID;
        $pilot = $this->withLockedPilots(function (&$pilots) use ($name, $nickname, $phoneNumberID, $storedPhone, $email) {
            foreach ($pilots as $existing) {
                if ($this->normalizePhone($existing['phoneNumberID'] ?? '') === $this->normalizePhone($phoneNumberID)) {
                    throw new Exception('Este telefone já está cadastrado.');
                }
                if (strcasecmp(trim((string) ($existing['nicknameTGC'] ?? '')), $nickname) === 0) {
                    throw new Exception('Este nickname já está cadastrado.');
                }
                if (strcasecmp(trim((string) ($existing['email'] ?? '')), $email) === 0) {
                    throw new Exception('Este e-mail já está cadastrado.');
                }
            }

            $last = count($pilots) ? $pilots[count($pilots) - 1] : null;
            if ($last === null) {
                $nextId = 1;
            } elseif (!isset($last['id']) || !is_numeric($last['id'])) {
                throw new Exception('Conflito: o último piloto não possui um ID numérico para calcular o próximo.');
            } else {
                $nextId = (int) $last['id'] + 1;
            }
            foreach ($pilots as $existing) {
                if (isset($existing['id']) && (int) $existing['id'] === $nextId) {
                    throw new Exception('Conflito: o próximo ID calculado já existe. Avise um administrador.');
                }
            }

            $newPilot = [
                    'id' => $nextId,
                    'phoneNumberID' => $storedPhone,
                    'nicknameTGC' => $nickname,
                    'name' => $name,
                    'email' => $email,
                    'pinB64' => '',
                    'activePilot' => false,
                    'createdAt' => gmdate('Y-m-d\TH:i:s\Z')
            ];
            $pilots[] = $newPilot;
            return $newPilot;
        }, true);

        $html = '<h2>Solicitação de cadastro de piloto</h2>'
            . '<p><strong>Nome:</strong> ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Nickname:</strong> ' . htmlspecialchars($nickname, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Telefone:</strong> ' . htmlspecialchars($phoneNumberID, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>E-mail:</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>ID criado:</strong> ' . (int) $pilot['id'] . '</p>'
            . '<p><a href="https://topgearchampionships.com/sites/records/TGC-PolePosition.php">Abrir TGC Pole Position</a></p>'
            . '<p>Entre na tab Admin &gt; Gestão de pilotos, analise o pedido e aprove ou recuse o cadastro.</p>';
        $text = "Solicitação de cadastro de piloto\nNome: {$name}\nNickname: {$nickname}\nTelefone: {$phoneNumberID}\nE-mail: {$email}\nID criado: {$pilot['id']}\n"
            . "Acesse https://topgearchampionships.com/sites/records/TGC-PolePosition.php e entre na tab Admin > Gestão de pilotos para analisar e aprovar ou não o pedido.";
        try {
            $sent = $this->mailer()->sendAdminNotification('TGC - Nova solicitação de cadastro de piloto', $html, $text);
        } catch (Exception $error) {
            $sent = ['success' => false];
        }
        if (empty($sent['success'])) {
            return ['success' => false, 'message' => 'Seu pedido foi registrado, mas a notificação automática aos administradores falhou. Avise-os por outro canal ou envie e-mail para admins@topgearchampionships.com.'];
        }
        return ['success' => true, 'message' => 'Pedido de cadastro registrado e encaminhado para análise dos administradores.'];
    }

    public function requestNewPIN($pilotId) {
        $pilotId = filter_var($pilotId, FILTER_VALIDATE_INT);
        if ($pilotId === false || $pilotId < 1) throw new Exception('Identificador de piloto inválido.');

        $pilot = $this->withLockedPilots(function (&$pilots) use ($pilotId) {
            foreach ($pilots as &$entry) {
                if ((int) ($entry['id'] ?? 0) !== $pilotId) continue;
                if (empty($entry['activePilot'])) throw new Exception('Pilotos inativos não podem solicitar reset de PIN.');
                $email = trim((string) ($entry['email'] ?? ''));
                if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) throw new Exception('Não há um e-mail cadastrado válido para este piloto.');
                $pinData = $this->generatePIN();
                $previous = [
                    'pinB64' => $entry['pinB64'] ?? null,
                    'pinUpdatedAt' => $entry['pinUpdatedAt'] ?? null
                ];
                $entry['pinB64'] = $pinData['pin_b64'];
                $entry['pinUpdatedAt'] = gmdate('Y-m-d\TH:i:s\Z');
                return ['pilot' => $entry, 'previous' => $previous, 'pin' => $pinData];
            }
            throw new Exception('Piloto não encontrado.');
        }, true);

        $pilotData = $pilot['pilot'];
        $pinData = $pilot['pin'];
        $email = trim((string) $pilotData['email']);
        try {
            $sent = $this->mailer()->sendRecoveryEmail($email, (string) $pilotData['name'], $pinData['pin']);
        } catch (Exception $error) {
            $sent = ['success' => false];
        }
        if (empty($sent['success'])) {
            try {
                $restored = $this->restorePin($pilotId, $pinData['pin_b64'], $pilot['previous']);
            } catch (Exception $error) {
                throw new Exception('Falha ao enviar o PIN e ao restaurar o anterior. O novo PIN permanece salvo; solicite um novo reset após corrigir o envio.');
            }
            $state = $restored ? 'O PIN anterior foi mantido.' : 'Não foi possível restaurar o PIN anterior; o novo PIN permanece salvo.';
            throw new Exception('Falha ao enviar o PIN. ' . $state);
        }

        return ['success' => true, 'message' => 'Novo PIN enviado para: ' . maskEmailForDisplay($email)];
    }

    public function editPendingPilot($pilotId, $data, $activate = false) {
        $pilotId = filter_var($pilotId, FILTER_VALIDATE_INT);
        if ($pilotId === false || $pilotId < 1) throw new Exception('Identificador de piloto inválido.');

        $name = trim((string) ($data['name'] ?? ''));
        $nickname = trim((string) ($data['nicknameTGC'] ?? ''));
        $phoneNumberID = trim((string) ($data['phoneNumberID'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        if ($name === '' || strlen($name) > 120 || $nickname === '' || strlen($nickname) > 60
            || strlen($email) > 254 || !preg_match('/^[0-9]{8,15}$/', $phoneNumberID)
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new Exception('Preencha nome, nickname, telefone com DDI e e-mail válidos.');
        }

        $storedPhone = (string) (int) $phoneNumberID === $phoneNumberID ? (int) $phoneNumberID : $phoneNumberID;
        $updated = $this->withLockedPilots(function (&$pilots) use ($pilotId, $name, $nickname, $phoneNumberID, $storedPhone, $email, $activate) {
            foreach ($pilots as $existing) {
                if ((int) ($existing['id'] ?? 0) === $pilotId) continue;
                if ($this->normalizePhone($existing['phoneNumberID'] ?? '') === $this->normalizePhone($phoneNumberID)) {
                    throw new Exception('Este telefone já pertence a outro piloto.');
                }
                if (strcasecmp(trim((string) ($existing['nicknameTGC'] ?? '')), $nickname) === 0) {
                    throw new Exception('Este nickname já pertence a outro piloto.');
                }
                if (strcasecmp(trim((string) ($existing['email'] ?? '')), $email) === 0) {
                    throw new Exception('Este e-mail já pertence a outro piloto.');
                }
            }

            foreach ($pilots as &$entry) {
                if ((int) ($entry['id'] ?? 0) !== $pilotId) continue;
                if (($entry['activePilot'] ?? false) === true) throw new Exception('Este piloto já está ativo. Atualize a página.');

                $entry['name'] = $name;
                $entry['nicknameTGC'] = $nickname;
                $entry['phoneNumberID'] = $storedPhone;
                $entry['email'] = $email;
                if (!$activate) return ['pilot' => $entry, 'pin' => null, 'previous' => null];

                $pinData = $this->generatePIN();
                $previous = ['pinB64' => $entry['pinB64'] ?? null, 'pinUpdatedAt' => $entry['pinUpdatedAt'] ?? null];
                $entry['pinB64'] = $pinData['pin_b64'];
                $entry['pinUpdatedAt'] = gmdate('Y-m-d\TH:i:s\Z');
                $entry['activePilot'] = true;
                return ['pilot' => $entry, 'pin' => $pinData, 'previous' => $previous];
            }
            throw new Exception('Piloto pendente não encontrado.');
        }, true);

        if (!$activate) return ['success' => true, 'message' => 'Dados do piloto atualizados; o cadastro continua pendente.'];

        try {
            $sent = $this->mailer()->sendRecoveryEmail($email, $name, $updated['pin']['pin']);
        } catch (Exception $error) {
            $sent = ['success' => false];
        }
        if (empty($sent['success'])) {
            try {
                $restored = $this->restoreActivation($pilotId, $updated['pin']['pin_b64'], $updated['previous']);
            } catch (Exception $error) {
                throw new Exception('Falha ao enviar o PIN e ao reverter a ativação. Os dados editados foram salvos e o piloto pode ter ficado ativo; confira a lista antes de tentar novamente.');
            }
            if ($restored) throw new Exception('Os dados editados foram salvos, mas o envio do PIN falhou. O piloto continua pendente; corrija o e-mail ou SMTP antes de tentar ativar novamente.');
            throw new Exception('Os dados foram editados, mas o envio do PIN falhou e não foi possível reverter a ativação. Confira o estado do piloto antes de tentar novamente.');
        }

        return ['success' => true, 'message' => 'Dados atualizados; piloto ativado e PIN enviado para ' . maskEmailForDisplay($email) . '.'];
    }

    public function setPilotActive($pilotId, $active) {
        $pilotId = filter_var($pilotId, FILTER_VALIDATE_INT);
        if ($pilotId === false || $pilotId < 1) throw new Exception('Identificador de piloto inválido.');

        $pilot = $this->withLockedPilots(function (&$pilots) use ($pilotId, $active) {
            foreach ($pilots as &$entry) {
                if ((int) ($entry['id'] ?? 0) !== $pilotId) continue;
                if ((($entry['activePilot'] ?? false) === true) === $active) throw new Exception('O estado do piloto já foi alterado. Atualize a página.');
                if (!$active) {
                    $entry['activePilot'] = false;
                    return ['pilot' => $entry, 'pin' => null, 'previous' => null];
                }

                $email = trim((string) ($entry['email'] ?? ''));
                if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) throw new Exception('Não é possível ativar: o piloto não possui e-mail válido.');
                $existingPin = base64_decode((string) ($entry['pinB64'] ?? ''), true);
                $needsFirstPin = $existingPin === false || !preg_match('/^[0-9]{6}$/', $existingPin);
                $previous = ['pinB64' => $entry['pinB64'] ?? null, 'pinUpdatedAt' => $entry['pinUpdatedAt'] ?? null];
                $pinData = $needsFirstPin ? $this->generatePIN() : null;
                if ($needsFirstPin) {
                    $entry['pinB64'] = $pinData['pin_b64'];
                    $entry['pinUpdatedAt'] = gmdate('Y-m-d\TH:i:s\Z');
                }
                $entry['activePilot'] = true;
                return ['pilot' => $entry, 'pin' => $needsFirstPin ? $pinData : null, 'previous' => $previous];
            }
            throw new Exception('Piloto não encontrado.');
        }, true);

        if (!$active || $pilot['pin'] === null) return ['success' => true, 'message' => $active ? 'Piloto reativado.' : 'Piloto desativado.'];

        try {
            $sent = $this->mailer()->sendRecoveryEmail(trim((string) $pilot['pilot']['email']), (string) $pilot['pilot']['name'], $pilot['pin']['pin']);
        } catch (Exception $error) {
            $sent = ['success' => false];
        }
        if (empty($sent['success'])) {
            try {
                $restored = $this->restoreActivation($pilotId, $pilot['pin']['pin_b64'], $pilot['previous']);
            } catch (Exception $error) {
                throw new Exception('Falha ao enviar o PIN e ao reverter a aprovação. O piloto permaneceu ativo com o PIN salvo; não recarregue para reenviar.');
            }
            if ($restored) throw new Exception('Falha ao enviar o PIN. O piloto permaneceu pendente e sem PIN ativo; a aprovação pode ser tentada novamente.');
            throw new Exception('Falha ao enviar o PIN e ao reverter o estado: o piloto ficou ativo com o PIN salvo. Não recarregue para reenviar; confira o estado no painel.');
        }

        return ['success' => true, 'message' => 'Piloto ativado e PIN enviado para ' . maskEmailForDisplay($pilot['pilot']['email']) . '.'];
    }

    public function getAllPilots() {
        return $this->loadPilots();
    }

    private function loadPilots() {
        return $this->withLockedPilots(function (&$pilots) { return $pilots; }, false);
    }

    private function withLockedPilots($callback, $write) {
        $directory = dirname($this->dataFile);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new Exception('Falha ao criar diretório de dados dos pilotos.');
        }
        $lock = fopen($this->dataFile . '.lock', 'c');
        if ($lock === false) throw new Exception('Falha ao abrir o bloqueio dos dados dos pilotos.');
        $temporaryFile = null;
        try {
            if (!flock($lock, LOCK_EX)) throw new Exception('Falha ao bloquear os dados dos pilotos.');
            $content = is_file($this->dataFile) ? file_get_contents($this->dataFile) : '';
            if ($content === false) throw new Exception('Falha ao ler os dados dos pilotos.');
            $pilots = $content === '' ? [] : json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($pilots)) throw new Exception('O arquivo de pilotos contém JSON inválido.');
            if (count($pilots) > 0 && !isset($pilots[0])) {
                $migrated = [];
                $id = 1;
                foreach ($pilots as $nickname => $pilot) {
                    $migrated[] = [
                        'id' => $id++,
                        'phoneNumberID' => $pilot['phoneNumberID'] ?? $pilot['email'] ?? '',
                        'nicknameTGC' => $pilot['nicknameTGC'] ?? $pilot['nickname'] ?? $nickname,
                        'name' => $pilot['name'] ?? $pilot['real_name'] ?? '',
                        'email' => $pilot['email'] ?? '',
                        'pinB64' => $pilot['pinB64'] ?? $pilot['pin_b64'] ?? '',
                        'activePilot' => $pilot['activePilot'] ?? $pilot['active_pilot'] ?? true,
                        'createdAt' => $pilot['createdAt'] ?? gmdate('Y-m-d\TH:i:s\Z')
                    ];
                }
                $pilots = $migrated;
            }
            $result = $callback($pilots);
            if ($write) {
                $json = json_encode(array_values($pilots), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $temporaryFile = tempnam($directory, '.pilots-');
                if ($temporaryFile === false) throw new Exception('Falha ao preparar a gravação dos pilotos.');
                $written = file_put_contents($temporaryFile, $json, LOCK_EX);
                if ($written !== strlen($json)) throw new Exception('Falha ao gravar os dados dos pilotos.');
                if (!rename($temporaryFile, $this->dataFile)) throw new Exception('Falha ao substituir os dados dos pilotos.');
                $temporaryFile = null;
            }
            return $result;
        } catch (JsonException $error) {
            throw new Exception('Não foi possível serializar os dados dos pilotos.');
        } finally {
            if ($temporaryFile !== null && is_file($temporaryFile)) unlink($temporaryFile);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function normalizePhone($phone) {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        return ltrim($digits, '0') ?: '0';
    }

    private function mailer() {
        if ($this->mailerInstance !== null) return $this->mailerInstance;
        require_once __DIR__ . '/TGCMailer.php';
        return new TGCMailer();
    }

    private function restorePin($pilotId, $newPinB64, $previous) {
        return $this->withLockedPilots(function (&$pilots) use ($pilotId, $newPinB64, $previous) {
            foreach ($pilots as &$entry) {
                if ((int) ($entry['id'] ?? 0) !== $pilotId) continue;
                if (($entry['pinB64'] ?? null) !== $newPinB64) return false;
                if ($previous['pinB64'] === null) unset($entry['pinB64']); else $entry['pinB64'] = $previous['pinB64'];
                if ($previous['pinUpdatedAt'] === null) unset($entry['pinUpdatedAt']); else $entry['pinUpdatedAt'] = $previous['pinUpdatedAt'];
                return true;
            }
            return false;
        }, true);
    }

    private function restoreActivation($pilotId, $newPinB64, $previous) {
        return $this->withLockedPilots(function (&$pilots) use ($pilotId, $newPinB64, $previous) {
            foreach ($pilots as &$entry) {
                if ((int) ($entry['id'] ?? 0) !== $pilotId) continue;
                if (($entry['pinB64'] ?? null) !== $newPinB64 || empty($entry['activePilot'])) return false;
                $entry['activePilot'] = false;
                if ($previous['pinB64'] === null) unset($entry['pinB64']); else $entry['pinB64'] = $previous['pinB64'];
                if ($previous['pinUpdatedAt'] === null) unset($entry['pinUpdatedAt']); else $entry['pinUpdatedAt'] = $previous['pinUpdatedAt'];
                return true;
            }
            return false;
        }, true);
    }

}

class SeasonRankingManager {
    private $dataFile = 'data/season_ranking.json';

    public function saveRoundPoints($round, $year, $rankingData) {
        $seasonData = $this->loadData();

        foreach ($rankingData as $entry) {
            $pilot = $entry['pilot_nickname'];
            $phone = $entry['phoneNumberID'];
            $points = $entry['points'];

            if (!isset($seasonData[$pilot])) {
                $seasonData[$pilot] = ['total' => 0, 'rounds' => [], 'phoneNumberID' => $phone];
            } else {
                $seasonData[$pilot]['phoneNumberID'] = $phone; // Garante a atualização
            }

            $seasonData[$pilot]['rounds']["$year-$round"] = $points;
            $total = 0;
            foreach ($seasonData[$pilot]['rounds'] as $rPoints) $total += $rPoints;
            $seasonData[$pilot]['total'] = $total;
        }
        $this->saveData($seasonData);
    }

    public function getSeasonRanking() {
        return $this->loadData();
    }

    private function loadData() {
        if (!file_exists($this->dataFile)) { $this->createDataDir(); return []; }
        $content = file_get_contents($this->dataFile);
        if ($content === false) throw new Exception("Falha ao ler o arquivo {$this->dataFile}");
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) throw new Exception("JSON inválido em {$this->dataFile}: " . json_last_error_msg());
        return $data;
    }

    private function saveData($data) {
        $this->createDataDir();
        $written = file_put_contents($this->dataFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        if ($written === false) throw new Exception("Falha ao gravar no arquivo {$this->dataFile}");
    }

    private function createDataDir() { if (!is_dir('data')) mkdir('data', 0755, true); }
}

class SubmissionManager {
    private $dataFile = 'data/submissions.json';

    public function deleteRoundSubmissions($roundNumber, $year) {
        $submissions = $this->loadSubmissions();
        $newSubmissions = [];
        foreach ($submissions as $sub) {
            if (!($sub['round'] === $roundNumber && $sub['year'] === $year)) {
                $newSubmissions[] = $sub;
            }
        }
        $this->saveSubmissions($newSubmissions);
    }

    public function submitTimes($data) {
        $submissions = $this->loadSubmissions();

        // Novo formato: YYYYMMDD_HHMMSS_mmm
        $t = microtime(true);
        $micro = sprintf("%03d", ($t - floor($t)) * 1000);
        $submissionId = date('Ymd_His') . '_' . $micro;

        // Evita colisões improváveis
        while (array_search($submissionId, array_column($submissions, 'submission_id')) !== false) {
            $micro = sprintf("%03d", (intval($micro) + 1) % 1000);
            $submissionId = date('Ymd_His') . '_' . $micro;
        }

        $totalMs = array_reduce($data['tracks'], function($sum, $track) {
            return $sum + $track['time_ms'];
        }, 0);

        $submission = [
                'submission_id' => $submissionId,
                'pilot_nickname' => $data['pilot_nickname'],
                'phoneNumberID' => $data['phoneNumberID'], // Adicionado rule 3
                'round' => $data['round'] ?? null,
                'year' => $data['year'],
                'video_url' => $data['video_url'],
                'mode' => $data['mode'] ?? 'Pole Position',
                'platform' => $data['platform'] ?? 'Console',
                'tracks' => $data['tracks'],
                'comments' => $data['comments'] ?? null,
                'total_round_ms' => $totalMs,
                'status' => [
                        'is_valid' => true,
                        'invalidated_by_admin' => false,
                        'reason' => null
                ],
                'submitted_at' => date('Y-m-d H:i:s')
        ];

        $submissions[] = $submission;
        $this->saveSubmissions($submissions);

        return $submissionId;
    }

    public function invalidateSubmission($submissionId, $reason) {
        $submissions = $this->loadSubmissions();
        $found = false;
        foreach ($submissions as &$sub) {
            if ($sub['submission_id'] === $submissionId) {
                $sub['status'] = [
                        'is_valid' => false,
                        'invalidated_by_admin' => true,
                        'reason' => $reason,
                        'updated_at' => date('Y-m-d H:i:s')
                ];
                $found = true;
                break;
            }
        }
        if ($found) $this->saveSubmissions($submissions);
        else throw new Exception("Submissão não encontrada.");
    }

    public function calculateRoundRanking($round, $year, $allPilots) {
        $submissions = array_filter($this->loadSubmissions(), function($sub) use ($round, $year) {
            $isPolePosition = ($sub['mode'] ?? 'Pole Position') === 'Pole Position';
            return $isPolePosition && $sub['round'] === $round && $sub['year'] === $year && $sub['status']['is_valid'];
        });

        // Separar melhor submission por phoneNumberID
        $bestSubmissions = [];
        foreach ($submissions as $sub) {
            $pid = $sub['phoneNumberID'];
            if (!isset($bestSubmissions[$pid]) || $sub['total_round_ms'] < $bestSubmissions[$pid]['total_round_ms']) {
                $bestSubmissions[$pid] = $sub;
            }
        }

        // Ordenar os melhores pelos menores tempos
        usort($bestSubmissions, function($a, $b) {
            return $a['total_round_ms'] <=> $b['total_round_ms'];
        });

        $ranking = [];
        $currentRank = 1;
        $previousTime = null;
        $rankOffset = 0;

        foreach ($bestSubmissions as $sub) {
            if ($previousTime === $sub['total_round_ms']) {
                $rankOffset++;
            } else {
                $currentRank = $currentRank + $rankOffset + ($previousTime !== null ? 1 : 0);
                $rankOffset = 0;
            }
            $previousTime = $sub['total_round_ms'];
            $currentPoints = PointsCalculator::getPoints($currentRank);

            $ranking[] = [
                    'position' => $currentRank,
                    'pilot_nickname' => $sub['pilot_nickname'],
                    'phoneNumberID' => $sub['phoneNumberID'],
                    'total_time_ms' => $sub['total_round_ms'],
                    'total_time_readable' => TimeConverter::toReadable($sub['total_round_ms']),
                    'points' => $currentPoints,
                    'submission_id' => $sub['submission_id'],
                    'video_url' => $sub['video_url'],
                    'platform' => $sub['platform'] ?? 'Console',
                    'tracks' => $sub['tracks']
            ];
        }

        // Adicionar pilotos sem submissão
        $submittedPhones = array_column($ranking, 'phoneNumberID');
        foreach ($allPilots as $p) {
            if ($p['activePilot'] && !in_array($p['phoneNumberID'], $submittedPhones)) {
                $ranking[] = [
                        'position' => 0,
                        'pilot_nickname' => $p['nicknameTGC'],
                        'phoneNumberID' => $p['phoneNumberID'],
                        'total_time_ms' => 0,
                        'total_time_readable' => '0:00:000',
                        'points' => 0,
                        'submission_id' => null,
                        'video_url' => null,
                        'platform' => null,
                        'tracks' => []
                ];
            }
        }

        return $ranking;
    }

    public function getAllSubmissions() { return $this->loadSubmissions(); }

    public function getHallOfFameData() {
        $submissions = $this->loadSubmissions();
        $hof = [];

        foreach ($submissions as $sub) {
            if (!$sub['status']['is_valid']) continue;

            foreach ($sub['tracks'] as $t) {
                $trackId = $t['track_id'];
                $mode = $sub['mode'] ?? 'Pole Position';
                $platform = $sub['platform'] ?? 'Console';

                if (!isset($hof[$trackId])) $hof[$trackId] = ['info' => $t, 'records' => []];
                if (!isset($hof[$trackId]['records'][$mode])) $hof[$trackId]['records'][$mode] = [];
                if (!isset($hof[$trackId]['records'][$mode][$platform])) $hof[$trackId]['records'][$mode][$platform] = [];

                $hof[$trackId]['records'][$mode][$platform][] = [
                        'pilot' => $sub['pilot_nickname'],
                        'time_ms' => $t['time_ms'],
                        'car' => $t['car_name'] ?? $t['car'] ?? 'N/A',
                        'video' => $sub['video_url'],
                        'date' => $sub['submitted_at']
                ];
            }
        }

        foreach ($hof as &$trackData) {
            foreach ($trackData['records'] as &$modeData) {
                foreach ($modeData as &$platData) {
                    usort($platData, function($a, $b) {
                        return $a['time_ms'] <=> $b['time_ms'];
                    });
                    $platData = array_slice($platData, 0, 3);
                }
            }
        }

        ksort($hof);
        return $hof;
    }

    private function loadSubmissions() {
        if (!file_exists($this->dataFile)) { $this->createDataDir(); return []; }
        $content = file_get_contents($this->dataFile);
        if ($content === false) throw new Exception("Falha ao ler o arquivo {$this->dataFile}");
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) throw new Exception("JSON inválido em {$this->dataFile}: " . json_last_error_msg());
        return is_array($data) ? array_values($data) : [];
    }

    private function saveSubmissions($submissions) {
        $this->createDataDir();
        $written = file_put_contents($this->dataFile, json_encode(array_values($submissions), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        if ($written === false) throw new Exception("Falha ao gravar no arquivo {$this->dataFile}");
    }

    private function createDataDir() { if (!is_dir('data')) mkdir('data', 0755, true); }
}

class TrackRecordsManager {
    private $dataFile = 'data/track_records.json';

    public function updateRecord($trackData, $recordData, $year) {
        $records = $this->loadRecords();
        $updated = false;

        foreach ($records as &$item) {
            if ($item['track_id'] === $trackData['id'] &&
                    $item['record']['mode'] === $recordData['mode'] &&
                    $item['record']['platform'] === $recordData['platform']) {

                if ($recordData['time_ms'] < $item['record']['time_ms']) {
                    $item['record'] = array_merge($item['record'], $recordData);
                    $item['record']['year'] = $year;
                    $item['record']['updated_at'] = date('Y-m-d H:i:s');
                }
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            $records[] = [
                    "track_id" => $trackData['id'],
                    "track_country" => $trackData['country'],
                    "track_city" => $trackData['city'],
                    "record" => array_merge($recordData, [
                            "created_at" => date('Y-m-d H:i:s'),
                            "year" => $year,
                            "updated_at" => date('Y-m-d H:i:s')
                    ])
            ];
        }
        $this->saveRecords($records);
    }

    public function getAllRecords() { return $this->loadRecords(); }

    private function loadRecords() {
        if (!file_exists($this->dataFile)) { $this->createDataDir(); return []; }
        $content = file_get_contents($this->dataFile);
        if ($content === false) throw new Exception("Falha ao ler o arquivo {$this->dataFile}");
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) throw new Exception("JSON inválido em {$this->dataFile}: " . json_last_error_msg());
        return $data;
    }

    private function saveRecords($records) {
        $this->createDataDir();
        $written = file_put_contents($this->dataFile, json_encode(array_values($records), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        if ($written === false) throw new Exception("Falha ao gravar no arquivo {$this->dataFile}");
    }
    private function createDataDir() { if (!is_dir('data')) mkdir('data', 0755, true); }
}

class AdminPanel {
    private $tracksFile = 'data/all_tracks.json';
    private $roundsFile = 'data/rounds.json';

    public function getTrackList() { return $this->loadAllTracks(); }

    public function roundExists($roundNumber, $year) {
        $rounds = $this->loadRounds();
        foreach ($rounds as $round) {
            if ($round['round_number'] === $roundNumber && $round['year'] === $year) return true;
        }
        return false;
    }

    public function isRoundClosed($roundNumber, $year) {
        $rounds = $this->loadRounds();
        foreach ($rounds as $round) {
            if ($round['round_number'] === $roundNumber && $round['year'] === $year) {
                return isset($round['status']) && $round['status'] === 'closed';
            }
        }
        return false;
    }

    public function finalizeRound($roundNumber, $year) {
        $rounds = $this->loadRounds();
        $found = false;
        foreach ($rounds as &$round) {
            if ($round['round_number'] === $roundNumber && $round['year'] === $year) {
                $round['status'] = 'closed';
                $found = true;
                break;
            }
        }
        if ($found) $this->saveRounds($rounds);
        return $found;
    }

    public function deleteRound($roundNumber, $year) {
        $rounds = $this->loadRounds();
        $newRounds = [];
        foreach ($rounds as $round) {
            if (!($round['round_number'] === $roundNumber && $round['year'] === $year)) {
                $newRounds[] = $round;
            }
        }
        $this->saveRounds($newRounds);
    }

    public function drawTracksForRound($roundNumber, $year, $forceRedraw = false) {
        $allTracks = $this->loadAllTracks();
        $rounds = $this->loadRounds();

        if (!$forceRedraw && $this->roundExists($roundNumber, $year)) {
            return ['error' => 'Rodada já existe', 'needs_confirmation' => true];
        }

        if ($forceRedraw) {
            $this->deleteRound($roundNumber, $year);
            $rounds = $this->loadRounds();
        }

        $usedTracks = [];
        foreach ($rounds as $round) {
            if ($round['year'] === $year) {
                $usedTracks = array_merge($usedTracks, array_column($round['tracks'], 'id'));
            }
        }

        $availableTracks = array_filter($allTracks, function($track) use ($usedTracks) {
            return !in_array($track['id'], $usedTracks);
        });

        if (count($availableTracks) < 8) {
            $availableTracks = $allTracks;
        }

        $availableTracks = array_values($availableTracks);
        $keys = array_rand($availableTracks, 8);
        $selectedTracks = [];
        foreach ((array)$keys as $key) $selectedTracks[] = $availableTracks[$key];

        $rounds[] = [
                'round_number' => $roundNumber,
                'year' => $year,
                'tracks' => $selectedTracks,
                'deadline' => null,
                'status' => 'open',
                'created_at' => date('Y-m-d H:i:s')
        ];

        $this->saveRounds($rounds);
        return $selectedTracks;
    }

    public function setDeadline($roundNumber, $year, $date) {
        $rounds = $this->loadRounds();
        $deadline = $date . 'T23:59:59';
        $found = false;
        foreach ($rounds as &$round) {
            if ($round['round_number'] === $roundNumber && $round['year'] === $year) {
                $round['deadline'] = $deadline;
                $found = true;
                break;
            }
        }
        if (!$found) throw new Exception("Rodada não encontrada.");
        $this->saveRounds($rounds);
    }

    public function getCurrentRound($year) {
        $rounds = array_filter($this->loadRounds(), function($round) use ($year) {
            return $round['year'] === $year;
        });
        if (empty($rounds)) return null;

        usort($rounds, function($a, $b) { return $b['round_number'] - $a['round_number']; });
        return $rounds[0];
    }

    private function loadAllTracks() {
        if (!file_exists($this->tracksFile)) {
            $this->createDataDir();
            $defaultTracks = [
                    ["id" => 1,  "country" => "USA",  "city" => "Las Vegas"],
                    ["id" => 2,  "country" => "USA",  "city" => "Los Angeles"],
                    ["id" => 3,  "country" => "USA",  "city" => "New York"],
                    ["id" => 4,  "country" => "USA",  "city" => "San Francisco"],
                    ["id" => 5,  "country" => "SAM",  "city" => "Rio"],
                    ["id" => 6,  "country" => "SAM",  "city" => "Machu Picchu"],
                    ["id" => 7,  "country" => "SAM",  "city" => "Chichen Itza"],
                    ["id" => 8,  "country" => "SAM",  "city" => "Rain Forest"],
                    ["id" => 9,  "country" => "JAP",  "city" => "Tokyo"],
                    ["id" => 10, "country" => "JAP",  "city" => "Hiroshima"],
                    ["id" => 11, "country" => "JAP",  "city" => "Yokohama"],
                    ["id" => 12, "country" => "JAP",  "city" => "Kyoto"],
                    ["id" => 13, "country" => "GER",  "city" => "Munich"],
                    ["id" => 14, "country" => "GER",  "city" => "Cologne"],
                    ["id" => 15, "country" => "GER",  "city" => "Black Forest"],
                    ["id" => 16, "country" => "GER",  "city" => "Frankfurt"],
                    ["id" => 17, "country" => "SCAN", "city" => "Stockholm"],
                    ["id" => 18, "country" => "SCAN", "city" => "Copenhagen"],
                    ["id" => 19, "country" => "SCAN", "city" => "Helsinki"],
                    ["id" => 20, "country" => "SCAN", "city" => "Oslo"],
                    ["id" => 21, "country" => "FRA",  "city" => "Paris"],
                    ["id" => 22, "country" => "FRA",  "city" => "Nice"],
                    ["id" => 23, "country" => "FRA",  "city" => "Bordeaux"],
                    ["id" => 24, "country" => "FRA",  "city" => "Monaco"],
                    ["id" => 25, "country" => "ITA",  "city" => "Pisa"],
                    ["id" => 26, "country" => "ITA",  "city" => "Rome"],
                    ["id" => 27, "country" => "ITA",  "city" => "Sicily"],
                    ["id" => 28, "country" => "ITA",  "city" => "Florence"],
                    ["id" => 29, "country" => "UKG",  "city" => "London"],
                    ["id" => 30, "country" => "UKG",  "city" => "Sheffield"],
                    ["id" => 31, "country" => "UKG",  "city" => "Loch Ness"],
                    ["id" => 32, "country" => "UKG",  "city" => "Stonehenge"]
            ];
            file_put_contents($this->tracksFile, json_encode($defaultTracks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $defaultTracks;
        }
        $content = file_get_contents($this->tracksFile);
        if ($content === false) throw new Exception("Falha ao ler o arquivo {$this->tracksFile}");
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) throw new Exception("JSON inválido em {$this->tracksFile}: " . json_last_error_msg());
        return $data;
    }

    private function loadRounds() {
        if (!file_exists($this->roundsFile)) { $this->createDataDir(); return []; }
        $content = file_get_contents($this->roundsFile);
        if ($content === false) throw new Exception("Falha ao ler o arquivo {$this->roundsFile}");
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) throw new Exception("JSON inválido em {$this->roundsFile}: " . json_last_error_msg());
        return $data;
    }

    private function saveRounds($rounds) {
        $this->createDataDir();
        $written = file_put_contents($this->roundsFile, json_encode($rounds, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        if ($written === false) throw new Exception("Falha ao gravar no arquivo {$this->roundsFile}");
    }

    private function createDataDir() { if (!is_dir('data')) mkdir('data', 0755, true); }
}

if (defined('TGC_POLE_POSITION_LIBRARY_ONLY')) return;

// ============================================================================
// PROCESSAMENTO DE AÇÕES
// ============================================================================

$pilotManager = new PilotManager();
$submissionManager = new SubmissionManager();
$recordsManager = new TrackRecordsManager();
$seasonRankingManager = new SeasonRankingManager();
$admin = new AdminPanel();

// Set session defaults
if (isset($_POST['change_year'])) {
    $_SESSION['selected_year'] = intval($_POST['change_year']);
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit;
}
$selectedYear = $_SESSION['selected_year'] ?? 2026;

// Restore flash messages
$message = $_SESSION['flash_msg'] ?? '';
$messageType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);

$popupEmailMessage = null;
$popupSubmissionData = null;

function redirectWithMessage($msg, $type = 'success') {
    $_SESSION['flash_msg'] = $msg;
    $_SESSION['flash_type'] = $type;
    // Alterado o redirecionamento para o nome do arquivo ou usar PHP_SELF
    $redirectUrl = basename($_SERVER['PHP_SELF']);
    // Preserva o ano na URL se necessário, embora agora estejamos usando SESSION para o ano
    header("Location: " . $redirectUrl);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
                case 'admin_login':
                    if (isset($_SESSION['tgc_admin_csrf'], $_POST['admin_csrf'])
                        && hash_equals($_SESSION['tgc_admin_csrf'], (string) $_POST['admin_csrf'])
                        && verifyAdminPassword($_POST['admin_password'] ?? '')) {
                        session_regenerate_id(true);
                        $_SESSION['tgc_admin_authenticated'] = true;
                        $_SESSION['tgc_admin_last_activity'] = time();
                        $_SESSION['tgc_admin_csrf'] = bin2hex(random_bytes(32));
                        redirectWithMessage('Acesso administrativo autorizado.');
                    }
                    $message = '❌ Acesso administrativo não autorizado. Confira a configuração do servidor e a senha.';
                    $messageType = 'error';
                    break;

                case 'admin_logout':
                    if (isAdminAuthorized()) {
                        unset($_SESSION['tgc_admin_authenticated'], $_SESSION['tgc_admin_last_activity']);
                        redirectWithMessage('Sessão administrativa encerrada.');
                    }
                    $message = '❌ Sessão administrativa inválida ou expirada.';
                    $messageType = 'error';
                    break;

                case 'request_pilot_registration':
                try {
                        $result = $pilotManager->registerPilot($_POST);
                        $message = $result['message'];
                        $messageType = $result['success'] ? 'success' : 'error';
                    } catch (Exception $e) {
                        $message = "❌ Não foi possível registrar o pedido: " . $e->getMessage();
                        $messageType = 'error';
                    }
                    break;

                case 'request_new_pin':
                    try {
                        $result = $pilotManager->requestNewPIN($_POST['pilot_id'] ?? '');
                        $popupEmailMessage = $result['message'];
                        $message = "✅ " . $result['message'];
                    } catch (Exception $e) {
                        $message = "❌ Erro: " . $e->getMessage();
                        $messageType = 'error';
                }
                break;

            case 'submit_times': // Pole Position
                try {
                    $pilot = $pilotManager->getPilotByPhone($_POST['phoneNumberID']);

                    $currentRound = $admin->getCurrentRound($selectedYear);
                    if ($currentRound && isset($currentRound['status']) && $currentRound['status'] === 'closed') {
                        throw new Exception("Esta rodada já está encerrada! Aguarde o próximo sorteio.");
                    }

                    $tracks = [];
                    $roundTracks = $currentRound ? $currentRound['tracks'] : [];
                    $platform = $_POST['platform'] ?? 'Console';
                    $mode = 'Pole Position';

                    for ($i = 1; $i <= 8; $i++) {
                        if (!empty($_POST["track_{$i}_time"]) && !empty($_POST["track_{$i}_car"])) {
                            $trackInfo = $roundTracks[$i - 1] ?? null;
                            if (!$trackInfo) throw new Exception("Pista $i não definida para esta rodada.");

                            $timeMs = TimeConverter::toMilliseconds($_POST["track_{$i}_time"]);

                            $trackRecord = [
                                    'track_id' => $trackInfo['id'],
                                    'track_country' => $trackInfo['country'],
                                    'track_city' => $trackInfo['city'],
                                    'time_ms' => $timeMs,
                                    'car_name' => $_POST["track_{$i}_car"],
                                    'car_color' => $_POST["track_{$i}_color"]
                            ];
                            $tracks[] = $trackRecord;

                            $recordsManager->updateRecord(
                                    ['id' => $trackInfo['id'], 'country' => $trackInfo['country'], 'city' => $trackInfo['city']],
                                    [
                                            'pilot_nickname' => $pilot['nicknameTGC'],
                                            'phoneNumberID' => $pilot['phoneNumberID'],
                                            'time_ms' => $timeMs,
                                            'car_name' => $_POST["track_{$i}_car"],
                                            'car_color' => $_POST["track_{$i}_color"],
                                            'mode' => $mode,
                                            'platform' => $platform,
                                            'video_url' => $_POST['video_url']
                                    ],
                                    $selectedYear
                            );
                        }
                    }

                    if (empty($tracks)) {
                        throw new Exception("Nenhum tempo válido foi informado!");
                    }

                    $submissionId = $submissionManager->submitTimes([
                            'pilot_nickname' => $pilot['nicknameTGC'],
                            'phoneNumberID' => $pilot['phoneNumberID'],
                            'round' => $currentRound['round_number'] ?? 1,
                            'year' => $selectedYear,
                            'video_url' => $_POST['video_url'],
                            'platform' => $platform,
                            'mode' => $mode,
                            'tracks' => $tracks,
                            'comments' => $_POST['comments']
                    ]);

                    $_SESSION['popup_submission'] = [
                            'nickname' => $pilot['nicknameTGC'],
                            'round' => $currentRound['round_number'] ?? 1,
                            'tracks' => $tracks,
                            'total_ms' => array_reduce($tracks, fn($s, $t) => $s + $t['time_ms'], 0)
                    ];
                    redirectWithMessage("🚀 Tempos Pole Position submetidos com sucesso!");

                } catch (Exception $e) {
                    $message = "❌ Erro ao submeter: " . $e->getMessage();
                    $messageType = 'error';
                }
                break;

            case 'submit_versus': // Versus
                try {
                    $pilot = $pilotManager->getPilotByPhone($_POST['phoneNumberID']);

                    $trackId = intval($_POST['track_id']);
                    $allTracks = $admin->getTrackList();
                    $trackInfo = null;
                    foreach ($allTracks as $t) {
                        if ($t['id'] === $trackId) { $trackInfo = $t; break; }
                    }

                    if (!$trackInfo) throw new Exception("Pista inválida!");

                    $timeMs = TimeConverter::toMilliseconds($_POST['time']);
                    $platform = $_POST['platform'] ?? 'Console';
                    $mode = 'Versus';

                    $recordsManager->updateRecord(
                            ['id' => $trackInfo['id'], 'country' => $trackInfo['country'], 'city' => $trackInfo['city']],
                            [
                                    'pilot_nickname' => $pilot['nicknameTGC'],
                                    'phoneNumberID' => $pilot['phoneNumberID'],
                                    'time_ms' => $timeMs,
                                    'car_name' => $_POST['car'],
                                    'car_color' => null,
                                    'mode' => $mode,
                                    'platform' => $platform,
                                    'video_url' => $_POST['video_url']
                            ],
                            $selectedYear
                    );

                    $trackDataForSubmission = [[
                            'track_id' => $trackInfo['id'],
                            'track_country' => $trackInfo['country'],
                            'track_city' => $trackInfo['city'],
                            'time_ms' => $timeMs,
                            'car_name' => $_POST['car'],
                            'car_color' => null
                    ]];

                    $submissionId = $submissionManager->submitTimes([
                            'pilot_nickname' => $pilot['nicknameTGC'],
                            'phoneNumberID' => $pilot['phoneNumberID'],
                            'round' => 0,
                            'year' => $selectedYear,
                            'video_url' => $_POST['video_url'],
                            'platform' => $platform,
                            'mode' => $mode,
                            'tracks' => $trackDataForSubmission,
                            'comments' => $_POST['comments']
                    ]);

                    redirectWithMessage("⚔️ Recorde Versus registrado com sucesso!");
                } catch (Exception $e) {
                    $message = "❌ Erro ao submeter Versus: " . $e->getMessage();
                    $messageType = 'error';
                }
                break;

            case 'admin_manage_pilot':
                if (isAdminAuthorized()) {
                    try {
                        $operation = $_POST['pilot_operation'] ?? '';
                        if ($operation === 'edit_pending' || $operation === 'edit_and_activate') {
                            $result = $pilotManager->editPendingPilot($_POST['pilot_id'] ?? '', $_POST, $operation === 'edit_and_activate');
                        } elseif ($operation === 'deactivate') {
                            $result = $pilotManager->setPilotActive($_POST['pilot_id'] ?? '', false);
                        } else {
                            throw new Exception('Ação de gestão inválida.');
                        }
                        redirectWithMessage('✅ ' . $result['message']);
                    } catch (Exception $e) {
                        $message = '❌ Erro na gestão do piloto: ' . $e->getMessage();
                        $messageType = 'error';
                    }
                } else {
                    $message = '❌ Sessão administrativa inválida ou expirada.';
                    $messageType = 'error';
                }
                break;

            case 'admin_invalidate':
                if (isAdminAuthorized()) {
                    try {
                        $submissionManager->invalidateSubmission($_POST['submission_id'], $_POST['reason']);
                        redirectWithMessage("✅ Submissão invalidada com sucesso!");
                    } catch (Exception $e) {
                        $message = "❌ Erro: " . $e->getMessage();
                        $messageType = 'error';
                    }
                } else {
                    $message = '❌ Sessão administrativa inválida ou expirada.';
                    $messageType = 'error';
                }
                break;

            case 'admin_draw_tracks':
                if (isAdminAuthorized()) {
                    try {
                        $roundNumber = intval($_POST['round_number']);
                        if ($roundNumber < 1) throw new Exception("O número da rodada deve ser no mínimo 1!");

                        $year = intval($_POST['year']);
                        $forceRedraw = isset($_POST['force_redraw']) && $_POST['force_redraw'] === 'yes';

                        if ($forceRedraw) {
                            $pending = $_SESSION['pending_draw'] ?? null;
                            if (!$pending || (int) $pending['round'] !== $roundNumber || (int) $pending['year'] !== $year) {
                                throw new Exception('A confirmação do novo sorteio expirou ou não corresponde à rodada selecionada.');
                            }
                        }

                        if (!$forceRedraw && $admin->roundExists($roundNumber, $year)) {
                            $_SESSION['pending_draw'] = ['round' => $roundNumber, 'year' => $year];
                            redirectWithMessage("⚠️ Esta rodada já foi sorteada! Confirme o novo sorteio no painel.", 'warning');
                        }

                        if ($forceRedraw) {
                            $submissionManager->deleteRoundSubmissions($roundNumber, $year);
                        }

                        $tracks = $admin->drawTracksForRound($roundNumber, $year, $forceRedraw);

                        if (isset($tracks['error']) && !isset($tracks['needs_confirmation'])) {
                            throw new Exception($tracks['error']);
                        } else {
                            unset($_SESSION['pending_draw']);
                            redirectWithMessage("✅ 8 pistas sorteadas para a Rodada " . $roundNumber . " de " . $year . "!");
                        }
                    } catch (Exception $e) {
                        $message = "❌ Erro: " . $e->getMessage();
                        $messageType = 'error';
                    }
                } else {
                    $message = '❌ Sessão administrativa inválida ou expirada.';
                    $messageType = 'error';
                }
                break;

            case 'admin_finalize_round':
                if (isAdminAuthorized()) {
                    try {
                        $roundNumber = intval($_POST['round_number']);
                        if ($roundNumber < 1) throw new Exception("O número da rodada deve ser no mínimo 1!");
                        $year = intval($_POST['year']);

                        $allP = $pilotManager->getAllPilots();
                        $roundRanking = $submissionManager->calculateRoundRanking($roundNumber, $year, $allP);
                        $seasonRankingManager->saveRoundPoints($roundNumber, $year, $roundRanking);
                        $admin->finalizeRound($roundNumber, $year);

                        redirectWithMessage("🏆 Rodada $roundNumber Finalizada! Pontos distribuídos e submissões encerradas.");
                    } catch (Exception $e) {
                        $message = "❌ Erro ao finalizar: " . $e->getMessage();
                        $messageType = 'error';
                    }
                } else {
                    $message = '❌ Sessão administrativa inválida ou expirada.';
                    $messageType = 'error';
                }
                break;

            case 'admin_set_deadline':
                if (isAdminAuthorized()) {
                    try {
                        $roundNumber = intval($_POST['round_number']);
                        if ($roundNumber < 1) throw new Exception("O número da rodada deve ser no mínimo 1!");
                        $year = intval($_POST['year']);

                        $admin->setDeadline($roundNumber, $year, $_POST['deadline_date']);
                        redirectWithMessage("✅ Prazo definido com sucesso para 23:59:59!");
                    } catch (Exception $e) {
                        $message = "❌ Erro: " . $e->getMessage();
                        $messageType = 'error';
                    }
                } else {
                    $message = '❌ Sessão administrativa inválida ou expirada.';
                    $messageType = 'error';
                }
                break;
        }
    }
}

if (isset($_SESSION['popup_submission'])) {
    $popupSubmissionData = $_SESSION['popup_submission'];
    unset($_SESSION['popup_submission']);
}

// Carregar dados da página
$currentRound = $admin->getCurrentRound($selectedYear);
$currentRoundNumber = $currentRound ? $currentRound['round_number'] : 1;
$roundTracks = $currentRound ? $currentRound['tracks'] : [];
$roundDeadline = $currentRound ? ($currentRound['deadline'] ?? null) : null;
$allPilots = $pilotManager->getAllPilots();
$ranking = $submissionManager->calculateRoundRanking($currentRoundNumber, $selectedYear, $allPilots);
$completeTrackList = $admin->getTrackList();
$hallOfFameData = $submissionManager->getHallOfFameData();
$seasonRanking = $seasonRankingManager->getSeasonRanking();
$pendingDraw = $_SESSION['pending_draw'] ?? null;
$adminAuthenticated = adminSessionActive();
$adminCsrf = adminCsrfToken();
$resetPilots = getResetEligiblePilots($allPilots);
$pilotsToActivate = array_values(array_filter($allPilots, function ($pilot) { return ($pilot['activePilot'] ?? false) === false; }));
$pilotsToDeactivate = array_values(array_filter($allPilots, function ($pilot) { return ($pilot['activePilot'] ?? false) === true; }));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top Gear Championships - TGC 2026</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .tab-button.active {
            background: linear-gradient(to right, #ef4444, #dc2626);
            color: white;
            border-bottom: 2px solid #fff;
        }
        .tooltip { position: relative; display: inline-block; cursor: help; }
        .tooltip .tooltiptext {
            visibility: hidden; width: 200px; background-color: #1f2937; color: #fff;
            text-align: center; border-radius: 6px; padding: 8px; position: absolute; z-index: 10;
            bottom: 125%; left: 50%; margin-left: -100px; opacity: 0; transition: opacity 0.3s;
        }
        .tooltip:hover .tooltiptext { visibility: visible; opacity: 1; }
        .popup {
            display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.8); backdrop-filter: blur(5px);
        }
        .popup-content {
            background-color: #1f2937; margin: 10% auto; padding: 30px; border: 2px solid #ef4444;
            border-radius: 10px; width: 90%; max-width: 500px; text-align: center; box-shadow: 0 0 20px rgba(239, 68, 68, 0.5);
        }
        .close-popup { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-popup:hover { color: #fff; }
        #countdown { font-size: 2rem; font-weight: bold; color: #ef4444; text-shadow: 0 0 10px rgba(239, 68, 68, 0.5); }
        .platform-selector input[type="radio"] { display: none; }
        .platform-selector label {
            cursor: pointer; padding: 8px 16px; border: 1px solid #4b5563; border-radius: 6px;
            background-color: #1f2937; color: #9ca3af; font-weight: bold; transition: all 0.2s;
        }
        .platform-selector input[type="radio"]:checked + label { border-color: #ef4444; background-color: #ef4444; color: white; }
        .filter-btn.active { background-color: #ef4444; color: white; border-color: #ef4444; }
        .medal-1 { color: #ffd700; text-shadow: 0 0 5px rgba(255, 215, 0, 0.5); }
        .medal-2 { color: #c0c0c0; text-shadow: 0 0 5px rgba(192, 192, 192, 0.5); }
        .medal-3 { color: #cd7f32; text-shadow: 0 0 5px rgba(205, 127, 50, 0.5); }
        .hof-card { transition: transform 0.2s; }
        .hof-card:hover { transform: translateY(-2px); }
        .hof-nickname { font-size: 1.125rem; font-weight: 800; letter-spacing: 0.025em; }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen font-sans">

<!-- Popup Email enviado (Recuperação) -->
<div id="emailPopup" class="popup" <?= $popupEmailMessage ? 'style="display:block;"' : '' ?>>
    <div class="popup-content">
        <span class="close-popup" onclick="closePopup('emailPopup')">&times;</span>
        <h2 class="text-2xl font-bold mb-4">📧 E-mail Enviado!</h2>
        <div class="bg-gray-800 p-6 rounded-lg mb-4 border border-gray-700">
            <i class="fa-solid fa-paper-plane text-5xl text-blue-400 mb-4"></i>
            <p class="text-lg"><?= htmlspecialchars($popupEmailMessage, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <p class="text-gray-400 mb-4 text-sm">Verifique a sua caixa de entrada e spam.</p>
        <button onclick="closePopup('emailPopup')" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-full shadow-lg">
            Fechar
        </button>
    </div>
</div>

<!-- Popup Resumo da Submissão (WhatsApp) -->
<div id="subPopup" class="popup" <?= $popupSubmissionData ? 'style="display:block;"' : '' ?>>
    <div class="popup-content">
        <span class="close-popup" onclick="closePopup('subPopup')">&times;</span>
        <h2 class="text-2xl font-bold mb-4 text-green-400">🏁 Envio Realizado!</h2>

        <div class="bg-gray-800 p-4 rounded-lg mb-4 border border-gray-700 text-left text-sm font-mono overflow-auto max-h-60" id="subShareContent">
            <?php if ($popupSubmissionData): ?>
                🏁 Olá Pessoal, aqui é o Piloto <?= htmlspecialchars($popupSubmissionData['nickname']) ?> 🏁<br>
                🥇 Veja os meus Tempos da Rodada <?= $popupSubmissionData['round'] ?> 🥇<br>
                🥇 TGC Pole Position <?= $selectedYear ?> 🥇<br><br>
                TID | Tempo    | Cor<br>
                <?php foreach ($popupSubmissionData['tracks'] as $t):
                    $colorEmoji = ['red'=>'🔴','purple'=>'🟣','blue'=>'🔵','white'=>'⚪'][$t['car_color']] ?? $t['car_color'];
                    $tName = str_pad($t['track_id'], 2, '0', STR_PAD_LEFT);
                    ?>
                    <?= $tName ?>  | <?= TimeConverter::toReadable($t['time_ms']) ?> | <?= $colorEmoji ?><br>
                <?php endforeach; ?><br>
                O meu total até o momento é<br>
                <?= TimeConverter::toReadable($popupSubmissionData['total_ms']) ?>
            <?php endif; ?>
        </div>

        <button onclick="shareIndividualToWhatsApp()" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-full shadow-lg transition transform hover:scale-105 flex items-center justify-center gap-2">
            <i class="fa-brands fa-whatsapp text-xl"></i> Compartilhar no Grupo
        </button>
    </div>
</div>

<!-- Header -->
<header class="bg-gradient-to-r from-gray-900 via-red-900 to-gray-900 py-8 shadow-xl border-b border-red-800 relative overflow-hidden">
    <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')] opacity-30"></div>
    <div class="container mx-auto px-4 flex items-center justify-between relative z-10">
        <div class="flex items-center gap-4">
            <i class="fa-solid fa-car-side text-4xl text-red-500 transform -scale-x-100"></i>
            <div class="text-left">
                <h1 class="text-3xl md:text-5xl font-black italic tracking-tighter text-white">
                    TOP GEAR <span class="text-red-500">CHAMPIONSHIPS</span>
                </h1>
                <p class="text-gray-300 font-mono text-sm md:text-lg tracking-widest uppercase">TGC <?= $selectedYear ?> - Portal de Competição de Tempos</p>
            </div>
        </div>
        <i class="fa-solid fa-flag-checkered text-4xl md:text-6xl text-white opacity-80 animate-pulse"></i>
    </div>
</header>

<!-- Year Filter via POST -->
<div class="bg-gray-800 border-b border-gray-700 sticky top-0 z-20 shadow-md">
    <div class="container mx-auto px-4 py-3">
        <form method="POST" id="yearForm" class="flex items-center justify-center gap-4 text-sm">
            <input type="hidden" name="change_year" id="yearInput" value="<?= $selectedYear ?>">
            <span class="font-semibold text-gray-400 uppercase tracking-wide">Temporada:</span>
            <button type="button" onclick="document.getElementById('yearInput').value='2025'; document.getElementById('yearForm').submit();" class="px-4 py-1 rounded-full <?= $selectedYear === 2025 ? 'bg-red-600 text-white shadow-lg shadow-red-900/50' : 'bg-gray-700 text-gray-400 hover:bg-gray-600' ?> transition font-bold">2025</button>
            <button type="button" onclick="document.getElementById('yearInput').value='2026'; document.getElementById('yearForm').submit();" class="px-4 py-1 rounded-full <?= $selectedYear === 2026 ? 'bg-red-600 text-white shadow-lg shadow-red-900/50' : 'bg-gray-700 text-gray-400 hover:bg-gray-600' ?> transition font-bold">2026</button>
        </form>
    </div>
</div>

<!-- Messages -->
<?php if ($message): ?>
    <div class="container mx-auto px-4 mt-6">
        <div class="<?php
        if ($messageType === 'error') echo 'bg-red-900/50 border-red-500 text-red-200';
        elseif ($messageType === 'warning') echo 'bg-yellow-900/50 border-yellow-500 text-yellow-200';
        else echo 'bg-green-900/50 border-green-500 text-green-200';
        ?> border px-6 py-4 rounded-lg shadow-lg flex items-center gap-3">
            <i class="fa-solid fa-circle-info text-xl"></i>
            <div><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>
<?php endif; ?>

<!-- Main Container -->
<div class="container mx-auto px-4 py-8">

    <!-- Tabs Navigation (Order: Rodada, Ranking, Hall, Submissoes, Pole, Versus, Cadastro, Admin) -->
    <div class="flex flex-wrap gap-1 mb-6 border-b border-gray-700">
        <button onclick="switchTab('rodada-atual')" class="tab-button active px-5 py-3 rounded-t-lg font-bold transition hover:bg-gray-800 text-sm md:text-base flex items-center gap-2">
            <i class="fa-solid fa-flag"></i> Rodada Atual
        </button>
        <button onclick="switchTab('ranking-global')" class="tab-button px-5 py-3 rounded-t-lg font-bold transition hover:bg-gray-800 text-sm md:text-base flex items-center gap-2 text-yellow-400">
            <i class="fa-solid fa-trophy"></i> Ranking Pole Position
        </button>
        <button onclick="switchTab('hall-fama')" class="tab-button px-5 py-3 rounded-t-lg font-bold transition hover:bg-gray-800 text-sm md:text-base flex items-center gap-2 text-blue-400">
            <i class="fa-solid fa-star"></i> Hall da Fama
        </button>
        <button onclick="switchTab('submissoes')" class="tab-button px-5 py-3 rounded-t-lg font-bold transition hover:bg-gray-800 text-sm md:text-base flex items-center gap-2">
            <i class="fa-solid fa-list"></i> Submissões
        </button>
        <button onclick="switchTab('submeter-pole')" class="tab-button px-5 py-3 rounded-t-lg font-bold transition hover:bg-gray-800 text-sm md:text-base flex items-center gap-2 text-green-400">
            <i class="fa-solid fa-stopwatch"></i> Submeter Pole
        </button>
        <button onclick="switchTab('submeter-versus')" class="tab-button px-5 py-3 rounded-t-lg font-bold transition hover:bg-gray-800 text-sm md:text-base flex items-center gap-2 text-purple-400">
            <i class="fa-solid fa-hand-fist"></i> Submeter Versus
        </button>
        <button onclick="switchTab('cadastro-piloto')" class="tab-button px-5 py-3 rounded-t-lg font-bold transition hover:bg-gray-800 text-sm md:text-base flex items-center gap-2 text-blue-300">
            <i class="fa-solid fa-user-plus"></i> Cadastro e reset PIN
        </button>
        <button onclick="switchTab('admin')" class="tab-button px-5 py-3 rounded-t-lg font-bold transition hover:bg-gray-800 text-sm md:text-base flex items-center gap-2 text-gray-400 ml-auto">
            <i class="fa-solid fa-lock"></i> Admin
        </button>
    </div>

    <!-- Tab: Rodada Atual -->
    <div id="rodada-atual" class="tab-content active">
        <div class="bg-gray-800 rounded-xl p-6 shadow-2xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div class="flex items-center gap-4">
                    <h2 class="text-3xl font-black italic">Rodada <?= $currentRoundNumber ?> <span class="text-red-500">///</span> <?= $selectedYear ?></h2>
                    <?php if ($currentRound && (!isset($currentRound['status']) || $currentRound['status'] !== 'closed')): ?>
                        <button type="button" onclick="shareRoundWhatsApp()" class="bg-green-600 hover:bg-green-700 text-white p-2 rounded-full shadow transition" title="Compartilhar Rodada"> <i class="fa-brands fa-whatsapp text-xl px-1"></i></button>
                    <?php endif; ?>
                </div>

                <?php if ($currentRound && isset($currentRound['status']) && $currentRound['status'] === 'closed'): ?>
                    <span class="bg-red-600 text-white px-4 py-1 rounded-full font-bold text-sm uppercase shadow-lg shadow-red-900/50">
                        🔒 Rodada Finalizada
                    </span>
                <?php else: ?>
                    <span class="bg-green-600 text-white px-4 py-1 rounded-full font-bold text-sm uppercase shadow-lg shadow-green-900/50">
                        🟢 Aberta para Envios
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($roundDeadline && (!isset($currentRound['status']) || $currentRound['status'] !== 'closed')): ?>
                <div class="bg-gray-900/50 p-6 rounded-xl mb-6 text-center border border-gray-700 relative overflow-hidden">
                    <div class="relative z-10">
                        <p class="text-gray-400 text-sm uppercase tracking-widest mb-2">Tempo restante para Pole Position</p>
                        <p id="countdown"></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($roundTracks)): ?>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                    <?php foreach ($roundTracks as $track): ?>
                        <div class="bg-gray-700 p-3 rounded-lg flex items-center gap-3 border border-gray-600">
                            <div class="bg-gray-800 w-10 h-10 rounded flex items-center justify-center font-bold text-gray-500">
                                <?= $track['id'] ?>
                            </div>
                            <div>
                                <div class="text-xs text-gray-400 uppercase"><?= htmlspecialchars($track['country']) ?></div>
                                <div class="font-bold text-sm leading-tight"><?= htmlspecialchars($track['city']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Ranking Table -->
            <div class="overflow-hidden rounded-xl border border-gray-700">
                <table class="w-full" id="roundTableData">
                    <thead class="bg-gray-900 text-gray-400 uppercase text-xs font-bold tracking-wider">
                    <tr>
                        <th class="px-6 py-4 text-left">Pos</th>
                        <th class="px-6 py-4 text-left">Piloto</th>
                        <th class="px-6 py-4 text-left">Plataforma</th>
                        <th class="px-6 py-4 text-right">Tempo Total</th>
                        <th class="px-6 py-4 text-right">Pontos</th>
                        <th class="px-6 py-4 text-center">Mídia</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700 bg-gray-800">
                    <?php if (empty($ranking)): ?>
                        <tr><td colspan="6" class="p-8 text-center text-gray-500 italic">Ainda não há tempos registrados para esta rodada.</td></tr>
                    <?php else: ?>
                        <?php foreach ($ranking as $entry): ?>
                            <tr class="hover:bg-gray-700/50 transition">
                                <td class="px-6 py-4 font-black text-xl w-16 text-center" data-pos="<?= $entry['position'] ?>">
                                    <?php if ($entry['position'] == 1) echo '<span class="text-yellow-400 drop-shadow-md">🥇</span>';
                                    elseif ($entry['position'] == 2) echo '<span class="text-gray-300 drop-shadow-md">🥈</span>';
                                    elseif ($entry['position'] == 3) echo '<span class="text-orange-400 drop-shadow-md">🥉</span>';
                                    elseif ($entry['position'] == 0) echo '<span class="text-gray-600">??</span>';
                                    else echo '<span class="text-gray-400">'.$entry['position'].'º</span>'; ?>
                                </td>
                                <td class="px-6 py-4 font-bold text-lg" data-pilot="<?= htmlspecialchars($entry['pilot_nickname']) ?>"><?= htmlspecialchars($entry['pilot_nickname']) ?></td>
                                <td class="px-6 py-4 text-sm text-gray-400"><?= htmlspecialchars($entry['platform'] ?? '-') ?></td>
                                <td class="px-6 py-4 text-right font-mono text-blue-300 font-bold" data-time="<?= $entry['total_time_readable'] ?>"><?= $entry['total_time_readable'] ?></td>
                                <td class="px-6 py-4 text-right" data-pts="<?= $entry['points'] ?>">
                                    <span class="inline-block px-3 py-1 rounded font-bold text-sm" style="background-color: <?= PointsCalculator::getColorForPoints($entry['points']) ?>; color: white;">
                                        <?= $entry['points'] ?> pts
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php if ($entry['video_url']): ?>
                                        <a href="<?= htmlspecialchars($entry['video_url']) ?>" target="_blank" class="text-red-500 hover:text-red-400 text-xl transition transform hover:scale-110 inline-block">
                                            <i class="fa-brands fa-youtube"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-600">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tab: Ranking Global -->
    <div id="ranking-global" class="tab-content">
        <div class="bg-gray-800 rounded-xl p-6 shadow-2xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-3xl font-black italic text-yellow-400">
                    <i class="fa-solid fa-trophy"></i> Ranking Geral Pole Position <span class="text-gray-500 text-xl">// <?= $selectedYear ?></span>
                </h2>
                <button type="button" onclick="shareGeneralWhatsApp()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded shadow transition font-bold flex items-center gap-2"> <i class="fa-brands fa-whatsapp text-xl"></i>Compartilhar</button>
            </div>

            <div class="overflow-x-auto rounded-xl border border-gray-700">
                <table class="w-full" id="generalTableData">
                    <thead class="bg-gray-900 text-gray-400 uppercase text-xs font-bold tracking-wider">
                    <tr>
                        <th class="px-6 py-4 text-center w-16">Pos</th>
                        <th class="px-6 py-4 text-left">Piloto</th>
                        <?php
                        $yearRoundsCols = [];
                        foreach ($seasonRanking as $pData) {
                            if(isset($pData['rounds'])) {
                                foreach (array_keys($pData['rounds']) as $rKey) {
                                    if (strpos($rKey, $selectedYear . '-') === 0) {
                                        $yearRoundsCols[$rKey] = true;
                                    }
                                }
                            }
                        }
                        ksort($yearRoundsCols);
                        foreach ($yearRoundsCols as $rKey => $v): ?>
                            <th class="px-6 py-4 text-center">Rodada <?= explode('-', $rKey)[1] ?></th>
                        <?php endforeach; ?>
                        <th class="px-6 py-4 text-center bg-gray-900/80 font-black text-white">TOTAL</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700 bg-gray-800">
                    <?php
                    $displayRanking = [];
                    foreach ($seasonRanking as $pilotName => $data) {
                        $yearTotal = 0;
                        foreach ($yearRoundsCols as $rKey => $v) {
                            $yearTotal += ($data['rounds'][$rKey] ?? 0);
                        }
                        if ($yearTotal > 0) { // Só mostra quem tem pontos no ranking geral, conforme regra 16
                            $displayRanking[] = [
                                    'name' => $pilotName,
                                    'rounds' => $data['rounds'],
                                    'total' => $yearTotal
                            ];
                        }
                    }

                    usort($displayRanking, function($a, $b) { return $b['total'] - $a['total']; });

                    $pos = 1;
                    $prevTotal = null;
                    $rankOffset = 0;
                    foreach ($displayRanking as $data):
                        if ($prevTotal === $data['total']) { $rankOffset++; }
                        else { $pos = $pos + $rankOffset; $rankOffset = 0; }
                        $prevTotal = $data['total'];
                        ?>
                        <tr class="hover:bg-gray-700/50 transition general-row" data-pos="<?= $pos ?>" data-pilot="<?= htmlspecialchars($data['name']) ?>" data-pts="<?= $data['total'] ?>">
                            <td class="px-6 py-4 text-center text-xl">
                                <?php if ($pos == 1) echo '🥇';
                                elseif ($pos == 2) echo '🥈';
                                elseif ($pos == 3) echo '🥉';
                                else echo '<span class="text-gray-600 font-bold">'.$pos.'</span>'; ?>
                            </td>
                            <td class="px-6 py-4 font-bold text-lg text-white"><?= htmlspecialchars($data['name']) ?></td>
                            <?php foreach ($yearRoundsCols as $rKey => $v):
                                $pts = $data['rounds'][$rKey] ?? 0;
                                ?>
                                <td class="px-6 py-4 text-center font-mono">
                                    <?php if ($pts > 0): ?>
                                        <span class="px-2 py-1 rounded text-xs font-bold" style="background-color: <?= PointsCalculator::getColorForPoints($pts) ?>;">
                                            <?= $pts ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-600">-</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="px-6 py-4 text-center font-black text-xl text-yellow-400 bg-gray-700/30">
                                <?= $data['total'] ?>
                            </td>
                        </tr>
                        <?php $pos++; endforeach; ?>
                    <?php if (empty($displayRanking)): ?>
                        <tr><td colspan="<?= count($yearRoundsCols) + 3 ?>" class="p-8 text-center text-gray-500">Nenhum piloto com pontos nesta temporada ainda.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tab: Hall da Fama -->
    <div id="hall-fama" class="tab-content">
        <div class="bg-gray-800 rounded-xl p-6 shadow-2xl border border-gray-700">
            <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4 bg-gray-900 p-4 rounded-lg border border-gray-700">
                <h2 class="text-2xl font-black italic text-blue-400"><i class="fa-solid fa-star"></i> Hall da Fama</h2>

                <div class="platform-selector flex gap-4" id="hofFilters">
                    <label class="flex items-center gap-2 cursor-pointer hover:text-white text-gray-300 select-none">
                        <input type="checkbox" value="ZSNES" checked class="accent-red-500 w-4 h-4"> <i class="fa-solid fa-desktop"></i>ZSNES
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:text-white text-gray-300 select-none">
                        <input type="checkbox" value="Console" checked class="accent-purple-500 w-4 h-4"> <i class="fa-solid fa-gamepad"></i>Console
                    </label>
                    <div class="w-px bg-gray-600 mx-2"></div>
                    <label class="flex items-center gap-2 cursor-pointer hover:text-white text-gray-300 select-none">
                        <input type="checkbox" value="Pole Position" checked class="accent-blue-500 w-4 h-4"> <i class="fa-solid fa-stopwatch"></i>Pole P.
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:text-white text-gray-300 select-none">
                        <input type="checkbox" value="Versus" checked class="accent-white-500 w-4 h-4"> <i class="fa-solid fa-hand-fist"></i>Versus
                    </label>
                </div>

                <div class="relative w-full md:w-64">
                    <i class="fa-solid fa-search absolute left-3 top-3 text-gray-500"></i>
                    <label for="hofSearch"></label>
                    <input type="text" id="hofSearch" placeholder="Filtrar piloto, pista..." class="w-full bg-gray-800 border border-gray-600 rounded-full pl-10 pr-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>

            <?php if (empty($hallOfFameData)): ?>
                <div class="text-center py-12 text-gray-400">
                    <p class="text-xl">Nenhum recorde registrado ainda.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="hofGrid">
                    <?php foreach ($hallOfFameData as $trackId => $data): ?>
                        <div class="hof-card bg-gray-750 rounded-lg overflow-hidden border border-gray-600 shadow-lg relative group"
                             data-search="<?= strtolower($data['info']['track_city'] . ' ' . $data['info']['track_country']) ?>">

                            <div class="bg-gradient-to-r from-gray-800 to-gray-700 p-3 border-b border-gray-600 flex justify-between items-center">
                                <div>
                                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider"><?= htmlspecialchars($data['info']['track_country']) ?></span>
                                    <h3 class="font-bold text-lg text-white leading-none"><?= htmlspecialchars($data['info']['track_city']) ?></h3>
                                </div>
                                <span class="text-2xl font-black text-gray-600">#<?= $trackId ?></span>
                            </div>

                            <div class="p-4 space-y-4">
                                <?php foreach ($data['records'] as $mode => $platforms): ?>
                                    <?php foreach ($platforms as $platform => $records):
                                        if (empty($records)) continue;
                                        ?>
                                        <div class="hof-entry" data-mode="<?= $mode ?>" data-platform="<?= $platform ?>">
                                            <div class="flex items-center gap-2 mb-2">
                                                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded
                                                    <?= $mode === 'Versus' ? 'bg-purple-900 text-purple-200' : 'bg-green-900 text-green-200' ?>">
                                                    <?= $mode ?>
                                                </span>
                                                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-gray-700 text-gray-300 border border-gray-600">
                                                    <?= $platform ?>
                                                </span>
                                            </div>

                                            <div class="space-y-2">
                                                <?php foreach ($records as $idx => $rec):
                                                    $medalClass = ($idx === 0) ? 'medal-1' : (($idx === 1) ? 'medal-2' : 'medal-3');
                                                    ?>
                                                    <div class="flex items-center justify-between bg-gray-900/50 p-2 rounded border border-gray-700/50" data-pilot="<?= strtolower($rec['pilot']) ?>" data-car="<?= strtolower($rec['car']) ?>">
                                                        <div class="flex items-center gap-3">
                                                            <i class="fa-solid fa-medal <?= $medalClass ?>"></i>
                                                            <div>
                                                                <div class="text-white hof-nickname leading-tight"><?= htmlspecialchars($rec['pilot']) ?></div>
                                                                <div class="text-[10px] text-gray-400"><?= htmlspecialchars($rec['car']) ?></div>
                                                            </div>
                                                        </div>
                                                        <div class="text-right">
                                                            <div class="font-mono font-bold text-yellow-50 text-sm"><?= TimeConverter::toReadable($rec['time_ms']) ?></div>
                                                            <?php if ($rec['video']): ?>
                                                                <a href="<?= htmlspecialchars($rec['video']) ?>" target="_blank" class="text-red-500 hover:text-white text-[10px]"><i class="fa-brands fa-youtube"></i></a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab: Todas as Submissões -->
    <div id="submissoes" class="tab-content">
        <div class="bg-gray-800 rounded-xl p-6 shadow-2xl border border-gray-700">

            <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4 bg-gray-900 p-4 rounded-lg border border-gray-700">
                <h2 class="text-2xl font-black italic text-gray-300"><i class="fa-solid fa-list"></i> Todas as Submissões</h2>

                <div class="platform-selector flex gap-4" id="subFilters">
                    <label class="flex items-center gap-2 cursor-pointer hover:text-white text-gray-300 select-none">
                        <input type="checkbox" value="ZSNES" checked class="accent-red-500 w-4 h-4"> <i class="fa-solid fa-desktop"></i>ZSNES
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:text-white text-gray-300 select-none">
                        <input type="checkbox" value="Console" checked class="accent-purple-500 w-4 h-4"> <i class="fa-solid fa-gamepad"></i>Console
                    </label>
                    <div class="w-px bg-gray-600 mx-2"></div>
                    <label class="flex items-center gap-2 cursor-pointer hover:text-white text-gray-300 select-none">
                        <input type="checkbox" value="Pole Position" checked class="accent-blue-500 w-4 h-4"> <i class="fa-solid fa-stopwatch"></i>Pole P.
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:text-white text-gray-300 select-none">
                        <input type="checkbox" value="Versus" checked class="accent-white-500 w-4 h-4"> <i class="fa-solid fa-hand-fist"></i>Versus
                    </label>
                </div>

                <div class="relative w-full md:w-64">
                    <i class="fa-solid fa-search absolute left-3 top-3 text-gray-500"></i>
                    <input type="text" id="subSearch" placeholder="Filtrar submissões..."
                           class="w-full bg-gray-800 border border-gray-600 rounded-full pl-10 pr-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>

            <?php
            $allSubmissions = $submissionManager->getAllSubmissions();
            $allSubmissions = array_reverse($allSubmissions);
            if (empty($allSubmissions)):
                ?>
                <div class="text-center py-12 text-gray-500">
                    <p class="text-xl">Nenhuma submissão registrada.</p>
                </div>
            <?php else: ?>
                <div class="space-y-4" id="subGrid">
                    <?php foreach ($allSubmissions as $sub):
                        $isVersus = ($sub['mode'] ?? 'Pole Position') === 'Versus';
                        $roundLabel = $isVersus ? 'VERSUS' : "RODADA {$sub['round']}";
                        $roundColor = $isVersus ? 'bg-purple-600' : 'bg-red-600';
                        $platform = $sub['platform'] ?? 'Console';
                        $mode = $sub['mode'] ?? 'Pole Position';
                        ?>
                        <div class="sub-entry bg-gray-700 p-4 rounded-lg <?= !$sub['status']['is_valid'] ? 'border-2 border-red-500 opacity-75' : 'hover:bg-gray-600 transition' ?>"
                             data-mode="<?= $mode ?>" data-platform="<?= $platform ?>" data-search="<?= strtolower($sub['pilot_nickname'] . ' ' . $sub['submission_id'] . ' ' . $roundLabel) ?>">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <div class="flex items-center gap-3 flex-wrap">
                                    <span class="<?= $roundColor ?> px-3 py-1 rounded-full font-bold text-xs uppercase shadow">
                                        <?= $roundLabel ?> • <?= $sub['year'] ?>
                                    </span>
                                        <h3 class="font-bold text-lg text-white">
                                            <?= htmlspecialchars($sub['pilot_nickname']) ?>
                                        </h3>
                                        <span class="text-xs bg-gray-900 px-2 py-1 rounded text-gray-300 border border-gray-700 font-mono">
                                        <?= htmlspecialchars($platform) ?>
                                    </span>
                                        <?php if ($sub['video_url']): ?>
                                            <a href="<?= htmlspecialchars($sub['video_url']) ?>" target="_blank" class="text-red-500 hover:text-red-400 text-lg ml-2" title="Assistir Vídeo">
                                                <i class="fa-brands fa-youtube"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!$sub['status']['is_valid']): ?>
                                            <span class="tooltip">
                                        <span class="text-red-500 font-bold"><i class="fa-solid fa-ban"></i> INVALIDADO</span>
                                        <span class="tooltiptext"><?= htmlspecialchars($sub['status']['reason']) ?></span>
                                    </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1 font-mono">ID: <?= $sub['submission_id'] ?></p>
                                    <?php if (!empty($sub['comments'])): ?>
                                        <div class="mt-2 text-sm text-gray-300 italic border-l-2 border-gray-500 pl-2">
                                            "<?= htmlspecialchars($sub['comments']) ?>"
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="text-right">
                                    <?php if (!$isVersus): ?>
                                        <p class="text-2xl font-mono font-bold text-white"><?= TimeConverter::toReadable($sub['total_round_ms']) ?></p>
                                    <?php endif; ?>
                                    <p class="text-[10px] text-gray-400"><?= date('d/m/Y H:i', strtotime($sub['submitted_at'])) ?></p>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-3">
                                <?php foreach ($sub['tracks'] as $track): ?>
                                    <div class="bg-gray-800/50 px-3 py-2 rounded text-sm border border-gray-700/50 sub-track" data-search="<?= strtolower($track['track_city'] ?? '') ?>">
                                        <span class="font-bold text-gray-300 block text-xs uppercase"><?= htmlspecialchars($track['track_city'] ?? 'Pista ' . $track['track_id']) ?></span>
                                        <span class="font-mono text-blue-300 font-bold"><?= TimeConverter::toReadable($track['time_ms']) ?></span>
                                        <?php if ($isVersus): ?>
                                            <span class="text-[10px] text-gray-500 block"><?= $track['car_name'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- UI Comum de Validação PIN para Submissões -->
    <div id="pinValidationUI" class="hidden">
        <div class="bg-gray-800 rounded-xl p-8 max-w-md mx-auto shadow-2xl border border-gray-700 text-center">
            <h3 class="text-xl font-bold mb-4 text-white">🔒 Verificação de PIN</h3>
            <p class="text-gray-400 mb-6 text-sm">Selecione seu piloto e valide o PIN para acessar as áreas de submissão. (Válido por 12h)</p>

            <div class="space-y-4">
                <select id="authPhoneID" class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-3 focus:ring-2 focus:ring-blue-500 outline-none text-white">
                    <option value="">Selecione seu piloto...</option>
                    <?php foreach ($allPilots as $pilot): if ($pilot['activePilot']): ?>
                        <option value="<?= htmlspecialchars($pilot['phoneNumberID']) ?>"><?= htmlspecialchars($pilot['nicknameTGC']) ?></option>
                    <?php endif; endforeach; ?>
                </select>

                <input type="password" id="authPinCode" maxlength="6" minlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" required placeholder="PIN (6 dígitos)"
                       class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-3 tracking-widest text-center text-white font-mono text-xl focus:ring-2 focus:ring-blue-500 outline-none">

                <button onclick="validateGlobalPIN()" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg shadow transition" id="btnValidatePin">
                    Validar Acesso
                </button>
                <div id="authErrorMsg" class="text-red-500 text-sm font-bold mt-2 hidden"></div>
            </div>
        </div>
    </div>

    <!-- Wrappers que serão bloqueados/desbloqueados pelo JS -->
    <div id="secureFormsWrapper">
        <!-- Tab: Submeter Pole Position -->
        <div id="submeter-pole" class="tab-content">
            <div class="bg-gray-800 rounded-xl p-6 shadow-2xl border border-gray-700">
                <h2 class="text-3xl font-black italic mb-6 text-green-400">➕ Submeter Pole Position</h2>

                <?php if ($currentRound && isset($currentRound['status']) && $currentRound['status'] === 'closed'): ?>
                    <div class="bg-red-900/50 border border-red-500 text-red-100 p-8 rounded-xl text-center">
                        <i class="fa-solid fa-lock text-4xl mb-4"></i>
                        <h3 class="text-2xl font-bold">Rodada Fechada</h3>
                        <p>Os envios para esta rodada estão encerrados. Aguarde o início da próxima.</p>
                    </div>
                <?php else: ?>
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="submit_times">
                        <input type="hidden" name="phoneNumberID" class="authPhoneIDHidden">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-900 p-4 rounded-lg">
                            <div class="col-span-1 md:col-span-2">
                                <p class="text-gray-300 font-bold mb-2">Piloto Autenticado: <span class="authPilotNameDisplay text-green-400"></span></p>
                            </div>
                            <div class="col-span-1 md:col-span-2">
                                <label class="block mb-2 font-bold text-gray-300">Plataforma</label>
                                <div class="platform-selector flex gap-4">
                                    <div>
                                        <input type="radio" id="pp_zsnes" name="platform" value="ZSNES" checked>
                                        <label for="pp_zsnes"><i class="fa-solid fa-desktop"></i> ZSNES</label>
                                    </div>
                                    <div>
                                        <input type="radio" id="pp_console" name="platform" value="Console" >
                                        <label for="pp_console"><i class="fa-solid fa-gamepad"></i> Console</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block mb-2 font-bold text-gray-300">Link do Vídeo (Obrigatório)</label>
                            <input type="url" name="video_url" required
                                   class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-3 text-blue-400"
                                   placeholder="https://youtube.com/...">
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xl font-bold text-gray-300">Tempos das 8 Pistas</h3>
                                <span class="text-sm text-gray-500">Rodada #<?= $currentRoundNumber ?></span>
                            </div>

                            <?php if (empty($roundTracks)): ?>
                                <div class="bg-yellow-900/50 border border-yellow-600 p-4 rounded text-yellow-200">
                                    ⚠️ Nenhuma rodada ativa. Peça ao Admin para sortear.
                                </div>
                            <?php else: ?>
                                <div class="grid grid-cols-1 gap-4">
                                    <?php for ($i = 1; $i <= 8; $i++): $track = $roundTracks[$i - 1]; ?>
                                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 bg-gray-800 p-4 rounded-lg border border-gray-700 items-center">
                                            <div class="md:col-span-4">
                                                <div class="text-xs text-gray-500 uppercase font-bold"><?= htmlspecialchars($track['country']) ?></div>
                                                <div class="font-bold text-white"><?= htmlspecialchars($track['city']) ?></div>
                                                <input type="hidden" name="track_<?= $i ?>_id" value="<?= $track['id'] ?>">
                                            </div>
                                            <div class="md:col-span-4">
                                                <input type="text" name="track_<?= $i ?>_time"
                                                       pattern="[0-9]{2}:[0-9]{2}:[0-9]{2}" placeholder="00:00:00" required
                                                       class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-2 font-mono text-lg text-center focus:border-green-500 outline-none"
                                                       oninput="formatTime(this)">
                                            </div>
                                            <div class="md:col-span-4">
                                                <select name="track_<?= $i ?>_car" required
                                                        class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-2 text-sm"
                                                        onchange="setCarColor(this, <?= $i ?>)">
                                                    <option value="">Carro...</option>
                                                    <option value="Cannibal" data-color="red">🔴 Cannibal</option>
                                                    <option value="Razor" data-color="purple">🟣 Razor</option>
                                                    <option value="Weasel" data-color="blue">🔵 Weasel</option>
                                                    <option value="Sidewinder" data-color="white">⚪ Sidewinder</option>
                                                </select>
                                                <input type="hidden" name="track_<?= $i ?>_color" id="track_<?= $i ?>_color">
                                            </div>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label class="block mb-2 font-bold text-gray-300">Observações</label>
                            <textarea name="comments" rows="2"
                                      class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-3 text-gray-300 focus:ring-2 focus:ring-green-500 outline-none placeholder-gray-600 transition-opacity"
                                      placeholder="Estimado piloto, indique o tempo do vídeo que começa o record, bem como outras observações importantes se houver"
                                      onfocus="this.placeholder = ''"
                                      onblur="this.placeholder = 'Estimado piloto, indique o tempo do vídeo que começa o record, bem como outras observações importantes se houver'"></textarea>
                        </div>

                        <?php if (!empty($roundTracks)): ?>
                            <button type="submit" class="w-full bg-gradient-to-r from-green-600 to-green-800 hover:from-green-700 hover:to-green-900 text-white font-black py-4 px-6 rounded-lg text-xl shadow-lg transform transition hover:-translate-y-1">
                                🚀 ENVIAR TEMPOS
                            </button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab: Submeter Versus -->
        <div id="submeter-versus" class="tab-content">
            <div class="bg-gray-800 rounded-xl p-6 shadow-2xl border border-gray-700">
                <h2 class="text-3xl font-black italic mb-6 text-purple-400">⚔️ Submeter Recorde Versus</h2>

                <form method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="submit_versus">
                    <input type="hidden" name="phoneNumberID" class="authPhoneIDHidden">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-900 p-4 rounded-lg">
                        <div class="col-span-1 md:col-span-2">
                            <p class="text-gray-300 font-bold mb-2">Piloto Autenticado: <span class="authPilotNameDisplay text-purple-400"></span></p>
                        </div>
                        <div class="col-span-1 md:col-span-2">
                            <label class="block mb-2 font-bold text-gray-300">Plataforma</label>
                            <div class="platform-selector flex gap-4">
                                <div>
                                    <input type="radio" id="vs_zsnes" name="platform" value="ZSNES" checked>
                                    <label for="vs_zsnes"><i class="fa-solid fa-desktop"></i> ZSNES</label>
                                </div>
                                <div>
                                    <input type="radio" id="vs_console" name="platform" value="Console">
                                    <label for="vs_console"><i class="fa-solid fa-gamepad"></i> Console</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block mb-2 font-bold text-gray-300">Vídeo</label>
                        <input type="url" name="video_url" required
                               class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-3 text-blue-400"
                               placeholder="https://youtube.com/...">
                    </div>

                    <div class="bg-gray-700 p-6 rounded-lg space-y-4 border border-gray-600">
                        <h3 class="font-bold text-lg mb-2 text-white">Dados do Recorde</h3>

                        <div>
                            <label class="block mb-2 font-semibold text-gray-300">Pista (1-32)</label>
                            <select name="track_id" required class="w-full bg-gray-600 border border-gray-500 rounded px-4 py-3 focus:ring-2 focus:ring-purple-500">
                                <option value="">Selecione a pista...</option>
                                <?php foreach ($completeTrackList as $t): ?>
                                    <option value="<?= $t['id'] ?>">
                                        <?= $t['id'] ?>. <?= $t['city'] ?> (<?= $t['country'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block mb-2 font-semibold text-gray-300">Tempo</label>
                                <input type="text" name="time"
                                       pattern="[0-9]{2}:[0-9]{2}:[0-9]{2}" placeholder="00:00:00" required
                                       class="w-full bg-gray-600 border border-gray-500 rounded px-4 py-3 font-mono text-lg text-center"
                                       oninput="formatTime(this)">
                            </div>
                            <div>
                                <label class="block mb-2 font-semibold text-gray-300">Carro</label>
                                <select name="car" required class="w-full bg-gray-600 border border-gray-500 rounded px-4 py-3">
                                    <option value="">Selecione...</option>
                                    <option value="Cannibal">🔴 Cannibal</option>
                                    <option value="Razor">🟣 Razor</option>
                                    <option value="Weasel">🔵 Weasel</option>
                                    <option value="Sidewinder">⚪ Sidewinder</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block mb-2 font-bold text-gray-300">Observações</label>
                        <textarea name="comments" rows="2"
                                  class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-3 text-gray-300 focus:ring-2 focus:ring-purple-500 outline-none placeholder-gray-600 transition-opacity"
                                  placeholder="Estimado piloto, indique o tempo do vídeo que começa o record, bem como outras observações importantes se houver"
                                  onfocus="this.placeholder = ''"
                                  onblur="this.placeholder = 'Estimado piloto, indique o tempo do vídeo que começa o record, bem como outras observações importantes se houver'"></textarea>
                    </div>

                    <button type="submit" class="w-full bg-gradient-to-r from-purple-600 to-purple-800 hover:from-purple-700 hover:to-purple-900 text-white font-black py-4 px-6 rounded-lg text-xl shadow-lg transform transition hover:-translate-y-1">
                        ⚔️ REGISTRAR RECORD VERSUS
                    </button>
                </form>
            </div>
        </div>
    </div> <!-- /secureFormsWrapper -->

    <!-- Tab: Cadastro e reset PIN -->
    <div id="cadastro-piloto" class="tab-content">
        <div class="bg-gray-800 rounded-xl p-6 mb-8 border border-gray-700 shadow-xl">
            <h2 class="text-3xl font-black italic mb-4 flex items-center gap-2 text-blue-400">
                <i class="fa-solid fa-user-plus"></i> Cadastro e reset PIN
            </h2>
            <p class="text-gray-300 mb-4">Selecione um piloto ativo para enviar um novo PIN ao e-mail cadastrado. Os e-mails são exibidos parcialmente mascarados.</p>

            <form id="requestPinForm" method="POST" class="mt-4 pt-4 border-t border-gray-700">
                <input type="hidden" name="action" value="request_new_pin">
                <div class="flex flex-col gap-3">
                    <p class="text-yellow-400 text-sm mb-2"><i class="fa-solid fa-triangle-exclamation"></i> O PIN será enviado exclusivamente ao endereço salvo no cadastro.</p>
                    <select id="requestPinPilot" name="pilot_id" required class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-2">
                        <option value="">Selecione seu nome...</option>
                        <?php foreach ($resetPilots as $pilot): ?>
                            <option value="<?= (int) $pilot['id'] ?>"><?= htmlspecialchars((string) $pilot['name'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars((string) $pilot['nicknameTGC'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars(maskEmailForDisplay(trim((string) $pilot['email'])), ENT_QUOTES, 'UTF-8') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <div class="flex gap-3">
                        <button id="requestPinSubmit" type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-6 rounded transition disabled:opacity-50 disabled:cursor-not-allowed">Enviar novo PIN para o e-mail cadastrado</button>
                        <button type="button" onclick="hideRequestPinForm()" class="text-gray-400 hover:text-white px-4">Cancelar</button>
                    </div>
                </div>
            </form>
            <div class="mt-6 border-t border-gray-700 pt-5">
                <p class="text-gray-300 mb-3">Não encontrou seu nome e ainda não é um piloto cadastrado? Solicite um novo cadastro para análise dos administradores.</p>
                <button type="button" onclick="openRegistrationPopup()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded transition">Não encontrei meu nome — novo cadastro</button>
            </div>
        </div>
    </div>

    <div id="registrationPopup" class="popup" role="dialog" aria-modal="true" aria-labelledby="registrationTitle">
        <div class="popup-content text-left">
            <button type="button" class="close-popup" aria-label="Fechar" onclick="closePopup('registrationPopup')">&times;</button>
            <h2 id="registrationTitle" class="text-2xl font-bold mb-3 text-white">Solicitar cadastro de piloto</h2>
            <p class="text-gray-300 mb-4">O cadastro ficará pendente até ser analisado por um administrador. Nenhum PIN será gerado nesta etapa.</p>
            <form method="POST" class="space-y-3">
                <input type="hidden" name="action" value="request_pilot_registration">
                <label class="block text-sm">Nome real
                    <input name="name" type="text" required maxlength="120" autocomplete="name" class="mt-1 w-full bg-gray-900 border border-gray-600 rounded px-4 py-2">
                </label>
                <label class="block text-sm">Nickname TGC
                    <input name="nicknameTGC" type="text" required maxlength="60" class="mt-1 w-full bg-gray-900 border border-gray-600 rounded px-4 py-2">
                </label>
                <label class="block text-sm">Telefone com DDI
                    <input name="phoneNumberID" type="tel" required inputmode="numeric" pattern="[0-9]{8,15}" placeholder="5511999999999" class="mt-1 w-full bg-gray-900 border border-gray-600 rounded px-4 py-2">
                </label>
                <label class="block text-sm">E-mail
                    <input name="email" type="email" required maxlength="254" autocomplete="email" class="mt-1 w-full bg-gray-900 border border-gray-600 rounded px-4 py-2">
                </label>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-5 rounded">Enviar solicitação</button>
                    <button type="button" onclick="closePopup('registrationPopup')" class="text-gray-300 px-4">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tab: Admin -->
    <div id="admin" class="tab-content">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
            <h2 class="text-3xl font-black mb-6 text-gray-400"><i class="fa-solid fa-lock"></i> Painel Administrativo</h2>

            <?php if (!$adminAuthenticated): ?>
                <form method="POST" class="max-w-md space-y-4">
                    <input type="hidden" name="action" value="admin_login">
                    <input type="hidden" name="admin_csrf" value="<?= htmlspecialchars($adminCsrf, ENT_QUOTES, 'UTF-8') ?>">
                    <label class="block text-gray-300">Senha administrativa
                        <input type="password" name="admin_password" required autocomplete="current-password" class="mt-1 w-full bg-gray-600 rounded px-3 py-2">
                    </label>
                    <button class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded">Entrar</button>
                </form>
            <?php else: ?>
            <form method="POST" class="mb-6 text-right">
                <input type="hidden" name="action" value="admin_logout">
                <input type="hidden" name="admin_csrf" value="<?= htmlspecialchars($adminCsrf, ENT_QUOTES, 'UTF-8') ?>">
                <button class="text-sm text-gray-300 hover:text-white underline">Encerrar sessão administrativa</button>
            </form>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Sorteio -->
                <div class="bg-gray-700 p-6 rounded-lg border border-gray-600">
                    <h3 class="text-xl font-bold mb-4 text-white">🎲 Sortear Rodada - Pole Position</h3>

                    <?php if ($pendingDraw): ?>
                        <div class="bg-red-900/80 p-4 rounded mb-4 text-center border border-red-500 animate-pulse">
                            <p class="font-bold">⚠️ CONFIRMAÇÃO NECESSÁRIA</p>
                            <p class="text-sm">Isso APAGARÁ todos os dados da rodada <?= $pendingDraw['round'] ?>!</p>
                            <form method="POST" class="mt-2">
                                <input type="hidden" name="action" value="admin_draw_tracks">
                                <input type="hidden" name="admin_csrf" value="<?= htmlspecialchars($adminCsrf, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="year" value="<?= $pendingDraw['year'] ?>">
                                <input type="hidden" name="round_number" value="<?= $pendingDraw['round'] ?>">
                                <input type="hidden" name="force_redraw" value="yes">
                                <button class="bg-red-600 text-white px-4 py-2 rounded font-bold hover:bg-red-500">CONFIRMAR REDRAW</button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="admin_draw_tracks">
                        <input type="hidden" name="admin_csrf" value="<?= htmlspecialchars($adminCsrf, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="flex gap-2">
                            <select name="year" class="bg-gray-600 rounded px-3 py-2 flex-1"><option value="2026">2026</option><option value="2025">2025</option></select>
                            <input type="number" name="round_number" placeholder="Rodada #" min="1" class="bg-gray-600 rounded px-3 py-2 w-24">
                        </div>
                        <button class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 rounded">Sortear Pistas</button>
                    </form>
                </div>

                <!-- Finalizar -->
                <div class="bg-gray-700 p-6 rounded-lg border border-gray-600 relative overflow-hidden">
                    <div class="absolute top-0 right-0 p-2 opacity-10"><i class="fa-solid fa-flag-checkered text-9xl"></i></div>
                    <h3 class="text-xl font-bold mb-4 text-white">🏁 Finalizar Rodada - Pole Position</h3>
                    <p class="text-gray-300 text-sm mb-4">Calcula pontos, atualiza ranking global e encerra envios.</p>

                    <form method="POST" class="space-y-4 relative z-10" onsubmit="return confirm('ATENÇÃO: Isso encerrará a rodada permanentemente e calculará os pontos. Continuar?');">
                        <input type="hidden" name="action" value="admin_finalize_round">
                        <input type="hidden" name="admin_csrf" value="<?= htmlspecialchars($adminCsrf, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="flex gap-2">
                            <select name="year" class="bg-gray-600 rounded px-3 py-2 flex-1"><option value="2026">2026</option></select>
                            <input type="number" name="round_number" placeholder="Rodada #" min="1" class="bg-gray-600 rounded px-3 py-2 w-24">
                        </div>
                        <button class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 rounded shadow-lg">
                            ENCERRAR RODADA & DISTRIBUIR PONTOS
                        </button>
                    </form>
                </div>

                <!-- Prazo -->
                <div class="bg-gray-700 p-6 rounded-lg border border-gray-600">
                    <h3 class="text-xl font-bold mb-4 text-white">⏰ Definir Prazo - Pole Position</h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="admin_set_deadline">
                        <input type="hidden" name="admin_csrf" value="<?= htmlspecialchars($adminCsrf, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="flex gap-2">
                            <select name="year" class="bg-gray-600 rounded px-3 py-2 w-24">
                                <option value="2026">2026</option>
                                <option value="2025">2025</option>
                            </select>
                            <input type="number" name="round_number" placeholder="Rodada #" min="1" class="bg-gray-600 rounded px-3 py-2 w-20">
                            <input type="date" name="deadline_date" required class="bg-gray-600 rounded px-3 py-2 flex-1">
                        </div>
                        <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded">Salvar Prazo</button>
                    </form>
                </div>

                <!-- Invalidação -->
                <div class="bg-gray-700 p-6 rounded-lg border border-gray-600">
                    <h3 class="text-xl font-bold mb-4 text-white">❌ Moderação - Invalidar Tempos</h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="admin_invalidate">
                        <input type="hidden" name="admin_csrf" value="<?= htmlspecialchars($adminCsrf, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="text" name="submission_id" placeholder="ID da Submissão" required class="w-full bg-gray-600 rounded px-3 py-2">
                        <input type="text" name="reason" placeholder="Motivo" required class="w-full bg-gray-600 rounded px-3 py-2">
                        <button class="w-full bg-orange-600 hover:bg-orange-700 text-white font-bold py-2 rounded">Invalidar</button>
                    </form>
                </div>

                <!-- Gestão de pilotos -->
                <div class="md:col-span-2 bg-gray-700 p-6 rounded-lg border border-gray-600">
                    <h3 class="text-xl font-bold mb-5 text-white">Gestão de pilotos</h3>
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                        <section>
                            <h4 class="font-bold text-green-300 mb-3">Pilotos para ativar</h4>
                            <?php if (!$pilotsToActivate): ?>
                                <p class="text-sm text-gray-300">Não há pilotos pendentes.</p>
                            <?php else: ?>
                                <div class="space-y-3">
                                    <?php foreach ($pilotsToActivate as $pilot): ?>
                                        <div class="bg-gray-800 p-3 rounded flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                                            <div class="text-sm text-gray-200">
                                                <strong>ID <?= (int) ($pilot['id'] ?? 0) ?></strong> —
                                                <?= htmlspecialchars((string) ($pilot['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?> /
                                                <?= htmlspecialchars((string) ($pilot['nicknameTGC'] ?? ''), ENT_QUOTES, 'UTF-8') ?><br>
                                                Tel: <?= htmlspecialchars((string) ($pilot['phoneNumberID'] ?? ''), ENT_QUOTES, 'UTF-8') ?> ·
                                                E-mail: <?= htmlspecialchars((string) ($pilot['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                            <button type="button"
                                                data-pilot-id="<?= (int) ($pilot['id'] ?? 0) ?>"
                                                data-pilot-name="<?= htmlspecialchars((string) ($pilot['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-pilot-nickname="<?= htmlspecialchars((string) ($pilot['nicknameTGC'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-pilot-phone="<?= htmlspecialchars((string) ($pilot['phoneNumberID'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-pilot-email="<?= htmlspecialchars((string) ($pilot['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                onclick="openAdminPilotEditor(this)"
                                                class="bg-green-700 hover:bg-green-600 text-white font-bold px-4 py-2 rounded">Revisar / editar</button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>
                        <section>
                            <h4 class="font-bold text-yellow-300 mb-3">Pilotos para desativar</h4>
                            <?php if (!$pilotsToDeactivate): ?>
                                <p class="text-sm text-gray-300">Não há pilotos ativos.</p>
                            <?php else: ?>
                                <div class="space-y-3">
                                    <?php foreach ($pilotsToDeactivate as $pilot): ?>
                                        <div class="bg-gray-800 p-3 rounded flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                                            <div class="text-sm text-gray-200">
                                                <strong>ID <?= (int) ($pilot['id'] ?? 0) ?></strong> —
                                                <?= htmlspecialchars((string) ($pilot['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?> /
                                                <?= htmlspecialchars((string) ($pilot['nicknameTGC'] ?? ''), ENT_QUOTES, 'UTF-8') ?><br>
                                                Tel: <?= htmlspecialchars((string) ($pilot['phoneNumberID'] ?? ''), ENT_QUOTES, 'UTF-8') ?> ·
                                                E-mail: <?= htmlspecialchars((string) ($pilot['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                            <form method="POST" class="flex gap-2 items-center">
                                                <input type="hidden" name="action" value="admin_manage_pilot">
                                                <input type="hidden" name="admin_csrf" value="<?= htmlspecialchars($adminCsrf, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="pilot_id" value="<?= (int) ($pilot['id'] ?? 0) ?>">
                                                <input type="hidden" name="pilot_operation" value="deactivate">
                                                <button class="bg-yellow-700 hover:bg-yellow-600 text-white font-bold px-4 py-2 rounded">Desativar</button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>
                    </div>
                </div>
            </div>
            <div id="adminPilotEditPopup" class="popup" role="dialog" aria-modal="true" aria-labelledby="adminPilotEditTitle">
                <div class="popup-content text-left">
                    <button type="button" class="close-popup" aria-label="Fechar" onclick="closePopup('adminPilotEditPopup')">&times;</button>
                    <h3 id="adminPilotEditTitle" class="text-2xl font-bold mb-3">Revisar cadastro pendente</h3>
                    <p class="text-gray-300 mb-4">Revise e corrija os dados. Você pode salvar mantendo pendente ou salvar e ativar, o que gera e envia o primeiro PIN para o e-mail abaixo.</p>
                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="admin_manage_pilot">
                        <input type="hidden" name="admin_csrf" value="<?= htmlspecialchars($adminCsrf, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" id="adminPilotEditId" name="pilot_id">
                        <label class="block text-sm">Nome real
                            <input id="adminPilotEditName" name="name" type="text" required maxlength="120" class="mt-1 w-full bg-gray-900 border border-gray-600 rounded px-4 py-2">
                        </label>
                        <label class="block text-sm">Nickname TGC
                            <input id="adminPilotEditNickname" name="nicknameTGC" type="text" required maxlength="60" class="mt-1 w-full bg-gray-900 border border-gray-600 rounded px-4 py-2">
                        </label>
                        <label class="block text-sm">Telefone com DDI
                            <input id="adminPilotEditPhone" name="phoneNumberID" type="tel" required inputmode="numeric" pattern="[0-9]{8,15}" class="mt-1 w-full bg-gray-900 border border-gray-600 rounded px-4 py-2">
                        </label>
                        <label class="block text-sm">E-mail para receber o PIN
                            <input id="adminPilotEditEmail" name="email" type="email" required maxlength="254" class="mt-1 w-full bg-gray-900 border border-gray-600 rounded px-4 py-2">
                        </label>
                        <div class="flex flex-wrap gap-3 pt-2">
                            <button type="submit" name="pilot_operation" value="edit_pending" class="bg-blue-700 hover:bg-blue-600 text-white font-bold px-4 py-2 rounded">Salvar mantendo pendente</button>
                            <button type="submit" name="pilot_operation" value="edit_and_activate" class="bg-green-700 hover:bg-green-600 text-white font-bold px-4 py-2 rounded">Salvar e ativar</button>
                            <button type="button" onclick="closePopup('adminPilotEditPopup')" class="text-gray-300 px-4">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<footer class="bg-gray-900 border-t border-gray-800 py-8 mt-12">
    <div class="container mx-auto px-4 text-center text-gray-500">
        <p class="font-bold">TOP GEAR CHAMPIONSHIPS &copy; <?= date('Y') ?></p>
        <p class="text-sm">Powered by PHP & Tailwind - Coded by Leomarx Games</p>
    </div>
</footer>

<script>
    let activeTabId = 'rodada-atual';

    function switchTab(tabId) {
        if (tabId === 'submeter-pole' || tabId === 'submeter-versus') {
            if (!checkPinCache()) {
                showPinValidationUI(tabId);
                return;
            } else {
                populateAuthForms();
            }
        } else {
            hidePinValidationUI();
        }

        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));

        const el = document.getElementById(tabId);
        if (el) el.classList.add('active');

        const btn = document.querySelector(`button[onclick="switchTab('${tabId}')"]`);
        if (btn) btn.classList.add('active');

        activeTabId = tabId;
    }

    // Auth & PIN Management
    function checkPinCache() {
        const auth = JSON.parse(localStorage.getItem('tgc_pin_auth'));
        return !!(auth && auth.phoneId && (Date.now() - auth.timestamp < 12 * 60 * 60 * 1000));

    }

    function showPinValidationUI(targetTab) {
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));
        document.getElementById('secureFormsWrapper').classList.add('hidden');
        document.getElementById('pinValidationUI').classList.remove('hidden');
        document.getElementById('pinValidationUI').classList.add('active');
        document.getElementById('pinValidationUI').dataset.target = targetTab;

        const btn = document.querySelector(`button[onclick="switchTab('${targetTab}')"]`);
        if (btn) btn.classList.add('active');
    }

    function hidePinValidationUI() {
        document.getElementById('pinValidationUI').classList.add('hidden');
        document.getElementById('pinValidationUI').classList.remove('active');
    }

    async function validateGlobalPIN() {
        const phoneSelect = document.getElementById('authPhoneID');
        const pinInput = document.getElementById('authPinCode');
        const btn = document.getElementById('btnValidatePin');
        const msg = document.getElementById('authErrorMsg');

        msg.classList.add('hidden');

        if (!phoneSelect.value) {
            msg.innerText = 'Selecione seu piloto.';
            msg.classList.remove('hidden');
            phoneSelect.focus();
            return;
        }

        if (!pinInput.reportValidity()) {
            msg.innerText = 'Informe um PIN de 6 dígitos.';
            msg.classList.remove('hidden');
            pinInput.focus();
            return;
        }

        btn.disabled = true;
        btn.innerText = 'Validando...';

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    api_action: 'validate_pin',
                    phoneNumberID: phoneSelect.value,
                    pin: pinInput.value
                })
            });

            const result = await response.json();

            if (!result.success) {
                msg.innerText = result.error || 'PIN inválido.';
                msg.classList.remove('hidden');
                return;
            }

            const pilotName = phoneSelect.options[phoneSelect.selectedIndex].text;
            localStorage.setItem('tgc_pin_auth', JSON.stringify({
                phoneId: phoneSelect.value,
                pilotName: pilotName,
                timestamp: Date.now()
            }));

            const targetTab = document.getElementById('pinValidationUI').dataset.target;
            hidePinValidationUI();
            document.getElementById('secureFormsWrapper').classList.remove('hidden');
            switchTab(targetTab);
        } catch (error) {
            msg.innerText = 'Erro ao validar o PIN. Tente novamente.';
            msg.classList.remove('hidden');
        } finally {
            btn.disabled = false;
            btn.innerText = 'Validar Acesso';
        }
    }

    function populateAuthForms() {
        const auth = JSON.parse(localStorage.getItem('tgc_pin_auth'));
        if (auth) {
            document.querySelectorAll('.authPhoneIDHidden').forEach(el => el.value = auth.phoneId);
            document.querySelectorAll('.authPilotNameDisplay').forEach(el => el.innerText = auth.pilotName);
        }
    }

    function formatTime(input) {
        let value = input.value.replace(/[^0-9]/g, '');
        if (value.length >= 2) value = value.substring(0, 2) + ':' + value.substring(2);
        if (value.length >= 5) value = value.substring(0, 5) + ':' + value.substring(5, 7);
        input.value = value.substring(0, 8);
    }

    function setCarColor(select, trackNum) {
        const color = select.options[select.selectedIndex].dataset.color || '';
        const colorInput = document.getElementById(`track_${trackNum}_color`);

        if (colorInput) {
            colorInput.value = color;
        }
    }

    function closePopup(id) { document.getElementById(id).style.display = 'none'; }
    function openRegistrationPopup() { document.getElementById('registrationPopup').style.display = 'block'; }
    function openAdminPilotEditor(button) {
        document.getElementById('adminPilotEditId').value = button.dataset.pilotId;
        document.getElementById('adminPilotEditName').value = button.dataset.pilotName;
        document.getElementById('adminPilotEditNickname').value = button.dataset.pilotNickname;
        document.getElementById('adminPilotEditPhone').value = button.dataset.pilotPhone;
        document.getElementById('adminPilotEditEmail').value = button.dataset.pilotEmail;
        document.getElementById('adminPilotEditPopup').style.display = 'block';
        document.getElementById('adminPilotEditName').focus();
    }

    <?php if ($roundDeadline): ?>
    function updateCountdown() {
        const deadline = new Date('<?= $roundDeadline ?>').getTime();
        const now = new Date().getTime();
        const distance = deadline - now;
        const el = document.getElementById('countdown');
        if (!el) return;

        if (distance < 0) {
            el.innerHTML = "🏁 PRAZO ENCERRADO 🏁";
            return;
        }
        const d = Math.floor(distance / (1000 * 60 * 60 * 24));
        const h = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const m = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const s = Math.floor((distance % (1000 * 60)) / 1000);
        el.innerHTML = `<span class='text-4xl font-mono'>${d}d ${h}h ${m}m ${s}s</span>`;
    }
    setInterval(updateCountdown, 1000);
    updateCountdown();
    <?php endif; ?>

    // ============================================================================
    // FUNÇÕES DE COMPARTILHAMENTO NO WHATSAPP (PADRONIZADAS)
    // ============================================================================

    const currentRoundNumber = <?= json_encode((int)$currentRoundNumber) ?>;
    const selectedYear = <?= json_encode((int)$selectedYear) ?>;

    /**
     * Abre o WhatsApp com a mensagem pronta, permitindo ao usuário escolher o destino.
     * @param {string} message - Mensagem a ser enviada.
     */
    function openWhatsAppShare(message) {
        if (!message) return;
        const encodedMessage = encodeURIComponent(message);
        const whatsappUrl = `https://api.whatsapp.com/send?text=${encodedMessage}`;
        window.open(whatsappUrl, '_blank');
    }

    /**
     * Compartilha a tabela de tempos da rodada atual.
     */
    function shareRoundWhatsApp() {
        let msg = `Tabela de Tempos da Rodada ${currentRoundNumber}\n`;
        msg += `TGC Pole Position ${selectedYear}\n\n`;
        msg += `Pos  Piloto              Tempo       Pts\n`;
        msg += `--------------------------------------\n`;

        const rows = document.querySelectorAll('#roundTableData tbody tr');
        rows.forEach(r => {
            const posText = r.querySelector('td:nth-child(1)').innerText.trim();
            const pilot = r.querySelector('td:nth-child(2)').innerText.trim();
            const time = r.querySelector('td:nth-child(4)').innerText.trim();
            const ptsText = r.querySelector('td:nth-child(5)').innerText.replace('pts', '').trim();

            if (!pilot || !posText.includes('Ainda não há')) {
                let pClean = posText.replace('🥇', '1').replace('🥈', '2').replace('🥉', '3').replace('??', '');
                let padPos = pClean.padEnd(3, ' ');
                let padPilot = pilot.substring(0, 18).padEnd(18, ' ');
                let padTime = time.padEnd(10, ' ');
                let padPts = ptsText.padStart(3, ' ');
                msg += `${padPos} ${padPilot} ${padTime} ${padPts}\n`;
            }
        });

        openWhatsAppShare(msg);
    }

    /**
     * Compartilha o ranking geral.
     */
    function shareGeneralWhatsApp() {
        let msg = `CLASSIFICAÇÃO GERAL FINAL\n`;
        msg += `TGC Pole Position ${selectedYear}\n\n`;
        msg += `Pos  Piloto              Pts\n`;
        msg += `---------------------------\n`;

        const rows = document.querySelectorAll('#generalTableData tbody .general-row');
        rows.forEach(r => {
            const pos = r.dataset.pos;
            const pilot = r.dataset.pilot;
            const pts = r.dataset.pts;

            let emoji = '';
            if (pos == '1') emoji = '🥇';
            if (pos == '2') emoji = '🥈';
            if (pos == '3') emoji = '🥉';

            let padPos = (emoji + pos).padEnd(3, ' ');
            let padPilot = pilot.substring(0, 18).padEnd(18, ' ');
            let padPts = pts.padStart(3, ' ');
            msg += `${padPos} ${padPilot} ${padPts}\n`;
        });

        openWhatsAppShare(msg);
    }

    /**
     * Compartilha o resumo da submissão individual (popup após envio).
     */
    function shareIndividualToWhatsApp() {
        const msgContent = document.getElementById('subShareContent');
        if (!msgContent) return;

        // Converte <br> para quebras de linha
        let msg = msgContent.innerText;

        openWhatsAppShare(msg);
    }

    function setupFilters(containerId, searchId, cardSelector, entrySelector, subSearchSelector) {
        const container = document.getElementById(containerId);
        const searchInput = document.getElementById(searchId);
        if (!container || !searchInput) return;

        const checkboxes = container.querySelectorAll('input[type="checkbox"]');

        function doFilter() {
            const query = searchInput.value.toLowerCase();
            const activeFilters = Array.from(checkboxes).filter(cb => cb.checked).map(cb => cb.value);
            const cards = document.querySelectorAll(cardSelector);

            cards.forEach(card => {
                const cardSearchText = card.getAttribute('data-search') || '';
                const entries = card.querySelectorAll(entrySelector);
                let hasVisibleEntries = false;

                if (entries.length === 0) {
                    // For flat structures like subGrid
                    const m = card.getAttribute('data-mode');
                    const p = card.getAttribute('data-platform');

                    if (activeFilters.includes(p) && activeFilters.includes(m) && cardSearchText.includes(query)) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                    return;
                }

                entries.forEach(group => {
                    const m = group.getAttribute('data-mode');
                    const p = group.getAttribute('data-platform');

                    if (activeFilters.includes(p) && activeFilters.includes(m)) {
                        const subEntries = group.querySelectorAll(subSearchSelector);
                        let groupHasTextMatch = false;
                        subEntries.forEach(sub => {
                            const pilot = sub.getAttribute('data-pilot') || '';
                            const car = sub.getAttribute('data-car') || '';
                            const searchAttr = sub.getAttribute('data-search') || '';
                            const textMatch = cardSearchText.includes(query) || pilot.includes(query) || car.includes(query) || searchAttr.includes(query);

                            if (textMatch) {
                                sub.style.display = sub.tagName === 'DIV' ? 'flex' : 'block';
                                groupHasTextMatch = true;
                            } else {
                                sub.style.display = 'none';
                            }
                        });

                        if (groupHasTextMatch) {
                            group.style.display = 'block';
                            hasVisibleEntries = true;
                        } else {
                            group.style.display = 'none';
                        }
                    } else {
                        group.style.display = 'none';
                    }
                });

                if (entries.length > 0) {
                    card.style.display = hasVisibleEntries ? 'block' : 'none';
                }
            });
        }

        searchInput.addEventListener('input', doFilter);
        checkboxes.forEach(cb => cb.addEventListener('change', doFilter));
        doFilter();
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Init HoF Filters
        setupFilters('hofFilters', 'hofSearch', '#hofGrid .hof-card', '.hof-entry', '.bg-gray-900\\/50');
        // Init Submissions Filters
        setupFilters('subFilters', 'subSearch', '#subGrid .sub-entry', '', '');
    });
</script>

</body>
</html>