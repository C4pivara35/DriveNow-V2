<?php
require_once '../includes/auth.php';
require_once '../includes/VehicleImages.php';

exigirPerfil('proprietario', ['redirect' => '../vboard.php']);

$usuario = getUsuario();
$resposta = ['status' => 'error', 'message' => 'Requisicao invalida'];

global $pdo;
$stmt = $pdo->prepare('SELECT id FROM dono WHERE conta_usuario_id = ?');
$stmt->execute([$usuario['id']]);
$dono = $stmt->fetch();

if (!$dono) {
    $resposta['message'] = 'Voce nao tem permissao para executar esta acao.';
    header('Content-Type: application/json');
    echo json_encode($resposta);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['veiculo_id']) && ($_POST['acao'] ?? '') === 'editar') {
    if (!validarCsrfToken($_POST['csrf_token'] ?? '')) {
        $resposta['message'] = 'Nao foi possivel validar sua sessao. Tente novamente.';
        header('Content-Type: application/json');
        echo json_encode($resposta);
        exit;
    }

    $veiculoId = filter_input(INPUT_POST, 'veiculo_id', FILTER_VALIDATE_INT);
    if (!$veiculoId) {
        header('Content-Type: application/json');
        echo json_encode($resposta);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id, veiculo_placa FROM veiculo WHERE id = ? AND dono_id = ?');
    $stmt->execute([$veiculoId, $dono['id']]);
    $veiculo = $stmt->fetch();

    if ($veiculo) {
        $dados = [
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
        $erroNovasImagens = validarArquivosImagemVeiculo($arquivosImagem, 0, VEHICLE_IMAGES_MAX);
        $imagensAtuais = buscarImagensVeiculo($pdo, (int)$veiculoId);
        $idsAtuais = array_map('intval', array_column($imagensAtuais, 'id'));
        $removerImagens = $_POST['remover_imagens'] ?? [];
        $removerImagens = is_array($removerImagens) ? $removerImagens : [$removerImagens];
        $removerImagens = array_values(array_intersect(
            $idsAtuais,
            array_filter(array_map('intval', $removerImagens), static fn (int $id): bool => $id > 0)
        ));
        $totalFinalImagens = count($imagensAtuais) - count($removerImagens) + count($arquivosImagem);

        if (
            empty($dados['veiculo_marca'])
            || empty($dados['veiculo_modelo'])
            || empty($dados['veiculo_placa'])
            || empty($dados['veiculo_ano'])
            || empty($dados['veiculo_km'])
            || empty($dados['veiculo_cambio'])
            || empty($dados['veiculo_combustivel'])
            || empty($dados['veiculo_portas'])
            || empty($dados['veiculo_acentos'])
            || empty($dados['veiculo_tracao'])
        ) {
            $resposta['message'] = 'Todos os campos sao obrigatorios.';
        } elseif ($erroNovasImagens !== null) {
            $resposta['message'] = $erroNovasImagens;
        } elseif ($totalFinalImagens < VEHICLE_IMAGES_MIN) {
            $resposta['message'] = 'Cada veiculo deve manter pelo menos 1 imagem.';
        } elseif ($totalFinalImagens > VEHICLE_IMAGES_MAX) {
            $resposta['message'] = 'Cada veiculo pode ter no maximo ' . VEHICLE_IMAGES_MAX . ' imagens.';
        } elseif (!is_numeric($dados['veiculo_ano']) || $dados['veiculo_ano'] < 1900 || $dados['veiculo_ano'] > date('Y') + 1) {
            $resposta['message'] = 'Ano do veiculo invalido.';
        } elseif (!is_numeric($dados['preco_diaria']) || $dados['preco_diaria'] <= 0) {
            $resposta['message'] = 'O preco diario deve ser um valor numerico positivo.';
        } else {
            if ($dados['veiculo_placa'] !== $veiculo['veiculo_placa']) {
                $stmt = $pdo->prepare('SELECT id FROM veiculo WHERE veiculo_placa = ? AND id != ?');
                $stmt->execute([$dados['veiculo_placa'], $veiculoId]);
                if ($stmt->rowCount() > 0) {
                    $resposta['message'] = 'Esta placa ja esta cadastrada no sistema.';
                    header('Content-Type: application/json');
                    echo json_encode($resposta);
                    exit;
                }
            }

            $arquivosParaExcluir = [];
            $novosArquivosSalvos = [];

            try {
                $pdo->beginTransaction();

                $sql = 'UPDATE veiculo SET
                      veiculo_marca = ?,
                      veiculo_modelo = ?,
                      veiculo_ano = ?,
                      veiculo_km = ?,
                      veiculo_cambio = ?,
                      veiculo_combustivel = ?,
                      veiculo_portas = ?,
                      veiculo_acentos = ?,
                      veiculo_tracao = ?,
                      local_id = ?,
                      categoria_veiculo_id = ?,
                      preco_diaria = ?,
                      descricao = ?
                      WHERE id = ? AND dono_id = ?';

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $dados['veiculo_marca'],
                    $dados['veiculo_modelo'],
                    $dados['veiculo_ano'],
                    $dados['veiculo_km'],
                    $dados['veiculo_cambio'],
                    $dados['veiculo_combustivel'],
                    $dados['veiculo_portas'],
                    $dados['veiculo_acentos'],
                    $dados['veiculo_tracao'],
                    $dados['local_id'],
                    $dados['categoria_veiculo_id'],
                    $dados['preco_diaria'],
                    $dados['descricao'],
                    $veiculoId,
                    $dono['id'],
                ]);

                if (!empty($removerImagens)) {
                    $placeholders = implode(',', array_fill(0, count($removerImagens), '?'));
                    $stmt = $pdo->prepare("SELECT imagem_url FROM imagem WHERE veiculo_id = ? AND id IN ($placeholders)");
                    $stmt->execute(array_merge([$veiculoId], $removerImagens));
                    $arquivosParaExcluir = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'imagem_url');

                    $stmt = $pdo->prepare("DELETE FROM imagem WHERE veiculo_id = ? AND id IN ($placeholders)");
                    $stmt->execute(array_merge([$veiculoId], $removerImagens));
                }

                $ordemInicial = 1;
                $stmt = $pdo->prepare('SELECT COALESCE(MAX(imagem_ordem), 0) + 1 FROM imagem WHERE veiculo_id = ?');
                $stmt->execute([$veiculoId]);
                $ordemInicial = (int)$stmt->fetchColumn();
                $novosArquivosSalvos = salvarImagensVeiculo($pdo, (int)$veiculoId, $arquivosImagem, $ordemInicial);

                $pdo->commit();
                excluirArquivosFisicosVeiculo($arquivosParaExcluir);

                $resposta = [
                    'status' => 'success',
                    'message' => 'Veiculo atualizado com sucesso!',
                    'veiculo_id' => $veiculoId,
                ];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                excluirArquivosFisicosVeiculo($novosArquivosSalvos);
                error_log('Erro ao atualizar veiculo: ' . $e->getMessage());
                $resposta['message'] = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Nao foi possivel atualizar o veiculo agora. Tente novamente mais tarde.';
            }
        }
    }
}

header('Content-Type: application/json');
echo json_encode($resposta);
exit;
