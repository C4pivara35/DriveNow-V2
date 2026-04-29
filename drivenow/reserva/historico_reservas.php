<?php
require_once '../includes/auth.php';

verificarAutenticacao();

include_once '../api/atualizar_status_reservas.php';

$usuario = getUsuario();

global $pdo;
$stmt = $pdo->prepare("
    SELECT r.*, v.veiculo_marca, v.veiculo_modelo, v.veiculo_placa,
           CONCAT(u.primeiro_nome, ' ', u.segundo_nome) AS nome_proprietario
    FROM reserva r
    JOIN veiculo v ON r.veiculo_id = v.id
    JOIN dono d ON v.dono_id = d.id
    JOIN conta_usuario u ON d.conta_usuario_id = u.id
    WHERE r.conta_usuario_id = ?
    ORDER BY r.reserva_data DESC
");
$stmt->execute([(int)$usuario['id']]);
$reservas = $stmt->fetchAll();

function statusHistoricoReserva(array $reserva): array
{
    $statusBanco = strtolower((string)($reserva['status'] ?? 'pendente'));
    $inicio = !empty($reserva['reserva_data']) ? strtotime((string)$reserva['reserva_data']) : false;
    $fim = !empty($reserva['devolucao_data']) ? strtotime((string)$reserva['devolucao_data']) : false;
    $agora = time();

    if ($statusBanco === 'finalizada' || ($statusBanco === 'confirmada' && $fim && $fim <= strtotime(date('Y-m-d')))) {
        return ['label' => 'Finalizada', 'class' => 'bg-slate-500/20 text-slate-300 border border-slate-400/30', 'avaliavel' => true];
    }

    if ($statusBanco === 'confirmada' && $inicio && $fim && $agora >= $inicio && $agora <= $fim) {
        return ['label' => 'Em andamento', 'class' => 'bg-emerald-500/20 text-emerald-300 border border-emerald-400/30', 'avaliavel' => false];
    }

    if ($statusBanco === 'confirmada') {
        return ['label' => 'Confirmada', 'class' => 'bg-blue-500/20 text-blue-300 border border-blue-400/30', 'avaliavel' => false];
    }

    if ($statusBanco === 'pago') {
        return ['label' => 'Pago - aguardando confirmacao', 'class' => 'bg-purple-500/20 text-purple-300 border border-purple-400/30', 'avaliavel' => false];
    }

    if ($statusBanco === 'rejeitada') {
        return ['label' => 'Rejeitada', 'class' => 'bg-red-500/20 text-red-300 border border-red-400/30', 'avaliavel' => false];
    }

    if ($statusBanco === 'cancelada') {
        return ['label' => 'Cancelada', 'class' => 'bg-yellow-500/20 text-yellow-300 border border-yellow-400/30', 'avaliavel' => false];
    }

    return ['label' => 'Pendente', 'class' => 'bg-amber-500/20 text-amber-300 border border-amber-400/30', 'avaliavel' => false];
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
    <title>Historico de Reservas - DriveNow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/ui-modern.css">
    <style>
        .subtle-border { border-color: rgba(255, 255, 255, 0.1); }
        option { background-color: #1e293b; color: #fff; }
    </style>
</head>
<body class="drivenow-modern min-h-screen bg-gradient-to-br from-slate-900 via-indigo-950 to-purple-950 text-white p-4 md:p-8 overflow-x-hidden">
    <?php include_once '../includes/navbar.php'; ?>

    <main class="container mx-auto px-4 pt-28 pb-8">
        <section class="hero-surface section-shell mb-6 backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl p-6 md:p-8 mx-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <p class="text-indigo-200/90 text-xs uppercase tracking-[0.18em] font-semibold mb-3">Reservas</p>
                    <h1 class="text-3xl md:text-4xl font-bold text-white">Historico de Reservas</h1>
                    <p class="text-white/70 mt-2">Consulte suas reservas encerradas e acompanhe o historico da conta.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="../vboard.php" class="btn-dn-ghost border border-white/20 text-white hover:bg-white/10 rounded-xl px-4 py-2 font-medium">Voltar ao painel</a>
                    <a href="listagem_veiculos.php" class="btn-dn-primary bg-indigo-500 hover:bg-indigo-600 text-white rounded-xl px-4 py-2 font-medium border border-indigo-400/30">Nova reserva</a>
                </div>
            </div>
        </section>

        <?php if (empty($reservas)): ?>
            <section class="section-shell backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl p-8 shadow-lg mx-4 text-center">
                <h2 class="text-xl font-bold mb-3">Voce ainda nao fez nenhuma reserva</h2>
                <p class="text-white/70 mb-5">Busque veiculos disponiveis para iniciar sua primeira reserva.</p>
                <a href="listagem_veiculos.php" class="btn-dn-primary bg-indigo-500 hover:bg-indigo-600 text-white rounded-xl px-5 py-2.5 font-medium border border-indigo-400/30">Buscar veiculos</a>
            </section>
        <?php else: ?>
            <section class="section-shell backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl p-6 shadow-lg mx-4 overflow-x-auto">
                <table class="dn-table w-full min-w-[860px]">
                    <thead class="border-b border-white/10 text-left">
                        <tr>
                            <th class="px-4 py-3 text-white/70 font-medium">Veiculo</th>
                            <th class="px-4 py-3 text-white/70 font-medium">Proprietario</th>
                            <th class="px-4 py-3 text-white/70 font-medium">Periodo</th>
                            <th class="px-4 py-3 text-white/70 font-medium">Valor</th>
                            <th class="px-4 py-3 text-white/70 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <?php foreach ($reservas as $reserva): ?>
                            <?php $statusReserva = statusHistoricoReserva($reserva); ?>
                            <tr class="hover:bg-white/5 transition-colors">
                                <td class="px-4 py-4">
                                    <p class="font-medium text-white"><?= htmlspecialchars($reserva['veiculo_marca'] . ' ' . $reserva['veiculo_modelo']) ?></p>
                                    <p class="text-sm text-white/60"><?= htmlspecialchars((string)$reserva['veiculo_placa']) ?></p>
                                </td>
                                <td class="px-4 py-4 text-white/85"><?= htmlspecialchars((string)$reserva['nome_proprietario']) ?></td>
                                <td class="px-4 py-4 text-white/85">
                                    <?= date('d/m/Y', strtotime((string)$reserva['reserva_data'])) ?>
                                    a
                                    <?= date('d/m/Y', strtotime((string)$reserva['devolucao_data'])) ?>
                                </td>
                                <td class="px-4 py-4 font-medium">R$ <?= number_format((float)$reserva['valor_total'], 2, ',', '.') ?></td>
                                <td class="px-4 py-4">
                                    <span class="px-3 py-1 rounded-full text-xs font-medium <?= htmlspecialchars($statusReserva['class']) ?>">
                                        <?= htmlspecialchars($statusReserva['label']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>
    </main>

</body>
</html>
