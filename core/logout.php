<?php
session_start();

// Se houver sessão ativa, limpa e destrói
if (isset($_SESSION)) {
    $_SESSION = [];
    session_destroy();
}

// Detecta protocolo (http ou https)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";

// Detecta domínio atual
$host = $_SERVER['HTTP_HOST'];

// Redireciona sempre para o login na raiz do domínio
header("Location: " . $protocol . $host . "/login.php");
exit;