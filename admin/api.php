<?php
header('Content-Type: application/json');

session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => true,
    'cookie_samesite' => 'Strict',
]);

$action     = $_GET['action'] ?? '';
$emailsFile = __DIR__ . '/emails.json';

// ── Email management (auth required) ────────────────────────────────────────
if (in_array($action, ['get_emails', 'add_email', 'remove_email'], true)) {
    if (!isset($_SESSION['pp_auth'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $emails = file_exists($emailsFile)
        ? (json_decode(file_get_contents($emailsFile), true) ?: [])
        : [];

    if ($action === 'get_emails') {
        echo json_encode($emails);
        exit;
    }

    $body  = json_decode(file_get_contents('php://input'), true) ?: [];
    $email = strtolower(trim($body['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email address']);
        exit;
    }

    if ($action === 'add_email') {
        if (in_array($email, array_map('strtolower', $emails), true)) {
            echo json_encode(['success' => true, 'message' => 'Already in list']);
            exit;
        }
        $emails[] = $email;
        file_put_contents($emailsFile, json_encode(array_values($emails), JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'remove_email') {
        $emails = array_values(array_filter($emails, fn($e) => strtolower($e) !== $email));
        file_put_contents($emailsFile, json_encode($emails, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        exit;
    }
}

// ── All remaining actions require auth ───────────────────────────────────────
if (!isset($_SESSION['pp_auth'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$contentFile = __DIR__ . '/../content.json';
$imagesDir   = __DIR__ . '/../assets/images/';
// $action already set above

function deepMerge($base, $override) {
    if (!is_array($base)) return $override;
    foreach ($override as $key => $value) {
        if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && array_keys($value) !== range(0, count($value) - 1)) {
            $base[$key] = deepMerge($base[$key], $value);
        } else {
            $base[$key] = $value;
        }
    }
    return $base;
}

// Image upload
if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['image'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No file uploaded']);
        exit;
    }
    $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed)) {
        http_response_code(400);
        echo json_encode(['error' => 'File type not allowed']);
        exit;
    }
    if (!is_dir($imagesDir)) mkdir($imagesDir, 0755, true);
    $filename = uniqid('img_') . '.' . $ext;
    if (move_uploaded_file($_FILES['image']['tmp_name'], $imagesDir . $filename)) {
        echo json_encode(['success' => true, 'url' => 'assets/images/' . $filename]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Upload failed']);
    }
    exit;
}

// List images
if ($action === 'images' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $images = [];
    if (is_dir($imagesDir)) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        foreach (scandir($imagesDir) as $file) {
            if ($file === '.' || $file === '..') continue;
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $images[] = [
                    'url'  => 'assets/images/' . $file,
                    'name' => $file,
                    'size' => filesize($imagesDir . $file),
                ];
            }
        }
        usort($images, fn($a, $b) => filemtime($imagesDir . $b['name']) <=> filemtime($imagesDir . $a['name']));
    }
    echo json_encode($images);
    exit;
}

// GET content
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo file_exists($contentFile) ? file_get_contents($contentFile) : '{}';
    exit;
}

// POST / save content
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }
    $current = file_exists($contentFile) ? json_decode(file_get_contents($contentFile), true) : [];
    $merged  = deepMerge($current ?: [], $body);
    file_put_contents($contentFile, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
