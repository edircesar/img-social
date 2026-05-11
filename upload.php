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
$SUPABASE_API_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im12eHZjdGlvZmtqand3a2diZHdhIiwicm9sZSI6ImFub24iLCJpYXQiOjE3Nzg0NjAyNTgsImV4cCI6MjA5NDAzNjI1OH0.CzOQMFgS3-PFo9TiuS1syoVULbUSJ_OV-G-zkkAD6XI"; // Anon Key
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
$fileContent = file_get_contents($file['tmp_name']);

$endpoint = rtrim($SUPABASE_URL, '/') . "/storage/v1/object/{$BUCKET_NAME}/{$fileName}";

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
// Se tiver problemas com SSL na hospedagem (raro), descomente a linha abaixo
// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

curl_close($ch);

if ($curlError) {
    returnError("Erro de conexão com Supabase (cURL): " . $curlError);
}

// Analisa a resposta
$responseData = json_decode($response, true);

if ($httpCode >= 200 && $httpCode < 300) {
    // Sucesso no upload. Monta a URL pública.
    $publicUrl = rtrim($SUPABASE_URL, '/') . "/storage/v1/object/public/{$BUCKET_NAME}/{$fileName}";
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Upload realizado com sucesso!',
        'url' => $publicUrl,
        'fileName' => $fileName
    ]);
} else {
    // Erro do Supabase
    $errorMsg = isset($responseData['message']) ? $responseData['message'] : "Erro desconhecido ao enviar para o Supabase.";
    returnError("Erro Supabase ($httpCode): " . $errorMsg);
}
?>
