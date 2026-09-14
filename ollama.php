<?php
function analisarTextoComOllama($textoExtraido) {
    $instrucao = "Analise o texto do documento abaixo. " .
                 "Retorne os dados extraídos EXCLUSIVAMENTE em um objeto JSON válido, contendo as seguintes chaves:\n" .
                 "\"titulo\": \"(Escreva aqui o título ou assunto)\",\n" .
                 "\"conteudo\": \"(Escreva aqui o resumo de até 3 linhas)\",\n" .
                 "\"data_expiracao\": \"(Retorne APENAS a data no formato AAAA-MM-DD. Se não houver data, retorne null)\"\n\n" .
                 "--- TEXTO DO DOCUMENTO ---\n" .
                 $textoExtraido;

    $payload = json_encode([
        'model'   => 'mistral-nemo:latest',
        'format'  => 'json',
        'prompt'  => $instrucao,
        'stream'  => false,
        'options' => [
            'temperature' => 0.0,
            'num_ctx'     => 4096,
            'num_predict' => 250
        ]
    ]);

    if ($payload === false) {
        throw new Exception("Erro ao gerar pacote de dados JSON: " . json_last_error_msg());
    }

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
        throw new Exception("Falha de comunicação com o Ollama: " . ($erro['message'] ?? 'Erro desconhecido.'));
    }

    $dados = json_decode($respostaJson, true);
    
    if (!isset($dados['response'])) {
        throw new Exception("Resposta inválida do Ollama.");
    }

    return json_decode($dados['response'], true);
}