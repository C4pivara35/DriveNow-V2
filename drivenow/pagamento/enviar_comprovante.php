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
    SELECT p.*, r.conta_usuario_id, r.veiculo_id,
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
$podeEnviar = isAdmin()
    || (int)$pagamento['conta_usuario_id'] === $usuarioId
    || (int)$pagamento['proprietario_usuario_id'] === $usuarioId;

if (!$podeEnviar) {
    $_SESSION['notification'] = [
        'type' => 'error',
        'message' => 'Voce nao tem permissao para alterar este pagamento.'
    ];
    header('Location: ../reserva/minhas_reservas.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => 'Nao foi possivel validar sua sessao. Tente novamente.'
        ];
        header('Location: enviar_comprovante.php?id=' . $pagamentoId);
        exit;
    }

    $comprovanteUrl = trim((string)($_POST['comprovante_url'] ?? ''));

    if ($comprovanteUrl === '' || strlen($comprovanteUrl) > 500) {
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => 'Informe um link de comprovante valido.'
        ];
        header('Location: enviar_comprovante.php?id=' . $pagamentoId);
        exit;
    }

    if (!filter_var($comprovanteUrl, FILTER_VALIDATE_URL)) {
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => 'O comprovante deve ser um link valido.'
        ];
        header('Location: enviar_comprovante.php?id=' . $pagamentoId);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE pagamento SET comprovante_url = ? WHERE id = ?");
        $stmt->execute([$comprovanteUrl, $pagamentoId]);

        $stmt = $pdo->prepare("
            INSERT INTO historico_pagamento (pagamento_id, status_anterior, novo_status, observacao, usuario_id)
            VALUES (?, ?, ?, 'Comprovante enviado pelo usuario.', ?)
        ");
        $stmt->execute([$pagamentoId, $pagamento['status'], $pagamento['status'], $usuarioId]);

        $_SESSION['notification'] = [
            'type' => 'success',
            'message' => 'Comprovante enviado com sucesso.'
        ];
        header('Location: detalhe_pagamento.php?id=' . $pagamentoId);
        exit;
    } catch (PDOException $e) {
        error_log('Erro ao enviar comprovante: ' . $e->getMessage());
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => 'Nao foi possivel salvar o comprovante agora.'
        ];
        header('Location: detalhe_pagamento.php?id=' . $pagamentoId);
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
    <title>Enviar Comprovante - DriveNow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/ui-modern.css">
</head>
<body class="drivenow-modern min-h-screen bg-gradient-to-br from-slate-900 via-indigo-950 to-purple-950 text-white p-4 md:p-8">
    <?php include_once '../includes/navbar.php'; ?>

    <main class="container mx-auto px-4 pt-28 pb-8">
        <section class="section-shell max-w-2xl mx-auto backdrop-blur-lg bg-white/5 border border-white/10 rounded-3xl p-6 shadow-lg">
            <h1 class="text-3xl font-bold mb-3">Enviar comprovante</h1>
            <p class="text-white/70 mb-6">
                Cole o link do comprovante para que o proprietario ou administrador possa validar o pagamento.
            </p>

            <?php if (isset($_SESSION['notification'])): ?>
                <div class="mb-5 rounded-xl border <?= $_SESSION['notification']['type'] === 'error' ? 'border-red-400/30 bg-red-500/20' : 'border-emerald-400/30 bg-emerald-500/20' ?> px-4 py-3">
                    <?= htmlspecialchars($_SESSION['notification']['message']) ?>
                </div>
                <?php unset($_SESSION['notification']); ?>
            <?php endif; ?>

            <form method="post" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div>
                    <label for="comprovante_url" class="block text-white/80 mb-2">Link do comprovante</label>
                    <input
                        type="url"
                        id="comprovante_url"
                        name="comprovante_url"
                        value="<?= htmlspecialchars((string)($pagamento['comprovante_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="https://..."
                        class="w-full bg-white/5 border border-white/10 rounded-xl h-11 px-3 outline-none focus:ring-2 focus:ring-indigo-500 text-white"
                        required
                    >
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="btn-dn-primary bg-indigo-500 hover:bg-indigo-600 text-white rounded-xl px-4 py-2 font-medium border border-indigo-400/30">
                        Enviar comprovante
                    </button>
                    <a href="detalhe_pagamento.php?id=<?= (int)$pagamentoId ?>" class="btn-dn-ghost border border-white/20 text-white hover:bg-white/10 rounded-xl px-4 py-2 font-medium">
                        Voltar
                    </a>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
