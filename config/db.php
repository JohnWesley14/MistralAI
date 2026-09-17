<?php
function getDbConnection() {
    $host = '127.0.0.1';
    $db   = 'relatorios';
    $user = 'root';
    $pass = '12345678';

    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function relatorioExiste($hashArquivo) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT id FROM relatorios WHERE hash_arquivo = ?");
    $stmt->execute([$hashArquivo]);
    return $stmt->rowCount() > 0;
}

function salvarRelatorio($titulo, $descricao, $dataExpiracao, $hashArquivo) {
    $pdo = getDbConnection();
    
    if (empty($dataExpiracao) || strtolower($dataExpiracao) === 'null' || $dataExpiracao === 'Não consta') {
        $dataExpiracao = null;
    }

    $sql = "INSERT INTO relatorios (create_time, titulo, descricao, data_expiracao, hash_arquivo) 
            VALUES (NOW(), ?, ?, ?, ?)";
            
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$titulo, $descricao, $dataExpiracao, $hashArquivo]);
}