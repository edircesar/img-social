<?php
// Exibe erros para facilitar debug (remova em produção se desejar)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// ==========================================
// CONFIGURAÇÕES DO SUPABASE - PREENCHA AQUI!
// ==========================================
$SUPABASE_URL = "https://mvxvctiofkjjwwkgbdwa.supabase.co"; // URL extraída da anon key
$SUPABASE_API_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im12eHZjdGlvZmtqand3a2diZHdhIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc3ODQ2MDI1OCwiZXhwIjoyMDk0MDM2MjU4fQ.imqwg3Ilvc7JPrA2m_z_8IeUWDDLGqwzPqOBZeYCQ84"; // Service Role Key
$BUCKET_NAME = "instagram-posts";
// ==========================================

// Função para retornar JSON de erro e encerrar
function returnError($message) {
    echo json_encode([
        'status' => 'error',
        'message' => $message
    ]);
    exit;
}

// Verifica se foi enviado via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    returnError("Método não permitido.");
}

// Verifica se o arquivo foi enviado e não teve erros de upload do PHP
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    returnError("Nenhum arquivo enviado ou erro no upload do servidor.");
}

$file = $_FILES['image'];

// Validações de Segurança
$maxSize = 5 * 1024 * 1024; // 5MB
if ($file['size'] > $maxSize) {
    returnError("O arquivo excede o limite de 5MB.");
}

// Validar MIME Type
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
} elseif (function_exists('mime_content_type')) {
    $mimeType = mime_content_type($file['tmp_name']);
} else {
    // Fallback básico caso as extensões fileinfo não estejam habilitadas
    $mimeType = $file['type'];
}

$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
if (!in_array($mimeType, $allowedTypes)) {
    returnError("Formato de arquivo não permitido. Apenas JPG, PNG ou WEBP.");
}

// Gerar nome único para evitar sobrescrita
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
if (empty($extension)) {
    // Se não tiver extensão no nome, tenta inferir pelo mime
    $mimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];
    $extension = $mimeToExt[$mimeType] ?? 'jpg';
}

$fileName = uniqid(time() . '_') . '.' . strtolower($extension);

// Prepara para enviar ao Supabase via cURL (Storage API REST) - Padrão para Hospedagem
$endpoint = rtrim($SUPABASE_URL, '/') . "/storage/v1/object/{$BUCKET_NAME}/{$fileName}";

if (function_exists('curl_init')) {
    $fileContent = file_get_contents($file['tmp_name']);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);
    $headers = [
        "Authorization: Bearer {$SUPABASE_API_KEY}",
        "apikey: {$SUPABASE_API_KEY}",
        "Content-Type: {$mimeType}"
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
} else {
    // Fallback local via shell
    $filePathStr = escapeshellarg(str_replace('\\', '/', $file['tmp_name']));
    $cmd = sprintf(
        'curl.exe -s -w "\n%%{http_code}" -X POST "%s" -H "Authorization: Bearer %s" -H "apikey: %s" -H "Content-Type: %s" --data-binary "@%s" -k',
        $endpoint,
        $SUPABASE_API_KEY,
        $SUPABASE_API_KEY,
        $mimeType,
        str_replace("'", "", $filePathStr)
    );
    $output = shell_exec($cmd);
    if ($output === null) returnError("Erro: shell_exec falhou.");
    $lines = explode("\n", trim($output));
    $httpCode = (int)array_pop($lines);
    $response = implode("\n", $lines);
    $curlError = null;
}

if ($curlError) {
    returnError("Erro de conexão com Supabase (cURL): " . $curlError);
}

// Analisa a resposta
$responseData = json_decode($response, true);

if ($httpCode >= 200 && $httpCode < 300) {
    // Sucesso no upload. Monta a URL pública.
    $publicUrl = rtrim($SUPABASE_URL, '/') . "/storage/v1/object/public/{$BUCKET_NAME}/{$fileName}";
    
    // ==========================================
    // SALVAR METADADOS NO BANCO DE DADOS (Tabela 'images')
    // ==========================================
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $dbEndpoint = rtrim($SUPABASE_URL, '/') . "/rest/v1/images";
    $dbData = json_encode(['url' => $publicUrl, 'description' => $description]);
    
    if (function_exists('curl_init')) {
        $chDb = curl_init();
        curl_setopt($chDb, CURLOPT_URL, $dbEndpoint);
        curl_setopt($chDb, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chDb, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($chDb, CURLOPT_POSTFIELDS, $dbData);
        $dbHeaders = [
            "Authorization: Bearer {$SUPABASE_API_KEY}",
            "apikey: {$SUPABASE_API_KEY}",
            "Content-Type: application/json",
            "Prefer: return=representation"
        ];
        curl_setopt($chDb, CURLOPT_HTTPHEADER, $dbHeaders);
        $dbResponse = curl_exec($chDb);
        $dbHttpCode = curl_getinfo($chDb, CURLINFO_HTTP_CODE);
        curl_close($chDb);
    } else {
        // Usa um arquivo temporário para evitar problemas de escape de aspas no JSON no Windows CMD
        $tmpJson = tempnam(sys_get_temp_dir(), 'supa_');
        file_put_contents($tmpJson, $dbData);
        
        $cmdDb = sprintf(
            'curl.exe -s -w "\n%%{http_code}" -X POST "%s" -H "Authorization: Bearer %s" -H "apikey: %s" -H "Content-Type: application/json" -H "Prefer: return=representation" --data-binary "@%s" -k',
            $dbEndpoint,
            $SUPABASE_API_KEY,
            $SUPABASE_API_KEY,
            str_replace('\\', '/', $tmpJson)
        );
        $outputDb = shell_exec($cmdDb);
        @unlink($tmpJson);
        
        if ($outputDb === null) {
            $dbHttpCode = 500;
        } else {
            $linesDb = explode("\n", trim($outputDb));
            $dbHttpCode = (int)array_pop($linesDb);
        }
    }
    
    if ($dbHttpCode < 200 || $dbHttpCode >= 300) {
        // Se falhar o banco, ainda retornamos a URL mas avisamos do erro no DB.
        $dbErrorMsg = "Aviso: Imagem salva no Storage, mas falhou ao salvar no BD.";
    } else {
        $dbErrorMsg = null;
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Upload e registro realizados com sucesso!',
        'url' => $publicUrl,
        'fileName' => $fileName,
        'db_warning' => $dbErrorMsg
    ]);
} else {
    // Erro do Supabase
    $errorMsg = isset($responseData['message']) ? $responseData['message'] : "Erro desconhecido ao enviar para o Supabase.";
    returnError("Erro Supabase ($httpCode): " . $errorMsg);
}
?>
