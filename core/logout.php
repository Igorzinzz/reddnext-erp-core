<?php
session_start();

// Destrói todas as variáveis da sessão
$_SESSION = [];

// Finaliza a sessão
session_destroy();

// Redireciona de volta para o login
header("Location: ../login.php");
exit;