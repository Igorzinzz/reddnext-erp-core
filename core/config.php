<?php
// Detecta automaticamente o domínio e caminho do ERP
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];

// Detecta até onde vai o caminho (sem duplicar "modulos/")
$uri = $_SERVER['REQUEST_URI'];
$basePath = (str_contains($uri, '/modulos/')) ? '/modulos/' : '/';

return [
    'app_name' => 'ERP Reddnext',
    'versao'   => '1.0.0',
    'ambiente' => 'producao',
    'timezone' => 'America/Sao_Paulo',

    // base_url detectado dinamicamente
    'base_url' => $protocol . $host . $basePath,

    'db' => [
        'host' => 'localhost',
        'user' => 'u398439143_cliente1teste',
        'pass' => 'Higuor54@',
        'name' => 'u398439143_cliente1teste',
    ]
];