CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    nivel ENUM('admin','gerente','caixa') DEFAULT 'caixa',
    ativo TINYINT(1) DEFAULT 1,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO usuarios (nome, email, senha, nivel)
VALUES ('Administrador', 'admin@local', SHA2('admin123', 256), 'admin');