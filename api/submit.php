<?php
/**
 * LIENZO URBANO — captura de mails en archivo de texto plano.
 *
 * Guarda cada suscripción como una línea: email,timestamp_ISO8601
 * en /data/suscriptores.txt (fuera del webroot navegable, protegido
 * también por .htaccess como segunda capa).
 *
 * No requiere base de datos: pensado para minimizar cloudlets en Jelastic.
 */

header('Content-Type: application/json; charset=utf-8');

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Método no permitido.']);
    exit;
}

// Leer body JSON
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
$email = isset($payload['email']) ? trim($payload['email']) : '';

// Validar formato de mail
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Ese mail no es válido. Probá de nuevo.']);
    exit;
}

$email = mb_strtolower($email);

// Carpeta de datos: un nivel arriba de /api, fuera de rutas típicas indexadas
$dataDir  = __DIR__ . '/../data';
$dataFile = $dataDir . '/suscriptores.txt';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0750, true);
}

// Evitar duplicados: revisar si el mail ya está guardado
$isDuplicate = false;
if (file_exists($dataFile)) {
    $handle = fopen($dataFile, 'r');
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            $existingEmail = strtolower(trim(explode(',', $line)[0]));
            if ($existingEmail === $email) {
                $isDuplicate = true;
                break;
            }
        }
        fclose($handle);
    }
}

if (!$isDuplicate) {
    $timestamp = date('c'); // ISO 8601, con timezone del servidor
    $line = $email . ',' . $timestamp . PHP_EOL;

    // Escritura con lock para evitar carreras entre requests simultáneos
    $fp = fopen($dataFile, 'a');
    if ($fp) {
        if (flock($fp, LOCK_EX)) {
            fwrite($fp, $line);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'No pudimos guardar tu firma. Intentá de nuevo.']);
        exit;
    }
}

echo json_encode(['ok' => true, 'duplicate' => $isDuplicate]);
