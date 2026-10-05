<?php
/**
 * api.php
 * Endpoint principal del backend de Calavera IA.
 * Recibe preguntas via POST, aplica moderación, busca contexto RAG,
 * y llama a OpenRouter para obtener la respuesta en verso.
 *
 * Compatible con Hostinger shared hosting (solo PHP + cURL).
 */

// Permitir CORS para el frontend
error_reporting(E_ALL);
ini_set("display_errors", 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight de CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido. Usa POST.']);
    exit;
}

// Importar módulos
require_once __DIR__ . '/rag.php';
require_once __DIR__ . '/moderation.php';

// ─── CONFIGURACIÓN (desde .env) ─────────────────────────────
$API_KEY = null;
$MODELO  = null;

$env_path = __DIR__ . '/../.env';
if (file_exists($env_path)) {
    $lines = explode("\n", file_get_contents($env_path));
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1]);
            if ($key === 'OPENROUTER_API_KEY' || $key === 'API_KEY') {
                $API_KEY = $val;
            }
            if ($key === 'MODELO') {
                $MODELO = $val;
            }
        }
    }
}

// Validar que ambos valores existan
if (empty($API_KEY) || empty($MODELO)) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Configuración incompleta. Falta API_KEY o MODELO en el archivo .env.'
    ]);
    exit;
}

// ─── LEER CUERPO DE LA PETICIÓN ──────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['pregunta'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No se recibió ninguna pregunta.']);
    exit;
}

$pregunta = trim($input['pregunta']);
$modo_verso = isset($input['modo_verso']) ? filter_var($input['modo_verso'], FILTER_VALIDATE_BOOLEAN) : true;

// ─── MODERACIÓN ──────────────────────────────────────────────
$moderacion = moderar_contenido($pregunta);
if (!$moderacion['seguro']) {
    echo json_encode([
        'respuesta' => $moderacion['mensaje'],
        'moderado'  => true
    ]);
    exit;
}

// ─── RAG: BUSCAR CONTEXTO RELEVANTE ─────────────────────────
$contexto_rag = buscar_contexto($pregunta);

// ─── CONSTRUIR PROMPT DEL SISTEMA ───────────────────────────
if ($modo_verso) {
    $prompt_sistema =
        "Eres la Calavera Garbancera (La Catrina). Tu única forma de comunicación es el VERSO RIMADO "
        . "con rima consonante perfecta en español. "
        . "\n\nREGLAS DE OBLIGADO CUMPLIMIENTO:"
        . "\n1. PROHIBIDO hablar en prosa o verso libre. Si no rima, no lo digas."
        . "\n2. ESTRUCTURA: Responde siempre en estrofas de 4 versos (cuartetas)."
        . "\n3. MÉTRICA: Intenta que los versos tengan una longitud similar (octosílabos preferentemente)."
        . "\n4. RIMA: Usa rimas claras (ejemplo: flor/amor, hueso/regreso, altar/cantar)."
        . "\n5. TEMÁTICA: Mezcla la respuesta con el folclore del Día de Muertos y el Mictlán."
        . "\n6. BREVEDAD: Una sola estrofa de 4 versos es suficiente para saludos o dudas simples.";
    $instruccion_contexto = "Usa la información del contexto anterior para enriquecer tu respuesta rimada si es relevante.";
} else {
    $prompt_sistema =
        "Eres la Calavera Garbancera (La Catrina), sabia y elegante. Comunícate en PROSA (texto normal), NO en verso. "
        . "\n\nREGLAS DE OBLIGADO CUMPLIMIENTO:"
        . "\n1. PROHIBIDO hablar en verso. Usa prosa elegante, poética, solemne pero alegre."
        . "\n2. ESTRUCTURA: Responde en párrafos claros y bien estructurados."
        . "\n3. TONO: Educativo, culto y místico."
        . "\n4. TEMÁTICA: Mezcla la respuesta con detalles profundos e históricos del folclore del Día de Muertos y el Mictlán."
        . "\n5. BREVEDAD: Sé concisa y directa, no más de dos o tres párrafos cortos.";
    $instruccion_contexto = "Usa la información del contexto anterior para enriquecer tu respuesta en prosa si es relevante.";
}

// Agregar contexto RAG al prompt del usuario si se encontró algo relevante
$pregunta_con_contexto = $pregunta;
if (!empty($contexto_rag)) {
    $pregunta_con_contexto = $pregunta . $contexto_rag
        . "\n" . $instruccion_contexto;
}

// ─── LLAMAR A OPENROUTER VIA cURL ───────────────────────────
$url = 'https://openrouter.ai/api/v1/chat/completions';

$payload = json_encode([
    'model'    => $MODELO,
    'messages' => [
        ['role' => 'system',  'content' => $prompt_sistema],
        ['role' => 'user',    'content' => $pregunta_con_contexto]
    ],
    'temperature' => 0.8
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $API_KEY,
        'Content-Type: application/json',
        'HTTP-Referer: https://calavera-ia.com',
        'X-Title: Calavera IA Web',
    ],
    // SSL: En Hostinger, el bundle de CA ya está incluido.
    // Si tienes problemas de SSL, descomenta la siguiente línea (NO recomendado en producción):
    // CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// ─── MANEJAR ERRORES DE cURL ────────────────────────────────
if ($response === false) {
    http_response_code(502);
    echo json_encode([
        'error' => 'No se pudo conectar con el servidor de IA.',
        'detalle' => $curl_error
    ]);
    exit;
}

// ─── PARSEAR RESPUESTA ──────────────────────────────────────
$resultado = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(502);
    echo json_encode(['error' => 'Respuesta inválida del servidor de IA.']);
    exit;
}

// Verificar errores de la API
if (isset($resultado['error'])) {
    http_response_code(502);
    echo json_encode([
        'error' => 'Error del modelo de IA.',
        'detalle' => $resultado['error']['message'] ?? 'Error desconocido'
    ]);
    exit;
}

// Extraer respuesta
if (isset($resultado['choices'][0]['message']['content'])) {
    $respuesta_texto = $resultado['choices'][0]['message']['content'];

    echo json_encode([
        'respuesta' => $respuesta_texto,
        'moderado'  => false
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'No se recibió respuesta del Mictlán.']);
}
