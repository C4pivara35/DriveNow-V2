<?php
require_once '../includes/auth.php';

verificarAutenticacao();

$usuario = getUsuario();
global $pdo;

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['notification'] = [
        'type' => 'error',
        'message' => 'Pagamento nao especificado.'
    ];
    header('Location: ../reserva/minhas_reservas.php');
    exit;
}

$pagamentoId = (int)$_GET['id'];
$csrfToken = obterCsrfToken();

$stmt = $pdo->prepare("
    SELECT p.*, r.id AS reserva_id, r.conta_usuario_id, r.veiculo_id,
           v.veiculo_marca, v.veiculo_modelo, d.conta_usuario_id AS proprietario_usuario_id
    FROM pagamento p
    INNER JOIN reserva r ON p.reserva_id = r.id
    INNER JOIN veiculo v ON r.veiculo_id = v.id
    INNER JOIN dono d ON v.dono_id = d.id
    WHERE p.id = ?
    LIMIT 1
");
$stmt->execute([$pagamentoId]);
$pagamento = $stmt->fetch();

if (!$pagamento) {
    $_SESSION['notification'] = [
        'type' => 'error',
        'message' => 'Pagamento nao encontrado.'
    ];
    header('Location: ../reserva/minhas_reservas.php');
    exit;
}

$usuarioId = (int)($usuario['id'] ?? 0);
$podeConfirmar = isAdmin() || (int)$pagamento['proprietario_usuario_id'] === $usuarioId;

if (!$podeConfirmar) {
    $_SESSION['notification'] = [
        'type' => 'error',
        'message' => 'Apenas o proprietario do veiculo ou um administrador pode confirmar este pagamento.'
    ];
    header('Location: detalhe_pagamento.php?id=' . $pagamentoId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $retorno = $_POST['retorno'] ?? '';
    $urlRetorno = $retorno === 'detalhes_reserva'
        ? '../reserva/detalhes_reserva.php?id=' . (int)$pagamento['reserva_id']
        : 'detalhe_pagamento.php?id=' . $pagamentoId;

    if (!validarCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => 'Nao foi possivel validar sua sessao. Tente novamente.'
        ];
        header('Location: ' . $urlRetorno);
        exit;
    }

    if ($pagamento['status'] !== 'pendente') {
        $_SESSION['notification'] = [
            'type' => 'info',
            'message' => 'Este pagamento nao esta pendente.'
        ];
        header('Location: ' . $urlRetorno);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE pagamento
            SET status = 'aprovado', data_pagamento = NOW()
            WHERE id = ? AND status = 'pendente'
        ");
        $stmt->execute([$pagamentoId]);

        $stmt = $pdo->prepare("
            UPDATE reserva
            SET status = 'pago'
            WHERE id = ?
        ");
        $stmt->execute([(int)$pagamento['reserva_id']]);

        $stmt = $pdo->prepare("
            INSERT INTO historico_pagamento (pagamento_id, status_anterior, novo_status, observacao, usuario_id)
            VALUES (?, 'pendente', 'aprovado', 'Pagamento confirmado manualmente pelo proprietario/admin.', ?)
        ");
        $stmt->execute([$pagamentoId, $usuarioId]);

        $pdo->commit();

        $_SESSION['notification'] = [
            'type' => 'success',
            'message' => 'Pagamento confirmado com sucesso. A reserva agora aguarda confirmacao operacional.'
        ];
        header('Location: ' . $urlRetorno);
        exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Erro ao confirmar pagamento: ' . $e->getMessage());
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => 'Nao foi possivel confirmar o pagamento agora.'
        ];
        header('Location: ' . $urlRetorno);
        exit;
    }
}

$navBasePath = '../';
$navCurrent = 'reservas';
$navFixed = true;
$navShowMarketplaceAnchors = false;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar Pagamento - DriveNow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/ui-modern.css">
</head>
<body class="drivenow-modern min-h-screen bg-gradient-to-br from-slate-900 via-indigo-950 to-purple-950 text-white p-4 md:p-8">
    <?php include_once '../includes/navbar.php'; ?>

    <main class="container mx-auto px-4 pt-28 pb-8">
        <section class="section-shell max-w-2xl mx-auto backdrop-blur-lg bg-white/5 border border-white/10 rounded-3xl p-6 shadow-lg">
            <h1 class="text-3xl font-bold mb-3">Confirmar pagamento</h1>
            <p class="text-white/70 mb-6">
                Confirme apenas depois de validar o comprovante ou a compensacao do pagamento.
            </p>

            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 mb-6 text-sm space-y-2">
                <div class="flex justify-between gap-4">
                    <span class="text-white/60">Pagamento</span>
                    <span>#<?= (int)$pagamentoId ?></span>
                </div>
                <div class="flex justify-between gap-4">
                    <span class="text-white/60">Reserva</span>
                    <span>#<?= (int)$pagamento['reserva_id'] ?></span>
                </div>
                <div class="flex justify-between gap-4">
                    <span class="text-white/60">Veiculo</span>
                    <span><?= htmlspecialchars($pagamento['veiculo_marca'] . ' ' . $pagamento['veiculo_modelo']) ?></span>
                </div>
                <div class="flex justify-between gap-4">
                    <span class="text-white/60">Valor</span>
                    <span>R$ <?= number_format((float)$pagamento['valor'], 2, ',', '.') ?></span>
                </div>
            </div>

            <form method="post" class="flex flex-wrap gap-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn-dn-primary bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl px-4 py-2 font-medium border border-emerald-400/30">
                    Confirmar pagamento
                </button>
                <a href="detalhe_pagamento.php?id=<?= (int)$pagamentoId ?>" class="btn-dn-ghost border border-white/20 text-white hover:bg-white/10 rounded-xl px-4 py-2 font-medium">
                    Voltar
                </a>
            </form>
        </section>
    </main>
</body>
</html>
