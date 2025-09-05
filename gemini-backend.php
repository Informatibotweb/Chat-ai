<?php
// gemini-proxy.php - Server backend che funziona DAVVERO con Gemini

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Gestisci richieste OPTIONS per CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST method allowed']);
    exit;
}

// Leggi i dati dalla richiesta
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['message']) || !isset($input['apiKey'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing message or apiKey']);
    exit;
}

$message = $input['message'];
$apiKey = $input['apiKey'];

// URL dell'API di Gemini
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . $apiKey;

// Prepara il payload per Gemini
$payload = json_encode([
    'contents' => [
        [
            'parts' => [
                ['text' => $message]
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => 0.7,
        'topK' => 40,
        'topP' => 0.95,
        'maxOutputTokens' => 1024
    ],
    'safetySettings' => [
        [
            'category' => 'HARM_CATEGORY_HARASSMENT',
            'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
        ],
        [
            'category' => 'HARM_CATEGORY_HATE_SPEECH',
            'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
        ],
        [
            'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
            'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
        ],
        [
            'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
            'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
        ]
    ]
]);

// Configura cURL
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'User-Agent: GeminiChatBot/1.0'
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_FOLLOWLOCATION => true,
]);

// Esegui la richiesta
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Gestisci errori cURL
if ($curlError) {
    http_response_code(500);
    echo json_encode(['error' => 'Network error: ' . $curlError]);
    exit;
}

// Gestisci risposte HTTP non OK
if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo json_encode(['error' => 'API error', 'status' => $httpCode, 'response' => $response]);
    exit;
}

// Decodifica la risposta di Gemini
$geminiResponse = json_decode($response, true);

if (!$geminiResponse) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid JSON response from Gemini']);
    exit;
}

// Estrai il testo della risposta
if (isset($geminiResponse['candidates'][0]['content']['parts'][0]['text'])) {
    $responseText = $geminiResponse['candidates'][0]['content']['parts'][0]['text'];
    echo json_encode(['success' => true, 'response' => $responseText]);
} else if (isset($geminiResponse['error'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Gemini API error', 'details' => $geminiResponse['error']]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Unexpected response format', 'response' => $geminiResponse]);
}
?>