<?php
require_once '../includes/auth.php';

exigirPerfil('admin', ['redirect' => '../index.php']);

$usuario = getUsuario();
$csrfToken = obterCsrfToken();

global $pdo;

function colunaExisteAdmin(PDO $pdo, string $tabela, string $coluna): bool
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

function redirecionarPainelAdminComQueryAtual(): void
{
    $destino = 'dadmin.php';
    $queryString = (string)($_SERVER['QUERY_STRING'] ?? '');
    if ($queryString !== '') {
        $destino .= '?' . $queryString;
    }

    header('Location: ' . $destino);
    exit;
}

function metadadosStatusReserva(?string $statusBruto, ?string $devolucaoData): array
{
    $status = strtolower(trim((string)$statusBruto));
    $hoje = strtotime(date('Y-m-d'));
    $fim = $devolucaoData ? strtotime($devolucaoData) : false;

    if ($status === 'cancelada' || $status === 'rejeitada') {
        return [
            'categoria' => 'cancelada',
            'label' => 'Cancelada/Rejeitada',
            'class' => 'bg-red-500/20 text-red-300 border border-red-400/30',
        ];
    }

    if ($status === 'finalizada' || ($status === 'confirmada' && $fim && $fim < $hoje)) {
        return [
            'categoria' => 'concluida',
            'label' => 'Concluida',
            'class' => 'bg-slate-500/20 text-slate-300 border border-slate-400/30',
        ];
    }

    if ($status === 'confirmada') {
        return [
            'categoria' => 'aprovada',
            'label' => 'Confirmada',
            'class' => 'bg-emerald-500/20 text-emerald-300 border border-emerald-400/30',
        ];
    }

    if ($status === 'pago') {
        return [
            'categoria' => 'pendente',
            'label' => 'Pago (aguardando)',
            'class' => 'bg-purple-500/20 text-purple-300 border border-purple-400/30',
        ];
    }

    return [
        'categoria' => 'pendente',
        'label' => 'Pendente',
        'class' => 'bg-amber-500/20 text-amber-300 border border-amber-400/30',
    ];
}

function metadadosStatusDocumento(?string $statusBruto): array
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

$filtroBusca = trim((string)($_GET['q'] ?? ''));
$filtroDocsStatus = strtolower(trim((string)($_GET['docs_status'] ?? '')));
$filtroVeiculoStatus = strtolower(trim((string)($_GET['veiculo_status'] ?? '')));
$filtroReservaStatus = strtolower(trim((string)($_GET['reserva_status'] ?? '')));
$paginaDocs = max(1, (int)($_GET['docs_page'] ?? 1));

$statusDocsValidos = ['', 'pendente', 'verificando', 'aprovado', 'rejeitado'];
if (!in_array($filtroDocsStatus, $statusDocsValidos, true)) {
    $filtroDocsStatus = '';
}

$statusVeiculoValidos = ['', 'ativos', 'inativos'];
if (!in_array($filtroVeiculoStatus, $statusVeiculoValidos, true)) {
    $filtroVeiculoStatus = '';
}

$statusReservaValidos = ['', 'pendente', 'aprovada', 'concluida', 'cancelada'];
if (!in_array($filtroReservaStatus, $statusReservaValidos, true)) {
    $filtroReservaStatus = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => 'Nao foi possivel validar sua sessao. Tente novamente.',
        ];
        redirecionarPainelAdminComQueryAtual();
    }

    $adminAction = trim((string)($_POST['admin_action'] ?? ''));

    try {
        if ($adminAction === 'doc_review') {
            $action = trim((string)($_POST['action'] ?? ''));
            $userId = (int)($_POST['user_id'] ?? 0);
            $observacoes = trim((string)($_POST['observacoes'] ?? ''));

            if ($userId <= 0 || !in_array($action, ['aprovar', 'rejeitar'], true)) {
                throw new RuntimeException('Acao de documento invalida.');
            }

            if ($action === 'rejeitar' && $observacoes === '') {
                throw new RuntimeException('Informe o motivo para rejeitar os documentos.');
            }

            $novoStatus = ($action === 'aprovar') ? 'aprovado' : 'rejeitado';

            $stmt = $pdo->prepare(
                'UPDATE conta_usuario
                 SET status_docs = ?,
                     observacoes_docs = ?,
                     data_verificacao = NOW(),
                     admin_verificacao = ?
                 WHERE id = ?'
            );
            $stmt->execute([$novoStatus, $observacoes, (int)$usuario['id'], $userId]);

            $_SESSION['notification'] = [
                'type' => 'success',
                'message' => ($novoStatus === 'aprovado')
                    ? 'Documentos aprovados com sucesso.'
                    : 'Documentos rejeitados com sucesso.',
            ];
        } elseif ($adminAction === 'vehicle_manage') {
            $vehicleAction = trim((string)($_POST['vehicle_action'] ?? ''));
            $veiculoId = (int)($_POST['veiculo_id'] ?? 0);

            if ($veiculoId <= 0 || !in_array($vehicleAction, ['approve', 'deactivate'], true)) {
                throw new RuntimeException('Acao de veiculo invalida.');
            }

            $disponivel = ($vehicleAction === 'approve') ? 1 : 0;

            $stmt = $pdo->prepare('UPDATE veiculo SET disponivel = ? WHERE id = ?');
            $stmt->execute([$disponivel, $veiculoId]);

            $_SESSION['notification'] = [
                'type' => 'success',
                'message' => ($disponivel === 1)
                    ? 'Veiculo aprovado/ativado com sucesso.'
                    : 'Veiculo desativado com sucesso.',
            ];
        } elseif ($adminAction === 'reservation_manage') {
            $reservaId = (int)($_POST['reserva_id'] ?? 0);
            $novoStatus = strtolower(trim((string)($_POST['novo_status'] ?? '')));
            $statusPermitidos = ['pendente', 'pago', 'confirmada', 'finalizada', 'cancelada', 'rejeitada'];

            if ($reservaId <= 0 || !in_array($novoStatus, $statusPermitidos, true)) {
                throw new RuntimeException('Atualizacao de reserva invalida.');
            }

            $stmt = $pdo->prepare('UPDATE reserva SET status = ? WHERE id = ?');
            $stmt->execute([$novoStatus, $reservaId]);

            $_SESSION['notification'] = [
                'type' => 'success',
                'message' => 'Status da reserva atualizado com sucesso.',
            ];
        }
    } catch (RuntimeException $e) {
        error_log('Erro de validacao no painel admin: ' . $e->getMessage());
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => 'Nao foi possivel validar essa acao. Confira os dados e tente novamente.',
        ];
    } catch (Throwable $e) {
        error_log('Erro ao processar acao do painel admin: ' . $e->getMessage());
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => 'Nao foi possivel concluir a acao agora. Tente novamente mais tarde.',
        ];
    }

    redirecionarPainelAdminComQueryAtual();
}

$metricas = [
    'total_usuarios' => 0,
    'total_proprietarios' => 0,
    'total_veiculos' => 0,
    'total_reservas' => 0,
    'reservas_ativas' => 0,
    'reservas_concluidas' => 0,
    'veiculos_pendentes' => 0,
    'docs_pendentes' => 0,
];

$alertas = [
    'novos_usuarios_hoje' => 0,
    'reservas_atencao' => 0,
    'veiculos_pendentes' => 0,
    'docs_pendentes' => 0,
];

$resultadosBusca = [
    'usuarios' => [],
    'veiculos' => [],
    'reservas' => [],
];

$veiculosAdmin = [];
$reservasAdmin = [];
$documentos = [];
$topVeiculos = [];
$topUsuarios = [];
$topCidades = [];
$tendenciaReservas = [];
$erroCarga = '';

$itensPorPaginaDocs = 8;
$totalDocs = 0;
$totalPaginasDocs = 1;

try {
    $stmtMetricas = $pdo->query(
        "SELECT
            (SELECT COUNT(*) FROM conta_usuario WHERE is_admin = 0) AS total_usuarios,
            (SELECT COUNT(*) FROM dono) AS total_proprietarios,
            (SELECT COUNT(*) FROM veiculo) AS total_veiculos,
            (SELECT COUNT(*) FROM reserva) AS total_reservas,
            (SELECT COUNT(*) FROM reserva WHERE COALESCE(status, 'pendente') IN ('pendente', 'pago', 'confirmada') AND (devolucao_data IS NULL OR devolucao_data >= CURDATE())) AS reservas_ativas,
            (SELECT COUNT(*) FROM reserva WHERE status = 'finalizada' OR (status = 'confirmada' AND devolucao_data < CURDATE())) AS reservas_concluidas,
            (SELECT COUNT(*) FROM veiculo WHERE disponivel = 0) AS veiculos_pendentes,
            (SELECT COUNT(*) FROM conta_usuario WHERE status_docs = 'pendente') AS docs_pendentes"
    );
    $metricasBanco = $stmtMetricas->fetch(PDO::FETCH_ASSOC) ?: [];
    foreach ($metricas as $chave => $valor) {
        $metricas[$chave] = (int)($metricasBanco[$chave] ?? 0);
    }

    $stmtNovosUsuarios = $pdo->query("SELECT COUNT(*) FROM conta_usuario WHERE is_admin = 0 AND data_de_entrada = CURDATE()");
    $alertas['novos_usuarios_hoje'] = (int)$stmtNovosUsuarios->fetchColumn();

    $stmtReservasAtencao = $pdo->query(
        "SELECT COUNT(*)
         FROM reserva
         WHERE COALESCE(status, 'pendente') IN ('pendente', 'pago')
           AND reserva_data <= CURDATE()"
    );
    $alertas['reservas_atencao'] = (int)$stmtReservasAtencao->fetchColumn();
    $alertas['veiculos_pendentes'] = $metricas['veiculos_pendentes'];
    $alertas['docs_pendentes'] = $metricas['docs_pendentes'];

    if ($filtroBusca !== '') {
        $termoBusca = '%' . $filtroBusca . '%';
        $idBusca = ctype_digit($filtroBusca) ? (int)$filtroBusca : null;

        $stmtBuscaUsuarios = $pdo->prepare(
            "SELECT cu.id,
                    cu.primeiro_nome,
                    cu.segundo_nome,
                    cu.e_mail,
                    cu.is_admin
             FROM conta_usuario cu
             WHERE cu.primeiro_nome LIKE ? OR cu.segundo_nome LIKE ? OR cu.e_mail LIKE ?
             ORDER BY cu.id DESC
             LIMIT 5"
        );
        $stmtBuscaUsuarios->execute([$termoBusca, $termoBusca, $termoBusca]);
        $resultadosBusca['usuarios'] = $stmtBuscaUsuarios->fetchAll(PDO::FETCH_ASSOC);

        $stmtBuscaVeiculos = $pdo->prepare(
            "SELECT v.id,
                    v.veiculo_marca,
                    v.veiculo_modelo,
                    v.veiculo_placa,
                    v.disponivel,
                    CONCAT(COALESCE(cu.primeiro_nome, ''), ' ', COALESCE(cu.segundo_nome, '')) AS proprietario_nome
             FROM veiculo v
             LEFT JOIN dono d ON d.id = v.dono_id
             LEFT JOIN conta_usuario cu ON cu.id = d.conta_usuario_id
             WHERE v.veiculo_marca LIKE ? OR v.veiculo_modelo LIKE ? OR v.veiculo_placa LIKE ?
             ORDER BY v.id DESC
             LIMIT 5"
        );
        $stmtBuscaVeiculos->execute([$termoBusca, $termoBusca, $termoBusca]);
        $resultadosBusca['veiculos'] = $stmtBuscaVeiculos->fetchAll(PDO::FETCH_ASSOC);

        $sqlBuscaReserva =
            "SELECT r.id,
                    r.status,
                    r.reserva_data,
                    r.devolucao_data,
                    CONCAT(COALESCE(u.primeiro_nome, ''), ' ', COALESCE(u.segundo_nome, '')) AS usuario_nome,
                    CONCAT(COALESCE(v.veiculo_marca, ''), ' ', COALESCE(v.veiculo_modelo, '')) AS veiculo_nome
             FROM reserva r
             LEFT JOIN conta_usuario u ON u.id = r.conta_usuario_id
             LEFT JOIN veiculo v ON v.id = r.veiculo_id
             WHERE (CONCAT(COALESCE(u.primeiro_nome, ''), ' ', COALESCE(u.segundo_nome, '')) LIKE ?
                    OR CONCAT(COALESCE(v.veiculo_marca, ''), ' ', COALESCE(v.veiculo_modelo, '')) LIKE ?";

        $paramsBuscaReserva = [$termoBusca, $termoBusca];
        if ($idBusca !== null) {
            $sqlBuscaReserva .= ' OR r.id = ?';
            $paramsBuscaReserva[] = $idBusca;
        }

        $sqlBuscaReserva .= ') ORDER BY r.id DESC LIMIT 5';

        $stmtBuscaReservas = $pdo->prepare($sqlBuscaReserva);
        $stmtBuscaReservas->execute($paramsBuscaReserva);
        $resultadosBusca['reservas'] = $stmtBuscaReservas->fetchAll(PDO::FETCH_ASSOC);
    }

    $whereVeiculos = [];
    $paramsVeiculos = [];

    if ($filtroVeiculoStatus === 'ativos') {
        $whereVeiculos[] = 'v.disponivel = 1';
    } elseif ($filtroVeiculoStatus === 'inativos') {
        $whereVeiculos[] = 'v.disponivel = 0';
    }

    if ($filtroBusca !== '') {
        $termoBusca = '%' . $filtroBusca . '%';
        $whereVeiculos[] = "(v.veiculo_marca LIKE ?
                            OR v.veiculo_modelo LIKE ?
                            OR v.veiculo_placa LIKE ?
                            OR CONCAT(COALESCE(cu.primeiro_nome, ''), ' ', COALESCE(cu.segundo_nome, '')) LIKE ?)";
        $paramsVeiculos[] = $termoBusca;
        $paramsVeiculos[] = $termoBusca;
        $paramsVeiculos[] = $termoBusca;
        $paramsVeiculos[] = $termoBusca;
    }

    $sqlVeiculos =
        "SELECT v.id,
                v.veiculo_marca,
                v.veiculo_modelo,
                v.veiculo_ano,
                v.veiculo_placa,
                v.disponivel,
                c.cidade_nome,
                CONCAT(COALESCE(cu.primeiro_nome, ''), ' ', COALESCE(cu.segundo_nome, '')) AS proprietario_nome,
                (
                    SELECT i.imagem_url
                    FROM imagem i
                    WHERE i.veiculo_id = v.id
                    ORDER BY i.imagem_ordem IS NULL, i.imagem_ordem, i.id
                    LIMIT 1
                ) AS imagem_url
         FROM veiculo v
         LEFT JOIN dono d ON d.id = v.dono_id
         LEFT JOIN conta_usuario cu ON cu.id = d.conta_usuario_id
         LEFT JOIN local l ON l.id = v.local_id
         LEFT JOIN cidade c ON c.id = l.cidade_id";

    if ($whereVeiculos) {
        $sqlVeiculos .= ' WHERE ' . implode(' AND ', $whereVeiculos);
    }

    $sqlVeiculos .= ' ORDER BY v.id DESC LIMIT 8';

    $stmtVeiculos = $pdo->prepare($sqlVeiculos);
    $stmtVeiculos->execute($paramsVeiculos);
    $veiculosAdmin = $stmtVeiculos->fetchAll(PDO::FETCH_ASSOC);

    $whereReservas = [];
    $paramsReservas = [];

    if ($filtroReservaStatus === 'pendente') {
        $whereReservas[] = "COALESCE(r.status, 'pendente') IN ('pendente', 'pago')";
    } elseif ($filtroReservaStatus === 'aprovada') {
        $whereReservas[] = "r.status = 'confirmada' AND (r.devolucao_data IS NULL OR r.devolucao_data >= CURDATE())";
    } elseif ($filtroReservaStatus === 'concluida') {
        $whereReservas[] = "(r.status = 'finalizada' OR (r.status = 'confirmada' AND r.devolucao_data < CURDATE()))";
    } elseif ($filtroReservaStatus === 'cancelada') {
        $whereReservas[] = "r.status IN ('cancelada', 'rejeitada')";
    }

    if ($filtroBusca !== '') {
        $termoBusca = '%' . $filtroBusca . '%';
        $idBusca = ctype_digit($filtroBusca) ? (int)$filtroBusca : null;

        $clausulaBuscaReserva =
            "(CONCAT(COALESCE(u.primeiro_nome, ''), ' ', COALESCE(u.segundo_nome, '')) LIKE ?
              OR CONCAT(COALESCE(v.veiculo_marca, ''), ' ', COALESCE(v.veiculo_modelo, '')) LIKE ?";
        $paramsReservas[] = $termoBusca;
        $paramsReservas[] = $termoBusca;

        if ($idBusca !== null) {
            $clausulaBuscaReserva .= ' OR r.id = ?';
            $paramsReservas[] = $idBusca;
        }

        $clausulaBuscaReserva .= ')';
        $whereReservas[] = $clausulaBuscaReserva;
    }

    $sqlReservas =
        "SELECT r.id,
                r.status,
                r.reserva_data,
                r.devolucao_data,
                r.valor_total,
                CONCAT(COALESCE(u.primeiro_nome, ''), ' ', COALESCE(u.segundo_nome, '')) AS locatario_nome,
                u.e_mail AS locatario_email,
                CONCAT(COALESCE(v.veiculo_marca, ''), ' ', COALESCE(v.veiculo_modelo, '')) AS veiculo_nome,
                CONCAT(COALESCE(prop.primeiro_nome, ''), ' ', COALESCE(prop.segundo_nome, '')) AS proprietario_nome
         FROM reserva r
         LEFT JOIN conta_usuario u ON u.id = r.conta_usuario_id
         LEFT JOIN veiculo v ON v.id = r.veiculo_id
         LEFT JOIN dono d ON d.id = v.dono_id
         LEFT JOIN conta_usuario prop ON prop.id = d.conta_usuario_id";

    if ($whereReservas) {
        $sqlReservas .= ' WHERE ' . implode(' AND ', $whereReservas);
    }

    $sqlReservas .= ' ORDER BY r.id DESC LIMIT 12';

    $stmtReservas = $pdo->prepare($sqlReservas);
    $stmtReservas->execute($paramsReservas);
    $reservasAdmin = $stmtReservas->fetchAll(PDO::FETCH_ASSOC);

    $whereDocs = ["(u.foto_cnh_frente IS NOT NULL OR u.foto_cnh_verso IS NOT NULL)"];
    $paramsDocs = [];

    if ($filtroDocsStatus !== '') {
        $whereDocs[] = 'u.status_docs = ?';
        $paramsDocs[] = $filtroDocsStatus;
    }

    if ($filtroBusca !== '') {
        $termoBusca = '%' . $filtroBusca . '%';
        $whereDocs[] = '(u.primeiro_nome LIKE ? OR u.segundo_nome LIKE ? OR u.e_mail LIKE ? OR u.cpf LIKE ?)';
        $paramsDocs[] = $termoBusca;
        $paramsDocs[] = $termoBusca;
        $paramsDocs[] = $termoBusca;
        $paramsDocs[] = $termoBusca;
    }

    $sqlCountDocs = 'SELECT COUNT(*) FROM conta_usuario u WHERE ' . implode(' AND ', $whereDocs);
    $stmtCountDocs = $pdo->prepare($sqlCountDocs);
    $stmtCountDocs->execute($paramsDocs);
    $totalDocs = (int)$stmtCountDocs->fetchColumn();

    $totalPaginasDocs = max(1, (int)ceil($totalDocs / $itensPorPaginaDocs));
    if ($paginaDocs > $totalPaginasDocs) {
        $paginaDocs = $totalPaginasDocs;
    }

    $offsetDocs = max(0, ($paginaDocs - 1) * $itensPorPaginaDocs);

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
                ) AS admin_nome,
                (
                    SELECT COUNT(*)
                    FROM reserva r
                    WHERE r.conta_usuario_id = u.id
                ) AS total_reservas,
                (
                    SELECT COUNT(*)
                    FROM veiculo v2
                    INNER JOIN dono d2 ON d2.id = v2.dono_id
                    WHERE d2.conta_usuario_id = u.id
                ) AS total_veiculos
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
         LIMIT {$offsetDocs}, {$itensPorPaginaDocs}";

    $stmtDocs = $pdo->prepare($sqlDocs);
    $stmtDocs->execute($paramsDocs);
    $documentos = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

    $stmtTopVeiculos = $pdo->query(
        "SELECT v.id,
                CONCAT(COALESCE(v.veiculo_marca, ''), ' ', COALESCE(v.veiculo_modelo, '')) AS nome,
                COUNT(r.id) AS total
         FROM veiculo v
         LEFT JOIN reserva r ON r.veiculo_id = v.id
         GROUP BY v.id
         ORDER BY total DESC, v.id DESC
         LIMIT 5"
    );
    $topVeiculos = $stmtTopVeiculos->fetchAll(PDO::FETCH_ASSOC);

    $stmtTopUsuarios = $pdo->query(
        "SELECT cu.id,
                CONCAT(COALESCE(cu.primeiro_nome, ''), ' ', COALESCE(cu.segundo_nome, '')) AS nome,
                COUNT(r.id) AS total
         FROM conta_usuario cu
         LEFT JOIN reserva r ON r.conta_usuario_id = cu.id
         WHERE cu.is_admin = 0
         GROUP BY cu.id
         ORDER BY total DESC, cu.id DESC
         LIMIT 5"
    );
    $topUsuarios = $stmtTopUsuarios->fetchAll(PDO::FETCH_ASSOC);

    $stmtTopCidades = $pdo->query(
        "SELECT c.cidade_nome,
                COUNT(r.id) AS total
         FROM cidade c
         LEFT JOIN local l ON l.cidade_id = c.id
         LEFT JOIN veiculo v ON v.local_id = l.id
         LEFT JOIN reserva r ON r.veiculo_id = v.id
         GROUP BY c.id
         HAVING total > 0
         ORDER BY total DESC
         LIMIT 5"
    );
    $topCidades = $stmtTopCidades->fetchAll(PDO::FETCH_ASSOC);

    $stmtTendencia = $pdo->query(
        "SELECT reserva_data AS dia, COUNT(*) AS total
         FROM reserva
         WHERE reserva_data >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY reserva_data
         ORDER BY reserva_data ASC"
    );
    $linhasTendencia = $stmtTendencia->fetchAll(PDO::FETCH_ASSOC);

    $mapaTendencia = [];
    foreach ($linhasTendencia as $linha) {
        $mapaTendencia[$linha['dia']] = (int)$linha['total'];
    }

    for ($i = 6; $i >= 0; $i--) {
        $diaIso = date('Y-m-d', strtotime('-' . $i . ' day'));
        $tendenciaReservas[] = [
            'dia' => date('d/m', strtotime($diaIso)),
            'total' => $mapaTendencia[$diaIso] ?? 0,
        ];
    }
} catch (PDOException $e) {
    $erroCarga = 'Nao foi possivel carregar todos os dados do painel admin.';
}

$notification = $_SESSION['notification'] ?? null;
if ($notification) {
    unset($_SESSION['notification']);
}

$documentosJson = json_encode($documentos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($documentosJson === false) {
    $documentosJson = '[]';
}

$veiculosJson = json_encode($veiculosAdmin, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($veiculosJson === false) {
    $veiculosJson = '[]';
}

$reservasJson = json_encode($reservasAdmin, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($reservasJson === false) {
    $reservasJson = '[]';
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
    <title>Painel Admin - DriveNow</title>
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
            max-height: 300px;
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
                <h2 class="text-2xl md:text-3xl font-bold text-white">Painel Administrativo</h2>
                <p class="text-white/70 mt-1">Monitoramento central de usuarios, veiculos, reservas e documentos.</p>
            </div>
            <a href="usuarios.php" class="btn-dn-primary bg-indigo-500 hover:bg-indigo-600 text-white rounded-xl transition-colors border border-indigo-400/30 px-4 py-2 text-sm font-medium shadow-md hover:shadow-lg flex items-center w-full md:w-auto justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                Usuarios
            </a>
        </div>

        <?php if ($erroCarga !== ''): ?>
            <div class="mb-6 rounded-2xl border border-red-400/30 bg-red-500/20 px-4 py-3 text-red-100">
                <?= htmlspecialchars($erroCarga) ?>
            </div>
        <?php endif; ?>

        <section class="section-shell backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl p-6 mb-8 shadow-lg">
            <form method="GET" action="dadmin.php" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
                <div class="xl:col-span-2">
                    <label for="q" class="block text-white font-medium mb-2">Busca administrativa</label>
                    <input
                        type="text"
                        id="q"
                        name="q"
                        value="<?= htmlspecialchars($filtroBusca) ?>"
                        placeholder="Usuarios (nome/email), veiculos (modelo), reservas (ID)"
                        class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50"
                    >
                </div>

                <div>
                    <label for="veiculo_status" class="block text-white font-medium mb-2">Veiculos</label>
                    <select id="veiculo_status" name="veiculo_status" class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50">
                        <option value="" <?= $filtroVeiculoStatus === '' ? 'selected' : '' ?>>Todos</option>
                        <option value="ativos" <?= $filtroVeiculoStatus === 'ativos' ? 'selected' : '' ?>>Ativos</option>
                        <option value="inativos" <?= $filtroVeiculoStatus === 'inativos' ? 'selected' : '' ?>>Inativos/Pendentes</option>
                    </select>
                </div>

                <div>
                    <label for="reserva_status" class="block text-white font-medium mb-2">Reservas</label>
                    <select id="reserva_status" name="reserva_status" class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50">
                        <option value="" <?= $filtroReservaStatus === '' ? 'selected' : '' ?>>Todas</option>
                        <option value="pendente" <?= $filtroReservaStatus === 'pendente' ? 'selected' : '' ?>>Pendentes</option>
                        <option value="aprovada" <?= $filtroReservaStatus === 'aprovada' ? 'selected' : '' ?>>Aprovadas</option>
                        <option value="concluida" <?= $filtroReservaStatus === 'concluida' ? 'selected' : '' ?>>Concluidas</option>
                        <option value="cancelada" <?= $filtroReservaStatus === 'cancelada' ? 'selected' : '' ?>>Canceladas/Rejeitadas</option>
                    </select>
                </div>

                <div>
                    <label for="docs_status" class="block text-white font-medium mb-2">Documentos</label>
                    <select id="docs_status" name="docs_status" class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50">
                        <option value="" <?= $filtroDocsStatus === '' ? 'selected' : '' ?>>Todos</option>
                        <option value="pendente" <?= $filtroDocsStatus === 'pendente' ? 'selected' : '' ?>>Pendentes</option>
                        <option value="verificando" <?= $filtroDocsStatus === 'verificando' ? 'selected' : '' ?>>Verificando</option>
                        <option value="aprovado" <?= $filtroDocsStatus === 'aprovado' ? 'selected' : '' ?>>Aprovados</option>
                        <option value="rejeitado" <?= $filtroDocsStatus === 'rejeitado' ? 'selected' : '' ?>>Rejeitados</option>
                    </select>
                </div>

                <div class="md:col-span-2 xl:col-span-5 flex flex-wrap gap-3 justify-end">
                    <a href="dadmin.php" class="btn-dn-ghost px-4 py-3 bg-white/10 hover:bg-white/20 border border-white/10 rounded-xl text-white font-medium transition-colors">Limpar</a>
                    <button type="submit" class="btn-dn-primary px-4 py-3 bg-indigo-500 hover:bg-indigo-600 text-white font-medium rounded-xl transition-colors border border-indigo-400/30 shadow-md hover:shadow-lg">Aplicar filtros</button>
                </div>
            </form>
        </section>

        <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
            <div class="metric-card backdrop-blur-lg bg-white/5 border subtle-border rounded-2xl p-5 shadow-lg">
                <p class="text-white/60 text-sm">Total de usuarios</p>
                <p class="text-3xl font-bold mt-2"><?= (int)$metricas['total_usuarios'] ?></p>
            </div>
            <div class="metric-card backdrop-blur-lg bg-white/5 border subtle-border rounded-2xl p-5 shadow-lg">
                <p class="text-white/60 text-sm">Total de proprietarios</p>
                <p class="text-3xl font-bold mt-2"><?= (int)$metricas['total_proprietarios'] ?></p>
            </div>
            <div class="metric-card backdrop-blur-lg bg-white/5 border subtle-border rounded-2xl p-5 shadow-lg">
                <p class="text-white/60 text-sm">Total de veiculos</p>
                <p class="text-3xl font-bold mt-2"><?= (int)$metricas['total_veiculos'] ?></p>
            </div>
            <div class="metric-card backdrop-blur-lg bg-white/5 border subtle-border rounded-2xl p-5 shadow-lg">
                <p class="text-white/60 text-sm">Total de reservas</p>
                <p class="text-3xl font-bold mt-2"><?= (int)$metricas['total_reservas'] ?></p>
            </div>
            <div class="metric-card backdrop-blur-lg bg-white/5 border subtle-border rounded-2xl p-5 shadow-lg">
                <p class="text-white/60 text-sm">Reservas ativas</p>
                <p class="text-3xl font-bold mt-2"><?= (int)$metricas['reservas_ativas'] ?></p>
            </div>
            <div class="metric-card backdrop-blur-lg bg-white/5 border subtle-border rounded-2xl p-5 shadow-lg">
                <p class="text-white/60 text-sm">Reservas concluidas</p>
                <p class="text-3xl font-bold mt-2"><?= (int)$metricas['reservas_concluidas'] ?></p>
            </div>
            <div class="metric-card backdrop-blur-lg bg-white/5 border subtle-border rounded-2xl p-5 shadow-lg">
                <p class="text-white/60 text-sm">Veiculos pendentes/inativos</p>
                <p class="text-3xl font-bold mt-2"><?= (int)$metricas['veiculos_pendentes'] ?></p>
            </div>
            <div class="metric-card backdrop-blur-lg bg-white/5 border subtle-border rounded-2xl p-5 shadow-lg">
                <p class="text-white/60 text-sm">Docs pendentes</p>
                <p class="text-3xl font-bold mt-2"><?= (int)$metricas['docs_pendentes'] ?></p>
            </div>
        </section>

        <section class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <div class="metric-highlight backdrop-blur-lg bg-amber-500/10 border border-amber-400/30 rounded-2xl p-4">
                <p class="text-amber-200 text-sm">Novos usuarios hoje</p>
                <p class="text-2xl font-bold text-white mt-1"><?= (int)$alertas['novos_usuarios_hoje'] ?></p>
            </div>
            <div class="metric-highlight backdrop-blur-lg bg-red-500/10 border border-red-400/30 rounded-2xl p-4">
                <p class="text-red-200 text-sm">Reservas exigindo atencao</p>
                <p class="text-2xl font-bold text-white mt-1"><?= (int)$alertas['reservas_atencao'] ?></p>
            </div>
            <div class="metric-highlight backdrop-blur-lg bg-indigo-500/10 border border-indigo-400/30 rounded-2xl p-4">
                <p class="text-indigo-200 text-sm">Veiculos pendentes/inativos</p>
                <p class="text-2xl font-bold text-white mt-1"><?= (int)$alertas['veiculos_pendentes'] ?></p>
            </div>
        </section>

        <section class="section-shell backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl p-6 mb-8 shadow-lg">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <h3 class="text-xl font-semibold">Verificacao de CNH</h3>
                    <p class="text-white/70 text-sm mt-1">Este painel continua com aprovacao/rejeicao de documentos no bloco abaixo.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="dadmin.php?docs_status=pendente#sec-documentos" class="px-3 py-2 rounded-lg bg-amber-500/20 hover:bg-amber-500/30 border border-amber-400/30 text-amber-200 text-sm">Pendentes (<?= (int)$metricas['docs_pendentes'] ?>)</a>
                    <a href="dadmin.php?docs_status=rejeitado#sec-documentos" class="px-3 py-2 rounded-lg bg-red-500/20 hover:bg-red-500/30 border border-red-400/30 text-red-200 text-sm">Rejeitados</a>
                    <a href="dadmin.php?docs_status=aprovado#sec-documentos" class="px-3 py-2 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-400/30 text-emerald-200 text-sm">Aprovados</a>
                    <a href="#sec-documentos" class="px-3 py-2 rounded-lg bg-indigo-500/20 hover:bg-indigo-500/30 border border-indigo-400/30 text-indigo-200 text-sm">Abrir verificacao</a>
                </div>
            </div>
        </section>

        <?php if ($filtroBusca !== ''): ?>
            <section class="section-shell backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl p-6 mb-8 shadow-lg">
                <h3 class="text-xl font-semibold mb-4">Resultado rapido da busca</h3>
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                    <div class="soft-surface bg-white/5 border border-white/10 rounded-2xl p-4">
                        <p class="text-white/70 text-sm mb-3">Usuarios</p>
                        <?php if (empty($resultadosBusca['usuarios'])): ?>
                            <p class="text-white/50 text-sm">Nenhum usuario encontrado.</p>
                        <?php else: ?>
                            <ul class="space-y-2">
                                <?php foreach ($resultadosBusca['usuarios'] as $item): ?>
                                    <li class="text-sm">
                                        <span class="font-medium"><?= htmlspecialchars(trim(($item['primeiro_nome'] ?? '') . ' ' . ($item['segundo_nome'] ?? ''))) ?></span>
                                        <span class="text-white/60">- <?= htmlspecialchars($item['e_mail'] ?? '-') ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <div class="soft-surface bg-white/5 border border-white/10 rounded-2xl p-4">
                        <p class="text-white/70 text-sm mb-3">Veiculos</p>
                        <?php if (empty($resultadosBusca['veiculos'])): ?>
                            <p class="text-white/50 text-sm">Nenhum veiculo encontrado.</p>
                        <?php else: ?>
                            <ul class="space-y-2">
                                <?php foreach ($resultadosBusca['veiculos'] as $item): ?>
                                    <li class="text-sm">
                                        <span class="font-medium"><?= htmlspecialchars(trim(($item['veiculo_marca'] ?? '') . ' ' . ($item['veiculo_modelo'] ?? ''))) ?></span>
                                        <span class="text-white/60">- placa <?= htmlspecialchars($item['veiculo_placa'] ?? '-') ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <div class="soft-surface bg-white/5 border border-white/10 rounded-2xl p-4">
                        <p class="text-white/70 text-sm mb-3">Reservas</p>
                        <?php if (empty($resultadosBusca['reservas'])): ?>
                            <p class="text-white/50 text-sm">Nenhuma reserva encontrada.</p>
                        <?php else: ?>
                            <ul class="space-y-2">
                                <?php foreach ($resultadosBusca['reservas'] as $item): ?>
                                    <li class="text-sm">
                                        <span class="font-medium">#<?= (int)$item['id'] ?></span>
                                        <span class="text-white/60">- <?= htmlspecialchars($item['usuario_nome'] ?? '-') ?> / <?= htmlspecialchars($item['veiculo_nome'] ?? '-') ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <section id="sec-veiculos" class="section-shell backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl p-6 mb-8 shadow-lg">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-semibold">Gestao de veiculos</h3>
                <span class="text-white/60 text-sm">Aprovar/ativar ou desativar veiculos</span>
            </div>

            <?php if (empty($veiculosAdmin)): ?>
                <p class="text-white/60">Nenhum veiculo encontrado para os filtros atuais.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="dn-table w-full min-w-[850px]">
                        <thead>
                            <tr class="border-b border-white/10">
                                <th class="px-4 py-3 text-left text-xs uppercase text-white/70">Imagem</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-white/70">Modelo</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-white/70">Proprietario</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-white/70">Cidade</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-white/70">Status</th>
                                <th class="px-4 py-3 text-right text-xs uppercase text-white/70">Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($veiculosAdmin as $veiculo): ?>
                                <?php
                                    $statusAtivo = ((int)$veiculo['disponivel'] === 1);
                                    $statusClasse = $statusAtivo
                                        ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-400/30'
                                        : 'bg-amber-500/20 text-amber-300 border border-amber-400/30';
                                    $statusLabel = $statusAtivo ? 'Ativo' : 'Pendente/Inativo';
                                    $imagemVeiculo = !empty($veiculo['imagem_url'])
                                        ? '../' . ltrim((string)$veiculo['imagem_url'], '/')
                                        : 'https://via.placeholder.com/120x80?text=Sem+imagem';
                                ?>
                                <tr class="border-b border-white/5 hover:bg-white/5">
                                    <td class="px-4 py-3">
                                        <img src="<?= htmlspecialchars($imagemVeiculo) ?>" alt="Veiculo" class="w-16 h-12 object-cover rounded-lg border border-white/10">
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <p class="font-medium"><?= htmlspecialchars(trim(($veiculo['veiculo_marca'] ?? '') . ' ' . ($veiculo['veiculo_modelo'] ?? ''))) ?></p>
                                        <p class="text-white/60">Ano <?= (int)($veiculo['veiculo_ano'] ?? 0) ?> - Placa <?= htmlspecialchars($veiculo['veiculo_placa'] ?? '-') ?></p>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-white/80"><?= htmlspecialchars(trim((string)($veiculo['proprietario_nome'] ?? '-'))) ?: '-' ?></td>
                                    <td class="px-4 py-3 text-sm text-white/80"><?= htmlspecialchars($veiculo['cidade_nome'] ?? 'Nao informada') ?></td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 text-xs rounded-full <?= $statusClasse ?>"><?= $statusLabel ?></span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button type="button" onclick="abrirModalVeiculo(<?= (int)$veiculo['id'] ?>)" class="text-indigo-300 hover:text-indigo-200 text-sm mr-3">Detalhes</button>

                                        <form method="POST" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="admin_action" value="vehicle_manage">
                                            <input type="hidden" name="veiculo_id" value="<?= (int)$veiculo['id'] ?>">
                                            <?php if ($statusAtivo): ?>
                                                <input type="hidden" name="vehicle_action" value="deactivate">
                                                <button type="submit" class="px-3 py-1 text-xs rounded-lg bg-red-500/20 hover:bg-red-500/30 border border-red-400/30 text-red-200">Desativar</button>
                                            <?php else: ?>
                                                <input type="hidden" name="vehicle_action" value="approve">
                                                <button type="submit" class="px-3 py-1 text-xs rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-400/30 text-emerald-200">Aprovar/Ativar</button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section id="sec-reservas" class="section-shell backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl p-6 mb-8 shadow-lg">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-semibold">Monitoramento de reservas</h3>
                <span class="text-white/60 text-sm">Visualizacao completa com atualizacao manual de status</span>
            </div>

            <?php if (empty($reservasAdmin)): ?>
                <p class="text-white/60">Nenhuma reserva encontrada para os filtros atuais.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="dn-table w-full min-w-[960px]">
                        <thead>
                            <tr class="border-b border-white/10">
                                <th class="px-4 py-3 text-left text-xs uppercase text-white/70">ID</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-white/70">Locatario</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-white/70">Veiculo</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-white/70">Periodo</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-white/70">Status</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-white/70">Valor</th>
                                <th class="px-4 py-3 text-right text-xs uppercase text-white/70">Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservasAdmin as $reserva): ?>
                                <?php $metaReserva = metadadosStatusReserva($reserva['status'] ?? null, $reserva['devolucao_data'] ?? null); ?>
                                <tr class="border-b border-white/5 hover:bg-white/5">
                                    <td class="px-4 py-3 text-sm font-medium">#<?= (int)$reserva['id'] ?></td>
                                    <td class="px-4 py-3 text-sm">
                                        <p><?= htmlspecialchars(trim((string)($reserva['locatario_nome'] ?? '-'))) ?: '-' ?></p>
                                        <p class="text-white/60"><?= htmlspecialchars($reserva['locatario_email'] ?? '-') ?></p>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-white/80"><?= htmlspecialchars($reserva['veiculo_nome'] ?? '-') ?></td>
                                    <td class="px-4 py-3 text-sm text-white/80">
                                        <?= !empty($reserva['reserva_data']) ? date('d/m/Y', strtotime($reserva['reserva_data'])) : '-' ?>
                                        ate
                                        <?= !empty($reserva['devolucao_data']) ? date('d/m/Y', strtotime($reserva['devolucao_data'])) : '-' ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 text-xs rounded-full <?= $metaReserva['class'] ?>"><?= htmlspecialchars($metaReserva['label']) ?></span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-white/80">R$ <?= number_format((float)($reserva['valor_total'] ?? 0), 2, ',', '.') ?></td>
                                    <td class="px-4 py-3 text-right">
                                        <button type="button" onclick="abrirModalReserva(<?= (int)$reserva['id'] ?>)" class="text-indigo-300 hover:text-indigo-200 text-sm mr-2">Detalhes</button>

                                        <form method="POST" class="inline-flex items-center gap-2">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="admin_action" value="reservation_manage">
                                            <input type="hidden" name="reserva_id" value="<?= (int)$reserva['id'] ?>">
                                            <select name="novo_status" class="px-2 py-1 text-xs rounded-lg bg-white/10 border border-white/10 text-white">
                                                <option value="pendente">Pendente</option>
                                                <option value="pago">Pago</option>
                                                <option value="confirmada">Confirmada</option>
                                                <option value="finalizada">Finalizada</option>
                                                <option value="cancelada">Cancelada</option>
                                                <option value="rejeitada">Rejeitada</option>
                                            </select>
                                            <button type="submit" class="px-2 py-1 text-xs rounded-lg bg-indigo-500/20 hover:bg-indigo-500/30 border border-indigo-400/30 text-indigo-200">Salvar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section id="sec-insights" class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-8">
            <div class="metric-card backdrop-blur-lg bg-white/5 border subtle-border rounded-2xl p-5 shadow-lg">
                <h3 class="text-lg font-semibold mb-3">Veiculos mais alugados</h3>
                <?php if (empty($topVeiculos)): ?>
                    <p class="text-white/60 text-sm">Sem dados de locacao ainda.</p>
                <?php else: ?>
                    <ul class="space-y-2">
                        <?php foreach ($topVeiculos as $item): ?>
                            <li class="text-sm flex items-center justify-between">
                                <span><?= htmlspecialchars($item['nome'] ?: 'Veiculo sem nome') ?></span>
                                <span class="text-white/70"><?= (int)$item['total'] ?> reserva(s)</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="metric-card backdrop-blur-lg bg-white/5 border subtle-border rounded-2xl p-5 shadow-lg">
                <h3 class="text-lg font-semibold mb-3">Usuarios mais ativos</h3>
                <?php if (empty($topUsuarios)): ?>
                    <p class="text-white/60 text-sm">Sem usuarios com reservas ainda.</p>
                <?php else: ?>
                    <ul class="space-y-2">
                        <?php foreach ($topUsuarios as $item): ?>
                            <li class="text-sm flex items-center justify-between">
                                <span><?= htmlspecialchars($item['nome'] ?: 'Usuario sem nome') ?></span>
                                <span class="text-white/70"><?= (int)$item['total'] ?> reserva(s)</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="metric-card backdrop-blur-lg bg-white/5 border subtle-border rounded-2xl p-5 shadow-lg">
                <h3 class="text-lg font-semibold mb-3">Top cidades</h3>
                <?php if (empty($topCidades)): ?>
                    <p class="text-white/60 text-sm">Sem dados por cidade ainda.</p>
                <?php else: ?>
                    <ul class="space-y-2">
                        <?php foreach ($topCidades as $item): ?>
                            <li class="text-sm flex items-center justify-between">
                                <span><?= htmlspecialchars($item['cidade_nome'] ?: 'Cidade') ?></span>
                                <span class="text-white/70"><?= (int)$item['total'] ?> reserva(s)</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="metric-card backdrop-blur-lg bg-white/5 border subtle-border rounded-2xl p-5 shadow-lg">
                <h3 class="text-lg font-semibold mb-3">Tendencia de reservas (7 dias)</h3>
                <?php if (empty($tendenciaReservas)): ?>
                    <p class="text-white/60 text-sm">Sem dados de tendencia.</p>
                <?php else: ?>
                    <ul class="space-y-2">
                        <?php foreach ($tendenciaReservas as $item): ?>
                            <li class="text-sm flex items-center justify-between">
                                <span><?= htmlspecialchars($item['dia']) ?></span>
                                <span class="text-white/70"><?= (int)$item['total'] ?> reserva(s)</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section id="sec-documentos" class="section-shell backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl p-6 shadow-lg">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-semibold">Verificacao de documentos da CNH</h3>
                <span class="text-white/60 text-sm"><?= (int)$totalDocs ?> registro(s)</span>
            </div>

            <?php if (empty($documentos)): ?>
                <p class="text-white/60">Nenhum documento encontrado para os filtros atuais.</p>
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
                                <?php $metaDoc = metadadosStatusDocumento($doc['status_docs'] ?? null); ?>
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

                <?php if ($totalPaginasDocs > 1): ?>
                    <div class="mt-6 flex justify-center">
                        <nav class="flex items-center space-x-2">
                            <?php
                            $paramsBasePaginacao = $_GET;
                            if ($paginaDocs > 1):
                                $paramsPrev = $paramsBasePaginacao;
                                $paramsPrev['docs_page'] = $paginaDocs - 1;
                            ?>
                                <a href="dadmin.php?<?= htmlspecialchars(http_build_query($paramsPrev)) ?>" class="px-3 py-1 rounded-md bg-white/10 text-white/70 hover:bg-white/20 hover:text-white transition-colors">&laquo; Anterior</a>
                            <?php endif; ?>

                            <?php
                            $inicio = max(1, $paginaDocs - 2);
                            $fim = min($totalPaginasDocs, $paginaDocs + 2);
                            for ($i = $inicio; $i <= $fim; $i++):
                                $paramsNumero = $paramsBasePaginacao;
                                $paramsNumero['docs_page'] = $i;
                            ?>
                                <?php if ($i === $paginaDocs): ?>
                                    <span class="px-3 py-1 rounded-md bg-indigo-500 text-white"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="dadmin.php?<?= htmlspecialchars(http_build_query($paramsNumero)) ?>" class="px-3 py-1 rounded-md bg-white/10 text-white/70 hover:bg-white/20 hover:text-white transition-colors"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($paginaDocs < $totalPaginasDocs):
                                $paramsNext = $paramsBasePaginacao;
                                $paramsNext['docs_page'] = $paginaDocs + 1;
                            ?>
                                <a href="dadmin.php?<?= htmlspecialchars(http_build_query($paramsNext)) ?>" class="px-3 py-1 rounded-md bg-white/10 text-white/70 hover:bg-white/20 hover:text-white transition-colors">Proximo &raquo;</a>
                            <?php endif; ?>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>

    <div id="modalDocumentos" class="fixed inset-0 z-50 hidden modal-backdrop flex items-center justify-center p-4">
        <div class="relative bg-gradient-to-br from-slate-900 via-indigo-950 to-purple-950 border subtle-border rounded-3xl shadow-xl max-w-5xl w-full max-h-[92vh] overflow-hidden">
            <div class="p-6 flex items-center justify-between border-b border-white/10">
                <h3 class="text-xl font-bold text-white" id="docModalTitulo">Documentos</h3>
                <button type="button" onclick="fecharModalDocumentos()" class="text-white/70 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
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
                        <p>Reservas: <span id="docTotalReservas">0</span></p>
                        <p>Veiculos: <span id="docTotalVeiculos">0</span></p>
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

    <div id="modalVeiculo" class="fixed inset-0 z-50 hidden modal-backdrop flex items-center justify-center p-4">
        <div class="relative bg-gradient-to-br from-slate-900 via-indigo-950 to-purple-950 border subtle-border rounded-3xl shadow-xl max-w-2xl w-full overflow-hidden">
            <div class="p-6 flex items-center justify-between border-b border-white/10">
                <h3 class="text-xl font-bold text-white">Detalhes do veiculo</h3>
                <button type="button" onclick="fecharModalVeiculo()" class="text-white/70 hover:text-white">Fechar</button>
            </div>
            <div class="p-6 space-y-4">
                <img id="veiculoModalImagem" src="" alt="Veiculo" class="w-full h-56 object-cover rounded-xl border border-white/10">
                <p class="text-lg font-semibold" id="veiculoModalNome">-</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm text-white/80">
                    <p>Placa: <span id="veiculoModalPlaca">-</span></p>
                    <p>Ano: <span id="veiculoModalAno">-</span></p>
                    <p>Proprietario: <span id="veiculoModalProprietario">-</span></p>
                    <p>Cidade: <span id="veiculoModalCidade">-</span></p>
                    <p>Status: <span id="veiculoModalStatus">-</span></p>
                </div>
            </div>
        </div>
    </div>

    <div id="modalReserva" class="fixed inset-0 z-50 hidden modal-backdrop flex items-center justify-center p-4">
        <div class="relative bg-gradient-to-br from-slate-900 via-indigo-950 to-purple-950 border subtle-border rounded-3xl shadow-xl max-w-2xl w-full overflow-hidden">
            <div class="p-6 flex items-center justify-between border-b border-white/10">
                <h3 class="text-xl font-bold text-white">Detalhes da reserva</h3>
                <button type="button" onclick="fecharModalReserva()" class="text-white/70 hover:text-white">Fechar</button>
            </div>
            <div class="p-6 space-y-4 text-sm text-white/85">
                <p><strong>ID:</strong> <span id="reservaModalId">-</span></p>
                <p><strong>Status:</strong> <span id="reservaModalStatus">-</span></p>
                <p><strong>Locatario:</strong> <span id="reservaModalLocatario">-</span></p>
                <p><strong>Proprietario:</strong> <span id="reservaModalProprietario">-</span></p>
                <p><strong>Veiculo:</strong> <span id="reservaModalVeiculo">-</span></p>
                <p><strong>Periodo:</strong> <span id="reservaModalPeriodo">-</span></p>
                <p><strong>Valor total:</strong> <span id="reservaModalValor">-</span></p>
            </div>
        </div>
    </div>

    <footer class="container mx-auto mt-12 px-4 pb-4 text-center text-white/60 text-sm">
        <p>&copy; <script>document.write(new Date().getFullYear())</script> DriveNow Admin. Todos os direitos reservados.</p>
    </footer>

    <script src="../assets/notifications.js"></script>
    <script>
        const docsData = <?= $documentosJson ?>;
        const veiculosData = <?= $veiculosJson ?>;
        const reservasData = <?= $reservasJson ?>;

        function carregarImagemDocumento(userId, tipo, caminho, imgEl, semEl) {
            if (!caminho) {
                imgEl.classList.add('hidden');
                semEl.classList.remove('hidden');
                imgEl.src = '';
                return;
            }

            imgEl.src = '../perfil/download_documento.php?id=' + encodeURIComponent(userId) + '&tipo=' + encodeURIComponent(tipo) + '&inline=1';
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
            document.getElementById('docTotalReservas').textContent = doc.total_reservas || '0';
            document.getElementById('docTotalVeiculos').textContent = doc.total_veiculos || '0';
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

        function abrirModalVeiculo(veiculoId) {
            const veiculo = veiculosData.find((item) => parseInt(item.id, 10) === parseInt(veiculoId, 10));
            if (!veiculo) {
                notifyError('Veiculo nao encontrado.');
                return;
            }

            const imagem = veiculo.imagem_url ? ('../' + (String(veiculo.imagem_url).replace(/^\//, ''))) : 'https://via.placeholder.com/1200x600?text=Sem+imagem';
            document.getElementById('veiculoModalImagem').src = imagem;
            document.getElementById('veiculoModalNome').textContent = ((veiculo.veiculo_marca || '') + ' ' + (veiculo.veiculo_modelo || '')).trim() || '-';
            document.getElementById('veiculoModalPlaca').textContent = veiculo.veiculo_placa || '-';
            document.getElementById('veiculoModalAno').textContent = veiculo.veiculo_ano || '-';
            document.getElementById('veiculoModalProprietario').textContent = veiculo.proprietario_nome || '-';
            document.getElementById('veiculoModalCidade').textContent = veiculo.cidade_nome || 'Nao informada';
            document.getElementById('veiculoModalStatus').textContent = parseInt(veiculo.disponivel, 10) === 1 ? 'Ativo' : 'Pendente/Inativo';

            document.getElementById('modalVeiculo').classList.remove('hidden');
        }

        function fecharModalVeiculo() {
            document.getElementById('modalVeiculo').classList.add('hidden');
        }

        function statusReservaLabel(status, devolucaoData) {
            const st = String(status || 'pendente').toLowerCase();
            const hoje = new Date();
            hoje.setHours(0, 0, 0, 0);

            if (st === 'cancelada' || st === 'rejeitada') {
                return 'Cancelada/Rejeitada';
            }
            if (st === 'finalizada') {
                return 'Concluida';
            }
            if (st === 'confirmada' && devolucaoData) {
                const fim = new Date(devolucaoData + 'T00:00:00');
                if (!Number.isNaN(fim.getTime()) && fim < hoje) {
                    return 'Concluida';
                }
                return 'Confirmada';
            }
            if (st === 'confirmada') {
                return 'Confirmada';
            }
            if (st === 'pago') {
                return 'Pago (aguardando)';
            }
            return 'Pendente';
        }

        function abrirModalReserva(reservaId) {
            const reserva = reservasData.find((item) => parseInt(item.id, 10) === parseInt(reservaId, 10));
            if (!reserva) {
                notifyError('Reserva nao encontrada.');
                return;
            }

            document.getElementById('reservaModalId').textContent = '#' + reserva.id;
            document.getElementById('reservaModalStatus').textContent = statusReservaLabel(reserva.status, reserva.devolucao_data);
            document.getElementById('reservaModalLocatario').textContent = (reserva.locatario_nome || '-') + ' (' + (reserva.locatario_email || '-') + ')';
            document.getElementById('reservaModalProprietario').textContent = reserva.proprietario_nome || '-';
            document.getElementById('reservaModalVeiculo').textContent = reserva.veiculo_nome || '-';

            const inicio = reserva.reserva_data ? new Date(reserva.reserva_data + 'T00:00:00').toLocaleDateString('pt-BR') : '-';
            const fim = reserva.devolucao_data ? new Date(reserva.devolucao_data + 'T00:00:00').toLocaleDateString('pt-BR') : '-';
            document.getElementById('reservaModalPeriodo').textContent = inicio + ' ate ' + fim;
            document.getElementById('reservaModalValor').textContent = 'R$ ' + Number(reserva.valor_total || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            document.getElementById('modalReserva').classList.remove('hidden');
        }

        function fecharModalReserva() {
            document.getElementById('modalReserva').classList.add('hidden');
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
                fecharModalDocumentos();
                fecharModalVeiculo();
                fecharModalReserva();
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
