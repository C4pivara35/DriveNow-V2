<?php
require_once '../includes/auth.php';
require_once '../includes/reserva_disponibilidade.php';

$veiculoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($veiculoId === false || $veiculoId === null || $veiculoId <= 0) {
    header('Location: catalogo.php');
    exit;
}

global $pdo;
$stmt = $pdo->prepare(
    "SELECT v.*, c.categoria, l.nome_local, cid.cidade_nome, e.sigla,
            CONCAT(u.primeiro_nome, ' ', u.segundo_nome) AS nome_proprietario,
            u.id AS proprietario_id, u.media_avaliacao_proprietario, u.total_avaliacoes_proprietario,
            v.media_avaliacao, v.total_avaliacoes
     FROM veiculo v
     LEFT JOIN categoria_veiculo c ON v.categoria_veiculo_id = c.id
     LEFT JOIN local l ON v.local_id = l.id
     LEFT JOIN cidade cid ON l.cidade_id = cid.id
     LEFT JOIN estado e ON cid.estado_id = e.id
     LEFT JOIN dono d ON v.dono_id = d.id
     LEFT JOIN conta_usuario u ON d.conta_usuario_id = u.id
     WHERE v.id = ?"
);
$stmt->execute([$veiculoId]);
$veiculo = $stmt->fetch();

if (!$veiculo) {
    header('Location: catalogo.php');
    exit;
}

$usuario = getUsuario();
$usuarioIdLogado = (int)($usuario['id'] ?? 0);
$usuarioEhProprietarioDoVeiculo = $usuarioIdLogado > 0 && $usuarioIdLogado === (int)$veiculo['proprietario_id'];

$diariaValor = (float)$veiculo['preco_diaria'];
$taxaUso = 20.00;
$taxaLimpeza = 30.00;

$erro = '';
$sucesso = '';
$erroBloqueio = '';
$sucessoBloqueio = '';
$csrfToken = obterCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && estaLogado()) {
    if (!validarCsrfToken($_POST['csrf_token'] ?? '')) {
        $erro = 'Nao foi possivel validar sua sessao. Tente novamente.';
    } else {
        $acao = trim((string)($_POST['acao'] ?? 'criar_reserva'));

        if ($acao === 'criar_reserva') {
            if (!usuarioPodeReservar()) {
                $erro = 'Complete seu cadastro e aguarde a aprovacao dos documentos para reservar.';
            } else {
                $reservaData = trim((string)($_POST['reserva_data'] ?? ''));
                $devolucaoData = trim((string)($_POST['devolucao_data'] ?? ''));
                $observacoes = trim((string)($_POST['observacoes'] ?? ''));

                $periodo = reservaNormalizarPeriodo($reservaData, $devolucaoData);
                if (!$periodo['ok']) {
                    $erro = $periodo['mensagem'];
                } else {
                    $disponibilidade = validarDisponibilidadeIntervalo(
                        $pdo,
                        (int)$veiculoId,
                        $periodo['inicio'],
                        $periodo['fim']
                    );

                    if (!$disponibilidade['ok']) {
                        $erro = $disponibilidade['mensagem'];
                    } else {
                        try {
                            $reservaCriada = criarReservaComBloqueio(
                                $pdo,
                                (int)$veiculoId,
                                $usuarioIdLogado,
                                $periodo,
                                $observacoes,
                                $taxaUso,
                                $taxaLimpeza
                            );

                            if (!$reservaCriada['ok']) {
                                $erro = $reservaCriada['mensagem'];
                            } else {
                                $_SESSION['notification'] = [
                                    'type' => 'success',
                                    'message' => 'Reserva realizada com sucesso! Prossiga para o pagamento.',
                                ];

                                header('Location: ../pagamento/realizar_pagamento.php?reserva=' . $reservaCriada['reserva_id']);
                                exit;
                            }
                        } catch (PDOException $e) {
                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }

                            error_log('Erro ao processar reserva nos detalhes do veiculo: ' . $e->getMessage());
                            $erro = 'Nao foi possivel processar sua reserva no momento.';
                        }
                    }
                }
            }
        } elseif ($acao === 'bloquear_datas') {
            if (!$usuarioEhProprietarioDoVeiculo) {
                $erroBloqueio = 'Apenas o proprietario do veiculo pode bloquear datas.';
            } elseif (!indisponibilidadeVeiculoCompativel($pdo)) {
                $erroBloqueio = 'Estrutura de bloqueio de datas nao compativel. Execute a migracao SQL para habilitar este recurso.';
            } else {
                $bloqueioInicio = trim((string)($_POST['bloqueio_inicio'] ?? ''));
                $bloqueioFim = trim((string)($_POST['bloqueio_fim'] ?? ''));
                $bloqueioMotivo = trim((string)($_POST['bloqueio_motivo'] ?? ''));

                if (!reservaDataValidaYmd($bloqueioInicio) || !reservaDataValidaYmd($bloqueioFim)) {
                    $erroBloqueio = 'Informe datas validas para bloquear o calendario.';
                } elseif ($bloqueioFim < $bloqueioInicio) {
                    $erroBloqueio = 'A data final do bloqueio nao pode ser menor que a data inicial.';
                } elseif ($bloqueioInicio < date('Y-m-d')) {
                    $erroBloqueio = 'Nao e permitido bloquear datas no passado.';
                } else {
                    $conflitoReserva = buscarConflitoReservaAtiva($pdo, (int)$veiculoId, $bloqueioInicio, $bloqueioFim);
                    $conflitoBloqueio = buscarConflitoBloqueioVeiculo($pdo, (int)$veiculoId, $bloqueioInicio, $bloqueioFim);

                    if ($conflitoReserva) {
                        $erroBloqueio = 'Existe reserva ativa nesse periodo. Ajuste as datas do bloqueio.';
                    } elseif ($conflitoBloqueio) {
                        $erroBloqueio = 'Ja existe um bloqueio manual para este intervalo.';
                    } else {
                        try {
                            if ($bloqueioMotivo === '') {
                                $bloqueioMotivo = 'Bloqueio manual do proprietario';
                            }

                            $bloqueioCriado = criarBloqueioManualVeiculo(
                                $pdo,
                                (int)$veiculoId,
                                $bloqueioInicio,
                                $bloqueioFim,
                                $bloqueioMotivo,
                                $usuarioIdLogado
                            );

                            if ($bloqueioCriado) {
                                $sucessoBloqueio = 'Bloqueio adicionado ao calendario do veiculo.';
                            } else {
                                $erroBloqueio = 'Nao foi possivel salvar o bloqueio com a estrutura atual do banco.';
                            }
                        } catch (PDOException $e) {
                            $erroBloqueio = 'Nao foi possivel salvar o bloqueio no momento.';
                        }
                    }
                }
            }
        } elseif ($acao === 'remover_bloqueio') {
            if (!$usuarioEhProprietarioDoVeiculo) {
                $erroBloqueio = 'Apenas o proprietario do veiculo pode remover bloqueios.';
            } elseif (!indisponibilidadeVeiculoCompativel($pdo)) {
                $erroBloqueio = 'Estrutura de bloqueio de datas nao compativel. Execute a migracao SQL para habilitar este recurso.';
            } else {
                $bloqueioId = filter_input(INPUT_POST, 'bloqueio_id', FILTER_VALIDATE_INT);

                if ($bloqueioId === false || $bloqueioId === null || $bloqueioId <= 0) {
                    $erroBloqueio = 'Bloqueio invalido.';
                } else {
                    try {
                        $bloqueioRemovido = removerBloqueioManualVeiculo($pdo, (int)$bloqueioId, (int)$veiculoId);

                        if ($bloqueioRemovido) {
                            $sucessoBloqueio = 'Bloqueio removido com sucesso.';
                        } else {
                            $erroBloqueio = 'Nao foi possivel encontrar o bloqueio informado.';
                        }
                    } catch (PDOException $e) {
                        $erroBloqueio = 'Nao foi possivel remover o bloqueio no momento.';
                    }
                }
            }
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM imagem WHERE veiculo_id = ? ORDER BY imagem_ordem");
$stmt->execute([$veiculoId]);
$imagens = $stmt->fetchAll();

function imagemVeiculoUrlDetalhe(?string $path): string
{
    $path = trim((string)$path);

    if ($path === '' || preg_match('#^(https?:)?//#', $path) || str_starts_with($path, '/')) {
        return $path;
    }

    return '../' . ltrim($path, '/');
}

foreach ($imagens as &$imagem) {
    $imagem['imagem_public_url'] = imagemVeiculoUrlDetalhe($imagem['imagem_url'] ?? '');
}
unset($imagem);

$stmt = $pdo->prepare(
    "SELECT av.nota, av.comentario, av.data_avaliacao,
            CONCAT(u.primeiro_nome, ' ', u.segundo_nome) AS nome_usuario
     FROM avaliacao_veiculo av
     INNER JOIN conta_usuario u ON av.usuario_id = u.id
     WHERE av.veiculo_id = ?
       AND av.comentario IS NOT NULL
       AND TRIM(av.comentario) <> ''
     ORDER BY av.data_avaliacao DESC
     LIMIT 3"
);
$stmt->execute([$veiculoId]);
$comentariosAvaliacaoVeiculo = $stmt->fetchAll();

$calendarioEventos = obterEventosCalendarioVeiculo($pdo, (int)$veiculoId);
$bloqueiosAtivos = buscarBloqueiosAtivosVeiculo($pdo, (int)$veiculoId);
$suportaBloqueioManual = indisponibilidadeVeiculoCompativel($pdo);
$calendarioEventosJson = htmlspecialchars(
    json_encode($calendarioEventos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);

$navBasePath = '../';
$navCurrent = 'veiculos';
$navFixed = true;
$navShowMarketplaceAnchors = false;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($veiculo['veiculo_marca']) ?> <?= htmlspecialchars($veiculo['veiculo_modelo']) ?> - DriveNow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/ui-modern.css">
    <style>
        .animate-pulse-15s { animation-duration: 15s; }
        .animate-pulse-20s { animation-duration: 20s; }
        .animate-pulse-25s { animation-duration: 25s; }

        .subtle-border {
            border-color: rgba(255, 255, 255, 0.1);
        }

        option {
            background-color: #1e293b !important;
            color: white !important;
        }
        
        /* Estrelas de avaliação */
        .text-yellow-300 {
            color: #fcd34d;
            font-size: 14px;
            letter-spacing: -1px;
        }
    </style>
</head>
<body class="drivenow-modern min-h-screen bg-gradient-to-br from-slate-900 via-indigo-950 to-purple-950 text-white p-4 md:p-8 overflow-x-hidden">

    <div class="fixed top-0 right-0 w-96 h-96 rounded-full bg-indigo-700 opacity-10 blur-3xl -z-10 animate-pulse animate-pulse-15s"></div>
    <div class="fixed bottom-0 left-0 w-80 h-80 rounded-full bg-purple-700 opacity-10 blur-3xl -z-10 animate-pulse animate-pulse-20s"></div>
    <div class="fixed top-1/3 left-1/4 w-64 h-64 rounded-full bg-slate-700 opacity-5 blur-3xl -z-10 animate-pulse animate-pulse-25s"></div>

    <?php include_once '../includes/navbar.php'; ?>

    <main class="container mx-auto px-4 pt-28 pb-8">
        <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-3 mb-8">
            <h1 class="text-3xl md:text-4xl font-bold"><?= htmlspecialchars($veiculo['veiculo_marca']) ?> <?= htmlspecialchars($veiculo['veiculo_modelo']) ?></h1>
            <a href="catalogo.php" class="btn-dn-ghost border border-white/20 text-white hover:bg-white/20 rounded-xl px-4 py-2 font-medium backdrop-blur-sm bg-white/5 hover:bg-white/10 shadow-md hover:shadow-lg flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                    <path d="m15 18-6-6 6-6"/>
                </svg>
                Voltar
            </a>
        </div>

        <div class="space-y-4">
            <!-- Coluna 1: Fotos e descrição -->
            <div>
                <!-- Carrossel de imagens -->
                <div class="section-shell market-card backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl overflow-hidden shadow-lg">
                    <div class="market-card-media relative h-72 md:h-96 xl:h-[28rem] w-full bg-gray-200">
                        <?php if (!empty($imagens)): ?>
                            <img src="<?= htmlspecialchars($imagens[0]['imagem_public_url']) ?>" alt="<?= htmlspecialchars($veiculo['veiculo_modelo']) ?>" class="w-full h-full object-contain bg-slate-950" id="main-image">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center bg-indigo-900/50">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-16 w-16 text-white">
                                    <path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/>
                                    <path d="M7 17h10"/>
                                    <circle cx="7" cy="17" r="2"/>
                                    <path d="M17 17h2"/>
                                    <circle cx="17" cy="17" r="2"/>
                                </svg>
                            </div>
                        <?php endif; ?>

                        <div class="absolute bottom-5 right-5 bg-indigo-500 text-white px-4 py-2 rounded-xl font-bold text-lg border border-indigo-400/30">
                            R$ <?= number_format($veiculo['preco_diaria'], 2, ',', '.') ?>/dia
                        </div>
                    </div>

                    <!-- Miniaturas das imagens -->
                    <?php if (count($imagens) > 1): ?>
                    <div class="flex p-4 gap-2 overflow-x-auto">
                        <?php foreach($imagens as $index => $imagem): ?>
                            <img src="<?= htmlspecialchars($imagem['imagem_public_url']) ?>" 
                                alt="<?= htmlspecialchars($veiculo['veiculo_modelo']) ?>" 
                                class="thumbnail h-20 w-20 object-contain bg-slate-950 rounded cursor-pointer hover:opacity-80 transition-opacity <?= $index === 0 ? 'ring-2 ring-indigo-500' : '' ?>" 
                                onclick="document.getElementById('main-image').src='<?= htmlspecialchars($imagem['imagem_public_url'], ENT_QUOTES) ?>';
                                document.querySelectorAll('.thumbnail').forEach(el => el.classList.remove('ring-2', 'ring-indigo-500'));
                                this.classList.add('ring-2', 'ring-indigo-500');"
                                data-index="<?= $index ?>">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Informações do veículo -->
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-stretch">
                <div class="section-shell backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl p-6 shadow-lg h-full">
                    <h2 class="text-xl font-bold mb-4">Informações do Veículo</h2>
                    
                    <!-- Avaliações do veículo e proprietário -->
                    <div class="flex gap-6 mb-4 pb-4 border-b subtle-border">
                        <div>
                            <div class="text-white/70 text-sm mb-1">Avaliação do Veículo</div>
                            <div class="flex items-center">
                                <div class="text-yellow-300">
                                    <?php
                                    $mediaVeiculo = $veiculo['media_avaliacao'] ?? 0;
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $mediaVeiculo) {
                                            echo '★';
                                        } else if ($i - $mediaVeiculo < 1 && $i - $mediaVeiculo > 0) {
                                            echo '★'; // Idealmente seria uma estrela meio preenchida
                                        } else {
                                            echo '☆';
                                        }
                                    }
                                    ?>
                                </div>
                                <span class="text-sm text-white/70 ml-1">(<?= $veiculo['total_avaliacoes'] ?? 0 ?> avaliações)</span>
                            </div>
                        </div>
                        <div>
                            <div class="text-white/70 text-sm mb-1">Avaliação do Proprietário</div>
                            <div class="flex items-center">
                                <div class="text-yellow-300">
                                    <?php
                                    $mediaProprietario = $veiculo['media_avaliacao_proprietario'] ?? 0;
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $mediaProprietario) {
                                            echo '★';
                                        } else if ($i - $mediaProprietario < 1 && $i - $mediaProprietario > 0) {
                                            echo '★'; // Idealmente seria uma estrela meio preenchida
                                        } else {
                                            echo '☆';
                                        }
                                    }
                                    ?>
                                </div>
                                <span class="text-sm text-white/70 ml-1">(<?= $veiculo['total_avaliacoes_proprietario'] ?? 0 ?> avaliações)</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Descrição -->
                    <div class="mb-6">
                        <h3 class="font-semibold text-white/90 mb-2">Descrição</h3>
                        <div class="bg-white/5 rounded-xl p-4 border subtle-border">
                            <?php if (!empty($veiculo['descricao'])): ?>
                                <?= nl2br(htmlspecialchars($veiculo['descricao'])) ?>
                            <?php else: ?>
                                <p class="text-white/50 italic">O proprietário não forneceu uma descrição para este veículo.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Características do veículo -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-white/70">Marca:</span>
                                <span class="text-white font-medium"><?= htmlspecialchars($veiculo['veiculo_marca']) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-white/70">Modelo:</span>
                                <span class="text-white font-medium"><?= htmlspecialchars($veiculo['veiculo_modelo']) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-white/70">Ano:</span>
                                <span class="text-white font-medium"><?= htmlspecialchars($veiculo['veiculo_ano']) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-white/70">Placa:</span>
                                <span class="text-white font-medium"><?= htmlspecialchars($veiculo['veiculo_placa']) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-white/70">Quilometragem:</span>
                                <span class="text-white font-medium"><?= number_format($veiculo['veiculo_km'], 0, ',', '.') ?> km</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-white/70">Categoria:</span>
                                <span class="text-white font-medium"><?= htmlspecialchars($veiculo['categoria'] ?? 'Não informada') ?></span>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-white/70">Câmbio:</span>
                                <span class="text-white font-medium"><?= htmlspecialchars($veiculo['veiculo_cambio']) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-white/70">Combustível:</span>
                                <span class="text-white font-medium"><?= htmlspecialchars($veiculo['veiculo_combustivel']) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-white/70">Portas:</span>
                                <span class="text-white font-medium"><?= htmlspecialchars($veiculo['veiculo_portas']) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-white/70">Assentos:</span>
                                <span class="text-white font-medium"><?= htmlspecialchars($veiculo['veiculo_acentos']) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-white/70">Tração:</span>
                                <span class="text-white font-medium"><?= htmlspecialchars($veiculo['veiculo_tracao'] ?? 'Não informada') ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-white/70">Localização:</span>
                                <span class="text-white font-medium">
                                    <?= htmlspecialchars($veiculo['nome_local'] ?? 'Não informada') ?>
                                    <?php if (isset($veiculo['cidade_nome'])): ?>
                                        (<?= htmlspecialchars($veiculo['cidade_nome']) ?>-<?= htmlspecialchars($veiculo['sigla']) ?>)
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Proprietário -->
                    <div class="mt-6 pt-4 border-t subtle-border">
                        <h3 class="font-semibold text-white/90 mb-2">Proprietário</h3>
                        <div class="flex items-center">
                            <div class="h-10 w-10 rounded-full bg-indigo-500 flex items-center justify-center mr-3 overflow-hidden">
                                <img src="https://api.dicebear.com/7.x/initials/svg?seed=<?= urlencode($veiculo['nome_proprietario']) ?>&backgroundColor=818cf8&textColor=ffffff&fontSize=40" alt="Proprietário" class="h-full w-full object-cover">
                            </div>
                            <div>
                                <div class="font-medium"><?= htmlspecialchars($veiculo['nome_proprietario']) ?></div>
                                <a href="../avaliacao/avaliacoes_proprietario.php?id=<?= $veiculo['proprietario_id'] ?>" class="text-sm text-indigo-300 hover:text-indigo-200 transition-colors">Ver perfil e avaliações</a>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t subtle-border">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <h3 class="font-semibold text-white/90">Avaliacoes do Veiculo</h3>
                            <a href="../avaliacao/avaliacoes_veiculo.php?veiculo=<?= (int)$veiculoId ?>" class="text-sm text-indigo-300 hover:text-indigo-200 transition-colors">
                                Ver todas
                            </a>
                        </div>

                        <?php if (empty($comentariosAvaliacaoVeiculo)): ?>
                            <div class="rounded-xl border subtle-border bg-white/5 p-4 text-sm text-white/60">
                                Este veiculo ainda nao recebeu comentarios de avaliacao.
                            </div>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($comentariosAvaliacaoVeiculo as $comentarioAvaliacao): ?>
                                    <div class="rounded-xl border subtle-border bg-white/5 p-4">
                                        <div class="flex items-start justify-between gap-3 mb-2">
                                            <div>
                                                <p class="font-medium text-white"><?= htmlspecialchars($comentarioAvaliacao['nome_usuario']) ?></p>
                                                <p class="text-xs text-white/50">
                                                    <?= htmlspecialchars(date('d/m/Y', strtotime($comentarioAvaliacao['data_avaliacao']))) ?>
                                                </p>
                                            </div>
                                            <div class="text-yellow-300 whitespace-nowrap">
                                                <?php for ($estrela = 1; $estrela <= 5; $estrela++): ?>
                                                    <?= $estrela <= (int)$comentarioAvaliacao['nota'] ? '&#9733;' : '&#9734;' ?>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <p class="text-sm text-white/80 leading-relaxed">
                                            <?= nl2br(htmlspecialchars($comentarioAvaliacao['comentario'])) ?>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <!-- Calendario e reserva -->
            <div class="space-y-4 h-full">
                <div id="calendario" class="section-shell backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl p-6 shadow-lg">
                    <h2 class="text-xl font-bold mb-2">Calendario de disponibilidade</h2>
                    <p class="text-sm text-white/70 mb-4">Consulte datas livres, reservadas e bloqueadas antes de escolher seu periodo.</p>

                    <div class="flex flex-wrap gap-2 text-xs mb-4">
                        <span class="px-2.5 py-1 rounded-full bg-emerald-500/20 border border-emerald-400/30 text-emerald-100">Verde: disponivel</span>
                        <span class="px-2.5 py-1 rounded-full bg-red-500/20 border border-red-400/30 text-red-100">Vermelho: reservado</span>
                        <span class="px-2.5 py-1 rounded-full bg-amber-400/20 border border-amber-400/40 text-amber-100">Amarelo: bloqueado</span>
                    </div>

                    <?php if ($erroBloqueio): ?>
                        <div class="bg-red-500/20 border border-red-400/30 text-white p-3 rounded-xl mb-3 text-sm">
                            <?= htmlspecialchars($erroBloqueio) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($sucessoBloqueio): ?>
                        <div class="bg-green-500/20 border border-green-400/30 text-white p-3 rounded-xl mb-3 text-sm">
                            <?= htmlspecialchars($sucessoBloqueio) ?>
                        </div>
                    <?php endif; ?>

                    <div id="calendar-feedback" class="hidden bg-amber-500/20 border border-amber-400/30 text-amber-100 p-3 rounded-xl mb-3 text-sm"></div>
                    <div id="vehicle-calendar" data-calendar-events="<?= $calendarioEventosJson ?>"></div>

                    <?php if ($usuarioEhProprietarioDoVeiculo): ?>
                        <div class="mt-6 pt-4 border-t subtle-border">
                            <h3 class="text-base font-semibold mb-2">Bloqueio manual de datas</h3>

                            <?php if (!$suportaBloqueioManual): ?>
                                <div class="bg-amber-500/20 border border-amber-400/30 text-white p-3 rounded-xl text-sm">
                                    Execute o script SQL <strong>SQL/2026_04_16_indisponibilidade_veiculo.sql</strong> para habilitar o bloqueio manual.
                                </div>
                            <?php else: ?>
                                <form method="POST" class="space-y-3">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="acao" value="bloquear_datas">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <label for="bloqueio_inicio" class="block text-white/80 text-sm mb-1">Inicio do bloqueio</label>
                                            <input type="date" id="bloqueio_inicio" name="bloqueio_inicio" class="w-full bg-white/5 border subtle-border rounded-xl h-10 px-3 focus:ring-2 focus:ring-indigo-500 focus:border-none outline-none text-white" required>
                                        </div>
                                        <div>
                                            <label for="bloqueio_fim" class="block text-white/80 text-sm mb-1">Fim do bloqueio</label>
                                            <input type="date" id="bloqueio_fim" name="bloqueio_fim" class="w-full bg-white/5 border subtle-border rounded-xl h-10 px-3 focus:ring-2 focus:ring-indigo-500 focus:border-none outline-none text-white" required>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="bloqueio_motivo" class="block text-white/80 text-sm mb-1">Motivo (opcional)</label>
                                        <input type="text" id="bloqueio_motivo" name="bloqueio_motivo" maxlength="255" class="w-full bg-white/5 border subtle-border rounded-xl h-10 px-3 focus:ring-2 focus:ring-indigo-500 focus:border-none outline-none text-white" placeholder="Ex.: manutencao, uso pessoal">
                                    </div>
                                    <button type="submit" class="btn-dn-primary w-full bg-amber-500 hover:bg-amber-600 text-white font-medium rounded-xl transition-colors border border-amber-400/30 px-4 py-2 shadow-md hover:shadow-lg">
                                        Bloquear periodo
                                    </button>
                                </form>

                                <?php if (!empty($bloqueiosAtivos)): ?>
                                    <div class="mt-4 space-y-2">
                                        <p class="text-sm text-white/70">Bloqueios ativos</p>
                                        <?php foreach ($bloqueiosAtivos as $bloqueio): ?>
                                            <div class="rounded-xl border subtle-border bg-white/5 p-3 text-sm">
                                                <div class="flex items-center justify-between gap-2">
                                                    <div>
                                                        <p class="font-medium text-white">
                                                            <?= htmlspecialchars(date('d/m/Y', strtotime($bloqueio['data_inicio']))) ?> ate <?= htmlspecialchars(date('d/m/Y', strtotime($bloqueio['data_fim']))) ?>
                                                        </p>
                                                        <?php if (!empty($bloqueio['motivo'])): ?>
                                                            <p class="text-white/70 text-xs mt-1"><?= htmlspecialchars($bloqueio['motivo']) ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <form method="POST" class="inline-block">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                        <input type="hidden" name="acao" value="remover_bloqueio">
                                                        <input type="hidden" name="bloqueio_id" value="<?= (int)$bloqueio['id'] ?>">
                                                        <button type="submit" class="btn-dn-ghost px-3 py-1.5 rounded-lg bg-red-500/20 border border-red-400/30 text-red-100 hover:bg-red-500/30 transition-colors">
                                                            Remover
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Reserva -->
                <div class="section-shell backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl p-6 shadow-lg">
                    <h2 class="text-xl font-bold mb-4">Solicitar Reserva</h2>

                    <?php if ($erro): ?>
                        <div class="bg-red-500/20 border border-red-400/30 text-white p-4 rounded-xl mb-4">
                            <?= htmlspecialchars($erro) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($sucesso): ?>
                        <div class="bg-green-500/20 border border-green-400/30 text-white p-4 rounded-xl mb-4">
                            <?= htmlspecialchars($sucesso) ?>
                        </div>
                        <a href="../index.php" class="btn-dn-primary w-full bg-indigo-500 hover:bg-indigo-600 text-white font-medium rounded-xl transition-colors border border-indigo-400/30 px-4 py-2 shadow-md hover:shadow-lg flex items-center justify-center">
                            Voltar para a pagina inicial
                        </a>
                    <?php else: ?>
                        <?php if (estaLogado()): ?>
                            <?php if (usuarioPodeReservar()): ?>
                                <form method="POST" class="space-y-4">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="acao" value="criar_reserva">
                                    <div>
                                        <label for="reserva_data" class="block text-white/90 font-medium mb-1">Data de Reserva</label>
                                        <input type="date" class="w-full bg-white/5 border subtle-border rounded-xl h-10 px-3 focus:ring-2 focus:ring-indigo-500 focus:border-none outline-none text-white" id="reserva_data" name="reserva_data" value="<?= htmlspecialchars((string)($_POST['reserva_data'] ?? '')) ?>" required>
                                    </div>
                                    <div>
                                        <label for="devolucao_data" class="block text-white/90 font-medium mb-1">Data de Devolução</label>
                                        <input type="date" class="w-full bg-white/5 border subtle-border rounded-xl h-10 px-3 focus:ring-2 focus:ring-indigo-500 focus:border-none outline-none text-white" id="devolucao_data" name="devolucao_data" value="<?= htmlspecialchars((string)($_POST['devolucao_data'] ?? '')) ?>" required>
                                    </div>
                                    <div>
                                        <label for="observacoes" class="block text-white/90 font-medium mb-1">Observações</label>
                                        <textarea class="w-full bg-white/5 border subtle-border rounded-xl px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-none outline-none text-white resize-none" id="observacoes" name="observacoes" rows="3"><?= htmlspecialchars((string)($_POST['observacoes'] ?? '')) ?></textarea>
                                    </div>
                                    
                                    <!-- Cálculo de preço -->
                                    <div id="price-calculation" class="bg-indigo-500/10 border border-indigo-400/20 rounded-xl p-4 mt-4 hidden">
                                        <div class="text-lg font-medium mb-2">Estimativa de Custo</div>
                                        <div class="flex justify-between items-center mb-1">
                                            <span class="text-white/70">Diárias (<span id="days-count">0</span> dias):</span>
                                            <span class="text-white" id="daily-cost">R$ 0,00</span>
                                        </div>
                                        <div class="flex justify-between items-center mb-1">
                                            <span class="text-white/70">Taxa de uso:</span>
                                            <span class="text-white">R$ <?= number_format($taxaUso, 2, ',', '.') ?></span>
                                        </div>
                                        <div class="flex justify-between items-center mb-1 pb-2 border-b border-indigo-400/20">
                                            <span class="text-white/70">Taxa de limpeza:</span>
                                            <span class="text-white">R$ <?= number_format($taxaLimpeza, 2, ',', '.') ?></span>
                                        </div>
                                        <div class="flex justify-between items-center mt-2 font-bold">
                                            <span>Total estimado:</span>
                                            <span id="total-cost">R$ 0,00</span>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn-dn-primary w-full bg-indigo-500 hover:bg-indigo-600 text-white font-medium rounded-xl transition-colors border border-indigo-400/30 px-4 py-3 shadow-md hover:shadow-lg">
                                        Solicitar Reserva
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="bg-amber-500/20 border border-amber-400/30 text-white p-4 rounded-xl">
                                    <p class="mb-2">Você não pode fazer reservas neste momento.</p>
                                    <p class="text-white/80 text-sm">Para reservar veículos, complete seu cadastro e aguarde a aprovação da sua CNH.</p>
                                </div>
                                <a href="../perfil/editar.php?tab=editar" class="btn-dn-primary w-full bg-indigo-500 hover:bg-indigo-600 text-white font-medium rounded-xl transition-colors border border-indigo-400/30 px-4 py-2 mt-4 shadow-md hover:shadow-lg flex items-center justify-center">
                                    Completar Cadastro
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="bg-amber-500/20 border border-amber-400/30 text-white p-4 rounded-xl">
                                <p class="mb-2">Você precisa estar logado para fazer uma reserva.</p>
                            </div>
                            <div class="flex gap-3 mt-4">
                                <a href="../login.php?msg=reserve_required&amp;redirect=<?= urlencode('reserva/detalhes_veiculo.php?id=' . $veiculoId) ?>" class="btn-dn-primary flex-1 bg-indigo-500 hover:bg-indigo-600 text-white font-medium rounded-xl transition-colors border border-indigo-400/30 px-4 py-2 shadow-md hover:shadow-lg flex items-center justify-center">
                                    Fazer Login
                                </a>
                                <a href="../cadastro.php" class="btn-dn-ghost flex-1 border border-white/20 text-white hover:bg-white/20 rounded-xl px-4 py-2 font-medium backdrop-blur-sm bg-white/5 hover:bg-white/10 shadow-md hover:shadow-lg flex items-center justify-center">
                                    Cadastrar
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            </div>

            <!-- Informações adicionais -->
            <div class="section-shell backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl p-6 shadow-lg">
                    <h2 class="text-xl font-bold mb-4">Informações Adicionais</h2>
                    <div class="space-y-3">
                        <div class="flex items-center gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 text-indigo-300">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            </svg>
                            <div>
                                <div class="font-medium">Seguro Incluso</div>
                                <div class="text-sm text-white/70">Todos os aluguéis incluem seguro básico</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 text-indigo-300">
                                <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <path d="M12 18v-6"/>
                                <path d="M8 15h8"/>
                            </svg>
                            <div>
                                <div class="font-medium">Contrato Digital</div>
                                <div class="text-sm text-white/70">Todas as reservas incluem contrato digital</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 text-indigo-300">
                                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                            </svg>
                            <div>
                                <div class="font-medium">Suporte 24/7</div>
                                <div class="text-sm text-white/70">Assistência durante toda a reserva</div>
                            </div>
                        </div>
                    </div>
                </div>
        </div>
    </main>

    <footer class="mt-12 mb-6 px-4 text-center text-white/50 text-sm">
        <p>&copy; <?= date('Y') ?> DriveNow. Todos os direitos reservados.</p>
    </footer>

    <script src="../assets/notifications.js"></script>
    <script src="../assets/reservation-calendar.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            try {
                initializeNotifications();

                <?php if (isset($_SESSION['notification'])): ?>
                    notify({
                        type: '<?= $_SESSION['notification']['type'] ?>',
                        message: '<?= addslashes($_SESSION['notification']['message']) ?>'
                    });
                    <?php unset($_SESSION['notification']); ?>
                <?php endif; ?>
            } catch (e) {
                console.error('Erro ao inicializar notificacoes:', e);
            }

            const today = new Date().toISOString().split('T')[0];
            const reservaDataEl = document.getElementById('reserva_data');
            const devolucaoDataEl = document.getElementById('devolucao_data');
            const priceCalculationEl = document.getElementById('price-calculation');
            const bloqueioInicioEl = document.getElementById('bloqueio_inicio');
            const bloqueioFimEl = document.getElementById('bloqueio_fim');
            const calendarContainer = document.getElementById('vehicle-calendar');
            const calendarFeedback = document.getElementById('calendar-feedback');
            let calendarInstance = null;

            if (bloqueioInicioEl) {
                bloqueioInicioEl.min = today;
                bloqueioInicioEl.addEventListener('change', function() {
                    if (bloqueioFimEl) {
                        bloqueioFimEl.min = this.value || today;
                    }
                });
            }

            if (bloqueioFimEl) {
                bloqueioFimEl.min = today;
            }

            if (calendarContainer && typeof createReservationCalendar === 'function') {
                let calendarPayload = {};
                try {
                    calendarPayload = JSON.parse(calendarContainer.getAttribute('data-calendar-events') || '{}');
                } catch (e) {
                    calendarPayload = {};
                }

                calendarInstance = createReservationCalendar({
                    container: calendarContainer,
                    reservedRanges: calendarPayload.reservados || [],
                    blockedRanges: calendarPayload.bloqueados || [],
                    startInput: reservaDataEl,
                    endInput: devolucaoDataEl,
                    feedbackElement: calendarFeedback
                });
            }

            if (reservaDataEl) {
                reservaDataEl.min = today;
                reservaDataEl.addEventListener('change', function() {
                    if (devolucaoDataEl) {
                        devolucaoDataEl.min = this.value || today;
                    }
                    updatePriceCalculation();
                });
            }

            if (devolucaoDataEl) {
                devolucaoDataEl.min = today;
                devolucaoDataEl.addEventListener('change', updatePriceCalculation);
            }

            function updatePriceCalculation() {
                if (!priceCalculationEl) {
                    return;
                }

                if (!reservaDataEl || !devolucaoDataEl || !reservaDataEl.value || !devolucaoDataEl.value) {
                    priceCalculationEl.classList.add('hidden');
                    return;
                }

                if (calendarInstance && typeof calendarInstance.validateRange === 'function') {
                    if (!calendarInstance.validateRange()) {
                        priceCalculationEl.classList.add('hidden');
                        return;
                    }
                }

                const start = new Date(reservaDataEl.value + 'T00:00:00');
                const end = new Date(devolucaoDataEl.value + 'T00:00:00');
                const diffTime = end.getTime() - start.getTime();
                const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));

                if (!Number.isFinite(diffDays) || diffDays <= 0) {
                    priceCalculationEl.classList.add('hidden');
                    return;
                }

                const dailyPrice = <?= $diariaValor ?>;
                const useRate = <?= $taxaUso ?>;
                const cleaningRate = <?= $taxaLimpeza ?>;
                const dailyCost = dailyPrice * diffDays;
                const totalCost = dailyCost + useRate + cleaningRate;

                document.getElementById('days-count').textContent = diffDays;
                document.getElementById('daily-cost').textContent = 'R$ ' + dailyCost.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                document.getElementById('total-cost').textContent = 'R$ ' + totalCost.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                priceCalculationEl.classList.remove('hidden');
            }

            updatePriceCalculation();
        });
    </script>
</body>
</html>
