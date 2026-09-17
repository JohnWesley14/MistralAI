<?php

require 'vendor/autoload.php';

use LLPhant\Chat\OllamaChat;
use LLPhant\OllamaConfig;

// Previne que o script PHP dê "timeout" antes da IA terminar a resposta longa
set_time_limit(0);

// Cabeçalhos essenciais para permitir o streaming em tempo real no navegador
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // Impede que servidores como Nginx retenham o buffer

$prompt = $_POST['prompt'] ?? '';

if (empty(trim($prompt))) {
    echo "Nenhuma pergunta recebida.";
    exit;
}

$config = new OllamaConfig();
$config->model = 'mistral'; // Modelo baixado no Ollama

$chat = new OllamaChat($config);

// Em vez de 'generateText', usamos 'generateStreamOfText'
$stream = $chat->generateStreamOfText($prompt);

// Verifica o tipo de stream que o LLPhant retornou e lê pedaço por pedaço
if (is_iterable($stream)) {
    foreach ($stream as $chunk) {
        echo $chunk;
        if (ob_get_level() > 0) ob_flush();
        flush(); // Força o envio imediato deste pedacinho para o navegador
    }
} else {
    // Para adaptadores que retornam o padrão PSR-7 StreamInterface
    while (!$stream->eof()) {
        echo $stream->read(1024);
        if (ob_get_level() > 0) ob_flush();
        flush();
    }
}