<?php

class FileUpload
{
    private string $uploadDir;
    private array $allowedExtensions;
    private int $maxFileSize;
    private ?string $storagePrefix;
    private array $errors = [];

    private const ALLOWED_MIME_TYPES = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'pdf' => ['application/pdf'],
    ];

    public function __construct(
        string $uploadDir = 'uploads/',
        array $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'],
        int $maxFileSize = 5242880,
        ?string $storagePrefix = null
    ) {
        $this->uploadDir = rtrim(str_replace('\\', '/', $uploadDir), '/') . '/';
        $this->allowedExtensions = array_map('strtolower', $allowedExtensions);
        $this->maxFileSize = $maxFileSize;
        $this->storagePrefix = $storagePrefix !== null
            ? rtrim(str_replace('\\', '/', $storagePrefix), '/') . '/'
            : null;

        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    public function uploadFile(array $file, string $prefix = ''): array|false
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $this->addError('Falha no upload do arquivo.');
            return false;
        }

        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $this->addError('Arquivo de upload invalido.');
            return false;
        }

        if (($file['size'] ?? 0) > $this->maxFileSize) {
            $this->addError('O arquivo excede o tamanho maximo permitido de ' . $this->formatSize($this->maxFileSize));
            return false;
        }

        $originalName = (string)($file['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension === '' || !in_array($extension, $this->allowedExtensions, true)) {
            $this->addError('Tipo de arquivo nao permitido. Extensoes permitidas: ' . implode(', ', $this->allowedExtensions));
            return false;
        }

        $mimeType = $this->detectMimeType($file['tmp_name']);
        $allowedMimeTypes = self::ALLOWED_MIME_TYPES[$extension] ?? [];

        if ($mimeType === null || !in_array($mimeType, $allowedMimeTypes, true)) {
            $this->addError('O conteudo do arquivo nao corresponde a um tipo permitido.');
            return false;
        }

        if (in_array($extension, ['jpg', 'jpeg', 'png'], true) && getimagesize($file['tmp_name']) === false) {
            $this->addError('O arquivo enviado nao e uma imagem valida.');
            return false;
        }

        $safePrefix = preg_replace('/[^a-zA-Z0-9_-]/', '', $prefix);
        $newFileName = $safePrefix . bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = $this->uploadDir . $newFileName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $this->addError('Nao foi possivel mover o arquivo para o destino.');
            return false;
        }

        @chmod($destination, 0644);

        return [
            'path' => $this->buildStoredPath($destination, $newFileName),
            'absolute_path' => $destination,
            'name' => $newFileName,
            'original_name' => $originalName,
            'size' => (int)$file['size'],
            'type' => $mimeType,
            'extension' => $extension,
        ];
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getLastError(): string
    {
        $lastError = end($this->errors);

        return $lastError === false ? '' : $lastError;
    }

    public function createUserDirectory(int $userId): string
    {
        $dirPath = $this->uploadDir . 'user_' . $userId . '/docs/';

        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        return $dirPath;
    }

    private function buildStoredPath(string $destination, string $fileName): string
    {
        if ($this->storagePrefix !== null) {
            return $this->storagePrefix . $fileName;
        }

        $documentRoot = isset($_SERVER['DOCUMENT_ROOT'])
            ? realpath((string)$_SERVER['DOCUMENT_ROOT'])
            : false;
        $destinationReal = realpath($destination);

        if ($documentRoot !== false && $destinationReal !== false) {
            $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/') . '/';
            $destinationReal = str_replace('\\', '/', $destinationReal);

            if (str_starts_with($destinationReal, $documentRoot)) {
                return ltrim(substr($destinationReal, strlen($documentRoot)), '/');
            }
        }

        return $fileName;
    }

    private function detectMimeType(string $tmpName): ?string
    {
        if (!function_exists('finfo_open')) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return null;
        }

        $mimeType = finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        return is_string($mimeType) ? $mimeType : null;
    }

    private function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    private function formatSize(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 2) . ' ' . $units[$i];
    }
}
