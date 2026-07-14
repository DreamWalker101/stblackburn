<?php
/* Public form handler — emails website form submissions to the school office.
 * Recipient is read from content.json (global.email) so it stays editable in
 * the admin. Every submission is also appended to form-submissions.log
 * (gitignored) so it can be verified even where outbound mail isn't delivered.
 */
$root = __DIR__;
$content = file_exists("$root/content.json") ? json_decode(file_get_contents("$root/content.json"), true) : [];
$g = $content['global'] ?? [];

$SITE = "St Thomas Primary School";
$TO   = $g['email'] ?? 'office@stblackburn.catholic.edu.au';
$FROM = 'noreply@stblackburn.catholic.edu.au';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ./'); exit; }

$formName = trim($_POST['_form'] ?? 'Website enquiry');
$back     = $_POST['_back'] ?? './';

/* Build the message from posted fields (skip internal / wpcf7 fields) */
$lines = [];
foreach ($_POST as $k => $val) {
    if ($k === '' || $k[0] === '_' || stripos($k, 'wpcf7') !== false) continue;
    if (is_array($val)) $val = implode(', ', $val);
    $val = trim($val);
    if ($val === '') continue;
    $label = ucwords(str_replace(['-', '_'], ' ', $k));
    $lines[] = "$label: $val";
}
$body = "New submission from the {$SITE} website — {$formName}\n"
      . str_repeat('-', 48) . "\n\n" . implode("\n", $lines) . "\n\n"
      . "Sent " . date('D j M Y, g:ia') . "\n";

/* Reply-To = submitter's email if present */
$replyTo = '';
foreach (['primary-email', 'your-email', 'email', 'parent-email'] as $ef) {
    if (!empty($_POST[$ef]) && filter_var($_POST[$ef], FILTER_VALIDATE_EMAIL)) { $replyTo = $_POST[$ef]; break; }
}

$subject = "Website enquiry — {$formName}";
$headers = "From: {$SITE} <{$FROM}>\r\n";
if ($replyTo) $headers .= "Reply-To: {$replyTo}\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8";

/* Always log (verifiable on staging), then attempt to email */
@file_put_contents($root . '/form-submissions.log',
    "=== " . date('Y-m-d H:i:s') . " | to=$TO | form=$formName ===\n$body\n",
    FILE_APPEND);
@mail($TO, $subject, $body, $headers, '-f ' . $FROM);
?>
<!doctype html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Thank you — <?= htmlspecialchars($SITE) ?></title>
<style>
 body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f4f5f7;color:#1c2530;display:flex;min-height:100vh;align-items:center;justify-content:center;}
 .card{background:#fff;border-radius:14px;padding:44px 40px;max-width:480px;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,.08);}
 .tick{width:64px;height:64px;border-radius:50%;background:#082a5e;color:#fff;font-size:32px;line-height:64px;margin:0 auto 18px;}
 h1{font-size:22px;margin:0 0 10px;} p{color:#6b7682;line-height:1.55;margin:0 0 22px;}
 a{display:inline-block;background:#082a5e;color:#fff;text-decoration:none;padding:11px 24px;border-radius:8px;font-weight:600;}
</style></head>
<body>
 <div class="card">
   <div class="tick">&checkmark;</div>
   <h1>Thank you</h1>
   <p>Your message has been sent to <?= htmlspecialchars($SITE) ?>. We'll be in touch as soon as we can.</p>
   <a href="<?= htmlspecialchars($back, ENT_QUOTES) ?>">Back to the website</a>
 </div>
</body></html>
