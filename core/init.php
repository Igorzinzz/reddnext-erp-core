<?php
require_once __DIR__ . '/autoload.php';
$config = include __DIR__ . '/config.php';

/**
 * Sincroniza o fuso horário entre PHP e a sessão do MySQL
 * usando o timezone salvo em config_sistema (fallback para America/Sao_Paulo).
 *
 * - Define date_default_timezone_set() no PHP
 * - Executa "SET time_zone = '±HH:MM'" na sessão atual do MySQL
 */
try {
    // Se o config.php criou o PDO $conn, sincronizamos também o MySQL
    if (isset($conn) && $conn instanceof PDO) {
        // Tenta buscar timezone do banco
        $tz = 'America/Sao_Paulo';
        $stmt = @$conn->query("SELECT timezone FROM config_sistema LIMIT 1");
        if ($stmt) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!empty($row['timezone'])) {
                $tz = $row['timezone'];
            }
        } elseif (!empty($config['timezone'])) {
            // fallback opcional via config.php
            $tz = $config['timezone'];
        }

        // PHP: aplica fuso
        @date_default_timezone_set($tz);

        // MySQL (sessão): aplica offset equivalente
        $offset = (new DateTime('now', new DateTimeZone($tz)))->format('P'); // e.g. -03:00
        @$conn->exec("SET time_zone = '{$offset}'");
    } else {
        // Sem $conn disponível aqui: ao menos ajuste o PHP
        $tz = !empty($config['timezone']) ? $config['timezone'] : 'America/Sao_Paulo';
        @date_default_timezone_set($tz);
    }
} catch (Throwable $e) {
    // Em qualquer falha, garante um timezone seguro
    @date_default_timezone_set('America/Sao_Paulo');
}