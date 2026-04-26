<?php
require_once 'includes/auth.php';

verificarAutenticacao();
updateInfosUsuario();

$usuario = getUsuario();
global $pdo;

include_once 'api/atualizar_status_reservas.php';

function e($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function formatarDataPainel($data): string
{
    if (empty($data)) {
        return '-';
    }

    $timestamp = strtotime((string)$data);
    return $timestamp ? date('d/m/Y', $timestamp) : '-';
}

function formatarMoedaPainel($valor): string
{
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

function obterStatusReservaPainel(array $reserva): array
{
    $statusBanco = strtolower((string)($reserva['status'] ?? 'pendente'));

    $mapaStatusFixos = [
        'rejeitada' => ['Rejeitada', 'bg-red-500/20 text-red-300 border border-red-400/30'],
        'cancelada' => ['Cancelada', 'bg-yellow-500/20 text-yellow-300 border border-yellow-400/30'],
        'pago' => ['Pago - aguardando confirmacao', 'bg-purple-500/20 text-purple-300 border border-purple-400/30'],
        'finalizada' => ['Finalizada', 'bg-slate-500/20 text-slate-300 border border-slate-400/30'],
    ];

    if (isset($mapaStatusFixos[$statusBanco])) {
        return [
            'label' => $mapaStatusFixos[$statusBanco][0],
            'class' => $mapaStatusFixos[$statusBanco][1],
        ];
    }

    $agora = time();
    $inicio = !empty($reserva['reserva_data']) ? strtotime((string)$reserva['reserva_data']) : false;
    $fim = !empty($reserva['devolucao_data']) ? strtotime((string)$reserva['devolucao_data']) : false;

    if ($inicio && $fim) {
        if ($agora < $inicio) {
            return $statusBanco === 'confirmada'
                ? ['label' => 'Confirmada', 'class' => 'bg-blue-500/20 text-blue-300 border border-blue-400/30']
                : ['label' => 'Pendente', 'class' => 'bg-amber-500/20 text-amber-300 border border-amber-400/30'];
        }

        if ($agora >= $inicio && $agora <= $fim) {
            return ['label' => 'Em andamento', 'class' => 'bg-emerald-500/20 text-emerald-300 border border-emerald-400/30'];
        }

        return ['label' => 'Concluida', 'class' => 'bg-slate-500/20 text-slate-300 border border-slate-400/30'];
    }

    return $statusBanco === 'confirmada'
        ? ['label' => 'Confirmada', 'class' => 'bg-blue-500/20 text-blue-300 border border-blue-400/30']
        : ['label' => 'Pendente', 'class' => 'bg-amber-500/20 text-amber-300 border border-amber-400/30'];
}

function reservaPodeSerCanceladaPainel(array $reserva): bool
{
    $status = strtolower((string)($reserva['status'] ?? 'pendente'));
    $inicio = !empty($reserva['reserva_data']) ? strtotime((string)$reserva['reserva_data']) : 0;

    return ($status === '' || $status === 'pendente') && $inicio > time();
}

function obterStatusVeiculoPainel($disponivel): array
{
    return ((int)$disponivel === 1)
        ? ['label' => 'Disponivel', 'class' => 'bg-emerald-500/20 text-emerald-200 border border-emerald-400/30']
        : ['label' => 'Indisponivel', 'class' => 'bg-slate-500/20 text-slate-200 border border-slate-400/30'];
}

$notificacoes = [];
foreach (['notification', 'sucesso', 'erro'] as $sessionKey) {
    if (!isset($_SESSION[$sessionKey])) {
        continue;
    }

    if (is_array($_SESSION[$sessionKey])) {
        $notificacoes[] = $_SESSION[$sessionKey];
    } else {
        $notificacoes[] = [
            'type' => $sessionKey === 'erro' ? 'error' : 'success',
            'message' => (string)$_SESSION[$sessionKey],
        ];
    }

    unset($_SESSION[$sessionKey]);
}

$usuarioId = (int)($usuario['id'] ?? 0);
$primeiroNome = trim((string)($usuario['primeiro_nome'] ?? ''));
$segundoNome = trim((string)($usuario['segundo_nome'] ?? ''));
$nomeUsuario = trim($primeiroNome . ' ' . $segundoNome);
if ($nomeUsuario === '') {
    $nomeUsuario = 'usuario';
}

$ehProprietario = usuarioEhProprietario($usuario);
$dono = null;
$donoId = null;

if ($ehProprietario) {
    $stmt = $pdo->prepare('SELECT id FROM dono WHERE conta_usuario_id = ? LIMIT 1');
    $stmt->execute([$usuarioId]);
    $dono = $stmt->fetch();
    $donoId = $dono ? (int)$dono['id'] : null;
    $ehProprietario = $donoId !== null;
}

$totalReservas = 0;
$reservasAtivas = 0;
$mensagensNaoLidas = 0;
$minhasReservas = [];
$totalVeiculos = 0;
$totalReservasRecebidas = 0;
$reservasRecebidasPendentes = 0;
$meusVeiculos = [];
$reservasRecebidas = [];

$stmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_reservas,
        SUM(
            CASE
                WHEN reserva_data >= CURDATE()
                     AND COALESCE(status, 'pendente') IN ('pendente', 'pago', 'confirmada')
                THEN 1
                ELSE 0
            END
        ) AS reservas_ativas
     FROM reserva
     WHERE conta_usuario_id = ?"
);
$stmt->execute([$usuarioId]);
$resumoReservas = $stmt->fetch();
$totalReservas = (int)($resumoReservas['total_reservas'] ?? 0);
$reservasAtivas = (int)($resumoReservas['reservas_ativas'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS nao_lidas
     FROM mensagem m
     INNER JOIN reserva r ON m.reserva_id = r.id
     INNER JOIN veiculo v ON r.veiculo_id = v.id
     INNER JOIN dono d ON v.dono_id = d.id
     WHERE (
            (r.conta_usuario_id = ? AND m.remetente_id != ?)
         OR (d.conta_usuario_id = ? AND m.remetente_id != ?)
     )
     AND m.lida = 0"
);
$stmt->execute([$usuarioId, $usuarioId, $usuarioId, $usuarioId]);
$mensagensNaoLidas = (int)($stmt->fetch()['nao_lidas'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT r.*, v.veiculo_marca, v.veiculo_modelo, v.veiculo_placa,
            CONCAT(u.primeiro_nome, ' ', u.segundo_nome) AS nome_proprietario,
            (SELECT COUNT(*)
             FROM avaliacao_veiculo av
             WHERE av.reserva_id = r.id AND av.usuario_id = ?) AS ja_avaliou
     FROM reserva r
     INNER JOIN veiculo v ON r.veiculo_id = v.id
     INNER JOIN dono d ON v.dono_id = d.id
     INNER JOIN conta_usuario u ON d.conta_usuario_id = u.id
     WHERE r.conta_usuario_id = ?
     ORDER BY r.reserva_data DESC
     LIMIT 6"
);
$stmt->execute([$usuarioId, $usuarioId]);
$minhasReservas = $stmt->fetchAll();

if ($ehProprietario && $donoId !== null) {
    $stmt = $pdo->prepare(
        "SELECT
            (SELECT COUNT(*) FROM veiculo WHERE dono_id = ?) AS total_veiculos,
            (SELECT COUNT(*)
             FROM reserva r
             INNER JOIN veiculo v ON v.id = r.veiculo_id
             WHERE v.dono_id = ?) AS total_reservas_recebidas,
            (SELECT COUNT(*)
             FROM reserva r
             INNER JOIN veiculo v ON v.id = r.veiculo_id
             WHERE v.dono_id = ?
               AND COALESCE(r.status, 'pendente') IN ('pendente', 'pago')
               AND r.reserva_data >= CURDATE()) AS reservas_pendentes"
    );
    $stmt->execute([$donoId, $donoId, $donoId]);
    $resumoProprietario = $stmt->fetch();
    $totalVeiculos = (int)($resumoProprietario['total_veiculos'] ?? 0);
    $totalReservasRecebidas = (int)($resumoProprietario['total_reservas_recebidas'] ?? 0);
    $reservasRecebidasPendentes = (int)($resumoProprietario['reservas_pendentes'] ?? 0);

    $stmt = $pdo->prepare(
        "SELECT v.*, c.categoria, l.nome_local,
                CASE WHEN v.disponivel IS NULL THEN 1 ELSE v.disponivel END AS disponivel
         FROM veiculo v
         LEFT JOIN categoria_veiculo c ON v.categoria_veiculo_id = c.id
         LEFT JOIN local l ON v.local_id = l.id
         WHERE v.dono_id = ?
         ORDER BY v.id DESC
         LIMIT 5"
    );
    $stmt->execute([$donoId]);
    $meusVeiculos = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT r.*, v.veiculo_marca, v.veiculo_modelo, v.veiculo_placa,
                CONCAT(u.primeiro_nome, ' ', u.segundo_nome) AS nome_locatario,
                u.telefone AS telefone_locatario
         FROM reserva r
         INNER JOIN veiculo v ON r.veiculo_id = v.id
         INNER JOIN conta_usuario u ON r.conta_usuario_id = u.id
         WHERE v.dono_id = ?
         ORDER BY
            CASE COALESCE(r.status, 'pendente')
                WHEN 'pago' THEN 1
                WHEN 'pendente' THEN 2
                WHEN 'confirmada' THEN 3
                ELSE 4
            END,
            r.reserva_data DESC
         LIMIT 5"
    );
    $stmt->execute([$donoId]);
    $reservasRecebidas = $stmt->fetchAll();
}

$navBasePath = '';
$navCurrent = 'painel';
$navFixed = true;
$navShowMarketplaceAnchors = false;
$navShowDashboardLink = true;
$csrfToken = obterCsrfToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Painel - DriveNow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/ui-modern.css">
    <style>
        .subtle-border { border-color: rgba(255, 255, 255, 0.1); }
        .animate-pulse-15s { animation-duration: 15s; }
        .animate-pulse-20s { animation-duration: 20s; }
        .animate-pulse-25s { animation-duration: 25s; }
        option { background-color: #1e293b; color: #fff; }
    </style>
</head>
<body class="drivenow-modern min-h-screen bg-gradient-to-br from-slate-900 via-indigo-950 to-purple-950 text-white p-4 md:p-8 overflow-x-hidden">
    <div class="fixed top-0 right-0 w-96 h-96 rounded-full bg-indigo-700 opacity-10 blur-3xl -z-10 animate-pulse animate-pulse-15s"></div>
    <div class="fixed bottom-0 left-0 w-80 h-80 rounded-full bg-purple-700 opacity-10 blur-3xl -z-10 animate-pulse animate-pulse-20s"></div>
    <div class="fixed top-1/3 left-1/4 w-64 h-64 rounded-full bg-slate-700 opacity-5 blur-3xl -z-10 animate-pulse animate-pulse-25s"></div>

    <?php include_once 'includes/navbar.php'; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 pt-28 pb-12">
        <section class="hero-surface section-shell backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl p-6 md:p-8 shadow-lg mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div class="max-w-3xl">
                    <p class="text-indigo-200/90 text-xs uppercase tracking-[0.18em] font-semibold mb-3">Painel da conta</p>
                    <h1 class="text-3xl md:text-5xl font-bold text-white">Ola, <?= e($primeiroNome !== '' ? $primeiroNome : $nomeUsuario) ?></h1>
                    <p class="text-white/70 mt-3 text-sm md:text-base">Acompanhe reservas, mensagens e atalhos importantes da sua conta em um unico lugar.</p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <a href="perfil/editar.php" class="btn-dn-ghost px-4 py-2 rounded-xl border subtle-border hover:bg-white/10 transition-colors">Perfil</a>
                    <a href="reserva/minhas_reservas.php" class="btn-dn-ghost px-4 py-2 rounded-xl border subtle-border hover:bg-white/10 transition-colors">Minhas Reservas</a>
                    <a href="mensagens/mensagens.php" class="btn-dn-ghost px-4 py-2 rounded-xl border subtle-border hover:bg-white/10 transition-colors">Mensagens</a>
                    <?php if ($ehProprietario): ?>
                        <button type="button" onclick="openVeiculoModal()" class="btn-dn-primary px-4 py-2 rounded-xl bg-indigo-500 hover:bg-indigo-600 font-medium transition-colors">Adicionar veiculo</button>
                        <a href="veiculo/veiculos.php" class="btn-dn-ghost px-4 py-2 rounded-xl border subtle-border hover:bg-white/10 transition-colors">Gerenciar veiculos</a>
                    <?php else: ?>
                        <button type="button" onclick="openModalProprietario()" class="btn-dn-primary px-4 py-2 rounded-xl bg-indigo-500 hover:bg-indigo-600 font-medium transition-colors">Tornar-se proprietario</button>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            <div class="section-shell rounded-3xl border subtle-border bg-white/5 p-5">
                <p class="text-white/60 text-sm">Minhas reservas</p>
                <p class="text-3xl font-bold mt-2"><?= $totalReservas ?></p>
                <a href="reserva/minhas_reservas.php" class="text-indigo-200 hover:text-white text-sm mt-3 inline-block">Ver historico</a>
            </div>
            <div class="section-shell rounded-3xl border subtle-border bg-white/5 p-5">
                <p class="text-white/60 text-sm">Reservas ativas</p>
                <p class="text-3xl font-bold mt-2"><?= $reservasAtivas ?></p>
                <a href="reserva/listagem_veiculos.php" class="text-indigo-200 hover:text-white text-sm mt-3 inline-block">Nova reserva</a>
            </div>
            <div class="section-shell rounded-3xl border subtle-border bg-white/5 p-5">
                <p class="text-white/60 text-sm">Mensagens nao lidas</p>
                <p class="text-3xl font-bold mt-2"><?= $mensagensNaoLidas ?></p>
                <a href="mensagens/mensagens.php" class="text-indigo-200 hover:text-white text-sm mt-3 inline-block">Abrir conversas</a>
            </div>
            <div class="section-shell rounded-3xl border subtle-border bg-white/5 p-5">
                <?php if ($ehProprietario): ?>
                    <p class="text-white/60 text-sm">Meus veiculos</p>
                    <p class="text-3xl font-bold mt-2"><?= $totalVeiculos ?></p>
                    <p class="text-white/60 text-xs mt-3">Reservas recebidas: <?= $totalReservasRecebidas ?>, pendentes: <?= $reservasRecebidasPendentes ?></p>
                <?php else: ?>
                    <p class="text-white/60 text-sm">Modo proprietario</p>
                    <p class="text-lg font-semibold mt-2">Disponivel</p>
                    <button type="button" onclick="openModalProprietario()" class="text-indigo-200 hover:text-white text-sm mt-3 inline-block">Ativar agora</button>
                <?php endif; ?>
            </div>
        </section>

        <section class="section-shell backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl p-6 shadow-lg mb-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                <div>
                    <h2 class="text-2xl font-bold">Minhas reservas</h2>
                    <p class="text-white/70 text-sm mt-1">Ultimas reservas feitas pela sua conta.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="reserva/listagem_veiculos.php" class="btn-dn-primary px-4 py-2 rounded-xl bg-indigo-500 hover:bg-indigo-600 font-medium transition-colors">Nova reserva</a>
                    <a href="reserva/minhas_reservas.php" class="btn-dn-ghost px-4 py-2 rounded-xl border subtle-border hover:bg-white/10 transition-colors">Pagina completa</a>
                </div>
            </div>

            <?php if (empty($minhasReservas)): ?>
                <div class="rounded-2xl border subtle-border bg-white/5 p-6 text-center">
                    <h3 class="font-semibold text-lg mb-2">Voce ainda nao possui reservas</h3>
                    <p class="text-white/70 text-sm mb-4">Explore a frota e encontre um veiculo para sua proxima viagem.</p>
                    <a href="reserva/catalogo.php" class="btn-dn-primary inline-flex px-5 py-2.5 rounded-xl bg-indigo-500 hover:bg-indigo-600 font-medium transition-colors">Ver veiculos</a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="dn-table w-full min-w-[860px]">
                        <thead class="border-b border-white/10 text-left">
                            <tr>
                                <th class="px-4 py-3 text-white/70 font-medium">Veiculo</th>
                                <th class="px-4 py-3 text-white/70 font-medium">Proprietario</th>
                                <th class="px-4 py-3 text-white/70 font-medium">Periodo</th>
                                <th class="px-4 py-3 text-white/70 font-medium">Valor</th>
                                <th class="px-4 py-3 text-white/70 font-medium">Status</th>
                                <th class="px-4 py-3 text-white/70 font-medium">Acoes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <?php foreach ($minhasReservas as $reserva): ?>
                                <?php $statusReserva = obterStatusReservaPainel($reserva); ?>
                                <tr class="hover:bg-white/5 transition-colors">
                                    <td class="px-4 py-4">
                                        <p class="font-medium"><?= e($reserva['veiculo_marca']) ?> <?= e($reserva['veiculo_modelo']) ?></p>
                                        <p class="text-sm text-white/60"><?= e($reserva['veiculo_placa']) ?></p>
                                    </td>
                                    <td class="px-4 py-4 text-white/85"><?= e($reserva['nome_proprietario']) ?></td>
                                    <td class="px-4 py-4 text-white/85"><?= e(formatarDataPainel($reserva['reserva_data'])) ?> a <?= e(formatarDataPainel($reserva['devolucao_data'])) ?></td>
                                    <td class="px-4 py-4 font-medium"><?= e(formatarMoedaPainel($reserva['valor_total'])) ?></td>
                                    <td class="px-4 py-4">
                                        <span class="px-3 py-1 rounded-full text-xs font-medium <?= e($statusReserva['class']) ?>"><?= e($statusReserva['label']) ?></span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            <a href="reserva/detalhes_reserva.php?id=<?= (int)$reserva['id'] ?>" class="btn-dn-ghost bg-indigo-500/20 hover:bg-indigo-500/30 text-indigo-200 border border-indigo-400/30 rounded-lg px-3 py-1 text-sm font-medium transition-colors">Detalhes</a>
                                            <?php if (reservaPodeSerCanceladaPainel($reserva)): ?>
                                                <form method="POST" action="reserva/cancelar_reserva.php" class="inline-block" onsubmit="return confirm('Tem certeza que deseja cancelar esta reserva?')">
                                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                    <input type="hidden" name="reserva_id" value="<?= (int)$reserva['id'] ?>">
                                                    <button type="submit" class="btn-dn-ghost bg-red-500/20 hover:bg-red-500/30 text-red-300 border border-red-400/30 rounded-lg px-3 py-1 text-sm font-medium transition-colors">Cancelar</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (($reserva['status'] ?? '') === 'finalizada' && (int)($reserva['ja_avaliou'] ?? 0) === 0): ?>
                                                <a href="avaliacao/avaliar_veiculo.php?reserva=<?= (int)$reserva['id'] ?>" class="btn-dn-ghost bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 border border-emerald-400/30 rounded-lg px-3 py-1 text-sm font-medium transition-colors">Avaliar</a>
                                            <?php elseif (($reserva['status'] ?? '') === 'finalizada'): ?>
                                                <span class="bg-slate-500/20 text-slate-300 border border-slate-400/30 rounded-lg px-3 py-1 text-sm font-medium">Avaliado</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($ehProprietario): ?>
            <section class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
                <div class="section-shell backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl p-6 shadow-lg">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                        <div>
                            <h2 class="text-2xl font-bold">Meus veiculos</h2>
                            <p class="text-white/70 text-sm mt-1">Resumo da sua frota cadastrada.</p>
                        </div>
                        <a href="veiculo/veiculos.php" class="btn-dn-ghost px-4 py-2 rounded-xl border subtle-border hover:bg-white/10 transition-colors">Gerenciar</a>
                    </div>

                    <?php if (empty($meusVeiculos)): ?>
                        <div class="rounded-2xl border subtle-border bg-white/5 p-6 text-center">
                            <h3 class="font-semibold text-lg mb-2">Nenhum veiculo cadastrado</h3>
                            <p class="text-white/70 text-sm mb-4">Cadastre seu primeiro veiculo para comecar a receber reservas.</p>
                            <button type="button" onclick="openVeiculoModal()" class="btn-dn-primary inline-flex px-5 py-2.5 rounded-xl bg-indigo-500 hover:bg-indigo-600 font-medium transition-colors">Adicionar veiculo</button>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($meusVeiculos as $veiculo): ?>
                                <?php
                                    $disponivel = $veiculo['disponivel'] ?? 1;
                                    $statusVeiculo = obterStatusVeiculoPainel($disponivel);
                                ?>
                                <div class="rounded-2xl border subtle-border bg-white/5 p-4">
                                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                        <div>
                                            <p class="font-semibold"><?= e($veiculo['veiculo_marca']) ?> <?= e($veiculo['veiculo_modelo']) ?></p>
                                            <p class="text-sm text-white/60"><?= e($veiculo['veiculo_placa']) ?> - <?= e($veiculo['categoria'] ?? 'Sem categoria') ?> - <?= e($veiculo['nome_local'] ?? 'Sem local') ?></p>
                                            <p class="text-sm text-white/80 mt-1"><?= e(formatarMoedaPainel($veiculo['preco_diaria'])) ?> / diaria</p>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="px-3 py-1 rounded-full text-xs font-medium <?= e($statusVeiculo['class']) ?>"><?= e($statusVeiculo['label']) ?></span>
                                            <a href="reserva/detalhes_veiculo.php?id=<?= (int)$veiculo['id'] ?>#calendario" class="p-2 rounded-lg bg-indigo-500/20 text-indigo-100 border border-indigo-400/30 hover:bg-indigo-500/40 transition-colors" title="Calendario" aria-label="Calendario">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M8 2v4"></path>
                                                    <path d="M16 2v4"></path>
                                                    <rect width="18" height="18" x="3" y="4" rx="2"></rect>
                                                    <path d="M3 10h18"></path>
                                                </svg>
                                            </a>
                                            <a href="veiculo/editar.php?id=<?= (int)$veiculo['id'] ?>" class="p-2 rounded-lg bg-amber-500/20 text-amber-100 border border-amber-400/30 hover:bg-amber-500/40 transition-colors" title="Editar" aria-label="Editar">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                                </svg>
                                            </a>
                                            <form method="POST" action="veiculo/ativar.php" class="inline-block">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                <input type="hidden" name="veiculo_id" value="<?= (int)$veiculo['id'] ?>">
                                                <input type="hidden" name="status" value="<?= (int)$disponivel === 1 ? 0 : 1 ?>">
                                                <button type="submit" class="p-2 rounded-lg border transition-colors <?= (int)$disponivel === 1 ? 'bg-slate-500/20 text-slate-100 border-slate-400/30 hover:bg-slate-500/40' : 'bg-cyan-500/20 text-cyan-100 border-cyan-400/30 hover:bg-cyan-500/40' ?>" title="<?= (int)$disponivel === 1 ? 'Desativar' : 'Ativar' ?>" aria-label="<?= (int)$disponivel === 1 ? 'Desativar' : 'Ativar' ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path>
                                                        <line x1="12" y1="2" x2="12" y2="12"></line>
                                                    </svg>
                                                </button>
                                            </form>
                                            <form method="POST" action="veiculo/excluir.php" class="inline-block" onsubmit="return confirm('Tem certeza que deseja excluir este veiculo?')">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                <input type="hidden" name="veiculo_id" value="<?= (int)$veiculo['id'] ?>">
                                                <button type="submit" class="p-2 rounded-lg bg-red-500/20 text-red-100 border border-red-400/30 hover:bg-red-500/40 transition-colors" title="Excluir" aria-label="Excluir">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <polyline points="3 6 5 6 21 6"></polyline>
                                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                        <line x1="10" y1="11" x2="10" y2="17"></line>
                                                        <line x1="14" y1="11" x2="14" y2="17"></line>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="section-shell backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl p-6 shadow-lg">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                        <div>
                            <h2 class="text-2xl font-bold">Reservas recebidas</h2>
                            <p class="text-white/70 text-sm mt-1">Visao rapida das solicitacoes da sua frota.</p>
                        </div>
                        <a href="reserva/reservas_recebidas.php" class="btn-dn-ghost px-4 py-2 rounded-xl border subtle-border hover:bg-white/10 transition-colors">Pagina completa</a>
                    </div>

                    <?php if (empty($reservasRecebidas)): ?>
                        <div class="rounded-2xl border subtle-border bg-white/5 p-6 text-center">
                            <h3 class="font-semibold text-lg mb-2">Nenhuma reserva recebida</h3>
                            <p class="text-white/70 text-sm">As solicitacoes dos seus veiculos aparecerao aqui.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($reservasRecebidas as $reservaRecebida): ?>
                                <?php $statusRecebida = obterStatusReservaPainel($reservaRecebida); ?>
                                <div class="rounded-2xl border subtle-border bg-white/5 p-4">
                                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                        <div>
                                            <p class="font-semibold"><?= e($reservaRecebida['veiculo_marca']) ?> <?= e($reservaRecebida['veiculo_modelo']) ?></p>
                                            <p class="text-sm text-white/60">Locatario: <?= e($reservaRecebida['nome_locatario']) ?></p>
                                            <p class="text-sm text-white/60"><?= e(formatarDataPainel($reservaRecebida['reserva_data'])) ?> a <?= e(formatarDataPainel($reservaRecebida['devolucao_data'])) ?></p>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="px-3 py-1 rounded-full text-xs font-medium <?= e($statusRecebida['class']) ?>"><?= e($statusRecebida['label']) ?></span>
                                            <a href="reserva/detalhes_reserva.php?id=<?= (int)$reservaRecebida['id'] ?>" class="btn-dn-ghost bg-indigo-500/20 hover:bg-indigo-500/30 text-indigo-200 border border-indigo-400/30 rounded-lg px-3 py-1 text-sm font-medium transition-colors">Detalhes</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php else: ?>
            <section class="section-shell backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl p-6 shadow-lg mb-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-bold">Modo proprietario</h2>
                        <p class="text-white/70 text-sm mt-1">Cadastre veiculos, receba reservas e acompanhe tudo por este painel.</p>
                    </div>
                    <button type="button" onclick="openModalProprietario()" class="btn-dn-primary px-5 py-2.5 rounded-xl bg-indigo-500 hover:bg-indigo-600 font-medium transition-colors">Tornar-se proprietario</button>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <footer class="max-w-7xl mx-auto px-4 sm:px-6 pb-8 text-center text-white/60 text-sm">
        <p>&copy; <?= date('Y') ?> DriveNow. Todos os direitos reservados.</p>
    </footer>

    <?php if (!$ehProprietario): ?>
        <div id="proprietarioModal" class="fixed inset-0 bg-black/60 backdrop-blur-md z-50 flex items-center justify-center hidden">
            <div class="w-full max-w-lg backdrop-blur-lg bg-white/10 border subtle-border rounded-3xl p-6 shadow-xl">
                <div class="flex items-center gap-4 mb-6">
                    <div class="p-3 rounded-2xl bg-indigo-500/30 text-white border border-indigo-400/30">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6">
                            <path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/>
                            <path d="M7 17h10"/>
                            <circle cx="7" cy="17" r="2"/>
                            <circle cx="17" cy="17" r="2"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-white">Tornar-se proprietario</h3>
                    <button type="button" onclick="closeModalProprietario()" class="ml-auto text-white/70 hover:text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>

                <div id="modalError" class="mb-4 rounded-xl bg-red-500/20 border border-red-400/30 text-white px-4 py-3 hidden"></div>
                <p class="text-white/80 mb-5 text-sm leading-relaxed">Ao se registrar como proprietario, voce podera cadastrar e gerenciar veiculos, receber reservas e acompanhar sua frota.</p>
                <p class="text-white/70 text-sm mb-6">Ao confirmar, voce concorda com os <a href="termos_proprietario.html" target="_blank" class="text-indigo-300 hover:text-indigo-200 underline">Termos para Proprietarios</a> e com a <a href="politicas.html" target="_blank" class="text-indigo-300 hover:text-indigo-200 underline">Politica de Uso</a>.</p>

                <div class="flex gap-3">
                    <button type="button" id="btnConfirmarRegistro" class="flex-1 rounded-xl bg-indigo-500 hover:bg-indigo-600 transition-colors px-4 py-2 font-medium">
                        <span>Confirmar registro</span>
                    </button>
                    <button type="button" onclick="closeModalProprietario()" class="flex-1 rounded-xl border subtle-border hover:bg-white/10 transition-colors px-4 py-2 font-medium">Cancelar</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($ehProprietario): ?>
        <?php include_once 'components/modal_veiculo.php'; ?>
    <?php endif; ?>

    <script src="assets/notifications.js"></script>
    <?php if ($ehProprietario): ?>
        <script src="components/modal_veiculo.js"></script>
    <?php endif; ?>

    <script>
        function openModalProprietario() {
            const modal = document.getElementById('proprietarioModal');
            if (!modal) {
                return;
            }

            modal.classList.remove('hidden');
            const modalError = document.getElementById('modalError');
            if (modalError) {
                modalError.classList.add('hidden');
                modalError.textContent = '';
            }
        }

        function closeModalProprietario() {
            const modal = document.getElementById('proprietarioModal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModalProprietario();
                if (typeof closeVeiculoModal === 'function') {
                    closeVeiculoModal();
                }
            }
        });

        const btnConfirmarRegistro = document.getElementById('btnConfirmarRegistro');
        if (btnConfirmarRegistro) {
            btnConfirmarRegistro.addEventListener('click', function() {
                const button = this;
                const label = button.querySelector('span');
                const textoOriginal = label.textContent;

                button.disabled = true;
                label.textContent = 'Processando...';

                fetch('perfil/registrar_proprietario.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                    .then(function(response) {
                        return response.json();
                    })
                    .then(function(data) {
                        button.disabled = false;
                        label.textContent = textoOriginal;

                        if (data.status === 'success') {
                            closeModalProprietario();
                            notifySuccess(data.message || 'Registro realizado com sucesso.');
                            setTimeout(function() {
                                window.location.reload();
                            }, 1200);
                            return;
                        }

                        const modalError = document.getElementById('modalError');
                        if (modalError) {
                            modalError.textContent = data.message || 'Nao foi possivel concluir o registro.';
                            modalError.classList.remove('hidden');
                        }

                        notifyError(data.message || 'Nao foi possivel concluir o registro.');
                    })
                    .catch(function() {
                        button.disabled = false;
                        label.textContent = textoOriginal;
                        notifyError('Erro de comunicacao com o servidor. Tente novamente.');
                    });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($notificacoes as $notificacao): ?>
                <?php
                    $tipo = strtolower((string)($notificacao['type'] ?? 'info'));
                    $mensagem = (string)($notificacao['message'] ?? '');
                ?>
                <?php if ($tipo === 'success'): ?>
                    notifySuccess(<?= json_encode($mensagem) ?>);
                <?php elseif ($tipo === 'error'): ?>
                    notifyError(<?= json_encode($mensagem) ?>, 9000);
                <?php elseif ($tipo === 'warning'): ?>
                    notifyWarning(<?= json_encode($mensagem) ?>);
                <?php else: ?>
                    notifyInfo(<?= json_encode($mensagem) ?>);
                <?php endif; ?>
            <?php endforeach; ?>

            const params = new URLSearchParams(window.location.search);
            if (params.get('openModal') === 'veiculos' && typeof openVeiculoModal === 'function') {
                openVeiculoModal();
            }
        });
    </script>
</body>
</html>
