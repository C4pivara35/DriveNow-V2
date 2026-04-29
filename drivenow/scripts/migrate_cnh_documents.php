<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/documentos_privados.php';

$execute = in_array('--execute', $argv, true);
$dryRun = !$execute;

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    echo "Usage: php migrate_cnh_documents.php [--dry-run|--execute]\n";
    echo "Default mode is --dry-run. Use --execute to copy verified files and update DB paths.\n";
    exit(0);
}

function migracaoLog(string $status, int $usuarioId, string $campo, string $detalhe): void
{
    printf("[%s] user=%d field=%s %s\n", $status, $usuarioId, $campo, $detalhe);
}

function caminhoPrivadoMigrado(int $usuarioId, string $caminhoLegado): string
{
    return obterPrefixoDocumentoUsuario($usuarioId) . basename(normalizarCaminhoDocumento($caminhoLegado));
}

function copiarDocumentoVerificado(string $origem, string $destino): bool
{
    $diretorio = dirname($destino);

    if (!is_dir($diretorio) && !mkdir($diretorio, 0755, true)) {
        return false;
    }

    if (is_file($destino)) {
        return filesize($origem) === filesize($destino)
            && hash_file('sha256', $origem) === hash_file('sha256', $destino);
    }

    if (!copy($origem, $destino)) {
        return false;
    }

    @chmod($destino, 0644);

    return filesize($origem) === filesize($destino)
        && hash_file('sha256', $origem) === hash_file('sha256', $destino);
}

global $pdo;

$stmt = $pdo->query(
    "SELECT id, foto_cnh_frente, foto_cnh_verso
     FROM conta_usuario
     WHERE foto_cnh_frente IS NOT NULL
        OR foto_cnh_verso IS NOT NULL
     ORDER BY id"
);

$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
$campos = ['foto_cnh_frente', 'foto_cnh_verso'];
$contadores = [
    'checked' => 0,
    'skipped' => 0,
    'would_migrate' => 0,
    'migrated' => 0,
    'missing' => 0,
    'failed' => 0,
];

echo $dryRun ? "Mode: dry-run\n" : "Mode: execute\n";

foreach ($usuarios as $usuario) {
    $usuarioId = (int)$usuario['id'];

    foreach ($campos as $campo) {
        $contadores['checked']++;
        $caminho = normalizarCaminhoDocumento($usuario[$campo] ?? '');

        if ($caminho === '') {
            $contadores['skipped']++;
            migracaoLog('skip', $usuarioId, $campo, 'empty');
            continue;
        }

        if (str_starts_with($caminho, 'private_documents/')) {
            $contadores['skipped']++;
            migracaoLog('skip', $usuarioId, $campo, 'already_private');
            continue;
        }

        if (!str_starts_with($caminho, 'uploads/')) {
            $contadores['skipped']++;
            migracaoLog('skip', $usuarioId, $campo, 'unsupported_path');
            continue;
        }

        $origem = resolverCaminhoDocumento($caminho);

        if ($origem === null || !is_file($origem) || !is_readable($origem)) {
            $contadores['missing']++;
            migracaoLog('missing', $usuarioId, $campo, 'legacy_file_not_found');
            continue;
        }

        $novoCaminho = caminhoPrivadoMigrado($usuarioId, $caminho);
        $destino = documentosPrivadosBaseDir() . '/' . substr($novoCaminho, strlen('private_documents/'));

        if ($dryRun) {
            $contadores['would_migrate']++;
            migracaoLog('dry-run', $usuarioId, $campo, 'would_migrate_to=' . $novoCaminho);
            continue;
        }

        if (!copiarDocumentoVerificado($origem, $destino)) {
            $contadores['failed']++;
            migracaoLog('failed', $usuarioId, $campo, 'copy_verify_failed');
            continue;
        }

        $update = $pdo->prepare("UPDATE conta_usuario SET {$campo} = ? WHERE id = ? AND {$campo} = ?");
        $update->execute([$novoCaminho, $usuarioId, $caminho]);

        if ($update->rowCount() === 1) {
            $contadores['migrated']++;
            migracaoLog('migrated', $usuarioId, $campo, 'updated_to=' . $novoCaminho);
        } else {
            $contadores['skipped']++;
            migracaoLog('skip', $usuarioId, $campo, 'record_changed_or_already_updated');
        }
    }
}

printf(
    "Summary: checked=%d skipped=%d would_migrate=%d migrated=%d missing=%d failed=%d\n",
    $contadores['checked'],
    $contadores['skipped'],
    $contadores['would_migrate'],
    $contadores['migrated'],
    $contadores['missing'],
    $contadores['failed']
);

exit($contadores['failed'] > 0 ? 1 : 0);
