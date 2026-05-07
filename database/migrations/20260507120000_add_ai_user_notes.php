<?php

use App\Core\MigrationContext;

return [
    'description' => 'Add AI user notes (persisted per user).',
    'up' => static function (MigrationContext $context): void {
        if ($context->tableExists('ai_user_notes')) {
            return;
        }

        if ($context->driver() === 'sqlite') {
            $context->execute(
                'CREATE TABLE ai_user_notes (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    company_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    title TEXT NULL,
                    content TEXT NOT NULL,
                    source TEXT NOT NULL DEFAULT "ai",
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )'
            );
            $context->execute('CREATE INDEX idx_ai_user_notes_company_user ON ai_user_notes(company_id, user_id, created_at)');
            return;
        }

        $context->execute(
            'CREATE TABLE ai_user_notes (
                id INT PRIMARY KEY AUTO_INCREMENT,
                company_id INT NOT NULL,
                user_id INT NOT NULL,
                title VARCHAR(180) NULL,
                content TEXT NOT NULL,
                source VARCHAR(30) NOT NULL DEFAULT "ai",
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_ai_user_notes_company_user (company_id, user_id, created_at)
            )'
        );
    },
];

