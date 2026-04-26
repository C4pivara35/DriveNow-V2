<?php

function carregarEnv(string $arquivoEnv): void
{
    if (!is_readable($arquivoEnv)) {
        return;
    }

    $linhas = file($arquivoEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($linhas as $linha) {
        $linha = trim($linha);

        if ($linha === '' || str_starts_with($linha, '#') || !str_contains($linha, '=')) {
            continue;
        }

        [$chave, $valor] = array_map('trim', explode('=', $linha, 2));

        if ($chave === '' || getenv($chave) !== false) {
            continue;
        }

        $valor = trim($valor, "\"'");
        putenv("$chave=$valor");
        $_ENV[$chave] = $valor;
        $_SERVER[$chave] = $valor;
    }
}

function envValor(string $chave, ?string $padrao = null): ?string
{
    $valor = getenv($chave);

    if ($valor === false) {
        return $padrao;
    }

    return $valor;
}

function envBooleano(string $chave, bool $padrao = false): bool
{
    $valor = envValor($chave);

    if ($valor === null) {
        return $padrao;
    }

    return in_array(strtolower($valor), ['1', 'true', 'sim', 'yes', 'on'], true);
}

function registrarErroBanco(Throwable $erro): void
{
    $diretorioLogs = __DIR__ . '/../logs';

    if (!is_dir($diretorioLogs)) {
        mkdir($diretorioLogs, 0750, true);
    }

    error_log(
        sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $erro->getMessage()),
        3,
        $diretorioLogs . '/database_errors.log'
    );
}

carregarEnv(__DIR__ . '/../.env');

define('DB_HOST', envValor('DB_HOST', 'localhost'));
define('DB_PORT', envValor('DB_PORT', '3306'));
define('DB_NAME', envValor('DB_NAME', 'drivenow'));
define('DB_USER', envValor('DB_USER', 'drivenow_app'));
define('DB_PASS', envValor('DB_PASS'));
define('DB_CHARSET', envValor('DB_CHARSET', 'utf8mb4'));
define('DB_ALLOW_EMPTY_PASSWORD', envBooleano('DB_ALLOW_EMPTY_PASSWORD', false));

try {
    if (DB_PASS === null || (DB_PASS === '' && !DB_ALLOW_EMPTY_PASSWORD)) {
        throw new RuntimeException('DB_PASS nao foi configurado no ambiente.');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Throwable $e) {
    registrarErroBanco($e);
    http_response_code(500);
    exit('Erro interno. Tente novamente mais tarde.');
}
