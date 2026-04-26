<?php
require_once '../includes/auth.php';

exigirPerfil('proprietario', ['redirect' => '../vboard.php']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: veiculos.php');
    exit;
}

if (!validarCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['erro'] = 'Sessão inválida. Tente novamente.';
    header('Location: veiculos.php');
    exit;
}

$id = filter_input(INPUT_POST, 'veiculo_id', FILTER_VALIDATE_INT);
$status = filter_input(INPUT_POST, 'status', FILTER_VALIDATE_INT);

if (!$id || ($status !== 0 && $status !== 1)) {
    header('Location: veiculos.php');
    exit;
}

global $pdo;
// Primeiro verifique se o veículo pertence ao usuário logado
$stmt = $pdo->prepare("SELECT v.id 
                      FROM veiculo v
                      JOIN dono d ON v.dono_id = d.id
                      JOIN conta_usuario u ON d.conta_usuario_id = u.id
                      WHERE v.id = ? AND u.id = ?");
$stmt->execute([$id, getUsuario()['id']]);
$veiculo = $stmt->fetch();

if (!$veiculo) {
    header('Location: veiculos.php');
    exit;
}

try {
    // Verifique se a coluna existe
    $stmt = $pdo->query("SHOW COLUMNS FROM veiculo LIKE 'disponivel'");
    if ($stmt->rowCount() > 0) {
        // Atualizar o status de disponibilidade
        $stmt = $pdo->prepare("UPDATE veiculo SET disponivel = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        header('Location: veiculos.php');
        exit;
    } else {
        // Registrar erro ou redirecionar com mensagem
        error_log("Coluna 'disponivel' não encontrada na tabela veiculo");
        header('Location: veiculos.php?erro=1');
        exit;
    }
} catch (PDOException $e) {
    // Log do erro para depuração
    error_log("Erro ao atualizar disponibilidade do veículo: " . $e->getMessage());
    
    // Redirecionar com mensagem de erro
    header('Location: veiculos.php?erro=1');
    exit;
}
?>
