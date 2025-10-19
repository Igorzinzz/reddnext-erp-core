<?php
/**
 * 🚀 Reddnext ERP — Deploy Automático (v2.4 - Hostinger Stable)
 * - Sempre lê a ÚLTIMA TAG do GitHub (nunca 'main')
 * - Extrai em pasta temporária e copia recursivamente
 * - Ignora arquivos sensíveis (config, uploads, env, etc.)
 * - Suporta ?force=1 e ?debug=1
 * - 100% compatível com hospedagem Hostinger
 */

$senhaSegura = 'mantereddpdv'; // 🔐 senha de segurança
$token = $_GET['token'] ?? '';
$force = isset($_GET['force']);
$debug = isset($_GET['debug']);

if ($token !== $senhaSegura) {
    http_response_code(403);
    exit('<h3 style="font-family:sans-serif;color:#d33;">❌ Acesso negado.</h3>');
}

@ini_set('max_execution_time', '300');
@ini_set('memory_limit', '512M');

// ===========================
// 🔧 CONFIGURAÇÕES PRINCIPAIS
// ===========================
$repoOwner = 'Igorzinzz';
$repoName  = 'reddnext-erp-core';
$branch    = 'main';
$baseDir   = __DIR__;
$versaoLocalFile = $baseDir . '/versao.txt';
$zipFile   = $baseDir . '/update.zip';
$logFile   = $baseDir . '/logs/deploy.log';
$tmpDir    = $baseDir . '/tmp_deploy';

if (!file_exists(dirname($logFile))) mkdir(dirname($logFile), 0775, true);

// ===========================
// ⚙️ FUNÇÕES AUXILIARES
// ===========================
function logMsg($msg, $logFile, $color = '#333') {
    $linha = "[" . date('Y-m-d H:i:s') . "] " . strip_tags($msg) . PHP_EOL;
    file_put_contents($logFile, $linha, FILE_APPEND);
    echo "<p style='margin:2px;font-family:sans-serif;color:{$color};'>{$msg}</p>";
}

/**
 * 🔍 Obtém SEMPRE a última TAG pública do GitHub
 */
function getVersaoRemota($owner, $repo) {
    $headers = ["User-Agent: Reddnext-ERP"];
    $tagsUrl = "https://api.github.com/repos/$owner/$repo/tags";

    $tagsJson = null;
    $httpCode = 0;

    // 🔹 Usa cURL (mais confiável)
    if (function_exists('curl_init')) {
        $ch = curl_init($tagsUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 15
        ]);
        $tagsJson = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $opts = ['http' => ['header' => implode("\r\n", $headers)]];
        $tagsJson = @file_get_contents($tagsUrl, false, stream_context_create($opts));
    }

    if ($tagsJson && strpos((string)$httpCode, '200') !== false) {
        $tags = json_decode($tagsJson, true);
        if (!empty($tags[0]['name'])) {
            return $tags[0]['name']; // ✅ última tag criada
        }
    }

    // 🚨 Caso não consiga ler tags
    return 'v0.0.0';
}

/**
 * 📦 Baixa ZIP público da versão/tag
 */
function baixarVersao($owner, $repo, $versao, $destino) {
    $url = "https://github.com/$owner/$repo/archive/refs/tags/$versao.zip";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_USERAGENT => 'Reddnext-ERP-Deploy'
    ]);
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$data) {
        throw new Exception("Falha ao baixar ZIP da tag $versao (HTTP $httpCode)");
    }

    file_put_contents($destino, $data);
    if (!file_exists($destino) || filesize($destino) < 1024) {
        throw new Exception('ZIP baixado muito pequeno ou inexistente.');
    }
}

/**
 * 🧹 Remove diretórios recursivamente
 */
function rrmdir($dir) {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}

/**
 * 🔁 Copia recursivamente, ignorando paths sensíveis
 */
function rrcopy($src, $dst, $skip, $debug, $logFile) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
        $rel = ltrim(str_replace($src, '', $item->getPathname()), DIRECTORY_SEPARATOR);

        // Ignora se o caminho CONTÉM algum termo do skip
        foreach ($skip as $ignore) {
            if (stripos($rel, $ignore) !== false) {
                if ($debug) logMsg("⏭️ Ignorado: $rel", $logFile, '#999');
                continue 2;
            }
        }

        $destPath = rtrim($dst, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $rel;

        if ($item->isDir()) {
            if (!is_dir($destPath)) @mkdir($destPath, 0775, true);
        } else {
            if (!is_dir(dirname($destPath))) @mkdir(dirname($destPath), 0775, true);
            if (@copy($item->getPathname(), $destPath)) {
                if ($debug) logMsg("✅ Copiado: $rel", $logFile, '#2e8b57');
            } else {
                logMsg("❌ Falha ao copiar: $rel", $logFile, '#d33');
            }
        }
    }
}

// ===========================
// 🚀 INÍCIO DO DEPLOY
// ===========================
echo "<h2 style='font-family:sans-serif;color:#e31b1b'>🚀 Reddnext ERP - Deploy (v2.4)</h2>";

$versaoLocal  = file_exists($versaoLocalFile) ? trim(file_get_contents($versaoLocalFile)) : 'v0.0.0';
$versaoRemota = getVersaoRemota($repoOwner, $repoName);

if ($versaoRemota === 'v0.0.0') {
    logMsg("⚠️ Não foi possível identificar a última tag — verifique se existem tags no repositório.", $logFile, '#b8860b');
    exit;
}

if (!$force && $versaoLocal === $versaoRemota) {
    logMsg("✅ Nenhuma atualização necessária. Versão atual: <b>{$versaoLocal}</b>", $logFile, '#2e8b57');
    echo "<p style='font-family:sans-serif;color:#777'>Use <code>?force=1</code> para forçar.</p>";
    exit;
}

logMsg("📦 Última tag detectada: <b>{$versaoRemota}</b> (local: {$versaoLocal})", $logFile, '#007bff');

// 1️⃣ Baixar ZIP
try {
    baixarVersao($repoOwner, $repoName, $versaoRemota, $zipFile);
    logMsg("⬇️ ZIP baixado com sucesso (" . filesize($zipFile) . " bytes).", $logFile, '#007bff');
} catch (Exception $e) {
    logMsg("❌ Erro ao baixar ZIP: " . $e->getMessage(), $logFile, '#d33');
    exit;
}

// 2️⃣ Extrair ZIP
rrmdir($tmpDir);
@mkdir($tmpDir, 0775, true);

$zip = new ZipArchive;
if ($zip->open($zipFile) !== TRUE) {
    logMsg("❌ Falha ao abrir ZIP.", $logFile, '#d33');
    exit;
}
$zip->extractTo($tmpDir);
$zip->close();
@unlink($zipFile);

// 3️⃣ Detectar pasta raiz
$entries = array_values(array_diff(scandir($tmpDir), ['.', '..']));
if (empty($entries)) {
    logMsg("❌ ZIP extraído vazio.", $logFile, '#d33');
    rrmdir($tmpDir);
    exit;
}
$root = $tmpDir . '/' . $entries[0];
if (!is_dir($root)) $root = $tmpDir;

if ($debug) logMsg("📁 Pasta raiz detectada: <b>" . basename($root) . "</b>", $logFile, '#555');

// 4️⃣ Copiar arquivos (respeitando SKIPs)
$skip = [
    'uploads',
    'storage',
    'logs',
    '.git',
    '.github',
    '.env',
    '/config.php',
    '/versao.txt'
];
rrcopy($root, $baseDir, $skip, $debug, $logFile);

// 5️⃣ Atualizar versão local
file_put_contents($versaoLocalFile, $versaoRemota);

// 6️⃣ Limpar temporários
rrmdir($tmpDir);

logMsg("✅ Deploy concluído com sucesso para <b>{$versaoRemota}</b>", $logFile, '#2e8b57');

if ($debug) {
    echo "<hr><p style='font-family:sans-serif;color:#777'>Debug habilitado — arquivos copiados listados acima.</p>";
} else {
    echo "<p style='font-family:sans-serif;color:#777'>Use <code>?debug=1</code> para listar arquivos copiados.</p>";
}
?>