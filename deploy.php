<?php
/**
 * 🚀 Reddnext ERP - Deploy Automático Inteligente (Versão Segura)
 * Atualiza apenas o código (mantém uploads, config, storage e banco)
 * Desenvolvido para ambientes Hostinger
 */

$senhaSegura = 'mantereddpdv'; // 🔐 senha para executar o deploy
$token = $_GET['token'] ?? '';
if ($token !== $senhaSegura) {
    http_response_code(403);
    exit('<h3 style="font-family:sans-serif;color:#d33;">❌ Acesso negado.</h3>');
}

// ===========================
// 🔧 CONFIGURAÇÕES PRINCIPAIS
// ===========================
$repoOwner = 'Igorzinzz';
$repoName  = 'reddnext-erp-core';
$branch    = 'main'; // usado como fallback
$versaoLocalFile = __DIR__ . '/versao.txt';
$zipFile   = __DIR__ . '/update.zip';
$logFile   = __DIR__ . '/logs/deploy.log';

// 🔐 Coloque seu token do GitHub abaixo:
$githubToken = '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';

if (!file_exists(dirname($logFile))) {
    mkdir(dirname($logFile), 0775, true);
}

// ===========================
// ⚙️ FUNÇÕES AUXILIARES
// ===========================
function logMsg($msg, $logFile, $color = '#333') {
    $linha = "[" . date('Y-m-d H:i:s') . "] " . strip_tags($msg) . PHP_EOL;
    file_put_contents($logFile, $linha, FILE_APPEND);
    echo "<p style='margin:2px;font-family:sans-serif;color:{$color};'>{$msg}</p>";
}

function getVersaoRemota($owner, $repo, $token) {
    $url = "https://api.github.com/repos/$owner/$repo/tags";
    $headers = [
        "User-Agent: Reddnext-ERP",
        "Authorization: token $token",
        "Accept: application/vnd.github.v3+json"
    ];

    // 🔹 Tenta via cURL
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $json = curl_exec($ch);
        curl_close($ch);
    } else {
        // 🔹 Fallback file_get_contents
        $opts = ['http' => ['header' => implode("\r\n", $headers)]];
        $context = stream_context_create($opts);
        $json = @file_get_contents($url, false, $context);
    }

    if (!$json) {
        return 'main'; // fallback automático
    }

    $tags = json_decode($json, true);
    return $tags[0]['name'] ?? 'main';
}

function baixarVersao($owner, $repo, $versao, $destino, $token) {
    $url = $versao === 'main'
        ? "https://github.com/$owner/$repo/archive/refs/heads/main.zip"
        : "https://api.github.com/repos/$owner/$repo/zipball/$versao";

    $headers = [
        "User-Agent: Reddnext-ERP",
        "Authorization: token $token",
        "Accept: application/vnd.github.v3+json"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $data = curl_exec($ch);
    curl_close($ch);

    file_put_contents($destino, $data);
}

// ===========================
// 🚀 INÍCIO DO DEPLOY
// ===========================
echo "<h2 style='font-family:sans-serif;color:#e31b1b'>🚀 Reddnext ERP - Deploy Automático</h2>";

$versaoLocal  = file_exists($versaoLocalFile) ? trim(file_get_contents($versaoLocalFile)) : 'v0.0.0';
$versaoRemota = getVersaoRemota($repoOwner, $repoName, $githubToken);

if ($versaoRemota === 'main') {
    logMsg("⚠️ API do GitHub indisponível — usando branch <b>main</b> como fallback.", $logFile, '#b8860b');
}

if (!$versaoRemota) {
    logMsg("❌ Não foi possível verificar a versão remota no GitHub.", $logFile, '#d33');
    exit;
}

if ($versaoLocal === $versaoRemota) {
    logMsg("✅ Nenhuma atualização necessária. Versão atual: <b>{$versaoLocal}</b>", $logFile, '#2e8b57');
    exit;
}

logMsg("📦 Nova versão detectada: <b>{$versaoRemota}</b> (Atual: {$versaoLocal})", $logFile, '#007bff');
baixarVersao($repoOwner, $repoName, $versaoRemota, $zipFile, $githubToken);

// ===========================
// 🧩 EXTRAÇÃO DO CÓDIGO
// ===========================
$zip = new ZipArchive;
if ($zip->open($zipFile) === TRUE) {
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $file = $zip->getNameIndex($i);

        // 🔹 Ignorar pastas e arquivos locais
        $skip = [
            'uploads/', 'storage/', 'config.php', 'versao.txt',
            '.env', '.git', 'logs/', '.gitignore'
        ];

        $ignorar = false;
        foreach ($skip as $ignore) {
            if (str_starts_with($file, $ignore) || str_contains($file, $ignore)) {
                $ignorar = true;
                break;
            }
        }

        if (!$ignorar) {
            $zip->extractTo(__DIR__, [$file]);
        }
    }
    $zip->close();
    unlink($zipFile);

    file_put_contents($versaoLocalFile, $versaoRemota);
    logMsg("✅ Atualização concluída com sucesso para <b>{$versaoRemota}</b>", $logFile, '#2e8b57');
} else {
    logMsg("❌ Falha ao extrair o pacote ZIP.", $logFile, '#d33');
}

echo "<hr><p style='font-family:sans-serif;color:#777;font-size:13px'>Log salvo em <b>{$logFile}</b></p>";
?>