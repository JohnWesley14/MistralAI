<?php
require 'vendor/autoload.php';

use Smalot\PdfParser\Parser;

set_time_limit(0); // Sem limite de tempo, pois PDFs grandes demoram

// 1. Verifica se o PDF chegou certinho
if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    die("Erro no upload do arquivo PDF.");
}

$caminhoTemp = $_FILES['pdf']['tmp_name'];

try {
    // 2. Extrai o texto do PDF nativamente
    $parser = new Parser();
    $pdf = $parser->parseFile($caminhoTemp);
    $textoExtraido = $pdf->getText();

    // Limpa excesso de quebras de linha e espaços para economizar tokens e ajudar a IA
    $textoExtraido = preg_replace('/\s+/', ' ', $textoExtraido);
    $textoExtraido = trim($textoExtraido);

    // Se o PDF for um amontoado de imagens escaneadas (sem texto nativo), a biblioteca não consegue ler
    if (empty($textoExtraido)) {
        die("<strong>Aviso:</strong> O texto deste PDF está vazio. Se este for um documento digitalizado (escaneado), você precisará de uma solução OCR para extrair as imagens primeiro.");
    }

   $textoExtraido = mb_convert_encoding($textoExtraido, 'UTF-8', 'UTF-8');
    // 3. Instrução focada em JSON
    $instrucao = "Analise o texto do documento abaixo. " .
                 "Retorne os dados extraídos EXCLUSIVAMENTE em um objeto JSON válido, contendo as seguintes chaves:\n" .
                 "\"titulo\": \"(Escreva aqui o título ou assunto)\",\n" .
                 "\"conteudo\": \"(Escreva aqui o resumo de até 3 linhas)\",\n" .
                 "\"data_expiracao\": \"(Escreva aqui a data de validade/expiração, ou 'Não consta')\"\n\n" .
                 "--- TEXTO DO DOCUMENTO ---\n" .
                 $textoExtraido;

    // 4. Prepara o pacote ativando o MODO JSON e Temperatura 0
    $payload = json_encode([
        'model'   => 'mistral-nemo', // ou gemma2, qwen2.5, etc.
        'format'  => 'json',     // <--- A MÁGICA ACONTECE AQUI! Bloqueia qualquer conversa fiada.
        'prompt'  => $instrucao,
        'stream'  => false,
        'options' => [
            'temperature' => 0.0 // Tira a "criatividade" da IA, tornando-a estrita e direta
        ]
    ]);

    $options = [
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\n" .
                               "Content-Length: " . strlen($payload) . "\r\n",
            'content'       => $payload,
            'ignore_errors' => true,
            'timeout'       => 300
        ]
    ];

    $context = stream_context_create($options);
    $respostaJson = @file_get_contents('http://127.0.0.1:11434/api/generate', false, $context);

    if ($respostaJson === false) {
        $erro = error_get_last();
        die("FALHA DE COMUNICAÇÃO COM O OLLAMA: " . ($erro['message'] ?? 'Erro desconhecido.'));
    }

    $dados = json_decode($respostaJson, true);

    // 5. Lendo a resposta JSON e exibindo perfeitamente limpo no frontend
    if (isset($dados['response'])) {
        // O Ollama devolveu um JSON (String). Nós convertemos isso num Array do PHP.
        $resultadoIA = json_decode($dados['response'], true);

        // Verificamos se deu tudo certo na conversão
        if (is_array($resultadoIA)) {
            echo "<div style='line-height: 1.6;'>";
            echo "<strong>TÍTULO:</strong> " . htmlspecialchars($resultadoIA['titulo'] ?? 'Não encontrado') . "<br><br>";
            echo "<strong>CONTEÚDO:</strong> " . htmlspecialchars($resultadoIA['conteudo'] ?? 'Não encontrado') . "<br><br>";
            echo "<strong>DATA DE EXPIRAÇÃO:</strong> " . htmlspecialchars($resultadoIA['data_expiracao'] ?? 'Não consta');
            echo "</div>";
        } else {
            // Se, por milagre, a IA quebrar o JSON
            echo "Erro ao interpretar o JSON retornado pela IA. Resposta bruta:<br>" . htmlspecialchars($dados['response']);
        }

    } else {
        echo "<strong>O OLLAMA RETORNOU UM ERRO:</strong><br>" . htmlspecialchars($respostaJson);
    }

} catch (Exception $e) {
    die("Erro no processamento do PDF: " . $e->getMessage());
}