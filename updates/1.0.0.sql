CREATE TABLE config_versao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    versao VARCHAR(10),
    ultima_atualizacao DATETIME
);

INSERT INTO config_versao (versao, ultima_atualizacao)
VALUES ('1.0.0', NOW());