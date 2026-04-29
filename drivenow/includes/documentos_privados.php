<?php

function documentosPrivadosBaseDir(): string
{
    $configurado = getenv('DOCUMENTS_PRIVATE_DIR');

    if ($configurado !== false && trim($configurado) !== '') {
        return rtrim(str_replace('\\', '/', trim($configurado)), '/');
    }

    return rtrim(str_replace('\\', '/', dirname(__DIR__, 3) . '/drivenow_private/documents'), '/');
}

function documentosProjetoBaseDir(): string
{
    return rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
}

function normalizarCaminhoDocumento(?string $caminho): string
{
    $caminho = trim((string)$caminho);
    $caminho = str_replace("\0", '', $caminho);
    $caminho = str_replace('\\', '/', $caminho);
    $caminho = ltrim($caminho, '/');

    return str_replace('user_user_', 'user_', $caminho);
}

function obterDiretorioDocumentoUsuario(int $usuarioId): string
{
    $diretorio = documentosPrivadosBaseDir() . '/user_' . $usuarioId . '/docs';

    if (!is_dir($diretorio)) {
        mkdir($diretorio, 0755, true);
    }

    return $diretorio;
}

function obterPrefixoDocumentoUsuario(int $usuarioId): string
{
    return 'private_documents/user_' . $usuarioId . '/docs/';
}

function obterUrlDocumentoUsuario(int $usuarioId, string $tipo, bool $inline = true, string $basePath = ''): string
{
    $parametros = [
        'tipo' => $tipo,
        'id' => $usuarioId,
    ];

    if ($inline) {
        $parametros['inline'] = '1';
    }

    return $basePath . 'perfil/download_documento.php?' . http_build_query($parametros);
}

function resolverCaminhoDocumento(?string $caminho): ?string
{
    $caminho = normalizarCaminhoDocumento($caminho);

    if ($caminho === '' || preg_match('/^https?:\/\//i', $caminho) || str_contains($caminho, '..')) {
        return null;
    }

    $prefixoPrivado = 'private_documents/';
    $prefixoLegado = 'uploads/';

    if (str_starts_with($caminho, $prefixoPrivado)) {
        $relativo = substr($caminho, strlen($prefixoPrivado));
        $base = realpath(documentosPrivadosBaseDir());
        $arquivo = realpath(documentosPrivadosBaseDir() . '/' . $relativo);

        if ($base !== false && $arquivo !== false && str_starts_with(str_replace('\\', '/', $arquivo), str_replace('\\', '/', $base) . '/')) {
            return $arquivo;
        }
    }

    if (str_starts_with($caminho, $prefixoLegado)) {
        $relativoPrivado = preg_replace('#^uploads/#', '', $caminho);
        $basePrivada = realpath(documentosPrivadosBaseDir());
        $arquivoPrivado = realpath(documentosPrivadosBaseDir() . '/' . $relativoPrivado);

        if ($basePrivada !== false && $arquivoPrivado !== false && str_starts_with(str_replace('\\', '/', $arquivoPrivado), rtrim(str_replace('\\', '/', $basePrivada), '/') . '/')) {
            return $arquivoPrivado;
        }

        $base = realpath(documentosProjetoBaseDir() . '/uploads');
        $arquivo = realpath(documentosProjetoBaseDir() . '/' . $caminho);

        if ($base !== false && $arquivo !== false && str_starts_with(str_replace('\\', '/', $arquivo), rtrim(str_replace('\\', '/', $base), '/') . '/')) {
            return $arquivo;
        }
    }

    return null;
}

function documentoExiste(?string $caminho): bool
{
    $arquivo = resolverCaminhoDocumento($caminho);

    return $arquivo !== null && is_file($arquivo) && is_readable($arquivo);
}
