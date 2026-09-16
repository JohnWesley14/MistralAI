-- Active: 1784565033500@@127.0.0.1@3306@relatorios
CREATE DATABASE relatorios   DEFAULT CHARACTER SET = 'utf8mb4';;

CREATE TABLE relatorios(  
    id int not null PRIMARY KEY,
    create_time DATETIME,
    titulo VARCHAR(255),
    descricao VARCHAR(255),
    data_expiracao DATETIME
) ;

ALTER TABLE relatorios MODIFY id INT NOT NULL AUTO_INCREMENT;
ALTER TABLE relatorios MODIFY descricao TEXT;
ALTER TABLE relatorios ADD hash_arquivo VARCHAR(64) UNIQUE;

CREATE TABLE fila_pdf (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_arquivo VARCHAR(255) NOT NULL,
    caminho_temp VARCHAR(255) NOT NULL,
    status ENUM('pendente', 'processando', 'concluido', 'erro') DEFAULT 'pendente',
    mensagem_erro TEXT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
);

SELECT * FROM relatorios;

