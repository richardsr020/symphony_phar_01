<?php

namespace App\Models;

class AiUserNote extends Model
{
    public function create(int $companyId, int $userId, string $title, string $content, string $source = 'ai'): int
    {
        $title = trim($title);
        $content = trim($content);
        if ($companyId <= 0 || $userId <= 0) {
            throw new \InvalidArgumentException('company_id / user_id invalides.');
        }
        if ($content === '') {
            throw new \InvalidArgumentException('Contenu de note obligatoire.');
        }

        $this->db->execute(
            'INSERT INTO ai_user_notes (company_id, user_id, title, content, source)
             VALUES (:company_id, :user_id, :title, :content, :source)',
            [
                'company_id' => $companyId,
                'user_id' => $userId,
                'title' => $title !== '' ? substr($title, 0, 180) : null,
                'content' => $content,
                'source' => substr(trim($source) !== '' ? $source : 'ai', 0, 30),
            ]
        );

        return $this->db->lastInsertId();
    }

    public function listForUser(int $companyId, int $userId, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        return $this->db->fetchAll(
            'SELECT id, title, content, source, created_at
             FROM ai_user_notes
             WHERE company_id = :company_id
               AND user_id = :user_id
             ORDER BY created_at DESC, id DESC
             LIMIT ' . (int) $limit,
            [
                'company_id' => $companyId,
                'user_id' => $userId,
            ]
        );
    }
}

