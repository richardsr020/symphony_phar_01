<?php
$user = $_SESSION['user'] ?? [];
$companyName = (string) ($user['company_name'] ?? 'votre entreprise');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application suspendue - Symphony</title>
    <style>
        body {
            margin: 0;
            font-family: Inter, sans-serif;
            background: #f4f7fb;
            color: #1f2937;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: #ffffff;
            border-radius: 14px;
            max-width: 540px;
            width: 100%;
            padding: 28px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.12);
        }
        h1 {
            margin: 0 0 10px;
            font-size: 24px;
        }
        p {
            margin: 0 0 14px;
            line-height: 1.55;
            color: #4b5563;
        }
        a {
            display: inline-block;
            margin-top: 8px;
            text-decoration: none;
            background: #111827;
            color: #fff;
            padding: 10px 14px;
            border-radius: 8px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Accès temporairement suspendu</h1>
        <p>L'accès à l'application pour <strong><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></strong> est actuellement verrouillé.</p>
        <p>Contactez NestCorporation ou votre administrateur pour régulariser le réabonnement.</p>
        <a href="/logout">Se déconnecter</a>
    </div>
</body>
</html>
