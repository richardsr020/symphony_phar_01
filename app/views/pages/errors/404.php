<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page non trouvée | Symphony</title>
    <!-- Use system fonts for offline operation -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #F8F9FB;
            color: #0F172A;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .error-container {
            text-align: center;
            padding: 40px;
            max-width: 500px;
        }
        
        .error-code {
            font-size: 120px;
            font-weight: 700;
            color: #0F9D58;
            line-height: 1;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .error-title {
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .error-message {
            color: #64748B;
            margin-bottom: 30px;
            font-size: 16px;
            line-height: 1.6;
        }
        
        .btn-home {
            display: inline-block;
            padding: 12px 30px;
            background: #0F9D58;
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        
        .btn-home:hover {
            background: #12824A;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(15, 157, 88, 0.3);
        }
        
        .ai-suggestion {
            margin-top: 40px;
            padding: 20px;
            background: #E8F6EF;
            border-radius: 12px;
            font-size: 14px;
        }
        
        .ai-suggestion strong {
            color: #0F9D58;
            display: block;
            margin-bottom: 8px;
        }
        
        .ai-suggestion p {
            color: #0F172A;
        }

        .demo-links {
            margin-top: 18px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
        }

        .demo-links a {
            color: #334155;
            text-decoration: none;
            background: #E2E8F0;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">404</div>
        <h1 class="error-title">Page introuvable</h1>
        <p class="error-message">
            Désolé, la page que vous recherchez n'existe pas ou a été déplacée.
        </p>
        
        <a href="/dashboard" class="btn-home">Retour au tableau de bord</a>
        
        <div class="ai-suggestion">
            <strong>🔍 Suggestion Symphony</strong>
            <p>Vous cherchez peut-être vos <a href="/transactions" style="color: #0F9D58;">transactions récentes</a> ou vos <a href="/reports" style="color: #0F9D58;">rapports financiers</a> ?</p>
        </div>

        <div class="demo-links">
            <a href="/dashboard">Dashboard</a>
            <a href="/transactions">Transactions</a>
            <a href="/invoices">Ventes</a>
            <a href="/reports">Rapports</a>
            <a href="/settings">Paramètres</a>
        </div>
    </div>
</body>
</html>
