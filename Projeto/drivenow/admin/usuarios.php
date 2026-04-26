<?php
require_once '../includes/auth.php';

exigirPerfil('admin', ['redirect' => '../index.php']);

$usuario = getUsuario();
$csrfToken = obterCsrfToken();

global $pdo;

function colunaExisteAdminUsuarios(PDO $pdo, string $tabela, string $coluna): bool
{
    static $cache = [];

    $chave = strtolower($tabela . '.' . $coluna);
    if (array_key_exists($chave, $cache)) {
        return $cache[$chave];
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$tabela, $coluna]);
        $cache[$chave] = ((int)$stmt->fetchColumn()) > 0;
    } catch (PDOException $e) {
        $cache[$chave] = false;
    }

    return $cache[$chave];
}

function redirecionarUsuariosComQueryAtual(): void
{
    $destino = 'usuarios.php';
    $queryString = (string)($_SERVER['QUERY_STRING'] ?? '');
    if ($queryString !== '') {
        $destino .= '?' . $queryString;
    }

    header('Location: ' . $destino);
    exit;
}

function metadadosStatusDocumentoUsuarios(?string $statusBruto): array
{
    $status = strtolower(trim((string)$statusBruto));

    if ($status === 'aprovado') {
        return ['label' => 'Aprovado', 'class' => 'bg-emerald-500/20 text-emerald-300 border border-emerald-400/30'];
    }

    if ($status === 'rejeitado') {
        return ['label' => 'Rejeitado', 'class' => 'bg-red-500/20 text-red-300 border border-red-400/30'];
    }

    if ($status === 'verificando') {
        return ['label' => 'Verificando', 'class' => 'bg-blue-500/20 text-blue-300 border border-blue-400/30'];
    }

    return ['label' => 'Pendente', 'class' => 'bg-amber-500/20 text-amber-300 border border-amber-400/30'];
}

$colunaAtivoExiste = colunaExisteAdminUsuarios($pdo, 'conta_usuario', 'ativo');

$filtroBusca = trim((string)($_GET['search'] ?? ''));
$filtroTipo = strtolower(trim((string)($_GET['tipo'] ?? '')));
$filtroContaStatus = strtolower(trim((string)($_GET['conta_status'] ?? '')));
$filtroDocsStatus = strtolower(trim((string)($_GET['docs_status'] ?? 'pendente')));
$paginaAtual = max(1, (int)($_GET['page'] ?? 1));
$itensPorPagina = 12;

$tiposValidos = ['', 'admin', 'proprietario', 'locatario'];
if (!in_array($filtroTipo, $tiposValidos, true)) {
    $filtroTipo = '';
}

$statusContaValidos = ['', 'ativos', 'bloqueados'];
if (!in_array($filtroContaStatus, $statusContaValidos, true)) {
    $filtroContaStatus = '';
}

$statusDocsValidos = ['', 'pendente', 'verificando', 'aprovado', 'rejeitado'];
if (!in_array($filtroDocsStatus, $statusDocsValidos, true)) {
    $filtroDocsStatus = 'pendente';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => 'Nao foi possivel validar sua sessao. Tente novamente.',
        ];
        redirecionarUsuariosComQueryAtual();
    }

    $adminAction = trim((string)($_POST['admin_action'] ?? ''));

    try {
        if ($adminAction === 'toggle_user_status') {
            if (!$colunaAtivoExiste) {
                throw new RuntimeException('Bloqueio de usuario indisponivel: execute o script de migracao para adicionar a coluna ativo.');
            }

            $alvoId = (int)($_POST['user_id'] ?? 0);
            $toggleAction = trim((string)($_POST['toggle_action'] ?? ''));

            if ($alvoId <= 0 || !in_array($toggleAction, ['bloquear', 'desbloquear'], true)) {
                throw new RuntimeException('Acao de usuario invalida.');
            }

            if ($alvoId === (int)$usuario['id'] && $toggleAction === 'bloquear') {
                throw new RuntimeException('Voce nao pode bloquear a propria conta.');
            }

            $novoStatus = ($toggleAction === 'desbloquear') ? 1 : 0;

            $stmt = $pdo->prepare('UPDATE conta_usuario SET ativo = ? WHERE id = ? AND is_admin = 0');
            $stmt->execute([$novoStatus, $alvoId]);

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Nao foi possivel alterar este usuario (admins nao podem ser bloqueados por esta tela).');
            }

            $_SESSION['notification'] = [
                'type' => 'success',
                'message' => ($novoStatus === 1)
                    ? 'Usuario desbloqueado com sucesso.'
                    : 'Usuario bloqueado com sucesso.',
            ];
        } elseif ($adminAction === 'doc_review') {
            $action = trim((string)($_POST['action'] ?? ''));
            $userId = (int)($_POST['user_id'] ?? 0);
            $observacoes = trim((string)($_POST['observacoes'] ?? ''));

            if ($userId <= 0 || !in_array($action, ['aprovar', 'rejeitar'], true)) {
                throw new RuntimeException('Acao de documento invalida.');
            }

            if ($action === 'rejeitar' && $observacoes === '') {
                throw new RuntimeException('Informe o motivo para rejeitar os documentos.');
            }

            $novoStatusDoc = ($action === 'aprovar') ? 'aprovado' : 'rejeitado';

            $stmt = $pdo->prepare(
                'UPDATE conta_usuario
                 SET status_docs = ?,
                     observacoes_docs = ?,
                     data_verificacao = NOW(),
                     admin_verificacao = ?
                 WHERE id = ?'
            );
            $stmt->execute([$novoStatusDoc, $observacoes, (int)$usuario['id'], $userId]);

            $_SESSION['notification'] = [
                'type' => 'success',
                'message' => ($novoStatusDoc === 'aprovado')
                    ? 'Documentos de CNH aprovados com sucesso.'
                    : 'Documentos de CNH rejeitados com sucesso.',
            ];
        }
    } catch (RuntimeException $e) {
        error_log('Erro de validacao em acao administrativa de usuarios: ' . $e->getMessage());
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => 'Nao foi possivel validar essa acao. Confira os dados e tente novamente.',
        ];
    } catch (Throwable $e) {
        error_log('Erro ao processar acao administrativa de usuarios: ' . $e->getMessage());
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => 'Nao foi possivel concluir a acao agora. Tente novamente mais tarde.',
        ];
    }

    redirecionarUsuariosComQueryAtual();
}

$metricas = [
    'total_usuarios' => 0,
    'total_admins' => 0,
    'total_proprietarios' => 0,
    'total_bloqueados' => 0,
];

$usuarios = [];
$documentos = [];
$totalUsuarios = 0;
$totalDocumentos = 0;
$totalPaginas = 1;
$erroCarga = '';

try {
    $sqlMetricas =
        "SELECT
            (SELECT COUNT(*) FROM conta_usuario) AS total_usuarios,
            (SELECT COUNT(*) FROM conta_usuario WHERE is_admin = 1) AS total_admins,
            (SELECT COUNT(*) FROM dono) AS total_proprietarios";

    if ($colunaAtivoExiste) {
        $sqlMetricas .= ", (SELECT COUNT(*) FROM conta_usuario WHERE ativo = 0) AS total_bloqueados";
    } else {
        $sqlMetricas .= ", 0 AS total_bloqueados";
    }

    $stmtMetricas = $pdo->query($sqlMetricas);
    $metricasBanco = $stmtMetricas->fetch(PDO::FETCH_ASSOC) ?: [];
    foreach ($metricas as $chave => $valor) {
        $metricas[$chave] = (int)($metricasBanco[$chave] ?? 0);
    }

    $where = ['1=1'];
    $params = [];

    if ($filtroBusca !== '') {
        $termo = '%' . $filtroBusca . '%';
        $where[] = '(cu.primeiro_nome LIKE ? OR cu.segundo_nome LIKE ? OR cu.e_mail LIKE ? OR cu.cpf LIKE ?)';
        $params[] = $termo;
        $params[] = $termo;
        $params[] = $termo;
        $params[] = $termo;
    }

    if ($filtroTipo === 'admin') {
        $where[] = 'cu.is_admin = 1';
    } elseif ($filtroTipo === 'proprietario') {
        $where[] = 'cu.is_admin = 0 AND d.id IS NOT NULL';
    } elseif ($filtroTipo === 'locatario') {
        $where[] = 'cu.is_admin = 0 AND d.id IS NULL';
    }

    if ($colunaAtivoExiste && $filtroContaStatus === 'ativos') {
        $where[] = 'COALESCE(cu.ativo, 1) = 1';
    } elseif ($colunaAtivoExiste && $filtroContaStatus === 'bloqueados') {
        $where[] = 'COALESCE(cu.ativo, 1) = 0';
    }

    $sqlCount =
        'SELECT COUNT(*)
         FROM conta_usuario cu
         LEFT JOIN dono d ON d.conta_usuario_id = cu.id
         WHERE ' . implode(' AND ', $where);

    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalUsuarios = (int)$stmtCount->fetchColumn();

    $totalPaginas = max(1, (int)ceil($totalUsuarios / $itensPorPagina));
    if ($paginaAtual > $totalPaginas) {
        $paginaAtual = $totalPaginas;
    }

    $offset = max(0, ($paginaAtual - 1) * $itensPorPagina);

    $colunaAtivoSql = $colunaAtivoExiste ? 'COALESCE(cu.ativo, 1)' : '1';

    $sqlUsuarios =
        "SELECT cu.id,
                cu.primeiro_nome,
                cu.segundo_nome,
                cu.e_mail,
                cu.telefone,
                cu.cpf,
                cu.data_de_entrada,
                cu.status_docs,
                cu.foto_cnh_frente,
                cu.foto_cnh_verso,
                cu.tem_cnh,
                cu.cadastro_completo,
                cu.is_admin,
                {$colunaAtivoSql} AS ativo,
                CASE
                    WHEN cu.is_admin = 1 THEN 'Administrador'
                    WHEN d.id IS NOT NULL THEN 'Proprietario'
                    ELSE 'Locatario'
                END AS tipo_conta,
                (
                    SELECT COUNT(*)
                    FROM reserva r
                    WHERE r.conta_usuario_id = cu.id
                ) AS total_reservas,
                (
                    SELECT COUNT(*)
                    FROM veiculo v
                    INNER JOIN dono d2 ON d2.id = v.dono_id
                    WHERE d2.conta_usuario_id = cu.id
                ) AS total_veiculos
         FROM conta_usuario cu
         LEFT JOIN dono d ON d.conta_usuario_id = cu.id
         WHERE " . implode(' AND ', $where) .
        " ORDER BY cu.id DESC
         LIMIT {$offset}, {$itensPorPagina}";

    $stmtUsuarios = $pdo->prepare($sqlUsuarios);
    $stmtUsuarios->execute($params);
    $usuarios = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);

    $whereDocs = ["(u.foto_cnh_frente IS NOT NULL OR u.foto_cnh_verso IS NOT NULL)"];
    $paramsDocs = [];

    if ($filtroDocsStatus !== '') {
        $whereDocs[] = 'u.status_docs = ?';
        $paramsDocs[] = $filtroDocsStatus;
    }

    if ($filtroBusca !== '') {
        $termoDoc = '%' . $filtroBusca . '%';
        $whereDocs[] = '(u.primeiro_nome LIKE ? OR u.segundo_nome LIKE ? OR u.e_mail LIKE ? OR u.cpf LIKE ?)';
        $paramsDocs[] = $termoDoc;
        $paramsDocs[] = $termoDoc;
        $paramsDocs[] = $termoDoc;
        $paramsDocs[] = $termoDoc;
    }

    $sqlCountDocs = 'SELECT COUNT(*) FROM conta_usuario u WHERE ' . implode(' AND ', $whereDocs);
    $stmtCountDocs = $pdo->prepare($sqlCountDocs);
    $stmtCountDocs->execute($paramsDocs);
    $totalDocumentos = (int)$stmtCountDocs->fetchColumn();

    $sqlDocs =
        "SELECT u.id,
                u.primeiro_nome,
                u.segundo_nome,
                u.e_mail,
                u.cpf,
                u.foto_cnh_frente,
                u.foto_cnh_verso,
                u.status_docs,
                u.observacoes_docs,
                u.data_verificacao,
                u.admin_verificacao,
                (
                    SELECT CONCAT(COALESCE(a.primeiro_nome, ''), ' ', COALESCE(a.segundo_nome, ''))
                    FROM conta_usuario a
                    WHERE a.id = u.admin_verificacao
                    LIMIT 1
                ) AS admin_nome
         FROM conta_usuario u
         WHERE " . implode(' AND ', $whereDocs) .
        " ORDER BY
            CASE
                WHEN u.status_docs = 'pendente' THEN 1
                WHEN u.status_docs = 'verificando' THEN 2
                WHEN u.status_docs = 'rejeitado' THEN 3
                WHEN u.status_docs = 'aprovado' THEN 4
                ELSE 5
            END,
            u.id DESC
         LIMIT 12";

    $stmtDocs = $pdo->prepare($sqlDocs);
    $stmtDocs->execute($paramsDocs);
    $documentos = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $erroCarga = 'Nao foi possivel carregar os dados de usuarios.';
}

$notification = $_SESSION['notification'] ?? null;
if ($notification) {
    unset($_SESSION['notification']);
}

$usuariosJson = json_encode($usuarios, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($usuariosJson === false) {
    $usuariosJson = '[]';
}

$documentosJson = json_encode($documentos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($documentosJson === false) {
    $documentosJson = '[]';
}

$navBasePath = '../';
$navCurrent = 'admin';
$navFixed = true;
$navShowMarketplaceAnchors = false;

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestao de Usuarios - DriveNow Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/ui-modern.css">
    <style>
        .animate-pulse-15s { animation-duration: 15s; }
        .animate-pulse-20s { animation-duration: 20s; }
        .animate-pulse-25s { animation-duration: 25s; }
        .subtle-border { border-color: rgba(255, 255, 255, 0.1); }

        option {
            background-color: #1e293b !important;
            color: #fff !important;
        }

        .document-image {
            max-width: 100%;
            max-height: 280px;
            object-fit: contain;
        }

        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
        }
    </style>
</head>
<body class="drivenow-modern min-h-screen bg-gradient-to-br from-slate-900 via-indigo-950 to-purple-950 text-white p-4 md:p-8 overflow-x-hidden">
    <div class="fixed top-0 right-0 w-96 h-96 rounded-full bg-indigo-700 opacity-10 blur-3xl -z-10 animate-pulse animate-pulse-15s"></div>
    <div class="fixed bottom-0 left-0 w-80 h-80 rounded-full bg-purple-700 opacity-10 blur-3xl -z-10 animate-pulse animate-pulse-20s"></div>
    <div class="fixed top-1/3 left-1/4 w-64 h-64 rounded-full bg-slate-700 opacity-5 blur-3xl -z-10 animate-pulse animate-pulse-25s"></div>

    <?php include_once '../includes/navbar.php'; ?>

    <main class="container mx-auto max-w-7xl pt-28">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <div>
                <h2 class="text-2xl md:text-3xl font-bold text-white">Gestao de usuarios</h2>
                <p class="text-white/70 mt-1">Controle de contas, tipo de perfil e atividade por usuario.</p>
            </div>
            <a href="dadmin.php" class="btn-dn-primary bg-indigo-500 hover:bg-indigo-600 text-white rounded-xl transition-colors border border-indigo-400/30 px-4 py-2 text-sm font-medium shadow-md hover:shadow-lg">Voltar ao painel admin</a>
        </div>

        <?php if ($erroCarga !== ''): ?>
            <div class="mb-6 rounded-2xl border border-red-400/30 bg-red-500/20 px-4 py-3 text-red-100">
                <?= htmlspecialchars($erroCarga) ?>
            </div>
        <?php endif; ?>

        <?php if (!$colunaAtivoExiste): ?>
            <div class="mb-6 rounded-2xl border border-amber-400/30 bg-amber-500/20 px-4 py-3 text-amber-100">
                Bloqueio/desbloqueio de usuarios requer a coluna <strong>ativo</strong> em conta_usuario.
                Execute o script SQL de migracao para habilitar esse controle.
            </div>
        <?php endif; ?>

        <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
            <div class="metric-card backdrop-blur-lg bg-white/5 border subtle-border rounded-2xl p-5 shadow-lg">
                <p class="text-white/60 text-sm">Total de contas</p>
                <p class="text-3xl font-bold mt-2"><?= (int)$metricas['total_usuarios'] ?></p>
            </div>
            <div class="metric-card backdrop-blur-lg bg-white/5 border subtle-border rounded-2xl p-5 shadow-lg">
                <p class="text-white/60 text-sm">Administradores</p>
                <p class="text-3xl font-bold mt-2"><?= (int)$metricas['total_admins'] ?></p>
            </div>
            <div class="metric-card backdrop-blur-lg bg-white/5 border subtle-border rounded-2xl p-5 shadow-lg">
                <p class="text-white/60 text-sm">Proprietarios</p>
                <p class="text-3xl font-bold mt-2"><?= (int)$metricas['total_proprietarios'] ?></p>
            </div>
            <div class="metric-card backdrop-blur-lg bg-white/5 border subtle-border rounded-2xl p-5 shadow-lg">
                <p class="text-white/60 text-sm">Usuarios bloqueados</p>
                <p class="text-3xl font-bold mt-2"><?= (int)$metricas['total_bloqueados'] ?></p>
            </div>
        </section>

        <section class="section-shell backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl p-6 mb-8 shadow-lg">
            <form method="GET" action="usuarios.php" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
                <div class="xl:col-span-2">
                    <label for="search" class="block text-white font-medium mb-2">Buscar usuario</label>
                    <input
                        type="text"
                        id="search"
                        name="search"
                        value="<?= htmlspecialchars($filtroBusca) ?>"
                        placeholder="Nome, email ou CPF"
                        class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50"
                    >
                </div>

                <div>
                    <label for="tipo" class="block text-white font-medium mb-2">Tipo de conta</label>
                    <select id="tipo" name="tipo" class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50">
                        <option value="" <?= $filtroTipo === '' ? 'selected' : '' ?>>Todos</option>
                        <option value="admin" <?= $filtroTipo === 'admin' ? 'selected' : '' ?>>Administrador</option>
                        <option value="proprietario" <?= $filtroTipo === 'proprietario' ? 'selected' : '' ?>>Proprietario</option>
                        <option value="locatario" <?= $filtroTipo === 'locatario' ? 'selected' : '' ?>>Locatario</option>
                    </select>
                </div>

                <div>
                    <label for="conta_status" class="block text-white font-medium mb-2">Status da conta</label>
                    <select id="conta_status" name="conta_status" class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50" <?= $colunaAtivoExiste ? '' : 'disabled' ?>>
                        <option value="" <?= $filtroContaStatus === '' ? 'selected' : '' ?>>Todos</option>
                        <option value="ativos" <?= $filtroContaStatus === 'ativos' ? 'selected' : '' ?>>Ativos</option>
                        <option value="bloqueados" <?= $filtroContaStatus === 'bloqueados' ? 'selected' : '' ?>>Bloqueados</option>
                    </select>
                </div>

                <div>
                    <label for="docs_status" class="block text-white font-medium mb-2">Status docs CNH</label>
                    <select id="docs_status" name="docs_status" class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50">
                        <option value="" <?= $filtroDocsStatus === '' ? 'selected' : '' ?>>Todos</option>
                        <option value="pendente" <?= $filtroDocsStatus === 'pendente' ? 'selected' : '' ?>>Pendentes</option>
                        <option value="verificando" <?= $filtroDocsStatus === 'verificando' ? 'selected' : '' ?>>Verificando</option>
                        <option value="aprovado" <?= $filtroDocsStatus === 'aprovado' ? 'selected' : '' ?>>Aprovados</option>
                        <option value="rejeitado" <?= $filtroDocsStatus === 'rejeitado' ? 'selected' : '' ?>>Rejeitados</option>
                    </select>
                </div>

                <div class="md:col-span-2 xl:col-span-5 flex flex-wrap gap-3 justify-end">
                    <a href="usuarios.php" class="btn-dn-ghost px-4 py-3 bg-white/10 hover:bg-white/20 border border-white/10 rounded-xl text-white font-medium transition-colors">Limpar</a>
                    <button type="submit" class="btn-dn-primary px-4 py-3 bg-indigo-500 hover:bg-indigo-600 text-white font-medium rounded-xl transition-colors border border-indigo-400/30 shadow-md hover:shadow-lg">Aplicar filtros</button>
                </div>
            </form>
        </section>

        <section class="section-shell backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl p-6 shadow-lg">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-semibold">Lista de usuarios</h3>
                <span class="text-white/60 text-sm"><?= (int)$totalUsuarios ?> registro(s)</span>
            </div>

            <?php if (empty($usuarios)): ?>
                <p class="text-white/60">Nenhum usuario encontrado para os filtros atuais.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="dn-table w-full min-w-[980px]">
                        <thead>
                            <tr class="border-b border-white/10">
                                <th class="px-4 py-3 text-left text-xs uppercase text-white/70">Nome</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-white/70">Email</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-white/70">Tipo</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-white/70">Cadastro</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-white/70">Reservas</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-white/70">Veiculos</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-white/70">Status</th>
                                <th class="px-4 py-3 text-right text-xs uppercase text-white/70">Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $item): ?>
                                <?php
                                    $ehAtivo = ((int)$item['ativo'] === 1);
                                    $statusContaClasse = $ehAtivo
                                        ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-400/30'
                                        : 'bg-red-500/20 text-red-300 border border-red-400/30';
                                    $statusContaLabel = $colunaAtivoExiste ? ($ehAtivo ? 'Ativo' : 'Bloqueado') : 'Nao configurado';
                                ?>
                                <tr class="border-b border-white/5 hover:bg-white/5">
                                    <td class="px-4 py-3 text-sm font-medium"><?= htmlspecialchars(trim(($item['primeiro_nome'] ?? '') . ' ' . ($item['segundo_nome'] ?? ''))) ?></td>
                                    <td class="px-4 py-3 text-sm text-white/80"><?= htmlspecialchars($item['e_mail'] ?? '-') ?></td>
                                    <td class="px-4 py-3 text-sm text-white/80"><?= htmlspecialchars($item['tipo_conta'] ?? '-') ?></td>
                                    <td class="px-4 py-3 text-sm text-white/80">
                                        <?= !empty($item['data_de_entrada']) ? date('d/m/Y', strtotime($item['data_de_entrada'])) : '-' ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-white/80"><?= (int)($item['total_reservas'] ?? 0) ?></td>
                                    <td class="px-4 py-3 text-sm text-white/80"><?= (int)($item['total_veiculos'] ?? 0) ?></td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 text-xs rounded-full <?= $statusContaClasse ?>"><?= $statusContaLabel ?></span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button type="button" onclick="abrirModalUsuario(<?= (int)$item['id'] ?>)" class="text-indigo-300 hover:text-indigo-200 text-sm mr-3">Detalhes</button>

                                        <?php if ($colunaAtivoExiste && (int)$item['is_admin'] !== 1): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="admin_action" value="toggle_user_status">
                                                <input type="hidden" name="user_id" value="<?= (int)$item['id'] ?>">
                                                <?php if ($ehAtivo): ?>
                                                    <input type="hidden" name="toggle_action" value="bloquear">
                                                    <button type="submit" class="px-3 py-1 text-xs rounded-lg bg-red-500/20 hover:bg-red-500/30 border border-red-400/30 text-red-200">Bloquear</button>
                                                <?php else: ?>
                                                    <input type="hidden" name="toggle_action" value="desbloquear">
                                                    <button type="submit" class="px-3 py-1 text-xs rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-400/30 text-emerald-200">Desbloquear</button>
                                                <?php endif; ?>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPaginas > 1): ?>
                    <div class="mt-6 flex justify-center">
                        <nav class="flex items-center space-x-2">
                            <?php
                            $paramsBase = $_GET;
                            if ($paginaAtual > 1):
                                $paramsPrev = $paramsBase;
                                $paramsPrev['page'] = $paginaAtual - 1;
                            ?>
                                <a href="usuarios.php?<?= htmlspecialchars(http_build_query($paramsPrev)) ?>" class="px-3 py-1 rounded-md bg-white/10 text-white/70 hover:bg-white/20 hover:text-white transition-colors">&laquo; Anterior</a>
                            <?php endif; ?>

                            <?php
                            $inicio = max(1, $paginaAtual - 2);
                            $fim = min($totalPaginas, $paginaAtual + 2);
                            for ($i = $inicio; $i <= $fim; $i++):
                                $paramsNumero = $paramsBase;
                                $paramsNumero['page'] = $i;
                            ?>
                                <?php if ($i === $paginaAtual): ?>
                                    <span class="px-3 py-1 rounded-md bg-indigo-500 text-white"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="usuarios.php?<?= htmlspecialchars(http_build_query($paramsNumero)) ?>" class="px-3 py-1 rounded-md bg-white/10 text-white/70 hover:bg-white/20 hover:text-white transition-colors"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($paginaAtual < $totalPaginas):
                                $paramsNext = $paramsBase;
                                $paramsNext['page'] = $paginaAtual + 1;
                            ?>
                                <a href="usuarios.php?<?= htmlspecialchars(http_build_query($paramsNext)) ?>" class="px-3 py-1 rounded-md bg-white/10 text-white/70 hover:bg-white/20 hover:text-white transition-colors">Proximo &raquo;</a>
                            <?php endif; ?>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <section id="sec-cnh" class="section-shell backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl p-6 mt-8 shadow-lg">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
                <div>
                    <h3 class="text-xl font-semibold">Verificacao de documentos da CNH</h3>
                    <p class="text-white/70 text-sm mt-1">Aprovacao/rejeicao de CNH agora fica junto da gestao de usuarios.</p>
                </div>
                <span class="text-white/60 text-sm"><?= (int)$totalDocumentos ?> registro(s)</span>
            </div>

            <?php if (empty($documentos)): ?>
                <p class="text-white/60">Nenhum documento de CNH encontrado para os filtros atuais.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="dn-table w-full min-w-[900px]">
                        <thead>
                            <tr class="border-b border-white/10">
                                <th class="px-4 py-3 text-left text-xs uppercase text-white/70">Usuario</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-white/70">Email</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-white/70">CPF</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-white/70">Status</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-white/70">Verificado por</th>
                                <th class="px-4 py-3 text-right text-xs uppercase text-white/70">Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documentos as $doc): ?>
                                <?php $metaDoc = metadadosStatusDocumentoUsuarios($doc['status_docs'] ?? null); ?>
                                <tr class="border-b border-white/5 hover:bg-white/5">
                                    <td class="px-4 py-3 text-sm font-medium"><?= htmlspecialchars(trim(($doc['primeiro_nome'] ?? '') . ' ' . ($doc['segundo_nome'] ?? ''))) ?></td>
                                    <td class="px-4 py-3 text-sm text-white/80"><?= htmlspecialchars($doc['e_mail'] ?? '-') ?></td>
                                    <td class="px-4 py-3 text-sm text-white/80"><?= htmlspecialchars($doc['cpf'] ?: 'Nao informado') ?></td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 text-xs rounded-full <?= $metaDoc['class'] ?>"><?= htmlspecialchars($metaDoc['label']) ?></span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-white/80">
                                        <?= htmlspecialchars(trim((string)($doc['admin_nome'] ?? ''))) !== '' ? htmlspecialchars(trim((string)$doc['admin_nome'])) : '-' ?>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button type="button" onclick="abrirModalDocumentos(<?= (int)$doc['id'] ?>)" class="text-indigo-300 hover:text-indigo-200 text-sm">Verificar</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <div id="modalUsuario" class="fixed inset-0 z-50 hidden modal-backdrop flex items-center justify-center p-4">
        <div class="relative bg-gradient-to-br from-slate-900 via-indigo-950 to-purple-950 border subtle-border rounded-3xl shadow-xl max-w-2xl w-full overflow-hidden">
            <div class="p-6 flex items-center justify-between border-b border-white/10">
                <h3 class="text-xl font-bold text-white">Detalhes do usuario</h3>
                <button type="button" onclick="fecharModalUsuario()" class="text-white/70 hover:text-white">Fechar</button>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-3 text-sm text-white/85">
                <p><strong>ID:</strong> <span id="uModalId">-</span></p>
                <p><strong>Tipo:</strong> <span id="uModalTipo">-</span></p>
                <p class="md:col-span-2"><strong>Nome:</strong> <span id="uModalNome">-</span></p>
                <p class="md:col-span-2"><strong>Email:</strong> <span id="uModalEmail">-</span></p>
                <p><strong>Telefone:</strong> <span id="uModalTelefone">-</span></p>
                <p><strong>CPF:</strong> <span id="uModalCpf">-</span></p>
                <p><strong>Cadastro:</strong> <span id="uModalCadastro">-</span></p>
                <p><strong>Status docs:</strong> <span id="uModalStatusDocs">-</span></p>
                <p><strong>Tem CNH:</strong> <span id="uModalTemCnh">-</span></p>
                <p><strong>Cadastro completo:</strong> <span id="uModalCadastroCompleto">-</span></p>
                <p><strong>Total reservas:</strong> <span id="uModalReservas">0</span></p>
                <p><strong>Total veiculos:</strong> <span id="uModalVeiculos">0</span></p>
                <p><strong>Status conta:</strong> <span id="uModalStatusConta">-</span></p>

                <div class="md:col-span-2 mt-2">
                    <p class="text-white font-semibold mb-2">Documentos CNH</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="bg-white/5 rounded-lg p-3 border border-white/10">
                            <p class="text-white/70 text-xs mb-2">Frente</p>
                            <div class="bg-white/5 rounded-lg p-2 min-h-[160px] flex items-center justify-center border border-white/10">
                                <img id="uDocImgFrente" src="" alt="CNH Frente" class="document-image hidden">
                                <span id="uDocSemFrente" class="text-white/50 text-xs">Imagem nao disponivel</span>
                            </div>
                        </div>

                        <div class="bg-white/5 rounded-lg p-3 border border-white/10">
                            <p class="text-white/70 text-xs mb-2">Verso</p>
                            <div class="bg-white/5 rounded-lg p-2 min-h-[160px] flex items-center justify-center border border-white/10">
                                <img id="uDocImgVerso" src="" alt="CNH Verso" class="document-image hidden">
                                <span id="uDocSemVerso" class="text-white/50 text-xs">Imagem nao disponivel</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="modalDocumentos" class="fixed inset-0 z-50 hidden modal-backdrop flex items-center justify-center p-4">
        <div class="relative bg-gradient-to-br from-slate-900 via-indigo-950 to-purple-950 border subtle-border rounded-3xl shadow-xl max-w-5xl w-full max-h-[92vh] overflow-hidden">
            <div class="p-6 flex items-center justify-between border-b border-white/10">
                <h3 class="text-xl font-bold text-white" id="docModalTitulo">Documentos CNH</h3>
                <button type="button" onclick="fecharModalDocumentos()" class="text-white/70 hover:text-white">Fechar</button>
            </div>

            <div class="p-6 overflow-y-auto max-h-[calc(92vh-120px)] space-y-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="bg-white/5 rounded-xl p-4 border border-white/10">
                        <h4 class="text-white font-medium mb-2">CNH Frente</h4>
                        <div class="bg-white/5 rounded-lg p-2 min-h-[220px] flex items-center justify-center border border-white/10">
                            <img id="docImgFrente" src="" alt="CNH Frente" class="document-image hidden">
                            <span id="docSemFrente" class="text-white/50 text-sm">Imagem nao disponivel</span>
                        </div>
                    </div>
                    <div class="bg-white/5 rounded-xl p-4 border border-white/10">
                        <h4 class="text-white font-medium mb-2">CNH Verso</h4>
                        <div class="bg-white/5 rounded-lg p-2 min-h-[220px] flex items-center justify-center border border-white/10">
                            <img id="docImgVerso" src="" alt="CNH Verso" class="document-image hidden">
                            <span id="docSemVerso" class="text-white/50 text-sm">Imagem nao disponivel</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white/5 rounded-xl p-4 border border-white/10">
                    <div class="flex flex-wrap items-center gap-3 mb-3">
                        <span id="docStatusBadge" class="px-3 py-1 rounded-full text-sm font-medium bg-amber-500/20 text-amber-300 border border-amber-400/30">Pendente</span>
                        <span class="text-white/60 text-sm" id="docMetaUsuario">-</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm text-white/80">
                        <p>Email: <span id="docEmail">-</span></p>
                        <p>CPF: <span id="docCpf">-</span></p>
                        <p>Verificado por: <span id="docAdmin">-</span></p>
                        <p>Data verificacao: <span id="docDataVerificacao">-</span></p>
                    </div>
                    <div class="mt-3 bg-white/5 rounded-lg border border-white/10 p-3">
                        <p class="text-white/60 text-xs mb-1">Observacoes atuais</p>
                        <p id="docObservacoesAtuais" class="text-white/80 text-sm">-</p>
                    </div>
                </div>

                <form id="formDocReview" method="POST" class="bg-white/5 rounded-xl p-4 border border-white/10 space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="admin_action" value="doc_review">
                    <input type="hidden" name="user_id" id="docUserId" value="">

                    <div>
                        <label for="docObservacoes" class="block text-white font-medium mb-2">Observacoes (obrigatorio para rejeicao)</label>
                        <textarea id="docObservacoes" name="observacoes" rows="3" class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50" placeholder="Descreva o motivo da analise..."></textarea>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <button type="submit" name="action" value="aprovar" class="flex-1 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl transition-colors border border-emerald-400/30 px-4 py-3 font-medium">Aprovar documentos</button>
                        <button type="submit" name="action" value="rejeitar" class="flex-1 bg-red-500 hover:bg-red-600 text-white rounded-xl transition-colors border border-red-400/30 px-4 py-3 font-medium">Rejeitar documentos</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer class="container mx-auto mt-12 px-4 pb-4 text-center text-white/60 text-sm">
        <p>&copy; <script>document.write(new Date().getFullYear())</script> DriveNow Admin. Todos os direitos reservados.</p>
    </footer>

    <script src="../assets/notifications.js"></script>
    <script>
        const usuariosData = <?= $usuariosJson ?>;
        const docsData = <?= $documentosJson ?>;
        const bloqueioDisponivel = <?= $colunaAtivoExiste ? 'true' : 'false' ?>;

        function abrirModalUsuario(userId) {
            const user = usuariosData.find((item) => parseInt(item.id, 10) === parseInt(userId, 10));
            if (!user) {
                notifyError('Usuario nao encontrado.');
                return;
            }

            document.getElementById('uModalId').textContent = user.id || '-';
            document.getElementById('uModalTipo').textContent = user.tipo_conta || '-';
            document.getElementById('uModalNome').textContent = ((user.primeiro_nome || '') + ' ' + (user.segundo_nome || '')).trim() || '-';
            document.getElementById('uModalEmail').textContent = user.e_mail || '-';
            document.getElementById('uModalTelefone').textContent = user.telefone || '-';
            document.getElementById('uModalCpf').textContent = user.cpf || '-';

            if (user.data_de_entrada) {
                document.getElementById('uModalCadastro').textContent = new Date(user.data_de_entrada + 'T00:00:00').toLocaleDateString('pt-BR');
            } else {
                document.getElementById('uModalCadastro').textContent = '-';
            }

            document.getElementById('uModalStatusDocs').textContent = user.status_docs || 'pendente';
            document.getElementById('uModalTemCnh').textContent = parseInt(user.tem_cnh || 0, 10) === 1 ? 'Sim' : 'Nao';
            document.getElementById('uModalCadastroCompleto').textContent = parseInt(user.cadastro_completo || 0, 10) === 1 ? 'Sim' : 'Nao';
            document.getElementById('uModalReservas').textContent = user.total_reservas || '0';
            document.getElementById('uModalVeiculos').textContent = user.total_veiculos || '0';

            if (bloqueioDisponivel) {
                document.getElementById('uModalStatusConta').textContent = parseInt(user.ativo || 1, 10) === 1 ? 'Ativo' : 'Bloqueado';
            } else {
                document.getElementById('uModalStatusConta').textContent = 'Nao configurado';
            }

            carregarImagemDocumento(user.id, 'frente', user.foto_cnh_frente, document.getElementById('uDocImgFrente'), document.getElementById('uDocSemFrente'));
            carregarImagemDocumento(user.id, 'verso', user.foto_cnh_verso, document.getElementById('uDocImgVerso'), document.getElementById('uDocSemVerso'));

            document.getElementById('modalUsuario').classList.remove('hidden');
        }

        function fecharModalUsuario() {
            document.getElementById('modalUsuario').classList.add('hidden');
        }

        function carregarImagemDocumento(userId, tipo, caminho, imgEl, semEl) {
            if (!caminho) {
                imgEl.classList.add('hidden');
                semEl.classList.remove('hidden');
                imgEl.src = '';
                return;
            }

            const caminhoNormalizado = String(caminho).replace(/\\/g, '/').replace(/^\//, '');
            imgEl.src = caminhoNormalizado.startsWith('uploads/')
                ? '../' + caminhoNormalizado
                : '../perfil/download_documento.php?id=' + encodeURIComponent(userId) + '&tipo=' + encodeURIComponent(tipo) + '&inline=1';
            imgEl.classList.remove('hidden');
            semEl.classList.add('hidden');

            imgEl.onerror = function () {
                imgEl.classList.add('hidden');
                semEl.classList.remove('hidden');
            };
        }

        function abrirModalDocumentos(userId) {
            const doc = docsData.find((item) => parseInt(item.id, 10) === parseInt(userId, 10));
            if (!doc) {
                notifyError('Documento nao encontrado.');
                return;
            }

            document.getElementById('docModalTitulo').textContent = 'Documentos de ' + ((doc.primeiro_nome || '') + ' ' + (doc.segundo_nome || '')).trim();
            document.getElementById('docMetaUsuario').textContent = 'Usuario #' + doc.id;
            document.getElementById('docEmail').textContent = doc.e_mail || '-';
            document.getElementById('docCpf').textContent = doc.cpf || '-';
            document.getElementById('docAdmin').textContent = (doc.admin_nome && doc.admin_nome.trim() !== '') ? doc.admin_nome : '-';
            document.getElementById('docDataVerificacao').textContent = doc.data_verificacao ? new Date(doc.data_verificacao).toLocaleString('pt-BR') : '-';
            document.getElementById('docObservacoesAtuais').textContent = doc.observacoes_docs || '-';
            document.getElementById('docUserId').value = doc.id;

            const statusBadge = document.getElementById('docStatusBadge');
            const status = (doc.status_docs || 'pendente').toLowerCase();
            if (status === 'aprovado') {
                statusBadge.className = 'px-3 py-1 rounded-full text-sm font-medium bg-emerald-500/20 text-emerald-300 border border-emerald-400/30';
                statusBadge.textContent = 'Aprovado';
            } else if (status === 'rejeitado') {
                statusBadge.className = 'px-3 py-1 rounded-full text-sm font-medium bg-red-500/20 text-red-300 border border-red-400/30';
                statusBadge.textContent = 'Rejeitado';
            } else if (status === 'verificando') {
                statusBadge.className = 'px-3 py-1 rounded-full text-sm font-medium bg-blue-500/20 text-blue-300 border border-blue-400/30';
                statusBadge.textContent = 'Verificando';
            } else {
                statusBadge.className = 'px-3 py-1 rounded-full text-sm font-medium bg-amber-500/20 text-amber-300 border border-amber-400/30';
                statusBadge.textContent = 'Pendente';
            }

            carregarImagemDocumento(doc.id, 'frente', doc.foto_cnh_frente, document.getElementById('docImgFrente'), document.getElementById('docSemFrente'));
            carregarImagemDocumento(doc.id, 'verso', doc.foto_cnh_verso, document.getElementById('docImgVerso'), document.getElementById('docSemVerso'));

            document.getElementById('docObservacoes').value = '';
            document.getElementById('modalDocumentos').classList.remove('hidden');
        }

        function fecharModalDocumentos() {
            document.getElementById('modalDocumentos').classList.add('hidden');
        }

        document.getElementById('formDocReview').addEventListener('submit', function (event) {
            const submitter = event.submitter;
            if (!submitter) {
                return;
            }

            if (submitter.value === 'rejeitar') {
                const obs = document.getElementById('docObservacoes').value.trim();
                if (obs === '') {
                    event.preventDefault();
                    notifyError('Informe o motivo para rejeitar os documentos.');
                }
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                fecharModalUsuario();
                fecharModalDocumentos();
            }
        });

        <?php if (is_array($notification) && !empty($notification['message'])): ?>
            document.addEventListener('DOMContentLoaded', function () {
                const tipo = <?= json_encode((string)($notification['type'] ?? 'info')) ?>;
                const mensagem = <?= json_encode((string)($notification['message'] ?? '')) ?>;

                if (tipo === 'success') {
                    notifySuccess(mensagem);
                } else if (tipo === 'error') {
                    notifyError(mensagem);
                } else if (tipo === 'warning') {
                    notifyWarning(mensagem);
                } else {
                    notifyInfo(mensagem);
                }
            });
        <?php endif; ?>
    </script>
</body>
</html>
