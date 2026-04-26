<?php
require_once 'includes/auth.php';
require_once 'includes/reserva_disponibilidade.php';

global $pdo;

if (estaLogado()) {
    updateInfosUsuario();
}

$usuario = getUsuario();
$logado = estaLogado();

function validarDataFiltro(?string $data): ?string
{
    if ($data === null) {
        return null;
    }

    $data = trim($data);
    if ($data === '') {
        return null;
    }

    $dateTime = DateTime::createFromFormat('Y-m-d', $data);
    if (!$dateTime || $dateTime->format('Y-m-d') !== $data) {
        return null;
    }

    return $data;
}

function normalizarPrecoFiltro($valor)
{
    if ($valor === null) {
        return null;
    }

    $valor = trim((string)$valor);
    if ($valor === '') {
        return null;
    }

    $valor = preg_replace('/[^\d\.,]/', '', $valor);
    if ($valor === '') {
        return false;
    }

    if (strpos($valor, ',') !== false && strpos($valor, '.') !== false) {
        $valor = str_replace('.', '', $valor);
    }

    $valor = str_replace(',', '.', $valor);
    if (!is_numeric($valor)) {
        return false;
    }

    return (float)$valor;
}

function imagemUrlIndex(?string $path): string
{
    $path = trim((string)$path);

    if ($path === '' || preg_match('#^(https?:)?//#', $path) || str_starts_with($path, '/')) {
        return $path;
    }

    return ltrim($path, '/');
}

$notificacoes = [];
if (isset($_SESSION['notification'])) {
    $notificacoes[] = $_SESSION['notification'];
    unset($_SESSION['notification']);
}

$filtroAvisos = [];

$filtroCidade = filter_input(INPUT_GET, 'cidade', FILTER_VALIDATE_INT);
if ($filtroCidade === false || $filtroCidade <= 0) {
    $filtroCidade = null;
}

$filtroCategoria = filter_input(INPUT_GET, 'categoria', FILTER_VALIDATE_INT);
if ($filtroCategoria === false || $filtroCategoria <= 0) {
    $filtroCategoria = null;
}

$filtroInicioRaw = trim((string)($_GET['inicio'] ?? ''));
$filtroFimRaw = trim((string)($_GET['fim'] ?? ''));
$filtroPrecoMinRaw = filter_input(INPUT_GET, 'preco_min', FILTER_UNSAFE_RAW);
$filtroPrecoMaxRaw = filter_input(INPUT_GET, 'preco_max', FILTER_UNSAFE_RAW);

$filtroInicio = validarDataFiltro($filtroInicioRaw);
$filtroFim = validarDataFiltro($filtroFimRaw);

if ($filtroInicioRaw !== '' && $filtroInicio === null) {
    $filtroAvisos[] = 'A data inicial informada e invalida.';
}

if ($filtroFimRaw !== '' && $filtroFim === null) {
    $filtroAvisos[] = 'A data final informada e invalida.';
}

if (($filtroInicioRaw === '' xor $filtroFimRaw === '') && ($filtroInicioRaw !== '' || $filtroFimRaw !== '')) {
    $filtroAvisos[] = 'Preencha data inicial e data final para filtrar disponibilidade por periodo.';
}

$usarFiltroData = false;
if ($filtroInicio !== null && $filtroFim !== null) {
    if ($filtroInicio > $filtroFim) {
        $tmp = $filtroInicio;
        $filtroInicio = $filtroFim;
        $filtroFim = $tmp;
        $filtroAvisos[] = 'As datas foram ajustadas porque a data inicial estava maior que a data final.';
    }

    $usarFiltroData = true;
}

$filtroPrecoMin = normalizarPrecoFiltro($filtroPrecoMinRaw);
if ($filtroPrecoMin === false || ($filtroPrecoMin !== null && $filtroPrecoMin < 0)) {
    $filtroAvisos[] = 'O preco minimo informado e invalido.';
    $filtroPrecoMin = null;
}

$filtroPrecoMax = normalizarPrecoFiltro($filtroPrecoMaxRaw);
if ($filtroPrecoMax === false || ($filtroPrecoMax !== null && $filtroPrecoMax < 0)) {
    $filtroAvisos[] = 'O preco maximo informado e invalido.';
    $filtroPrecoMax = null;
}

if ($filtroPrecoMin !== null && $filtroPrecoMax !== null && $filtroPrecoMin > $filtroPrecoMax) {
    $tmp = $filtroPrecoMin;
    $filtroPrecoMin = $filtroPrecoMax;
    $filtroPrecoMax = $tmp;
    $filtroAvisos[] = 'Os valores de preco foram invertidos para manter o intervalo valido.';
}

$categoriasDisponiveis = [];
$cidadesDisponiveis = [];

try {
    $stmt = $pdo->query(
        "SELECT DISTINCT cv.id, cv.categoria
         FROM categoria_veiculo cv
         INNER JOIN veiculo v ON v.categoria_veiculo_id = cv.id
         WHERE v.disponivel = 1
         ORDER BY cv.categoria"
    );
    $categoriasDisponiveis = $stmt->fetchAll();

    $stmt = $pdo->query(
        "SELECT DISTINCT c.id, c.cidade_nome, e.sigla
         FROM cidade c
         INNER JOIN estado e ON e.id = c.estado_id
         INNER JOIN local l ON l.cidade_id = c.id
         INNER JOIN veiculo v ON v.local_id = l.id
         WHERE v.disponivel = 1
         ORDER BY c.cidade_nome"
    );
    $cidadesDisponiveis = $stmt->fetchAll();
} catch (PDOException $e) {
    $filtroAvisos[] = 'Nao foi possivel carregar todos os filtros de pesquisa.';
}

$podeReservar = false;
if ($logado && $usuario) {
    $podeReservar = usuarioPodeReservar();
}

$limitePreviewHome = 6;
$linkCatalogoCompleto = 'reserva/catalogo.php';
$heroImagemFixa = 'assets/images/drivenow-hero.png';

$condicoesVeiculos = ['v.disponivel = 1'];
$paramsVeiculos = [];

if ($usarFiltroData) {
    $condicoesVeiculos[] = "NOT EXISTS (
        SELECT 1
        FROM reserva r
        WHERE r.veiculo_id = v.id
          AND COALESCE(r.status, 'pendente') NOT IN ('rejeitada', 'cancelada', 'finalizada')
          AND r.reserva_data <= ?
          AND r.devolucao_data >= ?
    )";
    $paramsVeiculos[] = $filtroFim;
    $paramsVeiculos[] = $filtroInicio;

    if (indisponibilidadeVeiculoCompativel($pdo)) {
        $colIndispVeiculo = obterNomeColunaIndisponibilidade($pdo, ['veiculo_id', 'vehicle_id']);
        $colIndispInicio = obterNomeColunaIndisponibilidade($pdo, ['data_inicio', 'start_date']);
        $colIndispFim = obterNomeColunaIndisponibilidade($pdo, ['data_fim', 'end_date']);
        $colIndispAtivo = obterNomeColunaIndisponibilidade($pdo, ['ativo', 'is_active', 'active']);
        $filtroIndispAtivo = $colIndispAtivo !== null ? "AND iv.`$colIndispAtivo` = 1" : '';

        $condicoesVeiculos[] = "NOT EXISTS (
            SELECT 1
            FROM indisponibilidade_veiculo iv
            WHERE iv.`$colIndispVeiculo` = v.id
              $filtroIndispAtivo
              AND iv.`$colIndispInicio` <= ?
              AND iv.`$colIndispFim` >= ?
        )";
        $paramsVeiculos[] = $filtroFim;
        $paramsVeiculos[] = $filtroInicio;
    }
}

if ($filtroCidade !== null) {
    $condicoesVeiculos[] = 'c.id = ?';
    $paramsVeiculos[] = $filtroCidade;
}

if ($filtroCategoria !== null) {
    $condicoesVeiculos[] = 'cv.id = ?';
    $paramsVeiculos[] = $filtroCategoria;
}

if ($filtroPrecoMin !== null) {
    $condicoesVeiculos[] = 'v.preco_diaria >= ?';
    $paramsVeiculos[] = $filtroPrecoMin;
}

if ($filtroPrecoMax !== null) {
    $condicoesVeiculos[] = 'v.preco_diaria <= ?';
    $paramsVeiculos[] = $filtroPrecoMax;
}

$sqlFromVeiculos =
    "FROM veiculo v
     LEFT JOIN categoria_veiculo cv ON cv.id = v.categoria_veiculo_id
     LEFT JOIN local l ON l.id = v.local_id
     LEFT JOIN cidade c ON c.id = l.cidade_id
     LEFT JOIN estado e ON e.id = c.estado_id
     LEFT JOIN dono d ON d.id = v.dono_id
     LEFT JOIN conta_usuario u ON u.id = d.conta_usuario_id
     WHERE " . implode(' AND ', $condicoesVeiculos);

$sqlCountVeiculos = "SELECT COUNT(*) " . $sqlFromVeiculos;
$stmtCount = $pdo->prepare($sqlCountVeiculos);
$stmtCount->execute($paramsVeiculos);
$totalVeiculosEncontrados = (int)$stmtCount->fetchColumn();

$sqlVeiculos =
    "SELECT
        v.id,
        v.veiculo_marca,
        v.veiculo_modelo,
        v.veiculo_ano,
        v.veiculo_km,
        v.veiculo_cambio,
        v.veiculo_combustivel,
        v.veiculo_portas,
        v.veiculo_acentos,
        v.preco_diaria,
        COALESCE(v.media_avaliacao, 0) AS media_avaliacao,
        COALESCE(v.total_avaliacoes, 0) AS total_avaliacoes,
        cv.categoria,
        l.nome_local,
        c.cidade_nome,
        e.sigla,
        TRIM(CONCAT(COALESCE(u.primeiro_nome, ''), ' ', COALESCE(u.segundo_nome, ''))) AS nome_proprietario,
        (
            SELECT i.imagem_url
            FROM imagem i
            WHERE i.veiculo_id = v.id
            ORDER BY i.imagem_ordem IS NULL, i.imagem_ordem, i.id
            LIMIT 1
        ) AS imagem_url
     " . $sqlFromVeiculos . "
     ORDER BY v.id DESC
     LIMIT " . $limitePreviewHome;

$stmt = $pdo->prepare($sqlVeiculos);
$stmt->execute($paramsVeiculos);
$veiculos = $stmt->fetchAll();
$heroImagemUrl = is_file(__DIR__ . '/' . $heroImagemFixa) ? imagemUrlIndex($heroImagemFixa) : '';


$totalVeiculosPreview = count($veiculos);
$filtrosAtivos = (
    $filtroCidade !== null
    || $filtroCategoria !== null
    || $filtroInicioRaw !== ''
    || $filtroFimRaw !== ''
    || ($filtroPrecoMinRaw !== null && trim((string)$filtroPrecoMinRaw) !== '')
    || ($filtroPrecoMaxRaw !== null && trim((string)$filtroPrecoMaxRaw) !== '')
);

$filtroBuscaAtiva = (
    $filtroCidade !== null
    || $filtroCategoria !== null
    || $filtroInicioRaw !== ''
    || $filtroFimRaw !== ''
);

$filtroPrecoAtivo = (
    ($filtroPrecoMinRaw !== null && trim((string)$filtroPrecoMinRaw) !== '')
    || ($filtroPrecoMaxRaw !== null && trim((string)$filtroPrecoMaxRaw) !== '')
);

$navBasePath = '';
$navCurrent = 'home';
$navFixed = true;
$navShowMarketplaceAnchors = true;
$navShowDashboardLink = $logado;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DriveNow - Marketplace de Veiculos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/ui-modern.css">
    <style>
        .subtle-border {
            border-color: rgba(255, 255, 255, 0.1);
        }

        .animate-pulse-15s { animation-duration: 15s; }
        .animate-pulse-20s { animation-duration: 20s; }
        .animate-pulse-25s { animation-duration: 25s; }

        option {
            background-color: #1e293b;
            color: #fff;
        }

        .home-panel-btn.is-active {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: #ffffff;
            border-color: rgba(129, 140, 248, 0.5);
            box-shadow: 0 12px 25px rgba(79, 70, 229, 0.35);
        }
    </style>
</head>
<body class="drivenow-modern min-h-screen bg-gradient-to-br from-slate-900 via-indigo-950 to-purple-950 text-white overflow-x-hidden">
    <div class="fixed top-0 right-0 w-96 h-96 rounded-full bg-indigo-700 opacity-10 blur-3xl -z-10 animate-pulse animate-pulse-15s"></div>
    <div class="fixed bottom-0 left-0 w-80 h-80 rounded-full bg-purple-700 opacity-10 blur-3xl -z-10 animate-pulse animate-pulse-20s"></div>
    <div class="fixed top-1/3 left-1/4 w-64 h-64 rounded-full bg-slate-700 opacity-5 blur-3xl -z-10 animate-pulse animate-pulse-25s"></div>

    <?php include_once 'includes/navbar.php'; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 pt-28 pb-12">
        <section id="busca" class="mb-8">
            <div class="hero-surface backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl overflow-hidden shadow-lg">
                <div class="grid grid-cols-1 lg:grid-cols-2">
                    <div class="p-6 md:p-8 flex flex-col justify-center">
                <p class="text-indigo-200/90 text-xs uppercase tracking-[0.18em] font-semibold mb-3">DriveNow</p>
                <h1 class="text-3xl md:text-5xl font-bold leading-tight max-w-3xl">Encontre o carro ideal para sua proxima viagem</h1>
                <p class="text-white/70 mt-3 max-w-2xl">
                    Pesquise, compare e reserve veículos disponíveis em tempo real, com total segurança.
                </p>

                <div class="mt-6 flex flex-wrap gap-3 text-sm">
                    <span class="px-3 py-1 rounded-full bg-indigo-500/20 text-indigo-200 border border-indigo-400/30">
                        <?= $totalVeiculosEncontrados ?> veiculo(s) encontrado(s)
                    </span>
                    <?php if ($usarFiltroData): ?>
                        <span class="px-3 py-1 rounded-full bg-cyan-500/20 text-cyan-200 border border-cyan-400/30">
                            Disponibilidade em <?= htmlspecialchars(date('d/m/Y', strtotime($filtroInicio))) ?> ate <?= htmlspecialchars(date('d/m/Y', strtotime($filtroFim))) ?>
                        </span>
                    <?php endif; ?>
                </div>

                    </div>

                    <div class="relative min-h-[260px] lg:min-h-[420px] bg-slate-800">
                        <?php if ($heroImagemUrl !== ''): ?>
                            <img src="<?= htmlspecialchars($heroImagemUrl) ?>" alt="Capa DriveNow" class="absolute inset-0 h-full w-full object-cover">
                        <?php else: ?>
                            <div class="absolute inset-0 flex items-center justify-center bg-indigo-900/50">
                                <svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-white/70">
                                    <path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/>
                                    <path d="M7 17h10"/>
                                    <circle cx="7" cy="17" r="2"/>
                                    <circle cx="17" cy="17" r="2"/>
                                </svg>
                            </div>
                        <?php endif; ?>
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/35 via-transparent to-transparent"></div>
                    </div>
                </div>
            </div>

            <div id="painel-busca-rapida" class="hidden mt-4 section-shell backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl p-6 shadow-lg">
                <h2 class="text-lg font-bold mb-4">Busca rapida</h2>
                <form action="index.php#frota" method="GET">
                    <?php if ($filtroPrecoMinRaw !== null && trim((string)$filtroPrecoMinRaw) !== ''): ?>
                        <input type="hidden" name="preco_min" value="<?= htmlspecialchars((string)$filtroPrecoMinRaw) ?>">
                    <?php endif; ?>
                    <?php if ($filtroPrecoMaxRaw !== null && trim((string)$filtroPrecoMaxRaw) !== ''): ?>
                        <input type="hidden" name="preco_max" value="<?= htmlspecialchars((string)$filtroPrecoMaxRaw) ?>">
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                        <div>
                            <label for="cidade_rapida" class="block text-sm text-white/70 mb-1">Cidade</label>
                            <select id="cidade_rapida" name="cidade" class="w-full rounded-xl bg-white/5 border subtle-border h-11 px-3 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                                <option value="">Todas</option>
                                <?php foreach ($cidadesDisponiveis as $cidade): ?>
                                    <option value="<?= (int)$cidade['id'] ?>" <?= ($filtroCidade === (int)$cidade['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cidade['cidade_nome']) ?> - <?= htmlspecialchars($cidade['sigla']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="categoria_rapida" class="block text-sm text-white/70 mb-1">Categoria</label>
                            <select id="categoria_rapida" name="categoria" class="w-full rounded-xl bg-white/5 border subtle-border h-11 px-3 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                                <option value="">Todas</option>
                                <?php foreach ($categoriasDisponiveis as $categoria): ?>
                                    <option value="<?= (int)$categoria['id'] ?>" <?= ($filtroCategoria === (int)$categoria['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($categoria['categoria']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="inicio_rapida" class="block text-sm text-white/70 mb-1">Inicio</label>
                            <input type="date" id="inicio_rapida" name="inicio" value="<?= htmlspecialchars($filtroInicio ?? $filtroInicioRaw) ?>" class="w-full rounded-xl bg-white/5 border subtle-border h-11 px-3 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        </div>

                        <div>
                            <label for="fim_rapida" class="block text-sm text-white/70 mb-1">Fim</label>
                            <input type="date" id="fim_rapida" name="fim" value="<?= htmlspecialchars($filtroFim ?? $filtroFimRaw) ?>" class="w-full rounded-xl bg-white/5 border subtle-border h-11 px-3 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <button type="submit" class="btn-dn-primary px-5 py-2.5 rounded-xl bg-indigo-500 hover:bg-indigo-600 font-medium transition-colors">
                            Pesquisar veiculos
                        </button>
                    </div>
                </form>
            </div>

            <div id="painel-filtros" class="hidden mt-4 section-shell backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl p-6 shadow-lg">
                <h2 class="text-lg font-bold mb-4">Filtros</h2>
                <form action="index.php#frota" method="GET">
                    <?php if ($filtroCidade !== null): ?>
                        <input type="hidden" name="cidade" value="<?= (int)$filtroCidade ?>">
                    <?php endif; ?>
                    <?php if ($filtroCategoria !== null): ?>
                        <input type="hidden" name="categoria" value="<?= (int)$filtroCategoria ?>">
                    <?php endif; ?>
                    <?php if ($filtroInicioRaw !== ''): ?>
                        <input type="hidden" name="inicio" value="<?= htmlspecialchars($filtroInicioRaw) ?>">
                    <?php endif; ?>
                    <?php if ($filtroFimRaw !== ''): ?>
                        <input type="hidden" name="fim" value="<?= htmlspecialchars($filtroFimRaw) ?>">
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="preco_min_filtro" class="block text-sm text-white/70 mb-1">Preco minimo (R$)</label>
                            <input type="number" step="0.01" min="0" id="preco_min_filtro" name="preco_min" value="<?= htmlspecialchars((string)($filtroPrecoMinRaw ?? '')) ?>" class="w-full rounded-xl bg-white/5 border subtle-border h-11 px-3 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        </div>

                        <div>
                            <label for="preco_max_filtro" class="block text-sm text-white/70 mb-1">Preco maximo (R$)</label>
                            <input type="number" step="0.01" min="0" id="preco_max_filtro" name="preco_max" value="<?= htmlspecialchars((string)($filtroPrecoMaxRaw ?? '')) ?>" class="w-full rounded-xl bg-white/5 border subtle-border h-11 px-3 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <button type="submit" class="btn-dn-primary px-5 py-2.5 rounded-xl bg-indigo-500 hover:bg-indigo-600 font-medium transition-colors">
                            Aplicar filtros
                        </button>
                        <?php if ($filtrosAtivos): ?>
                            <a href="index.php#frota" class="btn-dn-ghost px-5 py-2.5 rounded-xl border subtle-border hover:bg-white/10 transition-colors">
                                Limpar filtros
                            </a>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if (!empty($filtroAvisos)): ?>
                    <div class="mt-4 space-y-2">
                        <?php foreach ($filtroAvisos as $aviso): ?>
                            <div class="rounded-xl bg-amber-500/20 border border-amber-400/30 text-amber-100 px-4 py-2 text-sm">
                                <?= htmlspecialchars($aviso) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </section>

        <section id="frota" class="mb-10">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                <div>
                    <h2 class="text-2xl md:text-3xl font-bold">Veiculos disponiveis para reserva</h2>
                    <p class="text-white/70 text-sm mt-1">A frota e o foco principal da pagina inicial.</p>
                </div>
                <?php if ($filtrosAtivos): ?>
                    <span class="text-sm px-3 py-1 rounded-full bg-white/10 border subtle-border">Filtros ativos</span>
                <?php endif; ?>
            </div>

            <div class="mb-4 flex flex-wrap gap-2">
                <button type="button" data-home-panel-target="painel-busca-rapida" class="home-panel-btn px-4 py-2 rounded-xl bg-indigo-500 hover:bg-indigo-600 text-white text-sm font-medium transition-all">
                    Busca rapida
                </button>
                <button type="button" data-home-panel-target="painel-filtros" class="home-panel-btn px-4 py-2 rounded-xl border subtle-border text-white/90 hover:bg-white/10 text-sm font-medium transition-all">
                    Filtros
                </button>
            </div>

            <div id="home-filter-panels-slot" class="mb-6"></div>

            <?php if (empty($veiculos)): ?>
                <div class="section-shell backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl p-8 shadow-lg text-center">
                    <div class="w-16 h-16 mx-auto rounded-full bg-indigo-500/20 border border-indigo-400/30 flex items-center justify-center mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17h10"/>
                            <circle cx="7" cy="17" r="2"/>
                            <circle cx="17" cy="17" r="2"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Nenhum veiculo encontrado</h3>
                    <p class="text-white/70 mb-5">Tente ajustar os filtros para ampliar a busca.</p>
                    <a href="index.php#frota" class="btn-dn-primary inline-flex px-5 py-2.5 rounded-xl bg-indigo-500 hover:bg-indigo-600 font-medium transition-colors">
                        Limpar filtros
                    </a>
                </div>
            <?php else: ?>
                <div class="market-grid grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-6">
                    <?php foreach ($veiculos as $veiculo): ?>
                        <?php
                            $detalhesUrl = 'reserva/detalhes_veiculo.php?id=' . (int)$veiculo['id'];
                            $reservaVisitanteUrl = 'login.php?msg=reserve_required&redirect=' . urlencode($detalhesUrl);
                            $reservaUsuarioUrl = $podeReservar ? $detalhesUrl : 'perfil/editar.php?origem=reserva&tab=editar';
                            $nomeProprietario = trim((string)($veiculo['nome_proprietario'] ?? ''));
                            if ($nomeProprietario === '') {
                                $nomeProprietario = 'Proprietario DriveNow';
                            }
                        ?>
                        <article class="market-card backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl shadow-lg overflow-hidden flex flex-col transition-colors">
                            <div class="market-card-media h-52 bg-slate-800/80 relative">
                                <?php if (!empty($veiculo['imagem_url'])): ?>
                                    <img
                                        src="<?= htmlspecialchars($veiculo['imagem_url']) ?>"
                                        alt="<?= htmlspecialchars($veiculo['veiculo_marca'] . ' ' . $veiculo['veiculo_modelo']) ?>"
                                        class="w-full h-full object-cover"
                                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                    >
                                    <div class="hidden w-full h-full items-center justify-center absolute inset-0 bg-slate-900/70">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-white/60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17h10"/>
                                            <circle cx="7" cy="17" r="2"/>
                                            <circle cx="17" cy="17" r="2"/>
                                        </svg>
                                    </div>
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-slate-900/70">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-white/60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17h10"/>
                                            <circle cx="7" cy="17" r="2"/>
                                            <circle cx="17" cy="17" r="2"/>
                                        </svg>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($veiculo['categoria'])): ?>
                                    <span class="absolute top-3 left-3 px-2.5 py-1 rounded-full text-xs font-medium bg-indigo-500/80 border border-indigo-300/30">
                                        <?= htmlspecialchars($veiculo['categoria']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="p-5 flex-1 flex flex-col">
                                <div class="flex items-start justify-between gap-3 mb-2">
                                    <h3 class="text-lg font-semibold leading-tight">
                                        <?= htmlspecialchars($veiculo['veiculo_marca']) ?> <?= htmlspecialchars($veiculo['veiculo_modelo']) ?>
                                    </h3>
                                    <?php if ((float)$veiculo['media_avaliacao'] > 0): ?>
                                        <span class="text-xs px-2 py-1 rounded-full bg-amber-500/20 border border-amber-400/30 text-amber-200">
                                            <?= number_format((float)$veiculo['media_avaliacao'], 1, ',', '.') ?> (<?= (int)$veiculo['total_avaliacoes'] ?>)
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <p class="text-white/70 text-sm mb-3">
                                    <?= htmlspecialchars($veiculo['cidade_nome'] ?? 'Cidade nao informada') ?>
                                    <?php if (!empty($veiculo['sigla'])): ?>
                                        - <?= htmlspecialchars($veiculo['sigla']) ?>
                                    <?php endif; ?>
                                    <?php if (!empty($veiculo['nome_local'])): ?>
                                        <span class="text-white/50">. <?= htmlspecialchars($veiculo['nome_local']) ?></span>
                                    <?php endif; ?>
                                </p>

                                <div class="flex flex-wrap gap-2 text-xs mb-4">
                                    <?php if (!empty($veiculo['veiculo_ano'])): ?>
                                        <span class="px-2.5 py-1 rounded-lg bg-white/10 border subtle-border"><?= (int)$veiculo['veiculo_ano'] ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($veiculo['veiculo_cambio'])): ?>
                                        <span class="px-2.5 py-1 rounded-lg bg-white/10 border subtle-border"><?= htmlspecialchars($veiculo['veiculo_cambio']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($veiculo['veiculo_combustivel'])): ?>
                                        <span class="px-2.5 py-1 rounded-lg bg-white/10 border subtle-border"><?= htmlspecialchars($veiculo['veiculo_combustivel']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($veiculo['veiculo_acentos'])): ?>
                                        <span class="px-2.5 py-1 rounded-lg bg-white/10 border subtle-border"><?= (int)$veiculo['veiculo_acentos'] ?> lugares</span>
                                    <?php endif; ?>
                                </div>

                                <p class="text-xs text-white/60 mt-auto mb-4">
                                    Proprietario: <?= htmlspecialchars($nomeProprietario) ?>
                                </p>

                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-2xl font-bold">R$ <?= number_format((float)$veiculo['preco_diaria'], 2, ',', '.') ?></p>
                                        <p class="text-xs text-white/60">por dia</p>
                                    </div>
                                    <div class="flex gap-2">
                                        <a href="<?= htmlspecialchars($detalhesUrl) ?>" class="btn-dn-ghost px-3 py-2 rounded-xl border subtle-border hover:bg-white/10 text-sm transition-colors">
                                            Ver detalhes
                                        </a>
                                        <?php if (!$logado): ?>
                                            <a href="<?= htmlspecialchars($reservaVisitanteUrl) ?>" class="btn-dn-primary px-3 py-2 rounded-xl bg-indigo-500 hover:bg-indigo-600 text-sm font-medium transition-colors">
                                                Reservar
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= htmlspecialchars($reservaUsuarioUrl) ?>" class="btn-dn-primary px-3 py-2 rounded-xl bg-indigo-500 hover:bg-indigo-600 text-sm font-medium transition-colors">
                                                <?= $podeReservar ? 'Reservar' : 'Completar cadastro' ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($logado && !$podeReservar): ?>
                                    <p class="text-xs text-amber-200/90 mt-3">
                                        Para reservar, finalize seu cadastro e aguarde a aprovacao da CNH.
                                    </p>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="mt-8 text-center">
                    <a href="<?= htmlspecialchars($linkCatalogoCompleto) ?>" class="cta-marketplace btn-dn-primary inline-flex items-center justify-center px-6 py-3 rounded-xl bg-indigo-500 hover:bg-indigo-600 border border-indigo-400/30 font-medium transition-colors shadow-md hover:shadow-lg">
                        Ver todos os veiculos
                    </a>
                    <?php if ($totalVeiculosEncontrados > $totalVeiculosPreview): ?>
                        <p class="text-white/60 text-xs mt-2">
                            Mostrando <?= $totalVeiculosPreview ?> de <?= $totalVeiculosEncontrados ?> veiculo(s) no preview da home.
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="section-shell backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl p-6 shadow-lg">
            <h2 class="text-xl font-bold mb-4">Como funciona no DriveNow</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div class="soft-surface rounded-2xl bg-white/5 border subtle-border p-4">
                    <p class="font-semibold mb-1">1. Busque e filtre</p>
                    <p class="text-white/70">Escolha cidade, periodo e categoria para encontrar o carro ideal.</p>
                </div>
                <div class="soft-surface rounded-2xl bg-white/5 border subtle-border p-4">
                    <p class="font-semibold mb-1">2. Veja detalhes reais</p>
                    <p class="text-white/70">Acesse informacoes do veiculo, local de retirada e perfil do proprietario.</p>
                </div>
                <div class="soft-surface rounded-2xl bg-white/5 border subtle-border p-4">
                    <p class="font-semibold mb-1">3. Reserve com seguranca</p>
                    <p class="text-white/70">Usuarios autenticados seguem para o fluxo normal de reserva e pagamento.</p>
                </div>
            </div>
        </section>
    </main>

    <footer class="max-w-7xl mx-auto px-4 sm:px-6 pb-8 text-center text-white/60 text-sm">
        <p>&copy; <?= date('Y') ?> DriveNow. Todos os direitos reservados.</p>
    </footer>

    <script src="assets/notifications.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($notificacoes as $notificacao): ?>
                <?php
                    $tipo = isset($notificacao['type']) ? strtolower((string)$notificacao['type']) : 'info';
                    $mensagem = isset($notificacao['message']) ? (string)$notificacao['message'] : '';
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

            const panelButtons = document.querySelectorAll('[data-home-panel-target]');
            const panelIds = ['painel-busca-rapida', 'painel-filtros'];
            const stateKey = 'drivenow_home_active_panel';
            const filterPanelsSlot = document.getElementById('home-filter-panels-slot');

            if (filterPanelsSlot) {
                ['painel-busca-rapida', 'painel-filtros'].forEach(function(panelId) {
                    const panelElement = document.getElementById(panelId);
                    if (panelElement) {
                        filterPanelsSlot.appendChild(panelElement);
                    }
                });
            }

            const setActivePanelButton = function(panelId) {
                panelButtons.forEach(function(button) {
                    const buttonPanelId = button.getAttribute('data-home-panel-target');
                    const isActive = buttonPanelId === panelId;
                    button.classList.toggle('is-active', !!panelId && isActive);
                });
            };

            const openPanelById = function(panelId) {
                let opened = false;

                panelIds.forEach(function(id) {
                    const panelElement = document.getElementById(id);
                    if (!panelElement) {
                        return;
                    }

                    const shouldOpen = id === panelId;
                    panelElement.classList.toggle('hidden', !shouldOpen);

                    if (shouldOpen) {
                        opened = true;
                    }
                });

                setActivePanelButton(opened ? panelId : null);

                return opened;
            };

            const closeAllPanels = function() {
                panelIds.forEach(function(id) {
                    const panelElement = document.getElementById(id);
                    if (panelElement) {
                        panelElement.classList.add('hidden');
                    }
                });

                setActivePanelButton(null);
            };

            panelButtons.forEach(function(button) {
                button.addEventListener('click', function(event) {
                    event.preventDefault();

                    const targetPanelId = button.getAttribute('data-home-panel-target');
                    if (!targetPanelId) {
                        return;
                    }

                    const targetPanel = document.getElementById(targetPanelId);
                    if (!targetPanel) {
                        return;
                    }

                    const panelWillOpen = targetPanel.classList.contains('hidden');
                    let opened = false;
                    if (panelWillOpen) {
                        opened = openPanelById(targetPanelId);
                    } else {
                        closeAllPanels();
                    }

                    try {
                        if (opened) {
                            localStorage.setItem(stateKey, targetPanelId);
                        } else {
                            localStorage.removeItem(stateKey);
                        }
                    } catch (e) {
                        // Ignora erros de armazenamento local em modo restrito do navegador.
                    }

                    if (opened) {
                        targetPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            });

            const buscaAtiva = <?= $filtroBuscaAtiva ? 'true' : 'false' ?>;
            const precoAtivo = <?= $filtroPrecoAtivo ? 'true' : 'false' ?>;

            const initialHashPanelId = window.location.hash ? window.location.hash.substring(1) : '';

            if (initialHashPanelId && panelIds.indexOf(initialHashPanelId) !== -1 && openPanelById(initialHashPanelId)) {
                try {
                    localStorage.setItem(stateKey, initialHashPanelId);
                } catch (e) {
                    // Ignora falhas ao salvar armazenamento local.
                }
            } else if (precoAtivo) {
                openPanelById('painel-filtros');
            } else if (buscaAtiva) {
                openPanelById('painel-busca-rapida');
            } else {
                try {
                    const savedPanelId = localStorage.getItem(stateKey);
                    if (savedPanelId) {
                        openPanelById(savedPanelId);
                    }
                } catch (e) {
                    // Ignora falhas ao ler armazenamento local.
                }
            }
        });
    </script>
</body>
</html>
