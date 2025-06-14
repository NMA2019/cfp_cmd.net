<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Réinitialisation de mot de passe</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #0c6fb5;
            color: white !important;
            text-decoration: none;
            border-radius: 4px;
        }

        .footer {
            margin-top: 30px;
            font-size: 0.9em;
            color: #777;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Réinitialisation de votre mot de passe</h2>

        <p>Bonjour,</p>

        <p>Vous avez demandé à réinitialiser votre mot de passe pour le compte CFP-CMD.</p>

        <p>Cliquez sur le lien ci-dessous pour procéder :</p>

        <p>
            <a href="<?= htmlspecialchars($reset_link) ?>" class="button">
                Réinitialiser mon mot de passe
            </a>
        </p>

        <p>Ce lien expirera dans <?= $expiration ?>.</p>

        <p>Si vous n'êtes pas à l'origine de cette demande, veuillez ignorer cet email.</p>

        <div class="footer">
            <p>Cordialement,<br>L'équipe CFP-CMD</p>
            <p>
                <small>
                    Pour toute assistance, contactez
                    <a href="mailto:<?= htmlspecialchars($support_email) ?>">
                        <?= htmlspecialchars($support_email) ?>
                    </a>
                </small>
            </p>
        </div>
    </div>
</body>

</html>