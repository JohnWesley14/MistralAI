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

SELECT * FROM relatorios;