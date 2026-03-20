<?php

namespace App\Models;

class AiResource extends Model
{
    public function ensureDefaultsForCompany(int $companyId): void
    {
        if ($companyId <= 0) {
            return;
        }

        $prompts = \Config::AI_SYSTEM_PROMPTS ?? [];
        if (is_array($prompts)) {
            foreach ($prompts as $key => $content) {
                $this->upsert($companyId, (string) $key, 'prompt', (string) $content, 'Prompt ' . (string) $key);
            }
        }

        $knowledgeDir = APP_PATH . '/ai/knowledge';
        if (is_dir($knowledgeDir)) {
            $files = glob($knowledgeDir . '/*.txt') ?: [];
            foreach ($files as $filePath) {
                $filename = pathinfo($filePath, PATHINFO_FILENAME);
                $resourceKey = trim((string) $filename);
                if ($resourceKey === '') {
                    continue;
                }
                $content = (string) file_get_contents($filePath);
                $this->upsert($companyId, $resourceKey, 'knowledge', $content, 'Connaissance ' . $resourceKey);
            }
        }
    }

    public function getByType(int $companyId, string $type): array
    {
        return $this->db->fetchAll(
            'SELECT id, resource_key, resource_type, title, content, is_active, updated_at
             FROM ai_resources
             WHERE company_id = :company_id
               AND resource_type = :resource_type
             ORDER BY resource_key ASC',
            [
                'company_id' => $companyId,
                'resource_type' => $type,
            ]
        );
    }

    public function getContentMapByType(int $companyId, string $type): array
    {
        $rows = $this->db->fetchAll(
            'SELECT resource_key, content
             FROM ai_resources
             WHERE company_id = :company_id
               AND resource_type = :resource_type
               AND is_active = :is_active',
            [
                'company_id' => $companyId,
                'resource_type' => $type,
                'is_active' => 1,
            ]
        );
        $map = [];
        foreach ($rows as $row) {
            $key = (string) ($row['resource_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $map[$key] = (string) ($row['content'] ?? '');
        }
        return $map;
    }

    public function getContent(int $companyId, string $type, string $key, string $fallback = ''): string
    {
        $row = $this->db->fetchOne(
            'SELECT content
             FROM ai_resources
             WHERE company_id = :company_id
               AND resource_type = :resource_type
               AND resource_key = :resource_key
               AND is_active = :is_active
             LIMIT 1',
            [
                'company_id' => $companyId,
                'resource_type' => $type,
                'resource_key' => $key,
                'is_active' => 1,
            ]
        );

        if ($row === null) {
            return $fallback;
        }
        return (string) ($row['content'] ?? $fallback);
    }

    public function saveBulk(int $companyId, string $type, array $payload): void
    {
        foreach ($payload as $key => $value) {
            $resourceKey = trim((string) $key);
            if ($resourceKey === '') {
                continue;
            }
            $content = trim((string) $value);
            $this->upsert($companyId, $resourceKey, $type, $content, ucfirst($type) . ' ' . $resourceKey);
        }
    }

    private function upsert(int $companyId, string $key, string $type, string $content, string $title): void
    {
        $existing = $this->db->fetchOne(
            'SELECT id
             FROM ai_resources
             WHERE company_id = :company_id
               AND resource_type = :resource_type
               AND resource_key = :resource_key
             LIMIT 1',
            [
                'company_id' => $companyId,
                'resource_type' => $type,
                'resource_key' => $key,
            ]
        );

        if ($existing === null) {
            $this->db->execute(
                'INSERT INTO ai_resources (company_id, resource_key, resource_type, title, content, is_active)
                 VALUES (:company_id, :resource_key, :resource_type, :title, :content, :is_active)',
                [
                    'company_id' => $companyId,
                    'resource_key' => substr($key, 0, 120),
                    'resource_type' => substr($type, 0, 40),
                    'title' => substr($title, 0, 180),
                    'content' => $content,
                    'is_active' => 1,
                ]
            );
            return;
        }

        $this->db->execute(
            'UPDATE ai_resources
             SET title = :title,
                 content = :content,
                 is_active = :is_active,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            [
                'title' => substr($title, 0, 180),
                'content' => $content,
                'is_active' => 1,
                'id' => (int) ($existing['id'] ?? 0),
            ]
        );
    }
}
