<?php
/**
 * Routeur Symphony
 * Gère toutes les routes de l'application
 */

class Router {
    
    private $routes = [];
    private $params = [];
    private $requestMethod;
    
    public function __construct() {
        $this->requestMethod = $_SERVER['REQUEST_METHOD'];
        $this->defineRoutes();
    }
    
    /**
     * Définition de toutes les routes
     */
    private function defineRoutes() {
        // Routes publiques
        $this->addRoute('GET', '/', 'AuthController@showLogin');
        $this->addRoute('GET', '/login', 'AuthController@showLogin');
        $this->addRoute('POST', '/login', 'AuthController@login');
        $this->addRoute('GET', '/register', 'AuthController@showRegister');
        $this->addRoute('POST', '/register', 'AuthController@register');
        $this->addRoute('GET', '/logout', 'AuthController@logout');
        $this->addRoute('GET', '/forgot-password', 'PageController@forgotPassword');
        $this->addRoute('GET', '/terms', 'PageController@terms');

        // Espace fournisseur (NestCorporation)
        $this->addRoute('GET', '/provider/login', 'ProviderController@showLogin');
        $this->addRoute('POST', '/provider/login', 'ProviderController@login');
        $this->addRoute('GET', '/provider/logout', 'ProviderController@logout');
        $this->addRoute('GET', '/provider/dashboard', 'ProviderController@dashboard', ['providerAuth']);
        $this->addRoute('POST', '/provider/companies/{id}/subscription', 'ProviderController@configureSubscription', ['providerAuth']);
        $this->addRoute('POST', '/provider/subscriptions/run', 'ProviderController@runAutomationNow', ['providerAuth']);
        
        // Routes protégées (dashboard)
        $this->addRoute('GET', '/dashboard', 'DashboardController@index', ['auth']);
        $this->addRoute('GET', '/suivi-clients', 'DashboardController@clientTracking', ['auth']);
        $this->addRoute('GET', '/suivi-clients/export', 'DashboardController@exportClientLedger', ['auth']);
        $this->addRoute('GET', '/suivi-clients/export-summary', 'DashboardController@exportClientTracking', ['auth']);
        $this->addRoute('GET', '/fournisseurs', 'SuppliersController@index', ['auth']);
        $this->addRoute('GET', '/fournisseurs/export', 'SuppliersController@export', ['auth']);
        
        // Transactions
        $this->addRoute('GET', '/transactions', 'TransactionsController@index', ['auth']);
        $this->addRoute('GET', '/transactions/export', 'TransactionsController@export', ['auth']);
        $this->addRoute('GET', '/transactions/create', 'TransactionsController@create', ['auth']);
        $this->addRoute('POST', '/transactions/store', 'TransactionsController@store', ['auth']);
        $this->addRoute('GET', '/transactions/edit/{id}', 'TransactionsController@edit', ['auth']);
        $this->addRoute('POST', '/transactions/update/{id}', 'TransactionsController@update', ['auth']);
        $this->addRoute('POST', '/transactions/delete/{id}', 'TransactionsController@delete', ['auth']);
        $this->addRoute('GET', '/transactions/view/{id}', 'TransactionsController@view', ['auth']);
        $this->addRoute('GET', '/transactions/preview/{id}', 'TransactionsController@preview', ['auth']);
        $this->addRoute('GET', '/transactions/pdf/{id}', 'TransactionsController@generatePDF', ['auth']);
        
        // Factures
        $this->addRoute('GET', '/invoices', 'InvoicesController@index', ['auth']);
        $this->addRoute('GET', '/invoices/export', 'InvoicesController@export', ['auth']);
        $this->addRoute('GET', '/invoices/create', 'InvoicesController@create', ['auth']);
        $this->addRoute('GET', '/invoices/edit/{id}', 'InvoicesController@edit', ['auth']);
        $this->addRoute('GET', '/invoice/create', 'InvoicesController@create', ['auth']);
        $this->addRoute('POST', '/invoices/store', 'InvoicesController@store', ['auth']);
        $this->addRoute('POST', '/invoices/merge', 'InvoicesController@merge', ['auth']);
        $this->addRoute('POST', '/invoices/update/{id}', 'InvoicesController@update', ['auth']);
        $this->addRoute('POST', '/invoices/send/{id}', 'InvoicesController@send', ['auth']);
        $this->addRoute('POST', '/invoices/cancel/{id}', 'InvoicesController@cancel', ['auth']);
        $this->addRoute('POST', '/invoices/delete/{id}', 'InvoicesController@delete', ['auth']);
        $this->addRoute('POST', '/invoices/pay/{id}', 'InvoicesController@registerPayment', ['auth']);
        $this->addRoute('POST', '/invoices/mark-downloaded/{id}', 'InvoicesController@markDownloaded', ['auth']);
        $this->addRoute('GET', '/invoices/view/{id}', 'InvoicesController@view', ['auth']);
        $this->addRoute('GET', '/invoices/preview/{id}', 'InvoicesController@preview', ['auth']);
        $this->addRoute('GET', '/invoices/pdf/{id}', 'InvoicesController@generatePDF', ['auth']);

        // Recus de paiement
        $this->addRoute('GET', '/receipts/preview/{id}', 'ReceiptsController@preview', ['auth']);
        $this->addRoute('GET', '/receipts/pdf/{id}', 'ReceiptsController@generatePDF', ['auth']);

        // Stock produits
        $this->addRoute('GET', '/stock', 'StockController@index', ['auth']);
        $this->addRoute('GET', '/stock/alerts', 'StockController@alerts', ['auth']);
        $this->addRoute('POST', '/stock/store', 'StockController@store', ['auth']);
        $this->addRoute('POST', '/stock/add-lot', 'StockController@addLot', ['auth']);
        $this->addRoute('POST', '/stock/update/{id}', 'StockController@update', ['auth']);
        $this->addRoute('POST', '/stock/adjust/{id}', 'StockController@adjust', ['auth']);
        $this->addRoute('POST', '/stock/delete/{id}', 'StockController@delete', ['auth']);
        $this->addRoute('POST', '/stock/lots/{id}/update', 'StockController@updateLot', ['auth']);
        $this->addRoute('POST', '/stock/lots/{id}/declass', 'StockController@declassLot', ['auth']);
        $this->addRoute('POST', '/stock/lots/{id}/delete', 'StockController@deleteLot', ['auth']);
        $this->addRoute('GET', '/stock/lots/export', 'StockController@exportLots', ['auth']);
        $this->addRoute('POST', '/stock/delete-bulk', 'StockController@deleteBulk', ['auth']);
        $this->addRoute('POST', '/stock/lots/delete-bulk', 'StockController@deleteLotsBulk', ['auth']);
        $this->addRoute('POST', '/stock/purchase-orders/generate-critical', 'StockController@generateCriticalPurchaseOrder', ['auth']);
        $this->addRoute('GET', '/stock/purchase-orders/preview/{id}', 'StockController@previewPurchaseOrder', ['auth']);

        // Debug temporaire (a supprimer apres usage)
        $this->addRoute('GET', '/debug/product', 'DebugController@product', ['auth']);
        
        // Rapports
        $this->addRoute('GET', '/reports', 'ReportsController@index', ['auth']);
        $this->addRoute('GET', '/reports/profit-loss', 'ReportsController@profitLoss', ['auth']);
        $this->addRoute('GET', '/reports/balance', 'ReportsController@balanceSheet', ['auth']);
        $this->addRoute('GET', '/reports/tva', 'ReportsController@tva', ['auth']);
        $this->addRoute('GET', '/reports/export', 'ReportsController@export', ['auth']);
        $this->addRoute('GET', '/reports/pdf-content', 'ReportsController@pdfContent', ['auth']);
        $this->addRoute('GET', '/reports/pdf-download', 'ReportsController@pdfDownload', ['auth']);

        // Chatbox IA
        $this->addRoute('GET', '/chat', 'ChatController@index', ['auth']);
        
        // Paramètres
        $this->addRoute('GET', '/settings', 'SettingsController@index', ['auth', 'admin']);
        $this->addRoute('POST', '/settings/company', 'SettingsController@updateCompany', ['auth', 'admin']);
        $this->addRoute('POST', '/settings/profile', 'SettingsController@updateProfile', ['auth', 'admin']);
        $this->addRoute('POST', '/settings/fiscal', 'SettingsController@updateFiscal', ['auth', 'admin']);
        $this->addRoute('POST', '/settings/ai', 'SettingsController@updateAi', ['auth', 'admin']);
        $this->addRoute('POST', '/settings/stock-form', 'SettingsController@updateStockForm', ['auth', 'admin']);
        $this->addRoute('GET', '/settings/logs/export', 'SettingsController@exportLogs', ['auth', 'admin']);
        $this->addRoute('POST', '/admin/users', 'SettingsController@createUser', ['auth', 'admin']);
        $this->addRoute('POST', '/admin/users/{id}/status', 'SettingsController@updateUserStatus', ['auth', 'admin']);
        $this->addRoute('POST', '/admin/users/{id}/role', 'SettingsController@updateUserRole', ['auth', 'admin']);
        $this->addRoute('POST', '/admin/users/{id}/password', 'SettingsController@resetUserPassword', ['auth', 'admin']);
        $this->addRoute('POST', '/admin/users/{id}/delete', 'SettingsController@deleteUser', ['auth', 'admin']);

        // Clients
        $this->addRoute('POST', '/clients/store', 'ClientsController@store', ['auth']);
        
        // API (pour l'IA)
        $this->addRoute('POST', '/api/chat', 'ApiController@chat', ['auth']);
        $this->addRoute('GET', '/api/chat/conversations', 'ApiController@chatConversations', ['auth']);
        $this->addRoute('GET', '/api/chat/history/{id}', 'ApiController@chatHistory', ['auth']);
        $this->addRoute('GET', '/api/clients/search', 'ApiController@clientSearch', ['auth']);
        $this->addRoute('GET', '/api/clients/outstanding', 'ApiController@clientOutstanding', ['auth']);
        $this->addRoute('GET', '/api/dashboard', 'ApiController@dashboard', ['auth']);
        $this->addRoute('GET', '/api/user/context', 'ApiController@userContext', ['auth']);
        $this->addRoute('GET', '/api/stats', 'ApiController@stats', ['auth']);
        $this->addRoute('GET', '/api/alerts', 'ApiController@alerts', ['auth']);

        // API fournisseur (automatisation NestCorporation)
        $this->addRoute('POST', '/api/provider/subscriptions/run', 'ProviderController@apiRunAutomation', ['providerApi']);
        $this->addRoute('POST', '/api/provider/licenses/activate', 'ProviderController@apiActivateLicense', ['providerApi']);

        // Surveillance IA (cron)
        $this->addRoute('GET', '/cron/surveillance', 'ApiController@surveillance', ['cron']);
        $this->addRoute('GET', '/cron/subscriptions', 'ProviderController@cronRun', ['cron']);
    }
    
    /**
     * Ajoute une route
     */
    private function addRoute($method, $path, $handler, $middleware = []) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware
        ];
    }
    
    /**
     * Dispatch la requête
     */
    public function dispatch($uri) {
        // Nettoyer l'URI
        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
        $uri = str_replace('/index.php', '', $uri);
        $basePath = rtrim(str_replace('index.php', '', $_SERVER['SCRIPT_NAME'] ?? ''), '/');

        if ($basePath !== '' && $basePath !== '/' && strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath));
        }

        $uri = '/' . trim($uri, '/');
        if ($uri === '') {
            $uri = '/';
        }
        
        // Chercher une route correspondante
        foreach ($this->routes as $route) {
            if ($route['method'] !== $this->requestMethod) {
                continue;
            }
            
            $pattern = $this->convertToRegex($route['path']);
            if (preg_match($pattern, $uri, $matches)) {
                // Extraire les paramètres
                array_shift($matches);
                $this->params = $matches;
                
                // Exécuter les middlewares
                if (!$this->runMiddleware($route['middleware'])) {
                    return;
                }
                
                // Exécuter le contrôleur
                $this->executeHandler($route['handler']);
                return;
            }
        }
        
        // Route non trouvée
        $this->notFound();
    }
    
    /**
     * Convertit une route en regex
     */
    private function convertToRegex($route) {
        $route = preg_replace('/\//', '\/', $route);
        $route = preg_replace('/\{([a-z]+)\}/', '([^\/]+)', $route);
        return '/^' . $route . '$/';
    }
    
    /**
     * Exécute les middlewares
     */
    private function runMiddleware($middleware) {
        foreach ($middleware as $mw) {
            $class = "App\\Middleware\\" . ucfirst($mw) . "Middleware";
            if (class_exists($class)) {
                if (!$class::handle()) {
                    return false;
                }
            }
        }
        return true;
    }
    
    /**
     * Exécute le handler
     */
    private function executeHandler($handler) {
        list($controller, $method) = explode('@', $handler);
        $controller = "App\\Controllers\\" . $controller;
        
        if (class_exists($controller)) {
            $instance = new $controller();
            if (method_exists($instance, $method)) {
                call_user_func_array([$instance, $method], $this->params);
                return;
            }
        }
        
        $this->notFound();
    }
    
    /**
     * Page 404
     */
    private function notFound() {
        http_response_code(404);
        include APP_PATH . '/views/pages/errors/404.php';
        exit;
    }
}
