<?php
// ============================================
// CVMatch IA - Envoi Gmail SMTP pur PHP
// ============================================

define('GMAIL_USER', 'VOTRE_ADRESSE_GMAIL');
define('GMAIL_PASS', 'VOTRE_MOT_DE_PASSE_GMAIL');
define('GMAIL_FROM_NAME', 'CVMatch IA');

function envoyerEmail($destinataire, $sujet, $corps_html, $replyTo = null)
{
  $host = 'smtp.gmail.com';
  $port = 587;
  $user = GMAIL_USER;
  $pass = GMAIL_PASS;
  $from = GMAIL_USER;
  $fromName = GMAIL_FROM_NAME;

  $socket = @fsockopen($host, $port, $errno, $errstr, 15);
  if (!$socket)
    return ['ok' => false, 'erreur' => "Impossible de contacter smtp.gmail.com:$port — $errstr ($errno)"];

  $corps_texte = strip_tags(str_replace(['<br>', '<br/>', '</p>', '</div>'], "\n", $corps_html));
  $boundary = md5(uniqid(time()));

  $headers = "Date: " . date('r') . "\r\n";
  $headers .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$from>\r\n";
  if (!empty($replyTo)) {
    $headers .= "Reply-To: <$replyTo>\r\n";
    $headers .= "Return-Path: <$replyTo>\r\n";
  }
  $headers .= "To: <$destinataire>\r\n";
  $headers .= "Subject: =?UTF-8?B?" . base64_encode($sujet) . "?=\r\n";
  $headers .= "MIME-Version: 1.0\r\n";
  $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
  $headers .= "X-Mailer: CVMatch-IA-PHP\r\n";

  $body = "--$boundary\r\n";
  $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
  $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
  $body .= chunk_split(base64_encode($corps_texte)) . "\r\n";
  $body .= "--$boundary\r\n";
  $body .= "Content-Type: text/html; charset=UTF-8\r\n";
  $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
  $body .= chunk_split(base64_encode($corps_html)) . "\r\n";
  $body .= "--$boundary--\r\n";

  $banner = smtpRead($socket);
  if (substr($banner, 0, 3) !== '220') {
    fclose($socket);
    return ['ok' => false, 'erreur' => "Bannière SMTP inattendue : $banner"];
  }

  smtpWrite($socket, "EHLO localhost\r\n");
  smtpRead($socket);
  smtpWrite($socket, "STARTTLS\r\n");
  $tls = smtpRead($socket);
  if (substr($tls, 0, 3) !== '220') {
    fclose($socket);
    return ['ok' => false, 'erreur' => "STARTTLS refusé : $tls"];
  }

  stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
  smtpWrite($socket, "EHLO localhost\r\n");
  smtpRead($socket);

  smtpWrite($socket, "AUTH LOGIN\r\n");
  smtpRead($socket);
  smtpWrite($socket, base64_encode($user) . "\r\n");
  smtpRead($socket);
  smtpWrite($socket, base64_encode($pass) . "\r\n");
  $auth = smtpRead($socket);
  if (substr($auth, 0, 3) !== '235') {
    fclose($socket);
    return ['ok' => false, 'erreur' => "Authentification Gmail échouée : $auth"];
  }

  smtpWrite($socket, "MAIL FROM:<$from>\r\n");
  smtpRead($socket);
  smtpWrite($socket, "RCPT TO:<$destinataire>\r\n");
  $rcpt = smtpRead($socket);
  if (substr($rcpt, 0, 3) !== '250') {
    fclose($socket);
    return ['ok' => false, 'erreur' => "Destinataire refusé : $rcpt"];
  }

  smtpWrite($socket, "DATA\r\n");
  smtpRead($socket);
  smtpWrite($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
  $sent = smtpRead($socket);

  smtpWrite($socket, "QUIT\r\n");
  fclose($socket);

  return substr($sent, 0, 3) === '250'
    ? ['ok' => true]
    : ['ok' => false, 'erreur' => "Envoi échoué : $sent"];
}

function smtpWrite($socket, $cmd)
{
  fwrite($socket, $cmd);
}

function smtpRead($socket)
{
  $response = '';
  while ($line = fgets($socket, 512)) {
    $response .= $line;
    if (substr($line, 3, 1) === ' ')
      break;
  }
  return $response;
}

// ============================================================
// TEMPLATE : Code OTP de vérification
// ============================================================
function templateOTP($code, $email)
{
  // Construire les cases de chiffres
  $digits = '';
  foreach (str_split($code) as $ch) {
    $digits .= '<span style="'
      . 'display:inline-block;'
      . 'width:44px;height:52px;line-height:52px;'
      . 'text-align:center;font-size:26px;font-weight:800;'
      . 'color:#F67011;'
      . 'background:rgba(246,112,17,.08);'
      . 'border:2px solid rgba(246,112,17,.3);'
      . 'border-radius:10px;margin:0 4px'
      . '">' . htmlspecialchars($ch) . '</span>';
  }

  $html = '<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#161514;font-family:Arial,sans-serif">
  <div style="max-width:520px;margin:30px auto;background:#262626;border-radius:14px;overflow:hidden;border:1px solid rgba(246,112,17,.25)">

    <div style="background:linear-gradient(135deg,#F67011,#873800);padding:26px 32px;text-align:center">
      <h1 style="color:#fff;margin:0;font-size:24px;font-weight:800;letter-spacing:-0.5px">CVMatch IA</h1>
      <p style="color:rgba(255,255,255,.8);margin:6px 0 0;font-size:13px">V&eacute;rification de votre adresse email</p>
    </div>

    <div style="padding:36px 32px">
      <p style="color:#FFE4D0;font-size:15px;margin-bottom:12px">Bonjour,</p>
      <p style="color:#878787;font-size:14px;line-height:1.6;margin-bottom:28px">
        Vous avez demand&eacute; &agrave; cr&eacute;er un compte sur
        <strong style="color:#FFE4D0">CVMatch IA</strong>.<br>
        Voici votre code de v&eacute;rification &agrave; 6 chiffres :
      </p>

      <div style="text-align:center;margin-bottom:28px;padding:22px 16px;background:rgba(246,112,17,.05);border-radius:12px;border:1px solid rgba(246,112,17,.15)">
        ' . $digits . '
        <p style="color:#878787;font-size:11px;margin:16px 0 0">
          Valable pendant <strong style="color:#F67011">10 minutes</strong>
        </p>
      </div>

      <p style="color:#878787;font-size:13px;line-height:1.6;margin-bottom:20px">
        Saisissez ce code sur la page d&#39;inscription pour confirmer votre email et cr&eacute;er votre compte.
      </p>

      <div style="background:rgba(255,77,77,.08);border:1px solid rgba(255,77,77,.2);border-radius:8px;padding:12px 16px;margin-bottom:20px">
        <p style="color:#ff8c8c;font-size:12px;margin:0">
          &#128274; Si vous n&#39;avez pas demand&eacute; ce code, ignorez cet email. Votre compte ne sera pas cr&eacute;&eacute;.
        </p>
      </div>

      <p style="color:#555;font-size:11px;border-top:1px solid rgba(246,112,17,.1);padding-top:14px;margin:0;text-align:center">
        CVMatch IA &mdash; Plateforme de recrutement intelligent
      </p>
    </div>
  </div>
</body>
</html>';

  return $html;
}

// ============================================================
// TEMPLATE : Code recruteur
// ============================================================
function templateCodeRecruteur($code)
{
  $html = '<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#161514;font-family:Arial,sans-serif">
  <div style="max-width:520px;margin:30px auto;background:#262626;border-radius:12px;overflow:hidden;border:1px solid rgba(246,112,17,.3)">
    <div style="background:linear-gradient(135deg,#F67011,#873800);padding:28px 32px;text-align:center">
      <h1 style="color:#fff;margin:0;font-size:24px;font-weight:800">CVMatch IA</h1>
      <p style="color:rgba(255,255,255,.8);margin:6px 0 0;font-size:13px">Plateforme de recrutement intelligent</p>
    </div>
    <div style="padding:32px">
      <p style="color:#FFE4D0;font-size:15px;margin-bottom:16px">Bonjour,</p>
      <p style="color:#878787;font-size:14px;line-height:1.6;margin-bottom:24px">
        Vous avez demand&eacute; un <strong style="color:#FFE4D0">code d&#39;acc&egrave;s recruteur</strong> pour CVMatch IA.<br>
        Voici votre code :
      </p>
      <div style="background:rgba(246,112,17,.1);border:2px dashed #F67011;border-radius:10px;padding:22px;text-align:center;margin-bottom:24px">
        <div style="font-size:36px;font-weight:800;color:#F67011;letter-spacing:10px">' . htmlspecialchars($code) . '</div>
        <div style="font-size:12px;color:#878787;margin-top:8px">Code d&#39;acc&egrave;s recruteur</div>
      </div>
      <p style="color:#878787;font-size:13px;line-height:1.6">
        Utilisez ce code lors de votre inscription dans le champ <em style="color:#FFE4D0">"Code d&#39;acc&egrave;s recruteur"</em>.
      </p>
    </div>
  </div>
</body>
</html>';

  return $html;
}

// ============================================================
// TEMPLATE : Contact recruteur → candidat
// ============================================================
function templateContactRecruteur($prenomCandidat, $entrepriseRecruteur)
{
  $html = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#161514;font-family:Arial,sans-serif">
<div style="max-width:600px;margin:30px auto;background:#262626;border-radius:12px;overflow:hidden;border:1px solid rgba(246,112,17,.3)">
    <div style="background:linear-gradient(135deg,#F67011,#873800);color:white;padding:30px;text-align:center">
        <h1 style="margin:0;font-size:24px;font-weight:800">CVMatch IA</h1>
        <p style="margin:8px 0 0;opacity:.85;font-size:14px">Un recruteur vous a s&eacute;lectionn&eacute; !</p>
    </div>
    <div style="padding:36px 32px">
        <p style="color:#FFE4D0;font-size:15px;margin-bottom:14px">
            Bonjour <strong>' . htmlspecialchars($prenomCandidat) . '</strong>,
        </p>
        <p style="color:#878787;font-size:14px;line-height:1.6;margin-bottom:20px">
            L&#39;entreprise <strong style="color:#F67011">' . htmlspecialchars($entrepriseRecruteur) . '</strong>
            a vu votre profil sur CVMatch IA et souhaite vous contacter.
        </p>
        <div style="background:rgba(246,112,17,.08);border:1px solid rgba(246,112,17,.25);border-radius:10px;padding:18px 20px;margin-bottom:22px">
            <p style="color:#FFE4D0;font-size:13.5px;margin:0;line-height:1.6">
                Vous pouvez r&eacute;pondre directement &agrave; cet email ou vous connecter sur <strong>CVMatch IA</strong> pour voir les d&eacute;tails.
            </p>
        </div>
        <p style="color:#878787;font-size:13px">Bonne chance pour la suite de votre recherche !</p>
    </div>
</div>
</body></html>';

  return $html;
}
