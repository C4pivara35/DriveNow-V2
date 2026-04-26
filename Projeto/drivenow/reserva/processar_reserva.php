<?php
require_once '../includes/auth.php';
require_once '../includes/reserva_disponibilidade.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar se é uma requisição AJAX
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['status' => 'error', 'message' => 'Requisição inválida']);
    exit;
}

// Verificar autenticação
if (!estaLogado()) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['status' => 'error', 'message' => 'Você precisa estar logado para fazer uma reserva.']);
    exit;
}

if (!validarCsrfToken($_POST['csrf_token'] ?? '')) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['status' => 'error', 'message' => 'Não foi possível validar sua sessão. Atualize a página e tente novamente.']);
    exit;
}

if (!usuarioPodeReservar()) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['status' => 'error', 'message' => 'Complete seu cadastro e aguarde a aprovação da CNH para reservar.']);
    exit;
}

// Obter e validar dados
$veiculoId = filter_input(INPUT_POST, 'veiculo_id', FILTER_VALIDATE_INT);
$reservaData = trim((string)($_POST['reserva_data'] ?? ''));
$devolucaoData = trim((string)($_POST['devolucao_data'] ?? ''));
$observacoes = trim((string)($_POST['observacoes'] ?? ''));

// Validações básicas
if (!$veiculoId || $reservaData === '' || $devolucaoData === '') {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['status' => 'error', 'message' => 'Todos os campos obrigatórios devem ser preenchidos.']);
    exit;
}

$periodo = reservaNormalizarPeriodo($reservaData, $devolucaoData);
if (!$periodo['ok']) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['status' => 'error', 'message' => $periodo['mensagem']]);
    exit;
}

try {
    global $pdo;
    
    // Verificar se o veículo existe e obter preço da diária
    
    // Buscar o usuário logado
    $usuario = getUsuario();
    $reserva = criarReservaComBloqueio($pdo, (int)$veiculoId, (int)$usuario['id'], $periodo, $observacoes);

    if (!$reserva['ok']) {
        http_response_code((int)($reserva['http_status'] ?? 409));
        echo json_encode(['status' => 'error', 'message' => $reserva['mensagem']]);
        exit;
    }
    
      
    $_SESSION['notification'] = [
        'type' => 'success',
        'message' => 'Reserva realizada com sucesso! Prossiga para o pagamento.'
    ];
    
    // Redirecionar para a página de pagamento
    echo json_encode([
        'status' => 'success', 
        'message' => 'Reserva realizada com sucesso! Prossiga para o pagamento.', 
        'valor_total' => number_format((float)$reserva['valor_total'], 2, ',', '.'),
        'reserva_id' => $reserva['reserva_id'],
        'redirect' => '../pagamento/realizar_pagamento.php?reserva=' . $reserva['reserva_id']
    ]);
    
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Erro ao processar reserva: ' . $e->getMessage());

    if (http_response_code() < 400) {
        header('HTTP/1.1 500 Internal Server Error');
    }

    echo json_encode(['status' => 'error', 'message' => 'Nao foi possivel processar sua reserva agora. Tente novamente mais tarde.']);
    exit;
}
