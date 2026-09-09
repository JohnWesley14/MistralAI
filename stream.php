<?php
// Desativa qualquer buffer de saída do PHP
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
ob_implicit_flush(1);

// Limpa todos os buffers abertos silenciosamente
while (ob_get_level() > 0) {
    ob_end_clean();
}

// octet-stream força o Apache a não agrupar os pacotes como faria com textos
header('Content-Type: application/octet-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

$data = json_decode(file_get_contents('php://input'), true);
$prompt = $data['prompt'] ?? '';

if (empty($prompt)) {
    exit;
}

// Prepara a requisição HTTP nativa (sem cURL)
$options = [
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\n",
        'content' => json_encode([
            'model'  => 'mistral',
            'prompt' => $prompt,
            'stream' => true
        ]),
        'timeout' => 300
    ]
];

$context = stream_context_create($options);
$stream  = @fopen('http://localhost:11434/api/generate', 'r', false, $context);

if (!$stream) {
    echo "Erro: Não foi possível conectar ao Ollama. Verifique se ele está rodando na porta 11434.";
    exit;
}

// Lê e envia linha por linha em tempo real
while (!feof($stream)) {
    $line = fgets($stream);
    if ($line !== false) {
        $json = json_decode($line, true);
        if (isset($json['response'])) {
            echo $json['response'];
            
            if (ob_get_level() > 0) ob_flush();
            flush();
        }
    }
}

fclose($stream);