<?php
// Exibe erros para facilitar debug (remova em produção se desejar)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// ==========================================
// CONFIGURAÇÕES DO SUPABASE
// ==========================================
$SUPABASE_URL = "https://mvxvctiofkjjwwkgbdwa.supabase.co"; 
$SUPABASE_API_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im12eHZjdGlvZmtqand3a2diZHdhIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc3ODQ2MDI1OCwiZXhwIjoyMDk0MDM2MjU4fQ.imqwg3Ilvc7JPrA2m_z_8IeUWDDLGqwzPqOBZeYCQ84";
// ==========================================

// Endpoint para buscar todas as imagens ordenadas por data descrescente
$dbEndpoint = rtrim($SUPABASE_URL, '/') . "/rest/v1/images?select=*&order=created_at.desc";

if (function_exists('curl_init')) {
    // Hospedagem (Tem cURL nativo)
    $chDb = curl_init();
    curl_setopt($chDb, CURLOPT_URL, $dbEndpoint);
    curl_setopt($chDb, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chDb, CURLOPT_CUSTOMREQUEST, "GET");

    $dbHeaders = [
        "Authorization: Bearer {$SUPABASE_API_KEY}",
        "apikey: {$SUPABASE_API_KEY}",
        "Content-Type: application/json"
    ];

    curl_setopt($chDb, CURLOPT_HTTPHEADER, $dbHeaders);
    // curl_setopt($chDb, CURLOPT_SSL_VERIFYPEER, false);
    $dbResponse = curl_exec($chDb);
    $dbHttpCode = curl_getinfo($chDb, CURLINFO_HTTP_CODE);
    $curlError = curl_error($chDb);
    curl_close($chDb);
} else {
    // Local (Não tem cURL, usar curl.exe do Windows)
    $cmd = sprintf(
        'curl.exe -s -w "\n%%{http_code}" -X GET "%s" -H "Authorization: Bearer %s" -H "apikey: %s" -H "Content-Type: application/json" -k',
        $dbEndpoint,
        $SUPABASE_API_KEY,
        $SUPABASE_API_KEY
    );
    
    $output = shell_exec($cmd);
    if ($output === null) {
        echo json_encode(['status' => 'error', 'message' => 'Erro: shell_exec falhou.']);
        exit;
    }
    
    $lines = explode("\n", trim($output));
    $httpCodeStr = array_pop($lines);
    $dbHttpCode = (int)$httpCodeStr;
    $dbResponse = implode("\n", $lines);
    $curlError = null;
}

if ($curlError) {
    echo json_encode(['status' => 'error', 'message' => 'Erro cURL: ' . $curlError]);
    exit;
}

if ($dbHttpCode >= 200 && $dbHttpCode < 300) {
    // Retorna os dados como array JSON direto
    echo $dbResponse;
} else {
    echo json_encode(['status' => 'error', 'message' => 'Erro ao buscar dados do Supabase. HTTP Code: ' . $dbHttpCode, 'details' => json_decode($dbResponse)]);
}
?>
