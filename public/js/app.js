/**
 * Symphony - Application principale
 * 100% vanilla, pas de frameworks
 */

// Attendre que le DOM soit chargé
document.addEventListener('DOMContentLoaded', function() {
    
    // Initialiser tous les modules
    Symphony.init();
    window.setTimeout(() => Symphony.hideLoader(), 220);
    
});

// Namespace global unique
const Symphony = {
    
    // Configuration
    config: {
        theme: localStorage.getItem('theme') || 'light',
        apiUrl: '/api',
        debug: true
    },
    
    // Initialisation
    init: function() {
        this.showLoader();
        this.initTheme();
        this.initNavigation();
        this.initAsyncNavigation();
        this.initForms();
        this.initAsyncForms();
        this.initAutoFilters();
        this.initTables();
        if (this.isPrivateAppPage()) {
            this.loadUserData();
        }
        this.initEventListeners();
    },

    initAutoFilters: function() {
        const forms = document.querySelectorAll('form[data-auto-filter="true"]');
        if (!forms.length) return;

        forms.forEach((form) => {
            if (form.dataset.autoFilterBound === '1') {
                return;
            }
            form.dataset.autoFilterBound = '1';
            const submitButton = form.querySelector('.js-auto-filter-submit');
            const resetPage = () => {
                const pageInput = form.querySelector('input[name="page"]');
                if (pageInput) pageInput.value = '1';
            };
            const submitNow = () => {
                resetPage();
                this.submitGetFormAjax(form);
            };
            const submitDebounced = this.debounce(submitNow, 450);

            form.querySelectorAll('select,input[type="date"]').forEach((field) => {
                field.addEventListener('change', submitNow);
            });

            form.querySelectorAll('input[type="text"],input[type="search"],input[type="number"]').forEach((field) => {
                field.addEventListener('input', submitDebounced);
                field.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        submitNow();
                    }
                });
            });

            if (submitButton) {
                submitButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    submitNow();
                });
            }
        });
    },

    initAsyncForms: function() {
        if (!this.isPrivateAppPage()) return;
        const forms = document.querySelectorAll('form');
        forms.forEach((form) => {
            if (form.dataset.asyncBound === '1') {
                return;
            }
            if (form.dataset.sync === 'true' || form.dataset.noAsync === 'true') {
                return;
            }

            const action = form.getAttribute('action') || '';
            if (action.includes('/logout') || action.includes('/provider/logout')) {
                return;
            }

            const hasFileInput = form.querySelector('input[type="file"]') !== null;
            const enctype = (form.getAttribute('enctype') || '').toLowerCase();
            if (hasFileInput || enctype.includes('multipart/form-data')) {
                return;
            }

            form.dataset.asyncBound = '1';
            form.addEventListener('submit', (event) => {
                if (event.defaultPrevented) {
                    return;
                }
                event.preventDefault();
                const method = (form.getAttribute('method') || 'GET').toUpperCase();
                if (method === 'GET') {
                    this.submitGetFormAjax(form);
                    return;
                }
                this.submitFormAjax(form);
            });
        });
    },

    initAsyncNavigation: function() {
        if (!this.isPrivateAppPage() || document.body.dataset.asyncNavBound === '1') {
            return;
        }

        document.body.dataset.asyncNavBound = '1';
        document.addEventListener('click', (event) => {
            const link = event.target.closest('a[href]');
            if (!link) return;
            if (link.dataset.noAsync === 'true') return;
            if (link.closest('#sidebar')) return;
            if (link.target && link.target !== '_self') return;
            if (link.hasAttribute('download')) return;

            const href = link.getAttribute('href') || '';
            if (href === '' || href.startsWith('#')) return;
            if (href.startsWith('mailto:') || href.startsWith('tel:')) return;
            if (href.includes('/logout') || href.includes('/provider/logout')) return;

            let url;
            try {
                url = new URL(href, window.location.origin);
            } catch {
                return;
            }
            if (url.origin !== window.location.origin) return;

            event.preventDefault();
            this.navigateWithoutReload(url.pathname + url.search + url.hash, true);
        });
    },

    submitGetFormAjax: function(form) {
        const action = form.getAttribute('action') || window.location.pathname;
        const params = new URLSearchParams(new FormData(form));
        const url = action + (params.toString() ? `?${params.toString()}` : '');
        this.navigateWithoutReload(url, true);
    },

    submitFormAjax: function(form) {
        const action = form.getAttribute('action') || window.location.pathname;
        const method = (form.getAttribute('method') || 'POST').toUpperCase();
        const body = new FormData(form);
        const successMessage = form.getAttribute('data-async-success') || '';

        this.showLoader();
        fetch(action, {
            method,
            body,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then((response) => Promise.all([response.text(), response.url]))
            .then(([html, responseUrl]) => {
                this.hideLoader();
                this.swapViewFromHtml(html);
                if (responseUrl) {
                    history.replaceState({ url: responseUrl }, '', responseUrl);
                    this.syncSidebarActiveState(responseUrl);
                } else {
                    this.syncSidebarActiveState(window.location.pathname);
                }
                if (successMessage !== '') {
                    this.showNotification(successMessage, 'success');
                }
            })
            .catch(() => {
                this.hideLoader();
                this.showError('Impossible d\'envoyer le formulaire.');
            });
    },

    navigateWithoutReload: function(url, pushState = false) {
        this.showLoader();
        fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then((response) => response.text())
            .then((html) => {
                this.hideLoader();
                this.swapViewFromHtml(html);
                if (pushState) {
                    history.pushState({ url }, '', url);
                }
                this.syncSidebarActiveState(url);
            })
            .catch(() => {
                this.hideLoader();
                window.location.href = url;
            });
    },

    swapViewFromHtml: function(html) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newView = doc.querySelector('#view-container');
        const currentView = document.querySelector('#view-container');
        if (!newView || !currentView) {
            window.location.reload();
            return;
        }

        currentView.innerHTML = newView.innerHTML;
        const titleNode = doc.querySelector('title');
        if (titleNode) {
            document.title = titleNode.textContent;
        }
        this.executeInlineScripts(currentView);
        this.initAsyncForms();
        this.initAutoFilters();
        this.initForms();
        this.initTables();
    },

    executeInlineScripts: function(container) {
        const scripts = Array.from(container.querySelectorAll('script'));
        scripts.forEach((oldScript) => {
            const newScript = document.createElement('script');
            if (oldScript.src) {
                newScript.src = oldScript.src;
                newScript.async = false;
            } else {
                newScript.textContent = oldScript.textContent;
            }
            oldScript.replaceWith(newScript);
        });
    },

    debounce: function(fn, wait = 300) {
        let timer = null;
        return (...args) => {
            if (timer) clearTimeout(timer);
            timer = setTimeout(() => fn(...args), wait);
        };
    },

    isPrivateAppPage: function() {
        return document.querySelector('.app') !== null;
    },
    
    // ===== GESTION DU THÈME =====
    initTheme: function() {
        document.documentElement.setAttribute('data-theme', this.config.theme);
        
        // Bouton de toggle
        const themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', () => this.toggleTheme());
        }
        
        // Détecter la préférence système
        if (!localStorage.getItem('theme')) {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            this.setTheme(prefersDark ? 'dark' : 'light');
        }
    },
    
    toggleTheme: function() {
        const newTheme = this.config.theme === 'light' ? 'dark' : 'light';
        this.setTheme(newTheme);
    },
    
    setTheme: function(theme) {
        this.config.theme = theme;
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        
        // Déclencher un événement personnalisé
        window.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme } }));
    },
    
    // ===== NAVIGATION =====
    initNavigation: function() {
        // Sidebar toggle sur mobile
        const menuToggle = document.getElementById('menu-toggle');
        const sidebar = document.getElementById('sidebar');
        
        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', (e) => {
                e.preventDefault();
                sidebar.classList.toggle('open');
            });
        }
        
        // Navigation stack (pour mobile)
        this.setupHistoryAPI();
        this.syncSidebarActiveState(window.location.pathname);
    },
    
    setupHistoryAPI: function() {
        // Gérer le bouton retour du navigateur
        window.addEventListener('popstate', (event) => {
            if (event.state && event.state.url) {
                this.navigateWithoutReload(event.state.url, false);
            } else if (event.state && event.state.view) {
                this.loadView(event.state.view, event.state.data);
            } else {
                this.navigateWithoutReload(window.location.href, false);
            }
        });
    },

    syncSidebarActiveState: function(urlLike) {
        const navItems = Array.from(document.querySelectorAll('#sidebar .nav-item[data-nav-route]'));
        if (navItems.length === 0) {
            return;
        }

        let pathname = window.location.pathname;
        if (typeof urlLike === 'string' && urlLike.trim() !== '') {
            try {
                const parsed = new URL(urlLike, window.location.origin);
                pathname = parsed.pathname;
            } catch {
                pathname = String(urlLike);
            }
        }

        pathname = pathname.replace('/index.php', '');
        if (pathname.length > 1 && pathname.endsWith('/')) {
            pathname = pathname.slice(0, -1);
        }
        if (pathname === '') {
            pathname = '/';
        }

        const matchesRoute = (candidate) => {
            const route = String(candidate || '').trim();
            if (route === '') {
                return false;
            }
            if (route === '/') {
                return pathname === '/';
            }

            return pathname === route || pathname.startsWith(`${route}/`);
        };

        navItems.forEach((item) => {
            const route = item.dataset.navRoute || '';
            const aliases = (item.dataset.navAliases || '')
                .split(',')
                .map((value) => value.trim())
                .filter(Boolean);
            const isActive = matchesRoute(route) || aliases.some((alias) => matchesRoute(alias));
            item.classList.toggle('active', isActive);
        });
    },

    pushView: function(view, data = {}) {
        // Ajouter à l'historique
        history.pushState({ view, data }, '', `?view=${view}`);
        
        // Charger la vue
        this.loadView(view, data);
        
        // Ajuster le layout
        document.body.classList.add('view-open');
    },
    
    popView: function() {
        history.back();
    },
    
    loadView: function(view, data) {
        // Afficher un loader
        this.showLoader();
        
        // Charger la vue via AJAX
        fetch(`${this.config.apiUrl}/view/${view}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.getCsrfToken()
            },
            body: JSON.stringify(data)
        })
        .then(response => response.text())
        .then(html => {
            this.hideLoader();
            
            const container = document.getElementById('view-container');
            if (container) {
                container.innerHTML = html;
                container.classList.add('fade-in');
            }
        })
        .catch(error => {
            this.hideLoader();
            this.showError('Erreur lors du chargement de la vue');
        });
    },
    
    // ===== CHAT IA =====
    initChat: function() {
        const chatBtn = document.getElementById('chat-toggle');
        const chatContainer = document.getElementById('chat-container');
        const chatClose = document.getElementById('chat-close');
        const chatInput = document.getElementById('chat-input');
        const chatSend = document.getElementById('chat-send');
        const chatMessages = document.getElementById('chat-messages');
        const suggestions = document.querySelectorAll('.suggestion');
        
        // Bouton flottant: ouvrir l'onglet dedie /chat
        if (chatBtn) {
            chatBtn.addEventListener('click', (event) => {
                event.preventDefault();
                const currentPath = window.location.pathname.replace('/index.php', '');
                if (currentPath === '/chat') {
                    if (chatInput) {
                        chatInput.focus();
                    }
                    return;
                }
                this.navigateWithoutReload('/chat', true);
            });
        }
        
        // Fermer le chat
        if (chatClose && chatContainer) {
            chatClose.addEventListener('click', () => {
                chatContainer.classList.remove('open');
            });
        }
        
        // Envoyer un message
        const sendMessage = () => {
            const message = chatInput.value.trim();
            if (!message) return;
            
            // Afficher le message utilisateur
            this.addMessage(message, 'user');
            chatInput.value = '';
            
            // Simuler la réponse de l'IA
            this.showTypingIndicator();
            
            // Appel API réel
            fetch(`${this.config.apiUrl}/chat`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCsrfToken()
                },
                body: JSON.stringify({ message })
            })
            .then(response => response.json())
            .then(data => {
                this.hideTypingIndicator();
                this.addMessage(data.response, 'ai');
            })
            .catch(() => {
                this.hideTypingIndicator();
                this.addMessage("Désolé, je n'ai pas pu traiter votre demande pour le moment.", 'ai');
            });
        };
        
        if (chatSend && chatInput) {
            chatSend.addEventListener('click', sendMessage);
            chatInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') sendMessage();
            });
        }
        
        // Suggestions
        suggestions.forEach(suggestion => {
            suggestion.addEventListener('click', () => {
                chatInput.value = suggestion.textContent;
                sendMessage();
            });
        });
    },
    
    addMessage: function(text, type) {
        const messages = document.getElementById('chat-messages');
        if (!messages) return;
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `message message-${type}`;
        messageDiv.textContent = text;
        
        messages.appendChild(messageDiv);
        messages.scrollTop = messages.scrollHeight;
    },
    
    showTypingIndicator: function() {
        const messages = document.getElementById('chat-messages');
        if (!messages) return;
        
        const typingDiv = document.createElement('div');
        typingDiv.className = 'message message-ai typing';
        typingDiv.id = 'typing-indicator';
        typingDiv.innerHTML = '<span></span><span></span><span></span>';
        
        messages.appendChild(typingDiv);
        messages.scrollTop = messages.scrollHeight;
    },
    
    hideTypingIndicator: function() {
        const indicator = document.getElementById('typing-indicator');
        if (indicator) indicator.remove();
    },
    
    // ===== FORMULAIRES =====
    initForms: function() {
        // Auto-save pour les formulaires longs
        const forms = document.querySelectorAll('[data-auto-save]');
        forms.forEach(form => {
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.addEventListener('change', () => {
                    this.autoSaveForm(form);
                });
            });
        });
        
        // Validation en temps réel
        const validateInputs = document.querySelectorAll('[data-validate]');
        validateInputs.forEach(input => {
            input.addEventListener('blur', () => {
                this.validateField(input);
            });
        });
    },
    
    validateField: function(field) {
        const rules = field.dataset.validate.split(' ');
        let isValid = true;
        let errorMessage = '';
        
        rules.forEach(rule => {
            if (rule === 'required' && !field.value.trim()) {
                isValid = false;
                errorMessage = 'Ce champ est requis';
            }
            
            if (rule === 'email' && field.value) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(field.value)) {
                    isValid = false;
                    errorMessage = 'Email invalide';
                }
            }
            
            if (rule.startsWith('min:')) {
                const min = parseInt(rule.split(':')[1]);
                if (field.value.length < min) {
                    isValid = false;
                    errorMessage = `Minimum ${min} caractères`;
                }
            }
        });
        
        // Afficher l'erreur
        const errorDiv = field.nextElementSibling?.classList.contains('field-error') 
            ? field.nextElementSibling 
            : document.createElement('div');
            
        if (!isValid) {
            field.classList.add('error');
            errorDiv.className = 'field-error';
            errorDiv.textContent = errorMessage;
            field.parentNode.insertBefore(errorDiv, field.nextSibling);
        } else {
            field.classList.remove('error');
            if (errorDiv.parentNode) errorDiv.remove();
        }
        
        return isValid;
    },
    
    // ===== TABLEAUX =====
    initTables: function() {
        const tables = document.querySelectorAll('.table');
        tables.forEach(table => {
            // Tri des colonnes
            const headers = table.querySelectorAll('th[data-sort]');
            headers.forEach(header => {
                header.addEventListener('click', () => {
                    this.sortTable(table, header);
                });
            });
            
            // Lignes cliquables
            const rows = table.querySelectorAll('tbody tr[data-href]');
            rows.forEach(row => {
                row.addEventListener('click', (e) => {
                    if (!e.target.closest('a, button')) {
                        window.location.href = row.dataset.href;
                    }
                });
            });
        });
    },
    
    sortTable: function(table, header) {
        const index = Array.from(header.parentNode.children).indexOf(header);
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const direction = header.dataset.direction === 'asc' ? 'desc' : 'asc';
        
        // Trier les lignes
        rows.sort((a, b) => {
            const aVal = a.children[index].textContent.trim();
            const bVal = b.children[index].textContent.trim();
            
            // Détecter si c'est un nombre
            if (!isNaN(parseFloat(aVal)) && !isNaN(parseFloat(bVal))) {
                return direction === 'asc' 
                    ? parseFloat(aVal) - parseFloat(bVal)
                    : parseFloat(bVal) - parseFloat(aVal);
            }
            
            // Tri alphabétique
            return direction === 'asc'
                ? aVal.localeCompare(bVal)
                : bVal.localeCompare(aVal);
        });
        
        // Réinsérer les lignes triées
        rows.forEach(row => tbody.appendChild(row));
        
        // Mettre à jour les indicateurs
        table.querySelectorAll('th[data-sort]').forEach(th => {
            th.dataset.direction = '';
        });
        header.dataset.direction = direction;
    },
    
    // ===== API =====
    callApi: function(endpoint, method = 'GET', data = null) {
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.getCsrfToken()
            }
        };
        
        if (data) {
            options.body = JSON.stringify(data);
        }
        
        return fetch(`${this.config.apiUrl}${endpoint}`, options)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erreur API');
                }
                return response.json();
            });
    },
    
    // ===== UTILITAIRES =====
    getCsrfToken: function() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    },
    
    showLoader: function() {
        const loader = document.getElementById('page-loader') || document.getElementById('global-loader') || this.createLoader();
        loader.classList.add('visible');
        loader.classList.add('is-visible');
    },
    
    hideLoader: function() {
        const pageLoader = document.getElementById('page-loader');
        if (pageLoader) {
            pageLoader.classList.remove('visible');
            pageLoader.classList.remove('is-visible');
        }
        const loader = document.getElementById('global-loader');
        if (loader) {
            loader.classList.remove('visible');
            loader.classList.remove('is-visible');
        }
    },
    
    createLoader: function() {
        const loader = document.createElement('div');
        loader.id = 'global-loader';
        loader.className = 'page-loader is-visible';
        loader.setAttribute('aria-hidden', 'true');
        loader.innerHTML = `
            <div class="loader-water-scene" role="status" aria-label="Chargement en cours">
                <span class="loader-drop"></span>
                <span class="loader-splash"></span>
                <span class="loader-water-surface"></span>
                <span class="loader-ripple loader-ripple-1"></span>
                <span class="loader-ripple loader-ripple-2"></span>
                <span class="loader-ripple loader-ripple-3"></span>
            </div>
        `;
        document.body.appendChild(loader);
        return loader;
    },
    
    showError: function(message) {
        // Créer une notification toast
        const toast = document.createElement('div');
        toast.className = 'toast error';
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--danger);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            z-index: 9999;
            animation: slideIn 0.3s;
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    },
    
    loadUserData: function() {
        // Charger les données utilisateur pour l'IA
        this.callApi('/user/context')
            .then(data => {
                if (data.alerts && data.alerts.length > 0) {
                    this.showNotification(`${data.alerts.length} alerte(s)`, 'warning');
                }
            })
            .catch(() => {});
    },
    
    showNotification: function(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        const colors = {
            info: 'var(--info, #3B82F6)',
            success: 'var(--success, #10B981)',
            warning: 'var(--warning, #F59E0B)',
            error: 'var(--danger, #EF4444)'
        };

        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${colors[type] || colors.info};
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            z-index: 9999;
            animation: slideIn 0.3s;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.18);
        `;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s';
            setTimeout(() => toast.remove(), 300);
        }, 2200);
    },

    closeCurrentView: function() {
        document.body.classList.remove('view-open');
    },
    
    initEventListeners: function() {
        // Écouter les événements personnalisés
        window.addEventListener('themeChanged', (e) => {
            if (this.config.debug) {
                console.log('Theme changed to:', e.detail.theme);
            }
        });
    }
};

// Exposer globalement
window.Symphony = Symphony;
