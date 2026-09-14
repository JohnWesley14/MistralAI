<?php
require 'vendor/autoload.php';
require 'db.php';
require 'ollama.php';

use Smalot\PdfParser\Parser;

set_time_limit(0);

if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    die("Erro no upload do arquivo PDF.");
}

$caminhoTemp = $_FILES['pdf']['tmp_name'];

try {
    // 1. Extração do texto do PDF
    $parser = new Parser();
    $pdf = $parser->parseFile($caminhoTemp);
    $textoExtraido = $pdf->getText();

    $textoExtraido = mb_convert_encoding($textoExtraido, 'UTF-8', 'UTF-8');
    $textoExtraido = preg_replace('/\s+/', ' ', $textoExtraido);
    $textoExtraido = trim(mb_substr($textoExtraido, 0, 10000, 'UTF-8'));

    if (empty($textoExtraido)) {
        die("<strong>Aviso:</strong> O texto deste PDF está vazio ou é uma imagem escaneada.");
    }

    // 2. Análise com a IA (via ollama.php)
    $resultadoIA = analisarTextoComOllama($textoExtraido);

    if (!is_array($resultadoIA)) {
        die("Erro ao processar o formato da resposta da IA.");
    }

    // 3. Exibição do Resultado na Tela
    echo "<div style='line-height: 1.6;'>";
    echo "<strong>TÍTULO:</strong> " . htmlspecialchars($resultadoIA['titulo'] ?? 'Não encontrado') . "<br><br>";
    echo "<strong>CONTEÚDO:</strong> " . htmlspecialchars($resultadoIA['conteudo'] ?? 'Não encontrado') . "<br><br>";
    echo "<strong>DATA DE EXPIRAÇÃO:</strong> " . htmlspecialchars($resultadoIA['data_expiracao'] ?? 'Não consta');
    echo "</div>";

    // 4. Verificação e Salvamento no Banco (via db.php)
    $hashArquivo = md5_file($caminhoTemp);

    if (relatorioExiste($hashArquivo)) {
        echo "<div style='background:#fff3cd; padding:15px; border-left:4px solid #ffeeba; margin-top:15px; color:#856404;'>";
        echo "⚠️ <strong>Aviso:</strong> Este PDF já havia sido processado e salvo no banco de dados anteriormente.";
        echo "</div>";
    } else {
        salvarRelatorio(
            $resultadoIA['titulo'] ?? 'Sem Título',
            $resultadoIA['conteudo'] ?? 'Sem Descrição',
            $resultadoIA['data_expiracao'] ?? null,
            $hashArquivo
        );

        echo "<div style='background:#d4edda; padding:15px; border-left:4px solid #28a745; margin-top:15px; color:#155724;'>";
        echo "✅ <strong>Sucesso:</strong> Dados extraídos e salvos no banco com sucesso!";
        echo "</div>";
    }

} catch (Exception $e) {
    die("<div style='color:red;'>Erro no processamento: " . htmlspecialchars($e->getMessage()) . "</div>");
}