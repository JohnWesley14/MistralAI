<?php

ob_start();
error_reporting(0);

// Remove o limite de tempo de execução do PHP para esta requisição
set_time_limit(600); 
ini_set('max_execution_time', '600');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/ollama.php';

// ... resto do código

$id = $_GET['id'] ?? null;
if (!$id) {
    ob_clean();
    echo json_encode(['sucesso' => false, 'erro' => 'ID não informado.']);
    exit;
}

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare("SELECT * FROM fila_pdf WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception("Item não encontrado.");
    }

    $pdo->prepare("UPDATE fila_pdf SET status = 'processando' WHERE id = ?")->execute([$id]);

   $caminhoPdfToText = 'C:\poppler\bin\pdftotext.exe';
    $textoExtraido = '';

    // Verifica se o Poppler existe
    if (file_exists($caminhoPdfToText)) {
        $pdfSeguro = escapeshellarg($item['caminho_temp']);
        $saida = shell_exec("$caminhoPdfToText -enc UTF-8 $pdfSeguro -");
        $textoExtraido = $saida ?? ''; 
    } else {
        // PLANO B: Usa a biblioteca nativa do PHP se o Poppler não for encontrado
        if (class_exists('\Smalot\PdfParser\Parser')) {
            $parser = new \Smalot\PdfParser\Parser();
            $pdfParsed = $parser->parseFile($item['caminho_temp']);
            $textoExtraido = $pdfParsed->getText();
        } else {
            throw new Exception("Poppler não encontrado e biblioteca Smalot/PdfParser não está instalada.");
        }
    }

    // Se o texto continuar vazio, avisa de forma elegante
    if (trim($textoExtraido) === '') {
        throw new Exception("PDF vazio ou é apenas uma imagem escaneada.");
    }

    $textoExtraido = trim(mb_substr(preg_replace('/\s+/', ' ', $textoExtraido), 0, 10000, 'UTF-8'));

   // Chama o Ollama
    $retorno = analisarTextoComOllama($textoExtraido);
    
    // Trava de segurança: garante que $resultadoIA seja um array, mesmo se a IA falhar
    $resultadoIA = is_array($retorno['dados']) ? $retorno['dados'] : [];

    // Se o array estiver vazio, a IA gerou um JSON inválido
    if (empty($resultadoIA)) {
        throw new Exception("A IA de teste (1.5b) falhou ao gerar o formato correto. Tente enviar novamente.");
    }

    // Salva no Banco de Dados Final
    $hashArquivo = md5_file($item['caminho_temp']);
    if (!relatorioExiste($hashArquivo)) {
        salvarRelatorio(
            $resultadoIA['titulo'] ?? $item['nome_arquivo'],
            $resultadoIA['conteudo'] ?? 'Sem conteúdo',
            $resultadoIA['data_expiracao'] ?? null,
            $hashArquivo
        );
    }

    // Atualiza a Fila e Limpa
    $pdo->prepare("UPDATE fila_pdf SET status = 'concluido' WHERE id = ?")->execute([$id]);
    @unlink($item['caminho_temp']);

    ob_clean();
    echo json_encode([
        'sucesso' => true,
        'dados' => $resultadoIA,
        'tempo_ia' => $retorno['tempo_ia']
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $id) {
        $pdo->prepare("UPDATE fila_pdf SET status = 'erro', mensagem_erro = ? WHERE id = ?")
            ->execute([$e->getMessage(), $id]);
    }
    
    ob_clean();
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
exit;