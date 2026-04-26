<?php
require_once '../includes/auth.php';
require_once '../includes/VehicleImages.php';

exigirPerfil('proprietario', ['redirect' => '../vboard.php']);

$usuario = getUsuario();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: veiculos.php');
    exit;
}

if (!validarCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['erro'] = 'Sessão inválida. Tente novamente.';
    header('Location: veiculos.php');
    exit;
}

$veiculoId = filter_input(INPUT_POST, 'veiculo_id', FILTER_VALIDATE_INT);
if (!$veiculoId) {
    header('Location: veiculos.php');
    exit;
}

// Verificar se o usuário é um dono
global $pdo;
$stmt = $pdo->prepare("SELECT id FROM dono WHERE conta_usuario_id = ?");
$stmt->execute([$usuario['id']]);
$dono = $stmt->fetch();

if (!$dono) {
    header('Location: veiculos.php');
    exit;
}

// Verificar se o veículo pertence ao dono
$stmt = $pdo->prepare("SELECT id FROM veiculo WHERE id = ? AND dono_id = ?");
$stmt->execute([$veiculoId, $dono['id']]);
$veiculo = $stmt->fetch();

if (!$veiculo) {
    header('Location: veiculos.php');
    exit;
}

// Excluir o veículo
try {
    $pdo->beginTransaction();

    $imagensVeiculo = buscarImagensVeiculo($pdo, (int)$veiculoId);
    $arquivosImagem = array_column($imagensVeiculo, 'imagem_url');

    $stmt = $pdo->prepare("DELETE FROM imagem WHERE veiculo_id = ?");
    $stmt->execute([$veiculoId]);

    $stmt = $pdo->prepare("DELETE FROM veiculo WHERE id = ?");
    $stmt->execute([$veiculoId]);

    $pdo->commit();
    excluirArquivosFisicosVeiculo($arquivosImagem);
    
    $_SESSION['sucesso'] = 'Veículo excluído com sucesso!';
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Erro ao excluir veiculo: ' . $e->getMessage());
    $_SESSION['erro'] = 'Nao foi possivel excluir o veiculo agora. Tente novamente mais tarde.';
}

header('Location: veiculos.php');
exit;
