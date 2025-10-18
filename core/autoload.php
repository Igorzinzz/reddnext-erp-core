<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/funcoes.php';
require_once __DIR__ . '/conexao.php';

if (file_exists(__DIR__ . '/version.php')) {
    $version = include __DIR__ . '/version.php';
    define('VERSAO_SISTEMA', $version['versao']);
}