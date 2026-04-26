<?php

require_once __DIR__ . '/FileUpload.php';

const VEHICLE_IMAGES_MIN = 1;
const VEHICLE_IMAGES_MAX = 5;
const VEHICLE_IMAGE_MAX_SIZE = 5242880;

function normalizarArquivosVeiculo(array $files, string $fieldName = 'veiculo_imagens'): array
{
    if (!isset($files[$fieldName])) {
        return [];
    }

    $input = $files[$fieldName];
    $names = $input['name'] ?? [];

    if (!is_array($names)) {
        return [normalizarArquivoUnicoVeiculo($input)];
    }

    $normalized = [];
    foreach ($names as $index => $name) {
        $file = [
            'name' => $name,
            'type' => $input['type'][$index] ?? '',
            'tmp_name' => $input['tmp_name'][$index] ?? '',
            'error' => $input['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $input['size'][$index] ?? 0,
        ];

        if ((int)$file['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $normalized[] = normalizarArquivoUnicoVeiculo($file);
    }

    return $normalized;
}

function normalizarArquivoUnicoVeiculo(array $file): array
{
    return [
        'name' => (string)($file['name'] ?? ''),
        'type' => (string)($file['type'] ?? ''),
        'tmp_name' => (string)($file['tmp_name'] ?? ''),
        'error' => (int)($file['error'] ?? UPLOAD_ERR_NO_FILE),
        'size' => (int)($file['size'] ?? 0),
    ];
}

function mensagemErroUploadVeiculo(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Uma das imagens excede o tamanho maximo permitido.',
        UPLOAD_ERR_PARTIAL => 'Uma das imagens foi enviada apenas parcialmente. Tente novamente.',
        UPLOAD_ERR_NO_TMP_DIR => 'Nao foi possivel salvar o upload temporario no servidor.',
        UPLOAD_ERR_CANT_WRITE => 'Nao foi possivel gravar a imagem no servidor.',
        UPLOAD_ERR_EXTENSION => 'Uma extensao do servidor bloqueou o upload da imagem.',
        default => 'Falha no upload de uma das imagens.',
    };
}

function validarArquivosImagemVeiculo(array $arquivos, int $minimo, int $maximo): ?string
{
    $total = count($arquivos);

    if ($total < $minimo) {
        return $minimo === 1
            ? 'Envie pelo menos 1 imagem do veiculo.'
            : 'Envie as imagens obrigatorias do veiculo.';
    }

    if ($total > $maximo) {
        return 'Cada veiculo pode ter no maximo ' . $maximo . ' imagens.';
    }

    foreach ($arquivos as $arquivo) {
        if (($arquivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return mensagemErroUploadVeiculo((int)$arquivo['error']);
        }
    }

    return null;
}

function salvarImagensVeiculo(PDO $pdo, int $veiculoId, array $arquivos, int $ordemInicial = 1): array
{
    if (empty($arquivos)) {
        return [];
    }

    $uploadDir = dirname(__DIR__) . '/uploads/vehicles/vehicle_' . $veiculoId . '/';
    $storagePrefix = 'uploads/vehicles/vehicle_' . $veiculoId;
    $uploader = new FileUpload($uploadDir, ['jpg', 'jpeg', 'png'], VEHICLE_IMAGE_MAX_SIZE, $storagePrefix);
    $stmt = $pdo->prepare('INSERT INTO imagem (veiculo_id, imagem_url, imagem_ordem) VALUES (?, ?, ?)');
    $salvas = [];
    $ordem = $ordemInicial;

    foreach ($arquivos as $arquivo) {
        $resultado = $uploader->uploadFile($arquivo, 'veiculo_');

        if ($resultado === false) {
            excluirArquivosFisicosVeiculo($salvas);
            $erro = $uploader->getLastError();
            throw new RuntimeException($erro !== '' ? $erro : 'Nao foi possivel salvar uma das imagens.');
        }

        try {
            $stmt->execute([$veiculoId, $resultado['path'], $ordem]);
        } catch (Throwable $erro) {
            excluirArquivosFisicosVeiculo(array_merge($salvas, [$resultado['path']]));
            throw $erro;
        }

        $salvas[] = $resultado['path'];
        $ordem++;
    }

    return $salvas;
}

function excluirArquivosFisicosVeiculo(array $paths): void
{
    $baseUploads = realpath(dirname(__DIR__) . '/uploads');

    if ($baseUploads === false) {
        return;
    }

    $baseUploads = rtrim(str_replace('\\', '/', $baseUploads), '/') . '/';

    foreach ($paths as $path) {
        $relative = ltrim(str_replace('\\', '/', (string)$path), '/');
        if ($relative === '' || !str_starts_with($relative, 'uploads/')) {
            continue;
        }

        $absolute = realpath(dirname(__DIR__) . '/' . $relative);
        if ($absolute === false) {
            continue;
        }

        $absolute = str_replace('\\', '/', $absolute);
        if (str_starts_with($absolute, $baseUploads) && is_file($absolute)) {
            @unlink($absolute);
        }
    }
}

function buscarImagensVeiculo(PDO $pdo, int $veiculoId): array
{
    $stmt = $pdo->prepare('SELECT id, imagem_url, imagem_ordem FROM imagem WHERE veiculo_id = ? ORDER BY imagem_ordem IS NULL, imagem_ordem, id');
    $stmt->execute([$veiculoId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
