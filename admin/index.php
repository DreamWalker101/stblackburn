<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => true,
    'cookie_samesite' => 'Strict',
]);
require_once __DIR__ . '/config.php';

// Already authenticated
if (isset($_SESSION['pp_auth'])) {
    header('Location: editor.php');
    exit;
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$emailsFile    = __DIR__ . '/emails.json';
$allowedEmails = file_exists($emailsFile)
    ? array_map('strtolower', json_decode(file_get_contents($emailsFile), true) ?: [])
    : [];

$step   = 'email'; // 'email' | 'otp'
$error  = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postStep = $_POST['step'] ?? '';

    // ── Step 1: Request OTP ──────────────────────────────────────────────────
    if ($postStep === 'email') {
        $email = strtolower(trim($_POST['email'] ?? ''));

        $lastRequest = $_SESSION['pp_otp_last_request'] ?? 0;
        if (time() - $lastRequest < 60) {
            $step   = 'otp';
            $notice = 'A code was already sent. Check your inbox — or wait 60 seconds to request a new one.';
        } else {
            $_SESSION['pp_otp_last_request'] = time();

            if (in_array($email, $allowedEmails, true)) {
                $otp    = sprintf('%06d', random_int(0, 999999));
                $expiry = time() + 600;

                $_SESSION['pp_otp_hash']    = password_hash($otp, PASSWORD_BCRYPT, ['cost' => 10]);
                $_SESSION['pp_otp_email']   = $email;
                $_SESSION['pp_otp_expiry']  = $expiry;
                $_SESSION['pp_otp_attempts'] = 0;

                $siteName  = OTP_FROM_NAME;
                $fromEmail = OTP_FROM_EMAIL;
                $subject   = "Your admin login code — {$siteName}";
                $body      = "Your one-time login code is:\n\n    {$otp}\n\nThis code expires in 10 minutes.\nDo not share it with anyone.\n\n\xe2\x80\x94 {$siteName}";
                $headers   = "From: {$siteName} <{$fromEmail}>\r\n"
                           . "Reply-To: {$fromEmail}\r\n"
                           . "Content-Type: text/plain; charset=UTF-8";

                if (defined('DEV_MODE') && DEV_MODE) {
                    $logLine = date('Y-m-d H:i:s') . " | email={$email} | otp={$otp}\n";
                    file_put_contents(__DIR__ . '/otp-debug.log', $logLine, FILE_APPEND);
                } else {
                    // -f sets the envelope sender so Exim uses a valid local
                    // return-path; without it PHP mail() is silently dropped.
                    mail($email, $subject, $body, $headers, '-f ' . $fromEmail);
                }
            }
            // Same response whether email is allowed or not (prevents enumeration)
            $step   = 'otp';
            $notice = 'If that email is authorised, a 6-digit code has been sent. Check your inbox.';
        }
    }

    // ── Step 2: Verify OTP ───────────────────────────────────────────────────
    elseif ($postStep === 'otp') {
        $step = 'otp';
        $otp  = preg_replace('/\D/', '', trim($_POST['otp'] ?? ''));

        if (empty($_SESSION['pp_otp_hash'])) {
            $error = 'No code was requested. Please start again.';
            $step  = 'email';
        } elseif (time() > ($_SESSION['pp_otp_expiry'] ?? 0)) {
            session_unset();
            $error = 'Your code has expired. Please request a new one.';
            $step  = 'email';
        } elseif (($_SESSION['pp_otp_attempts'] ?? 0) >= 5) {
            session_unset();
            $error = 'Too many incorrect attempts. Please request a new code.';
            $step  = 'email';
        } else {
            $_SESSION['pp_otp_attempts']++;

            if (password_verify($otp, $_SESSION['pp_otp_hash'])) {
                $email = $_SESSION['pp_otp_email'];
                session_regenerate_id(true);
                $_SESSION['pp_auth']  = true;
                $_SESSION['pp_email'] = $email;
                unset(
                    $_SESSION['pp_otp_hash'],
                    $_SESSION['pp_otp_email'],
                    $_SESSION['pp_otp_expiry'],
                    $_SESSION['pp_otp_attempts'],
                    $_SESSION['pp_otp_last_request']
                );
                header('Location: editor.php');
                exit;
            }

            $remaining = 5 - $_SESSION['pp_otp_attempts'];
            if ($remaining <= 0) {
                session_unset();
                $error = 'Too many incorrect attempts. Please request a new code.';
                $step  = 'email';
            } else {
                $error = "Incorrect code. {$remaining} attempt" . ($remaining === 1 ? '' : 's') . " remaining.";
            }
        }
    }

    // ── Start over ───────────────────────────────────────────────────────────
    elseif ($postStep === 'back') {
        session_unset();
        $step = 'email';
    }
}

// Resume OTP step if a pending code exists in session
if ($step === 'email' && isset($_SESSION['pp_otp_hash']) && time() < ($_SESSION['pp_otp_expiry'] ?? 0)) {
    $step = 'otp';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login — St Thomas Primary School</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; background-color: #F9F8F5; }
    h1, h2, h3 { font-family: 'Montserrat', sans-serif; }
    .pp-card {
      background: #ffffff;
      border-radius: 16px;
      box-shadow: 0 4px 24px rgba(30,58,95,0.10), 0 1px 4px rgba(30,58,95,0.06);
      max-width: 420px;
      width: 100%;
    }
    .pp-card-header {
      background-color: #082a5e;
      border-radius: 16px 16px 0 0;
      padding: 32px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 10px;
    }
    .pp-card-subtitle {
      font-family: 'Montserrat', sans-serif;
      font-size: 14px;
      font-weight: 600;
      color: rgba(255,255,255,0.65);
      letter-spacing: 0.01em;
      margin-top: 2px;
    }
    .pp-card-body { padding: 32px; }
    .pp-input-label {
      display: block;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: rgba(30,58,95,0.55);
      margin-bottom: 8px;
    }
    .pp-input {
      width: 100%;
      height: 48px;
      padding: 0 16px;
      border: 1.5px solid #E2E6EA;
      border-radius: 12px;
      font-family: 'Inter', sans-serif;
      font-size: 15px;
      color: #082a5e;
      background: #ffffff;
      outline: none;
      transition: border-color 0.15s, box-shadow 0.15s;
      box-sizing: border-box;
    }
    .pp-input:focus { border-color: #082a5e; box-shadow: 0 0 0 2px rgba(8,42,94,0.18); }
    .pp-input::placeholder { color: #B0B8C4; }
    .pp-input.otp-input {
      font-size: 22px;
      font-weight: 700;
      text-align: center;
      letter-spacing: 0.18em;
    }
    .pp-btn {
      display: block;
      width: 100%;
      height: 48px;
      background-color: #082a5e;
      color: #ffffff;
      font-family: 'Montserrat', sans-serif;
      font-size: 14px;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      transition: background-color 0.15s;
      margin-top: 20px;
    }
    .pp-btn:hover { background-color: #0D3D17; }
    .pp-btn-secondary {
      display: block;
      width: 100%;
      text-align: center;
      margin-top: 14px;
      font-size: 13px;
      color: rgba(30,58,95,0.45);
      background: none;
      border: none;
      cursor: pointer;
      padding: 4px;
      text-decoration: underline;
      text-underline-offset: 3px;
      font-family: 'Inter', sans-serif;
    }
    .pp-btn-secondary:hover { color: #082a5e; }
    .pp-error {
      display: flex;
      align-items: center;
      gap: 8px;
      background: #FEF2F2;
      border: 1px solid #FECACA;
      border-radius: 10px;
      padding: 10px 14px;
      margin-bottom: 20px;
      color: #B91C1C;
      font-size: 13px;
    }
    .pp-notice {
      display: flex;
      align-items: flex-start;
      gap: 8px;
      background: #F0FDF4;
      border: 1px solid #BBF7D0;
      border-radius: 10px;
      padding: 10px 14px;
      margin-bottom: 20px;
      color: #166534;
      font-size: 13px;
      line-height: 1.5;
    }
    .pp-step-hint {
      font-size: 13px;
      color: rgba(30,58,95,0.5);
      line-height: 1.6;
      margin-bottom: 20px;
    }
    .pp-step-hint strong { color: #082a5e; font-weight: 600; }
    .pp-page-footer {
      text-align: center;
      margin-top: 28px;
      font-size: 12px;
      color: rgba(30,58,95,0.35);
    }
  </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-6">

  <div class="pp-card">
    <div class="pp-card-header">
      <img src="../assets/stblackburn.catholic.edu.au/wp-content/uploads/2025/10/freepik_br_12f8d406-08ec-478c-b6bc-e615ac4162b6.png"
           alt="St Thomas Primary School" style="height:54px;width:auto;filter:brightness(0) invert(1);">
      <div class="pp-card-subtitle">Content Admin</div>
    </div>

    <div class="pp-card-body">

      <?php if ($error): ?>
      <div class="pp-error">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" style="flex-shrink:0;">
          <circle cx="8" cy="8" r="7.25" stroke="#B91C1C" stroke-width="1.5"/>
          <path d="M8 4.5v4" stroke="#B91C1C" stroke-width="1.5" stroke-linecap="round"/>
          <circle cx="8" cy="11" r="0.75" fill="#B91C1C"/>
        </svg>
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
      </div>
      <?php endif; ?>

      <?php if ($notice && !$error): ?>
      <div class="pp-notice">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" style="flex-shrink:0;margin-top:1px;">
          <circle cx="8" cy="8" r="7.25" stroke="#166534" stroke-width="1.5"/>
          <path d="M8 7v5" stroke="#166534" stroke-width="1.5" stroke-linecap="round"/>
          <circle cx="8" cy="5" r="0.75" fill="#166534"/>
        </svg>
        <?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?>
      </div>
      <?php endif; ?>


      <?php if ($step === 'email'): ?>
      <!-- ── Step 1: Email ── -->
      <form method="POST" autocomplete="off" novalidate>
        <input type="hidden" name="step" value="email">
        <label class="pp-input-label" for="email">Email Address</label>
        <input
          id="email"
          type="email"
          name="email"
          class="pp-input"
          placeholder="you@example.com"
          required
          autofocus
        >
        <button type="submit" class="pp-btn">Send Login Code</button>
      </form>


      <?php else: ?>
      <!-- ── Step 2: OTP ── -->
      <p class="pp-step-hint">
        Enter the <strong>6-digit code</strong> sent to your email address.
        It expires in <strong>10 minutes</strong>.
      </p>
      <form method="POST" autocomplete="off" novalidate>
        <input type="hidden" name="step" value="otp">
        <label class="pp-input-label" for="otp">Login Code</label>
        <input
          id="otp"
          type="text"
          name="otp"
          class="pp-input otp-input"
          placeholder="000000"
          maxlength="6"
          inputmode="numeric"
          pattern="\d{6}"
          required
          autofocus
        >
        <button type="submit" class="pp-btn">Verify Code</button>
      </form>
      <form method="POST">
        <input type="hidden" name="step" value="back">
        <button type="submit" class="pp-btn-secondary">Use a different email address</button>
      </form>
      <?php endif; ?>

    </div>
  </div>

  <p class="pp-page-footer">St Thomas Primary School, Blackburn</p>

</body>
</html>
