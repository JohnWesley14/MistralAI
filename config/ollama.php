<?php
function analisarTextoComOllama($textoExtraido) {
    $inicio = microtime(true);

    $instrucao = "Analise o texto do documento abaixo. " .
                 "Retorne os dados extraídos EXCLUSIVAMENTE em um objeto JSON válido, contendo as seguintes chaves:\n" .
                 "\"titulo\": \"(Escreva aqui o título ou assunto)\",\n" .
                 "\"conteudo\": \"(Escreva aqui o resumo de até 3 linhas)\",\n" .
                 "\"data_expiracao\": \"(Retorne APENAS a data no formato AAAA-MM-DD. Se não houver data, retorne null)\"\n\n" .
                 "--- TEXTO DO DOCUMENTO ---\n" .
                 $textoExtraido;

    $payload = json_encode([
        'model'   => 'gemma2:latest ',
        'format'  => 'json',
        'prompt'  => $instrucao,
        'stream'  => false,
        'options' => [
            'temperature' => 0.0,
            'num_ctx'     => 1024,
            'num_predict' => 300
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
            'timeout'       => 600 
        ]
    ];

    $context = stream_context_create($options);
    $respostaJson = @file_get_contents('http://127.0.0.1:11434/api/generate', false, $context);

    // Se o Ollama não responder nada ou cair por falta de RAM
    if ($respostaJson === false || empty($respostaJson)) {
        throw new Exception("A IA demorou demais para responder ou o processo foi encerrado por falta de memória RAM.");
    }
    $dados = json_decode($respostaJson, true);
    
    if (!isset($dados['response'])) {
        throw new Exception("Resposta inválida ou vazia do Ollama.");
    }

    // Converte a string JSON que o Ollama gerou
    $dadosExtraidos = json_decode($dados['response'], true);

    // Trava de segurança se o modelo gerar texto corrompido
    if (!is_array($dadosExtraidos)) {
        throw new Exception("A IA gerou uma resposta fora do formato JSON esperado.");
    }

    $fim = microtime(true);
    $tempoIa = round($fim - $inicio, 2);

    // Retorna a estrutura exata que o processar_item.php exige
    return [
        'dados'    => $dadosExtraidos,
        'tempo_ia' => $tempoIa
    ];
}