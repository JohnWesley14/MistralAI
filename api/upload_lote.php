<?php
ob_start();
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

// Define a raiz exata do projeto (uma pasta acima da pasta api/)
$raizProjeto = dirname(__DIR__);

// 1. Verifica e carrega o arquivo de banco de dados
$caminhoDb = $raizProjeto . '/config/db.php';
if (!file_exists($caminhoDb)) {
    ob_clean();
    echo json_encode(['sucesso' => false, 'erro' => 'Arquivo config/db.php não foi encontrado em: ' . $caminhoDb]);
    exit;
}
require_once $caminhoDb;

// 2. Verifica envio de arquivos
if (!isset($_FILES['pdfs'])) {
    ob_clean();
    echo json_encode(['sucesso' => false, 'erro' => 'Nenhum arquivo enviado pelo formulário.']);
    exit;
}

try {
    $pdo = getDbConnection();
    $idsInseridos = [];

    // Caminho absoluto para a pasta uploads na raiz
    $pastaDestino = $raizProjeto . '/uploads/';

    // Cria a pasta uploads na raiz se ela não existir
    if (!is_dir($pastaDestino)) {
        if (!mkdir($pastaDestino, 0777, true)) {
            throw new Exception("Falha ao criar a pasta de uploads em: " . $pastaDestino);
        }
    }

    foreach ($_FILES['pdfs']['tmp_name'] as $index => $tmpName) {
        $erroUpload = $_FILES['pdfs']['error'][$index];
        if ($erroUpload !== UPLOAD_ERR_OK) {
            throw new Exception("Erro no upload do arquivo temporário (Código de erro do PHP: $erroUpload).");
        }

        $nomeOriginal = $_FILES['pdfs']['name'][$index];
        $nomeLimpo = preg_replace('/[^a-zA-Z0-9.\-_]/', '', $nomeOriginal);
        $caminhoFinal = $pastaDestino . uniqid() . '_' . $nomeLimpo;

        // Move o arquivo da pasta temporária do PHP para a /uploads do projeto
        if (!move_uploaded_file($tmpName, $caminhoFinal)) {
            throw new Exception("Não foi possível salvar o arquivo '$nomeOriginal' na pasta uploads. Verifique as permissões da pasta.");
        }

        // Insere o registro na fila
        $stmt = $pdo->prepare("INSERT INTO fila_pdf (nome_arquivo, caminho_temp) VALUES (?, ?)");
        $stmt->execute([$nomeOriginal, $caminhoFinal]);
        
        $idsInseridos[] = [
            'id' => $pdo->lastInsertId(),
            'nome' => $nomeOriginal
        ];
    }

    if (empty($idsInseridos)) {
        throw new Exception("Nenhum arquivo foi processado com sucesso.");
    }

    ob_clean();
    echo json_encode(['sucesso' => true, 'itens' => $idsInseridos]);

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
exit;