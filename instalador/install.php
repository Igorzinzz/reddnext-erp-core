<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = $_POST['host'];
    $user = $_POST['user'];
    $pass = $_POST['pass'];
    $name = $_POST['name'];

    try {
        $pdo = new PDO("mysql:host=$host", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$name`");

        // Executa script inicial
        $sql = file_get_contents(__DIR__ . '/../updates/1.0.0.sql');
        $pdo->exec($sql);

        // Salva config
        $config = "<?php\nreturn [\n".
            "    'app_name' => 'ERP Reddnext',\n".
            "    'versao' => '1.0.0',\n".
            "    'ambiente' => 'producao',\n".
            "    'timezone' => 'America/Sao_Paulo',\n".
            "    'db' => [\n".
            "        'host' => '$host',\n".
            "        'user' => '$user',\n".
            "        'pass' => '$pass',\n".
            "        'name' => '$name',\n".
            "    ]\n];";
        file_put_contents(__DIR__ . '/../core/config.php', $config);

        echo "<h2>✅ Instalação concluída!</h2>";
        echo "<a href='../public/'>Acessar o sistema</a>";
        exit;

    } catch (PDOException $e) {
        die("<strong>Erro:</strong> " . $e->getMessage());
    }
}
?>

<form method="POST">
    <h2>Instalar ERP Reddnext</h2>
    <label>Host: <input name="host" value="localhost"></label><br>
    <label>Usuário: <input name="user" value="root"></label><br>
    <label>Senha: <input name="pass"></label><br>
    <label>Banco: <input name="name"></label><br>
    <button type="submit">Instalar</button>
</form>