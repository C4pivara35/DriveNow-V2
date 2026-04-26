<?php
require_once '../includes/auth.php';

verificarAutenticacao();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: minhas_reservas.php');
    exit;
}

if (!validarCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['notification'] = [
        'type' => 'error',
        'message' => 'Não foi possível validar sua sessão. Tente novamente.'
    ];
    header('Location: minhas_reservas.php');
    exit;
}

$reservaId = filter_input(INPUT_POST, 'reserva_id', FILTER_VALIDATE_INT);
$usuario = getUsuario();

if (!$reservaId) {
    header('Location: minhas_reservas.php');
    exit;
}

// Verificar se a reserva pertence ao usuário
global $pdo;
$stmt = $pdo->prepare("SELECT id FROM reserva WHERE id = ? AND conta_usuario_id = ?");
$stmt->execute([$reservaId, $usuario['id']]);
$reserva = $stmt->fetch();

if (!$reserva) {
    $_SESSION['notification'] = [
        'type' => 'error',
        'message' => 'Reserva não encontrada ou sem permissão para cancelar.'
    ];
    header('Location: minhas_reservas.php');
    exit;
}

// Excluir a reserva
try {
    $stmt = $pdo->prepare("DELETE FROM reserva WHERE id = ?");
    $stmt->execute([$reservaId]);
    
    $_SESSION['notification'] = [
        'type' => 'success',
        'message' => 'Reserva cancelada com sucesso.'
    ];
} catch (PDOException $e) {
    error_log('Erro ao cancelar reserva: ' . $e->getMessage());
    $_SESSION['notification'] = [
        'type' => 'error',
        'message' => 'Não foi possível cancelar a reserva agora.'
    ];
}

header('Location: minhas_reservas.php');
exit;