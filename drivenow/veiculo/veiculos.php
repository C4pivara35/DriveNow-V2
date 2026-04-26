<?php
require_once '../includes/auth.php';

exigirPerfil('proprietario', ['redirect' => '../vboard.php']);

// Define a variável global $usuario para uso nas páginas
$usuario = getUsuario();

// Verificar se o usuário é um dono e obter estatísticas relevantes
global $pdo;
$stmt = $pdo->prepare("SELECT id FROM dono WHERE conta_usuario_id = ?");
$stmt->execute([$usuario['id']]);
$dono = $stmt->fetch();

$totalVeiculos = 0;
$totalReservas = 0;
$reservasAtivas = 0;

if ($dono) {
    // Consulta otimizada: obtém múltiplas estatísticas em uma única query
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM veiculo WHERE dono_id = :dono_id_veiculos) AS total_veiculos,
            (SELECT COUNT(*) FROM reserva r 
             JOIN veiculo v ON r.veiculo_id = v.id 
             WHERE v.dono_id = :dono_id_reservas) AS total_reservas
    ");
    $stmt->bindValue(':dono_id_veiculos', $dono['id'], PDO::PARAM_INT);
    $stmt->bindValue(':dono_id_reservas', $dono['id'], PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    
    $totalVeiculos = $result['total_veiculos'];
    $totalReservas = $result['total_reservas'];

    // Buscar veículos do dono com informações de categoria e local
    $stmt = $pdo->prepare("SELECT v.*, c.categoria, l.nome_local,
                          (
                              SELECT i.imagem_url
                              FROM imagem i
                              WHERE i.veiculo_id = v.id
                              ORDER BY i.imagem_ordem IS NULL, i.imagem_ordem, i.id
                              LIMIT 1
                          ) AS imagem_url,
                          CASE WHEN v.disponivel IS NULL THEN 1 ELSE v.disponivel END as disponivel 
                          FROM veiculo v
                          LEFT JOIN categoria_veiculo c ON v.categoria_veiculo_id = c.id
                          LEFT JOIN local l ON v.local_id = l.id
                          WHERE v.dono_id = ? 
                          ORDER BY v.id DESC");
    
    $stmt->execute([$dono['id']]);
    $veiculos = $stmt->fetchAll();
} else {
    $veiculos = [];
}

// Consulta otimizada para reservas do usuário
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS total_minhas_reservas,
        SUM(CASE WHEN reserva_data >= CURDATE() THEN 1 ELSE 0 END) AS reservas_ativas
    FROM reserva 
    WHERE conta_usuario_id = :usuario_id
");
$stmt->bindParam(':usuario_id', $usuario['id']);
$stmt->execute();
$resultReservas = $stmt->fetch();

$minhasReservas = $resultReservas['total_minhas_reservas'];
$reservasAtivas = $resultReservas['reservas_ativas'];

$navBasePath = '../';
$navCurrent = 'meus-veiculos';
$navFixed = true;
$navShowMarketplaceAnchors = false;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Veiculos - DriveNow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/ui-modern.css">
    <style>
        /* Para garantir que os orbes de vidro funcionem com a animação de pulso do Tailwind e duração customizada */
        .animate-pulse-15s { animation-duration: 15s; }
        .animate-pulse-20s { animation-duration: 20s; }
        .animate-pulse-25s { animation-duration: 25s; }

        /* Para o efeito de borda sutil nos cards e header quando o fundo é muito escuro */
        .subtle-border {
            border-color: rgba(255, 255, 255, 0.1); /* Ajuste a opacidade conforme necessário */
        }

        option {
            background-color: #172554; /* Azul-marinho profundo */
            color: white;
        }
    </style>
</head>
<body class="drivenow-modern min-h-screen bg-gradient-to-br from-slate-900 via-indigo-950 to-purple-950 text-white p-4 md:p-8 overflow-x-hidden">

    <div class="fixed top-0 right-0 w-96 h-96 rounded-full bg-indigo-700 opacity-10 blur-3xl -z-10 animate-pulse animate-pulse-15s"></div>
    <div class="fixed bottom-0 left-0 w-80 h-80 rounded-full bg-purple-700 opacity-10 blur-3xl -z-10 animate-pulse animate-pulse-20s"></div>
    <div class="fixed top-1/3 left-1/4 w-64 h-64 rounded-full bg-slate-700 opacity-5 blur-3xl -z-10 animate-pulse animate-pulse-25s"></div>

    <?php include_once '../includes/navbar.php'; ?>

    <main class="container mx-auto px-4 pt-28 pb-8">
        <div class="container mx-auto px-4 py-8">
            <div class="flex flex-col md:flex-row justify-between items-center mb-6">
                <h2 class="text-2xl md:text-3xl font-bold text-white mb-4 md:mb-0">Meus Veículos</h2>
                <?php if ($dono): ?>
                    <div class="flex gap-3">
                        <a href="../vboard.php" class="btn-dn-primary bg-red-500 hover:bg-red-600 text-white rounded-xl transition-colors border border-red-400/30 px-4 py-2 font-medium shadow-md hover:shadow-lg flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 mr-2">
                                <path d="m12 19-7-7 7-7"></path>
                                <path d="M19 12H5"></path>
                            </svg>
                            <span>Voltar</span>
                        </a>
                        <button onclick="openVeiculoModal()" class="btn-dn-primary bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl transition-colors border border-emerald-400/30 px-4 py-2 font-medium shadow-md hover:shadow-lg flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Adicionar Veículo
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (empty($veiculos)): ?>
                <div class="section-shell backdrop-blur-lg bg-indigo-500/20 border border-indigo-400/30 text-white px-6 py-4 rounded-xl mb-6">
                    <?php if ($dono): ?>
                        <p class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                <line x1="12" y1="8" x2="12.01" y2="8"></line>
                            </svg>
                            Você ainda não possui veículos cadastrados.
                        </p>
                    <?php else: ?>
                        <p class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                <line x1="12" y1="8" x2="12.01" y2="8"></line>
                            </svg>
                            Você não é registrado como proprietário de veículos.
                        </p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                    <?php foreach ($veiculos as $veiculo):
                        // Definir disponivel como 1 (disponivel) por padrao se nao existir
                        $disponivel = $veiculo['disponivel'] ?? 1;
                        $kmFormatado = $veiculo['veiculo_km'] ? number_format($veiculo['veiculo_km'], 0, ',', '.') . ' km' : '-';
                        $toggleLabel = $disponivel == 1 ? 'Desativar' : 'Ativar';
                    ?>
                        <article class="section-shell flex h-full flex-col overflow-hidden rounded-3xl bg-white/5 border subtle-border shadow-lg backdrop-blur-lg transition-colors hover:bg-white/10">
                            <div class="h-44 bg-slate-950/40 border-b subtle-border">
                                <?php if (!empty($veiculo['imagem_url'])): ?>
                                    <img src="../<?= htmlspecialchars(ltrim((string)$veiculo['imagem_url'], '/')) ?>" alt="<?= htmlspecialchars($veiculo['veiculo_marca'] . ' ' . $veiculo['veiculo_modelo']) ?>" class="h-full w-full object-cover">
                                <?php else: ?>
                                    <div class="flex h-full items-center justify-center text-white/50">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/>
                                            <path d="M7 17h10"/>
                                            <circle cx="7" cy="17" r="2"/>
                                            <circle cx="17" cy="17" r="2"/>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="p-5 border-b subtle-border">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-xs uppercase tracking-wide text-white/50">Veiculo</p>
                                        <h3 class="mt-1 text-xl font-bold text-white break-words">
                                            <?= htmlspecialchars($veiculo['veiculo_marca']) ?> <?= htmlspecialchars($veiculo['veiculo_modelo']) ?>
                                        </h3>
                                        <p class="mt-2 inline-flex rounded-full bg-slate-950/40 px-3 py-1 font-mono text-sm text-indigo-100 border border-white/10">
                                            <?= htmlspecialchars($veiculo['veiculo_placa']) ?>
                                        </p>
                                    </div>

                                    <?php if ($disponivel == 1): ?>
                                        <span class="shrink-0 px-3 py-1 rounded-full text-xs font-medium bg-emerald-500/30 text-emerald-100 border border-emerald-400/30">Disponivel</span>
                                    <?php else: ?>
                                        <span class="shrink-0 px-3 py-1 rounded-full text-xs font-medium bg-gray-500/30 text-gray-100 border border-gray-400/30">Indisponivel</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3 p-5 text-sm">
                                <div class="rounded-2xl bg-white/5 border border-white/10 p-3">
                                    <p class="text-white/50">Ano</p>
                                    <p class="mt-1 font-semibold text-white"><?= htmlspecialchars($veiculo['veiculo_ano']) ?></p>
                                </div>
                                <div class="rounded-2xl bg-white/5 border border-white/10 p-3">
                                    <p class="text-white/50">KM</p>
                                    <p class="mt-1 font-semibold text-white"><?= $kmFormatado ?></p>
                                </div>
                                <div class="rounded-2xl bg-white/5 border border-white/10 p-3">
                                    <p class="text-white/50">Cambio</p>
                                    <p class="mt-1 font-semibold text-white break-words"><?= htmlspecialchars($veiculo['veiculo_cambio']) ?></p>
                                </div>
                                <div class="rounded-2xl bg-white/5 border border-white/10 p-3">
                                    <p class="text-white/50">Combustivel</p>
                                    <p class="mt-1 font-semibold text-white break-words"><?= htmlspecialchars($veiculo['veiculo_combustivel']) ?></p>
                                </div>
                                <div class="rounded-2xl bg-white/5 border border-white/10 p-3">
                                    <p class="text-white/50">Tracao</p>
                                    <p class="mt-1 font-semibold text-white break-words"><?= htmlspecialchars($veiculo['veiculo_tracao']) ?></p>
                                </div>
                                <div class="rounded-2xl bg-white/5 border border-white/10 p-3">
                                    <p class="text-white/50">Categoria</p>
                                    <p class="mt-1 font-semibold text-white break-words"><?= htmlspecialchars($veiculo['categoria'] ?? '-') ?></p>
                                </div>
                                <div class="col-span-2 rounded-2xl bg-white/5 border border-white/10 p-3">
                                    <p class="text-white/50">Localizacao</p>
                                    <p class="mt-1 font-semibold text-white break-words"><?= htmlspecialchars($veiculo['nome_local'] ?? '-') ?></p>
                                </div>
                                <div class="col-span-2 rounded-2xl bg-indigo-500/15 border border-indigo-400/30 p-3">
                                    <p class="text-indigo-100/70">Diario</p>
                                    <p class="mt-1 text-lg font-bold text-white">R$ <?= number_format($veiculo['preco_diaria'], 2, ',', '.') ?></p>
                                </div>
                            </div>

                            <div class="mt-auto flex flex-wrap items-center gap-2 border-t subtle-border p-5">
                                <a href="../reserva/detalhes_veiculo.php?id=<?= (int)$veiculo['id'] ?>#calendario" class="inline-flex flex-1 min-w-[8.5rem] items-center justify-center gap-2 rounded-xl bg-indigo-500/20 px-3 py-2 text-sm font-medium text-indigo-100 border border-indigo-400/30 hover:bg-indigo-500/40 transition-colors" title="Calendario" aria-label="Calendario">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M8 2v4"></path>
                                        <path d="M16 2v4"></path>
                                        <rect width="18" height="18" x="3" y="4" rx="2"></rect>
                                        <path d="M3 10h18"></path>
                                    </svg>
                                    <span>Calendario</span>
                                </a>
                                <a href="./editar.php?id=<?= (int)$veiculo['id'] ?>" class="inline-flex flex-1 min-w-[8.5rem] items-center justify-center gap-2 rounded-xl bg-amber-500/20 px-3 py-2 text-sm font-medium text-amber-100 border border-amber-400/30 hover:bg-amber-500/40 transition-colors" title="Editar" aria-label="Editar">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                    <span>Editar</span>
                                </a>
                                <form method="POST" action="./ativar.php" class="flex-1 min-w-[8.5rem]">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(obterCsrfToken()) ?>">
                                    <input type="hidden" name="veiculo_id" value="<?= (int)$veiculo['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $disponivel == 1 ? 0 : 1 ?>">
                                    <button type="submit"
                                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl px-3 py-2 text-sm font-medium <?= $disponivel == 1 ? 'bg-gray-500/20 text-gray-100 border border-gray-400/30 hover:bg-gray-500/40' : 'bg-cyan-500/20 text-cyan-100 border border-cyan-400/30 hover:bg-cyan-500/40' ?> transition-colors"
                                        title="<?= $toggleLabel ?>"
                                        aria-label="<?= $toggleLabel ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path>
                                            <line x1="12" y1="2" x2="12" y2="12"></line>
                                        </svg>
                                        <span><?= $toggleLabel ?></span>
                                    </button>
                                </form>
                                <form method="POST" action="./excluir.php" class="flex-1 min-w-[8.5rem]" onsubmit="return confirm('Tem certeza que deseja excluir este veiculo?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(obterCsrfToken()) ?>">
                                    <input type="hidden" name="veiculo_id" value="<?= (int)$veiculo['id'] ?>">
                                    <button type="submit"
                                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-red-500/20 px-3 py-2 text-sm font-medium text-red-100 border border-red-400/30 hover:bg-red-500/40 transition-colors"
                                        title="Excluir"
                                        aria-label="Excluir">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="3 6 5 6 21 6"></polyline>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            <line x1="10" y1="11" x2="10" y2="17"></line>
                                            <line x1="14" y1="11" x2="14" y2="17"></line>
                                        </svg>
                                        <span>Excluir</span>
                                    </button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="hidden section-shell overflow-x-auto backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl shadow-lg">
                    <table class="dn-table w-full min-w-max">
                        <thead>
                            <tr class="bg-indigo-900/40 text-white text-sm uppercase">
                                <th class="px-4 py-3 text-left">Marca</th>
                                <th class="px-4 py-3 text-left">Modelo</th>
                                <th class="px-4 py-3 text-center">Placa</th>
                                <th class="px-4 py-3 text-center">Ano</th>
                                <th class="px-4 py-3 text-center">KM</th>
                                <th class="px-4 py-3 text-center">Câmbio</th>
                                <th class="px-4 py-3 text-center">Combustível</th>
                                <th class="px-4 py-3 text-center">Tração</th>
                                <th class="px-4 py-3 text-center">Categoria</th>
                                <th class="px-4 py-3 text-center">Localização</th>
                                <th class="px-4 py-3 text-center">Diário</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            <?php foreach ($veiculos as $veiculo): 
                                // Definir disponivel como 1 (disponível) por padrão se não existir
                                $disponivel = $veiculo['disponivel'] ?? 1;
                            ?>
                                <tr class="hover:bg-white/5 transition-colors text-white">
                                    <td class="px-4 py-3 text-left"><?= htmlspecialchars($veiculo['veiculo_marca']) ?></td>
                                    <td class="px-4 py-3 text-left"><?= htmlspecialchars($veiculo['veiculo_modelo']) ?></td>
                                    <td class="px-4 py-3 text-center font-mono"><?= htmlspecialchars($veiculo['veiculo_placa']) ?></td>
                                    <td class="px-4 py-3 text-center"><?= htmlspecialchars($veiculo['veiculo_ano']) ?></td>
                                    <td class="px-4 py-3 text-center"><?= $veiculo['veiculo_km'] ? number_format($veiculo['veiculo_km'], 0, ',', '.') . ' km' : '-' ?></td>
                                    <td class="px-4 py-3 text-center"><?= htmlspecialchars($veiculo['veiculo_cambio']) ?></td>
                                    <td class="px-4 py-3 text-center"><?= htmlspecialchars($veiculo['veiculo_combustivel']) ?></td>
                                    <td class="px-4 py-3 text-center"><?= htmlspecialchars($veiculo['veiculo_tracao']) ?></td>
                                    <td class="px-4 py-3 text-center"><?= htmlspecialchars($veiculo['categoria'] ?? '-') ?></td>
                                    <td class="px-4 py-3 text-center"><?= htmlspecialchars($veiculo['nome_local'] ?? '-') ?></td>
                                    <td class="px-4 py-3 text-center font-medium">R$ <?= number_format($veiculo['preco_diaria'], 2, ',', '.') ?></td>
                                    <td class="px-4 py-3 text-center">
                                        <?php if ($disponivel == 1): ?>
                                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-emerald-500/30 text-emerald-100 border border-emerald-400/30">Disponível</span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-gray-500/30 text-gray-100 border border-gray-400/30">Indisponível</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex gap-2 justify-center">
                                            <a href="../reserva/detalhes_veiculo.php?id=<?= (int)$veiculo['id'] ?>#calendario" class="p-1.5 rounded-lg bg-indigo-500/20 text-indigo-100 border border-indigo-400/30 hover:bg-indigo-500/40 transition-colors" title="Calendario">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M8 2v4"></path>
                                                    <path d="M16 2v4"></path>
                                                    <rect width="18" height="18" x="3" y="4" rx="2"></rect>
                                                    <path d="M3 10h18"></path>
                                                </svg>
                                            </a>
                                            <a href="./editar.php?id=<?= $veiculo['id'] ?>" class="p-1.5 rounded-lg bg-amber-500/20 text-amber-100 border border-amber-400/30 hover:bg-amber-500/40 transition-colors" title="Editar">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                                </svg>
                                            </a>
                                            <form method="POST" action="./ativar.php" class="inline-block">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(obterCsrfToken()) ?>">
                                                <input type="hidden" name="veiculo_id" value="<?= (int)$veiculo['id'] ?>">
                                                <input type="hidden" name="status" value="<?= $disponivel == 1 ? 0 : 1 ?>">
                                                <button type="submit"
                                                    class="p-1.5 rounded-lg <?= $disponivel == 1 ? 'bg-gray-500/20 text-gray-100 border border-gray-400/30 hover:bg-gray-500/40' : 'bg-cyan-500/20 text-cyan-100 border border-cyan-400/30 hover:bg-cyan-500/40' ?> transition-colors"
                                                    title="<?= $disponivel == 1 ? 'Desativar' : 'Ativar' ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path>
                                                        <line x1="12" y1="2" x2="12" y2="12"></line>
                                                    </svg>
                                                </button>
                                            </form>
                                            <form method="POST" action="./excluir.php" class="inline-block" onsubmit="return confirm('Tem certeza que deseja excluir este veículo?')">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(obterCsrfToken()) ?>">
                                                <input type="hidden" name="veiculo_id" value="<?= (int)$veiculo['id'] ?>">
                                                <button type="submit"
                                                    class="p-1.5 rounded-lg bg-red-500/20 text-red-100 border border-red-400/30 hover:bg-red-500/40 transition-colors"
                                                    title="Excluir">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <polyline points="3 6 5 6 21 6"></polyline>
                                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                        <line x1="10" y1="11" x2="10" y2="17"></line>
                                                        <line x1="14" y1="11" x2="14" y2="17"></line>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Incluir o componente do modal de veículo -->
    <?php include_once '../components/modal_veiculo.php'; ?>
    
    <script>
        // Verificar se há um parâmetro na URL para abrir o modal
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('openModal') === 'veiculos') {
                openVeiculoModal();
                
                // Limpar o parâmetro da URL sem recarregar a página
                const newUrl = window.location.pathname;
                window.history.pushState({}, '', newUrl);
            }
            
            // Adicionar listener para tecla ESC para fechar o modal
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && typeof closeVeiculoModal === 'function') {
                    closeVeiculoModal();
                }
            });
        });
    </script>
    
    <!-- Script para notificações -->
    <script src="../assets/notifications.js"></script>
    
    <!-- Importar o JavaScript do modal de veículo -->
    <script src="../components/modal_veiculo.js"></script>
</body>
</html>
