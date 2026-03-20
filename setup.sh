#!/bin/bash

echo "Création de l'arborescence Symphony..."

# ==============================
# DOSSIERS PRINCIPAUX
# ==============================

mkdir -p app/controllers
mkdir -p app/models
mkdir -p app/core
mkdir -p app/middleware
mkdir -p app/ai/tools
mkdir -p app/ai/knowledge
mkdir -p app/views/layouts
mkdir -p app/views/pages/auth
mkdir -p app/views/components

mkdir -p public/css
mkdir -p public/js/modules
mkdir -p public/js/vendor
mkdir -p public/fonts
mkdir -p public/images

mkdir -p database
mkdir -p storage/logs
mkdir -p storage/uploads
mkdir -p logs

# ==============================
# FICHIERS RACINE
# ==============================

touch index.php
touch .htaccess
touch config.php
touch router.php

# ==============================
# CONTROLLERS
# ==============================

touch app/controllers/AuthController.php
touch app/controllers/DashboardController.php
touch app/controllers/TransactionsController.php
touch app/controllers/InvoicesController.php
touch app/controllers/ReportsController.php
touch app/controllers/SettingsController.php
touch app/controllers/ApiController.php

# ==============================
# MODELS
# ==============================

touch app/models/Model.php
touch app/models/User.php
touch app/models/Transaction.php
touch app/models/Invoice.php
touch app/models/Company.php
touch app/models/Account.php
touch app/models/Category.php
touch app/models/Alert.php
touch app/models/AIMemory.php

# ==============================
# CORE
# ==============================

touch app/core/Database.php
touch app/core/Session.php
touch app/core/Security.php
touch app/core/Validator.php
touch app/core/Helper.php

# ==============================
# MIDDLEWARE
# ==============================

touch app/middleware/AuthMiddleware.php
touch app/middleware/CsrfMiddleware.php

# ==============================
# AI
# ==============================

touch app/ai/SymphonyAI.php
touch app/ai/tools/ToolInterface.php
touch app/ai/tools/TransactionTool.php
touch app/ai/tools/FiscalTool.php
touch app/ai/tools/AlertTool.php
touch app/ai/knowledge/fiscalite_congo.txt

# ==============================
# VIEWS
# ==============================

touch app/views/layouts/main.php
touch app/views/layouts/auth.php

touch app/views/pages/auth/login.php
touch app/views/pages/auth/register.php

touch app/views/pages/dashboard.php
touch app/views/pages/transactions.php
touch app/views/pages/invoices.php
touch app/views/pages/reports.php
touch app/views/pages/settings.php

touch app/views/components/sidebar.php
touch app/views/components/header.php
touch app/views/components/chat.php
touch app/views/components/alerts.php
touch app/views/components/modals.php

# ==============================
# PUBLIC ASSETS
# ==============================

touch public/css/style.css
touch public/css/theme-light.css
touch public/css/theme-dark.css

touch public/js/app.js
touch public/js/modules/theme.js
touch public/js/modules/navigation.js
touch public/js/modules/chat.js
touch public/js/modules/charts.js
touch public/js/modules/api.js

# ==============================
# DATABASE
# ==============================

touch database/schema.sql
touch database/seeds.sql

# ==============================
# LOGS
# ==============================

touch logs/app.log

echo "Arborescence Symphony créée avec succès ✅"
