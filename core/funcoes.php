<?php
function base_url($path = '') {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    return "{$protocol}://{$host}{$base}/{$path}";
}

function versaoSistema() {
    $version = include __DIR__ . '/version.php';
    return $version['versao'];
}