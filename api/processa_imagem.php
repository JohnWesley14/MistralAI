<?php
require 'vendor/autoload.php';
use thiagoalessio\TesseractOCR\TesseractOCR;

set_time_limit(0);

if (!isset($_FILES['imagem']) || $_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
    die("Erro no upload da imagem.");
}

// 1. Salvar a imagem temporariamente na pasta do PHP
$caminhoTemp = 'temp_' . time() . '.jpg';
move_uploaded_file($_FILES['imagem']['tmp_name'], $caminhoTemp);

try {
    // 2. Usar o Tesseract OCR para LER o que está escrito na imagem
    $ocr = new TesseractOCR($caminhoTemp);
    
    // Indica onde o Tesseract foi instalado no seu Windows
    $ocr->executable('C:\Users\E090485\AppData\Local\Tesseract-OCR\tesseract.exe');
    // Pede para ele ler em Português
    $ocr->lang('por'); 
    
    $textoExtraido = $ocr->run();

    // Apagar a imagem temporária do disco após ler
    if (file_exists($caminhoTemp)) {
        unlink($caminhoTemp);
    }

    if (empty(trim($textoExtraido))) {
        die("O Leitor OCR não conseguiu identificar nenhuma palavra nesta imagem.");
    }
// --- SUBSTITUA DAQUI PARA BAIXO ---

    // LIMPEZA: Remove caracteres inválidos que o OCR possa ter gerado acidentalmente
    $textoExtraido = mb_convert_encoding($textoExtraido, 'UTF-8', 'UTF-8');

    // 3. Montar a instrução para o Mistral-Nemo
    $instrucao = "Você é um assistente de análise de documentos.\n" .
                 "Baseado no texto extraído do OCR abaixo, extraia:\n" .
                 "1. Título/Assunto principal.\n" .
                 "2. Resumo (máx 2 linhas).\n" .
                 "3. Data de validade/expiração (ou 'Não consta').\n\n" .
                 "--- INÍCIO DO TEXTO ---\n" .
                 $textoExtraido . "\n" .
                 "--- FIM DO TEXTO ---";

    // 4. Converter para JSON de forma segura
    $payload = json_encode([
        'model'  => 'mistral-nemo:latest',
        'prompt' => $instrucao,
        'stream' => false
    ]);

    // Trava de segurança: Se o texto tiver sujeira que quebre o JSON
    if ($payload === false) {
        die("Erro ao gerar pacote de dados: " . json_last_error_msg() . "<br><br>Texto lido pelo OCR:<br>" . htmlspecialchars($textoExtraido));
    }

    $options = [
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\n" .
                               "Content-Length: " . strlen($payload) . "\r\n",
            'content'       => $payload,
            'ignore_errors' => true,
            'timeout'       => 300 // Aumentamos o tempo limite para 5 minutos
        ]
    ];

    $context = stream_context_create($options);
    
    // O '@' suprime o erro genérico do PHP para pegarmos o erro exato na linha abaixo
    $respostaJson = @file_get_contents('http://127.0.0.1:11434/api/generate', false, $context);

    if ($respostaJson === false) {
        $erro = error_get_last();
        die("FALHA DE COMUNICAÇÃO COM O OLLAMA: " . ($erro['message'] ?? 'Erro desconhecido.'));
    }

    $dados = json_decode($respostaJson, true);

    echo "<div style='background:#fffbcc; padding:15px; border-left:4px solid #f1c40f; margin-bottom:20px; font-size:13px;'>";
    echo "<strong>🔍 O que o Leitor OCR conseguiu enxergar na imagem:</strong><br>";
    echo nl2br(htmlspecialchars(substr($textoExtraido, 0, 300))) . "..."; // Mostra os 300 primeiros caracteres
    echo "</div>";

    if (isset($dados['response'])) {
        echo nl2br(htmlspecialchars($dados['response']));
    } else {
        echo "<strong>O OLLAMA RETORNOU UM ERRO:</strong><br>" . htmlspecialchars($respostaJson);
    }

} catch (Exception $e) {
    if (isset($caminhoTemp) && file_exists($caminhoTemp)) {
        unlink($caminhoTemp);
    }
    die("Erro no processamento do OCR: " . $e->getMessage());
}