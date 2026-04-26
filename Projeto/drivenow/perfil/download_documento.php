<?php

require_once '../includes/auth.php';
require_once '../includes/documentos_privados.php';

verificarAutenticacao();

$usuarioLogado = getUsuario();
$solicitadoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tipo = isset($_GET['tipo']) ? (string)$_GET['tipo'] : '';
$inline = isset($_GET['inline']) && $_GET['inline'] === '1';

function negarDocumento(int $statusCode = 404): never
{
    http_response_code($statusCode);
    exit('Documento nao encontrado.');
}

if ($solicitadoId <= 0 || !in_array($tipo, ['frente', 'verso'], true)) {
    negarDocumento();
}

if ((int)$usuarioLogado['id'] !== $solicitadoId && !isAdmin()) {
    negarDocumento(403);
}

global $pdo;

$campo = $tipo === 'frente' ? 'foto_cnh_frente' : 'foto_cnh_verso';
$stmt = $pdo->prepare("SELECT {$campo} FROM conta_usuario WHERE id = ?");
$stmt->execute([$solicitadoId]);
$resultado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resultado || empty($resultado[$campo])) {
    negarDocumento();
}

$arquivoPath = resolverCaminhoDocumento((string)$resultado[$campo]);

if ($arquivoPath === null || !is_file($arquivoPath) || !is_readable($arquivoPath)) {
    negarDocumento();
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$contentType = $finfo !== false ? finfo_file($finfo, $arquivoPath) : false;

if ($finfo !== false) {
    finfo_close($finfo);
}

$mimeExtensions = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'application/pdf' => 'pdf',
];

if (!is_string($contentType) || !isset($mimeExtensions[$contentType])) {
    negarDocumento();
}

$filename = sprintf('cnh_%s_%d.%s', $tipo, $solicitadoId, $mimeExtensions[$contentType]);
$disposition = $inline ? 'inline' : 'attachment';

if (ob_get_level() > 0) {
    ob_clean();
}

header('Content-Type: ' . $contentType);
header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
header('Content-Length: ' . filesize($arquivoPath));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');

readfile($arquivoPath);
exit;
