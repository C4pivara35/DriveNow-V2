<?php

function reservaDataValidaYmd(?string $data): bool
{
    if ($data === null) {
        return false;
    }

    $data = trim($data);
    if ($data === '') {
        return false;
    }

    $dateTime = DateTime::createFromFormat('Y-m-d', $data);
    return $dateTime !== false && $dateTime->format('Y-m-d') === $data;
}

function reservaNormalizarPeriodo(?string $inicio, ?string $fim): array
{
    if (!reservaDataValidaYmd($inicio) || !reservaDataValidaYmd($fim)) {
        return [
            'ok' => false,
            'mensagem' => 'Datas de reserva e devolucao sao obrigatorias e devem estar no formato valido.',
        ];
    }

    $inicioDate = new DateTimeImmutable($inicio);
    $fimDate = new DateTimeImmutable($fim);
    $hoje = new DateTimeImmutable('today');

    if ($fimDate <= $inicioDate) {
        return [
            'ok' => false,
            'mensagem' => 'A data de devolucao deve ser posterior a data de reserva.',
        ];
    }

    if ($inicioDate < $hoje) {
        return [
            'ok' => false,
            'mensagem' => 'Nao e possivel criar reservas com data inicial no passado.',
        ];
    }

    $dias = (int)$inicioDate->diff($fimDate)->format('%a');
    if ($dias <= 0) {
        return [
            'ok' => false,
            'mensagem' => 'Periodo de reserva invalido.',
        ];
    }

    return [
        'ok' => true,
        'inicio' => $inicioDate->format('Y-m-d'),
        'fim' => $fimDate->format('Y-m-d'),
        'dias' => $dias,
    ];
}

function tabelaIndisponibilidadeVeiculoExiste(PDO $pdo): bool
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'indisponibilidade_veiculo'");
        $cache = (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $cache = false;
    }

    return $cache;
}

function obterColunasIndisponibilidadeVeiculo(PDO $pdo): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    if (!tabelaIndisponibilidadeVeiculoExiste($pdo)) {
        $cache = [];
        return $cache;
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM indisponibilidade_veiculo");
        $colunas = [];
        foreach ($stmt->fetchAll() as $linha) {
            $nomeColuna = strtolower((string)($linha['Field'] ?? ''));
            if ($nomeColuna !== '') {
                $colunas[$nomeColuna] = true;
            }
        }

        $cache = $colunas;
    } catch (PDOException $e) {
        $cache = [];
    }

    return $cache;
}

function colunaIndisponibilidadeExiste(PDO $pdo, string $nomeColuna): bool
{
    $colunas = obterColunasIndisponibilidadeVeiculo($pdo);
    return isset($colunas[strtolower($nomeColuna)]);
}

function obterNomeColunaIndisponibilidade(PDO $pdo, array $candidatas): ?string
{
    foreach ($candidatas as $candidata) {
        if (colunaIndisponibilidadeExiste($pdo, $candidata)) {
            return $candidata;
        }
    }

    return null;
}

function indisponibilidadeVeiculoCompativel(PDO $pdo): bool
{
    if (!tabelaIndisponibilidadeVeiculoExiste($pdo)) {
        return false;
    }

    $colVeiculo = obterNomeColunaIndisponibilidade($pdo, ['veiculo_id', 'vehicle_id']);
    $colInicio = obterNomeColunaIndisponibilidade($pdo, ['data_inicio', 'start_date']);
    $colFim = obterNomeColunaIndisponibilidade($pdo, ['data_fim', 'end_date']);

    return $colVeiculo !== null && $colInicio !== null && $colFim !== null;
}

function buscarConflitoReservaAtiva(PDO $pdo, int $veiculoId, string $inicio, string $fim): ?array
{
    $stmt = $pdo->prepare(
        "SELECT id, reserva_data, devolucao_data, COALESCE(status, 'pendente') AS status
         FROM reserva
         WHERE veiculo_id = ?
           AND COALESCE(status, 'pendente') NOT IN ('rejeitada', 'cancelada', 'finalizada')
           AND reserva_data <= ?
           AND devolucao_data >= ?
         LIMIT 1"
    );

    $stmt->execute([$veiculoId, $fim, $inicio]);
    $conflito = $stmt->fetch();

    return $conflito ?: null;
}

function buscarConflitoBloqueioVeiculo(PDO $pdo, int $veiculoId, string $inicio, string $fim): ?array
{
    if (!indisponibilidadeVeiculoCompativel($pdo)) {
        return null;
    }

    $colVeiculo = obterNomeColunaIndisponibilidade($pdo, ['veiculo_id', 'vehicle_id']);
    $colInicio = obterNomeColunaIndisponibilidade($pdo, ['data_inicio', 'start_date']);
    $colFim = obterNomeColunaIndisponibilidade($pdo, ['data_fim', 'end_date']);
    $colMotivo = obterNomeColunaIndisponibilidade($pdo, ['motivo', 'reason']);
    $colAtivo = obterNomeColunaIndisponibilidade($pdo, ['ativo', 'is_active', 'active']);

    if ($colVeiculo === null || $colInicio === null || $colFim === null) {
        return null;
    }

    $selectMotivo = $colMotivo !== null ? "`$colMotivo` AS motivo" : "'' AS motivo";
    $filtroAtivo = $colAtivo !== null ? " AND `$colAtivo` = 1" : '';

    $stmt = $pdo->prepare(
        "SELECT id, `$colInicio` AS data_inicio, `$colFim` AS data_fim, $selectMotivo
         FROM indisponibilidade_veiculo
         WHERE `$colVeiculo` = ?
           $filtroAtivo
           AND `$colInicio` <= ?
           AND `$colFim` >= ?
         LIMIT 1"
    );

    $stmt->execute([$veiculoId, $fim, $inicio]);
    $conflito = $stmt->fetch();

    return $conflito ?: null;
}

function validarDisponibilidadeIntervalo(PDO $pdo, int $veiculoId, string $inicio, string $fim): array
{
    $conflitoReserva = buscarConflitoReservaAtiva($pdo, $veiculoId, $inicio, $fim);
    if ($conflitoReserva) {
        return [
            'ok' => false,
            'tipo' => 'reserva',
            'mensagem' => 'Ja existe uma reserva ativa neste periodo. Escolha outras datas.',
            'conflito' => $conflitoReserva,
        ];
    }

    $conflitoBloqueio = buscarConflitoBloqueioVeiculo($pdo, $veiculoId, $inicio, $fim);
    if ($conflitoBloqueio) {
        return [
            'ok' => false,
            'tipo' => 'bloqueio',
            'mensagem' => 'Este periodo esta bloqueado pelo proprietario e nao pode ser reservado.',
            'conflito' => $conflitoBloqueio,
        ];
    }

    return ['ok' => true];
}

function criarReservaComBloqueio(
    PDO $pdo,
    int $veiculoId,
    int $usuarioId,
    array $periodo,
    string $observacoes = '',
    float $taxaUso = 20.00,
    float $taxaLimpeza = 30.00
): array {
    $transacaoIniciadaAqui = false;

    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $transacaoIniciadaAqui = true;
        }

        // Serializa as tentativas de reserva por veiculo ate o commit.
        $stmt = $pdo->prepare(
            "SELECT id, preco_diaria, disponivel
             FROM veiculo
             WHERE id = ?
             FOR UPDATE"
        );
        $stmt->execute([$veiculoId]);
        $veiculo = $stmt->fetch();

        if (!$veiculo) {
            if ($transacaoIniciadaAqui) {
                $pdo->rollBack();
            }

            return [
                'ok' => false,
                'http_status' => 404,
                'mensagem' => 'Veiculo nao encontrado.',
            ];
        }

        if ((int)($veiculo['disponivel'] ?? 1) !== 1) {
            if ($transacaoIniciadaAqui) {
                $pdo->rollBack();
            }

            return [
                'ok' => false,
                'http_status' => 409,
                'mensagem' => 'Este veiculo esta indisponivel para novas reservas.',
            ];
        }

        $disponibilidade = validarDisponibilidadeIntervalo(
            $pdo,
            $veiculoId,
            (string)$periodo['inicio'],
            (string)$periodo['fim']
        );

        if (!$disponibilidade['ok']) {
            if ($transacaoIniciadaAqui) {
                $pdo->rollBack();
            }

            return [
                'ok' => false,
                'http_status' => 409,
                'mensagem' => $disponibilidade['mensagem'],
                'tipo' => $disponibilidade['tipo'] ?? null,
                'conflito' => $disponibilidade['conflito'] ?? null,
            ];
        }

        $diariaValor = (float)$veiculo['preco_diaria'];
        $dias = (int)$periodo['dias'];
        $valorTotal = ($diariaValor * $dias) + $taxaUso + $taxaLimpeza;

        $stmt = $pdo->prepare(
            "INSERT INTO reserva
             (veiculo_id, conta_usuario_id, reserva_data, devolucao_data,
              diaria_valor, taxas_de_uso, taxas_de_limpeza, valor_total, observacoes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $veiculoId,
            $usuarioId,
            (string)$periodo['inicio'],
            (string)$periodo['fim'],
            $diariaValor,
            $taxaUso,
            $taxaLimpeza,
            $valorTotal,
            $observacoes,
        ]);

        $reservaId = (int)$pdo->lastInsertId();

        if ($transacaoIniciadaAqui) {
            $pdo->commit();
        }

        return [
            'ok' => true,
            'reserva_id' => $reservaId,
            'valor_total' => $valorTotal,
            'diaria_valor' => $diariaValor,
            'taxa_uso' => $taxaUso,
            'taxa_limpeza' => $taxaLimpeza,
        ];
    } catch (Throwable $e) {
        if ($transacaoIniciadaAqui && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function buscarReservasCalendarioVeiculo(PDO $pdo, int $veiculoId): array
{
    $stmt = $pdo->prepare(
        "SELECT reserva_data, devolucao_data, COALESCE(status, 'pendente') AS status
         FROM reserva
         WHERE veiculo_id = ?
           AND COALESCE(status, 'pendente') NOT IN ('rejeitada', 'cancelada', 'finalizada')
           AND devolucao_data >= CURDATE()
         ORDER BY reserva_data"
    );

    $stmt->execute([$veiculoId]);
    return $stmt->fetchAll();
}

function buscarBloqueiosAtivosVeiculo(PDO $pdo, int $veiculoId, bool $somenteFuturos = true): array
{
    if (!indisponibilidadeVeiculoCompativel($pdo)) {
        return [];
    }

    $colVeiculo = obterNomeColunaIndisponibilidade($pdo, ['veiculo_id', 'vehicle_id']);
    $colInicio = obterNomeColunaIndisponibilidade($pdo, ['data_inicio', 'start_date']);
    $colFim = obterNomeColunaIndisponibilidade($pdo, ['data_fim', 'end_date']);
    $colMotivo = obterNomeColunaIndisponibilidade($pdo, ['motivo', 'reason']);
    $colAtivo = obterNomeColunaIndisponibilidade($pdo, ['ativo', 'is_active', 'active']);

    if ($colVeiculo === null || $colInicio === null || $colFim === null) {
        return [];
    }

    $selectMotivo = $colMotivo !== null ? "`$colMotivo` AS motivo" : "'' AS motivo";

    $sql =
        "SELECT id, `$colInicio` AS data_inicio, `$colFim` AS data_fim, $selectMotivo
         FROM indisponibilidade_veiculo
         WHERE `$colVeiculo` = ?";

    if ($colAtivo !== null) {
        $sql .= " AND `$colAtivo` = 1";
    }

    if ($somenteFuturos) {
        $sql .= " AND `$colFim` >= CURDATE()";
    }

    $sql .= " ORDER BY `$colInicio`";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$veiculoId]);

    return $stmt->fetchAll();
}

function criarBloqueioManualVeiculo(PDO $pdo, int $veiculoId, string $inicio, string $fim, string $motivo, ?int $usuarioId = null): bool
{
    if (!indisponibilidadeVeiculoCompativel($pdo)) {
        return false;
    }

    $colVeiculo = obterNomeColunaIndisponibilidade($pdo, ['veiculo_id', 'vehicle_id']);
    $colInicio = obterNomeColunaIndisponibilidade($pdo, ['data_inicio', 'start_date']);
    $colFim = obterNomeColunaIndisponibilidade($pdo, ['data_fim', 'end_date']);
    $colMotivo = obterNomeColunaIndisponibilidade($pdo, ['motivo', 'reason']);
    $colUsuario = obterNomeColunaIndisponibilidade($pdo, ['criado_por_usuario_id', 'created_by_user_id', 'conta_usuario_id', 'user_id']);
    $colAtivo = obterNomeColunaIndisponibilidade($pdo, ['ativo', 'is_active', 'active']);
    $colCriadoEm = obterNomeColunaIndisponibilidade($pdo, ['criado_em', 'created_at']);
    $colAtualizadoEm = obterNomeColunaIndisponibilidade($pdo, ['atualizado_em', 'updated_at']);

    if ($colVeiculo === null || $colInicio === null || $colFim === null) {
        return false;
    }

    $colunas = ["`$colVeiculo`", "`$colInicio`", "`$colFim`"];
    $params = [$veiculoId, $inicio, $fim];

    if ($colMotivo !== null) {
        $colunas[] = "`$colMotivo`";
        $params[] = $motivo;
    }

    if ($colUsuario !== null) {
        $colunas[] = "`$colUsuario`";
        $params[] = $usuarioId;
    }

    if ($colAtivo !== null) {
        $colunas[] = "`$colAtivo`";
        $params[] = 1;
    }

    $agora = date('Y-m-d H:i:s');
    if ($colCriadoEm !== null) {
        $colunas[] = "`$colCriadoEm`";
        $params[] = $agora;
    }

    if ($colAtualizadoEm !== null) {
        $colunas[] = "`$colAtualizadoEm`";
        $params[] = $agora;
    }

    $placeholders = implode(', ', array_fill(0, count($colunas), '?'));
    $sql = "INSERT INTO indisponibilidade_veiculo (" . implode(', ', $colunas) . ") VALUES ($placeholders)";

    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

function removerBloqueioManualVeiculo(PDO $pdo, int $bloqueioId, int $veiculoId): bool
{
    if (!indisponibilidadeVeiculoCompativel($pdo)) {
        return false;
    }

    $colVeiculo = obterNomeColunaIndisponibilidade($pdo, ['veiculo_id', 'vehicle_id']);
    $colAtivo = obterNomeColunaIndisponibilidade($pdo, ['ativo', 'is_active', 'active']);

    if ($colVeiculo === null) {
        return false;
    }

    if ($colAtivo !== null) {
        $stmt = $pdo->prepare(
            "UPDATE indisponibilidade_veiculo
             SET `$colAtivo` = 0
             WHERE id = ? AND `$colVeiculo` = ?"
        );
    } else {
        $stmt = $pdo->prepare(
            "DELETE FROM indisponibilidade_veiculo
             WHERE id = ? AND `$colVeiculo` = ?"
        );
    }

    $stmt->execute([$bloqueioId, $veiculoId]);
    return $stmt->rowCount() > 0;
}

function obterEventosCalendarioVeiculo(PDO $pdo, int $veiculoId): array
{
    $reservas = buscarReservasCalendarioVeiculo($pdo, $veiculoId);
    $bloqueios = buscarBloqueiosAtivosVeiculo($pdo, $veiculoId);

    $reservados = [];
    foreach ($reservas as $reserva) {
        $reservados[] = [
            'inicio' => $reserva['reserva_data'],
            'fim' => $reserva['devolucao_data'],
            'status' => $reserva['status'],
        ];
    }

    $bloqueados = [];
    foreach ($bloqueios as $bloqueio) {
        $bloqueados[] = [
            'id' => (int)$bloqueio['id'],
            'inicio' => $bloqueio['data_inicio'],
            'fim' => $bloqueio['data_fim'],
            'motivo' => (string)($bloqueio['motivo'] ?? ''),
        ];
    }

    return [
        'reservados' => $reservados,
        'bloqueados' => $bloqueados,
    ];
}
