<?php
session_start();

if (isset($_SESSION['usuario'])) {
    header("Location: modulos/dashboard/");
    exit;
}

header("Location: login.php");
exit;