<?php
require_once '../includes/auth.php';
require_once '../includes/security_log.php';

header('Content-Type: application/json');

function responderAvaliacaoJson(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function tabelaAvaliacaoCompativel(): bool
{
    global $pdo;

    $colunasObrigatorias = [
        'veiculo_id',
        'reserva_id',
        'usuario_id',
        'nota',
        'comentario',
        'data_avaliacao',
    ];

    try {
        $placeholders = implode(',', array_fill(0, count($colunasObrigatorias), '?'));
        $stmt = $pdo->prepare(
            "SELECT COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'avaliacao'
               AND COLUMN_NAME IN ($placeholders)"
        );
        $stmt->execute($colunasObrigatorias);
        $encontradas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log('Erro ao verificar schema de avaliacao: ' . $e->getMessage());
        return false;
    }

    return count(array_intersect($colunasObrigatorias, $encontradas ?: [])) === count($colunasObrigatorias);
}

if (!estaLogado()) {
    responderAvaliacaoJson(401, ['success' => false, 'message' => 'Nao autorizado']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderAvaliacaoJson(405, ['success' => false, 'message' => 'Metodo nao permitido']);
}

$csrfToken = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!validarCsrfToken($csrfToken)) {
    registrarEventoSeguranca('avaliacao_csrf_rejected');
    responderAvaliacaoJson(403, ['success' => false, 'message' => 'Sessao invalida.']);
}

$usuario = getUsuario();
$reservaId = filter_var($_POST['reserva_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$nota = filter_var($_POST['nota'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 5],
]);
$comentario = trim((string)($_POST['comentario'] ?? ''));

if ($comentario !== '' && mb_strlen($comentario) > 1000) {
    $comentario = mb_substr($comentario, 0, 1000);
}

if (!$reservaId || !$nota) {
    registrarEventoSeguranca('avaliacao_validation_rejected', [
        'reserva_id' => $reservaId ?: null,
        'nota_valida' => (bool)$nota,
    ]);
    responderAvaliacaoJson(422, ['success' => false, 'message' => 'Dados invalidos.']);
}

global $pdo;

try {
    $stmt = $pdo->prepare(
        "SELECT id, veiculo_id, conta_usuario_id, status, devolucao_data
         FROM reserva
         WHERE id = ? AND conta_usuario_id = ?
         LIMIT 1"
    );
    $stmt->execute([$reservaId, (int)$usuario['id']]);
    $reserva = $stmt->fetch();

    if (!$reserva) {
        registrarEventoSeguranca('avaliacao_authorization_rejected', [
            'reserva_id' => $reservaId,
        ]);
        responderAvaliacaoJson(404, ['success' => false, 'message' => 'Reserva nao encontrada']);
    }

    $statusReserva = strtolower((string)($reserva['status'] ?? ''));
    $statusBloqueados = ['cancelada', 'cancelado', 'rejeitada', 'rejeitado', 'pendente'];
    $devolucaoTimestamp = strtotime((string)($reserva['devolucao_data'] ?? ''));
    $reservaEncerrada = $devolucaoTimestamp !== false && $devolucaoTimestamp <= strtotime(date('Y-m-d'));

    if (!$reservaEncerrada || in_array($statusReserva, $statusBloqueados, true)) {
        registrarEventoSeguranca('avaliacao_state_rejected', [
            'reserva_id' => $reservaId,
            'status' => $statusReserva,
        ]);
        responderAvaliacaoJson(422, ['success' => false, 'message' => 'Reserva ainda nao esta elegivel para avaliacao.']);
    }

    if (!tabelaAvaliacaoCompativel()) {
        registrarEventoSeguranca('avaliacao_schema_unavailable', [
            'reserva_id' => $reservaId,
        ]);
        responderAvaliacaoJson(503, ['success' => false, 'message' => 'Servico de avaliacao indisponivel no momento.']);
    }

    $stmt = $pdo->prepare('SELECT id FROM avaliacao WHERE reserva_id = ? LIMIT 1');
    $stmt->execute([$reservaId]);
    $avaliacaoExistente = $stmt->fetch();

    if ($avaliacaoExistente) {
        responderAvaliacaoJson(409, ['success' => false, 'message' => 'Esta reserva ja foi avaliada']);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO avaliacao
            (veiculo_id, reserva_id, usuario_id, nota, comentario, data_avaliacao)
         VALUES (?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        (int)$reserva['veiculo_id'],
        $reservaId,
        (int)$usuario['id'],
        $nota,
        $comentario,
    ]);

    responderAvaliacaoJson(200, ['success' => true, 'message' => 'Avaliacao registrada com sucesso']);
} catch (PDOException $e) {
    error_log('Erro ao registrar avaliacao de reserva: ' . $e->getMessage());
    responderAvaliacaoJson(500, ['success' => false, 'message' => 'Nao foi possivel registrar sua avaliacao agora. Tente novamente mais tarde.']);
}
