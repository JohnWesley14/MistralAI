<?php
// Remove o limite de tempo do PHP (30s) para requisições longas
set_time_limit(0);
ini_set('max_execution_time', 0);

// Desativa o buffer do PHP
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);

while (ob_get_level() > 0) {
    ob_end_clean();
}

// Restante do cabeçalho SSE...
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// ... (mantenha o restante do código com o fopen)

$data = json_decode(file_get_contents('php://input'), true);
$prompt = $data['prompt'] ?? '';

if (empty($prompt)) {
    exit;
}

$options = [
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\n",
        'content' => json_encode([
            'model'  => 'mistral-nemo',
            'prompt' => $prompt,
            'system' => "Responda somente em português ou inglês, seja conciso e direto",
            'stream' => true
        ]),
        'timeout' => 300
    ]
];

$context = stream_context_create($options);
$stream  = @fopen('http://localhost:11434/api/generate', 'r', false, $context);

if (!$stream) {
    echo "data: Erro ao conectar ao Ollama.\n\n";
    flush();
    exit;
}

while (!feof($stream)) {
    $line = fgets($stream);
    if ($line !== false) {
        $json = json_decode($line, true);
        if (isset($json['response'])) {
            // Formato oficial do protocolo SSE
            echo "data: " . json_encode($json['response']) . "\n\n";
            flush();
        }
    }
}

fclose($stream);
