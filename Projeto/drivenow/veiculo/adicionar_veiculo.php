<?php
require_once '../includes/auth.php';
require_once '../includes/VehicleImages.php';

exigirPerfil('proprietario', ['redirect' => '../vboard.php']);

$usuario = getUsuario();

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'Erro desconhecido.'];

global $pdo;
$stmt = $pdo->prepare('SELECT id FROM dono WHERE conta_usuario_id = ?');
$stmt->execute([$usuario['id']]);
$dono = $stmt->fetch();

if (!$dono) {
    $response['message'] = 'Voce precisa ser um proprietario para cadastrar veiculos.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrfToken($_POST['csrf_token'] ?? '')) {
        $response['message'] = 'Nao foi possivel validar sua sessao. Tente novamente.';
        echo json_encode($response);
        exit;
    }

    $dados = [
        'dono_id' => $dono['id'],
        'veiculo_marca' => trim((string)($_POST['veiculo_marca'] ?? '')),
        'veiculo_modelo' => trim((string)($_POST['veiculo_modelo'] ?? '')),
        'veiculo_ano' => trim((string)($_POST['veiculo_ano'] ?? '')),
        'veiculo_placa' => trim((string)($_POST['veiculo_placa'] ?? '')),
        'veiculo_km' => trim((string)($_POST['veiculo_km'] ?? '')),
        'veiculo_cambio' => trim((string)($_POST['veiculo_cambio'] ?? '')),
        'veiculo_combustivel' => trim((string)($_POST['veiculo_combustivel'] ?? '')),
        'veiculo_portas' => trim((string)($_POST['veiculo_portas'] ?? '')),
        'veiculo_acentos' => trim((string)($_POST['veiculo_acentos'] ?? '')),
        'veiculo_tracao' => trim((string)($_POST['veiculo_tracao'] ?? '')),
        'local_id' => !empty($_POST['local_id']) ? $_POST['local_id'] : null,
        'categoria_veiculo_id' => !empty($_POST['categoria_veiculo_id']) ? $_POST['categoria_veiculo_id'] : null,
        'preco_diaria' => trim((string)($_POST['preco_diaria'] ?? '')),
        'descricao' => trim((string)($_POST['descricao'] ?? '')),
    ];

    $arquivosImagem = normalizarArquivosVeiculo($_FILES);
    $erroImagens = validarArquivosImagemVeiculo($arquivosImagem, VEHICLE_IMAGES_MIN, VEHICLE_IMAGES_MAX);

    $stmt = $pdo->prepare('SELECT id FROM veiculo WHERE veiculo_placa = ?');
    $stmt->execute([$dados['veiculo_placa']]);
    if ($stmt->rowCount() > 0) {
        $response['message'] = 'Esta placa ja esta cadastrada no sistema.';
        echo json_encode($response);
        exit;
    }

    if (
        empty($dados['veiculo_marca'])
        || empty($dados['veiculo_modelo'])
        || empty($dados['veiculo_ano'])
        || empty($dados['veiculo_placa'])
        || empty($dados['veiculo_km'])
        || empty($dados['veiculo_cambio'])
        || empty($dados['veiculo_combustivel'])
        || empty($dados['veiculo_portas'])
        || empty($dados['veiculo_acentos'])
        || empty($dados['veiculo_tracao'])
    ) {
        $response['message'] = 'Todos os campos sao obrigatorios.';
    } elseif ($erroImagens !== null) {
        $response['message'] = $erroImagens;
    } elseif (!is_numeric($dados['veiculo_ano']) || $dados['veiculo_ano'] < 1900 || $dados['veiculo_ano'] > date('Y') + 1) {
        $response['message'] = 'Ano do veiculo invalido.';
    } elseif (!is_numeric($dados['preco_diaria']) || $dados['preco_diaria'] <= 0) {
        $response['message'] = 'O preco diario deve ser um valor numerico positivo.';
    } else {
        $arquivosSalvos = [];

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO veiculo
                (dono_id, veiculo_marca, veiculo_modelo, veiculo_ano, veiculo_placa, veiculo_km, veiculo_cambio,
                veiculo_combustivel, veiculo_portas, veiculo_acentos, veiculo_tracao,
                local_id, categoria_veiculo_id, preco_diaria, descricao)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute(array_values($dados));
            $veiculoId = (int)$pdo->lastInsertId();

            $arquivosSalvos = salvarImagensVeiculo($pdo, $veiculoId, $arquivosImagem);
            $pdo->commit();

            $response['status'] = 'success';
            $response['message'] = 'Veiculo cadastrado com sucesso!';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            excluirArquivosFisicosVeiculo($arquivosSalvos);
            error_log('Erro ao cadastrar veiculo: ' . $e->getMessage());
            $response['message'] = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Nao foi possivel cadastrar o veiculo agora. Tente novamente mais tarde.';
        }
    }
}

echo json_encode($response);
