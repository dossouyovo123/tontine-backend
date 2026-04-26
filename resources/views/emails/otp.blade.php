<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Code OTP — J-Solution</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f4f8; margin: 0; padding: 0; }
        .container { max-width: 520px; margin: 40px auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #0d47a1, #1565c0); padding: 32px 40px; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 22px; font-weight: 700; letter-spacing: 0.5px; }
        .header p { color: rgba(255,255,255,0.75); margin: 8px 0 0; font-size: 13px; }
        .body { padding: 40px; }
        .greeting { font-size: 16px; color: #333; margin-bottom: 20px; }
        .otp-box { background: #f0f4f8; border: 2px solid #1565c0; border-radius: 14px; text-align: center; padding: 28px 20px; margin: 28px 0; }
        .otp-label { font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 12px; }
        .otp-code { font-size: 42px; font-weight: 900; letter-spacing: 12px; color: #1565c0; font-family: 'Courier New', monospace; }
        .expiry { background: #fff8e1; border-left: 4px solid #f9a825; border-radius: 8px; padding: 12px 16px; font-size: 13px; color: #5d4037; margin-bottom: 24px; }
        .info { font-size: 13px; color: #888; line-height: 1.7; }
        .footer { background: #f8f9fa; padding: 20px 40px; text-align: center; border-top: 1px solid #eee; }
        .footer p { margin: 0; font-size: 11px; color: #aaa; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🔐 J-Solution</h1>
        <p>Réinitialisation de mot de passe</p>
    </div>
    <div class="body">
        <p class="greeting">Bonjour <strong>{{ $nomAdmin }}</strong>,</p>
        <p class="info">Vous avez demandé la réinitialisation de votre mot de passe administrateur. Voici votre code de vérification :</p>

        <div class="otp-box">
            <div class="otp-label">Votre code OTP</div>
            <div class="otp-code">{{ $otp }}</div>
        </div>

        <div class="expiry">
            ⏱ Ce code expire dans <strong>15 minutes</strong>. Ne le partagez avec personne.
        </div>

        <p class="info">Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email. Votre compte reste sécurisé.</p>
    </div>
    <div class="footer">
        <p>© {{ date('Y') }} J-Solution — Système de gestion de tontine</p>
    </div>
</div>
</body>
</html>