<?php

namespace App\Models;

class ChatConversation extends Model
{
    public function getOrCreateLatest(int $companyId, int $userId): array
    {
        $conversation = $this->db->fetchOne(
            'SELECT id, company_id, user_id, provider, model, title, memory_summary, created_at, updated_at, last_message_at
             FROM chat_conversations
             WHERE company_id = :company_id
               AND user_id = :user_id
             ORDER BY last_message_at DESC, id DESC
             LIMIT 1',
            [
                'company_id' => $companyId,
                'user_id' => $userId,
            ]
        );

        if ($conversation !== null) {
            return $conversation;
        }

        $this->db->execute(
            'INSERT INTO chat_conversations (company_id, user_id, provider, model, title, memory_summary)
             VALUES (:company_id, :user_id, :provider, :model, :title, :memory_summary)',
            [
                'company_id' => $companyId,
                'user_id' => $userId,
                'provider' => (string) (\Config::AI_DEFAULT_PROVIDER ?? 'internal'),
                'model' => 'symphony-accountant-v1',
                'title' => 'Conversation principale',
                'memory_summary' => null,
            ]
        );

        $createdId = $this->db->lastInsertId();
        return $this->getByIdForUser($companyId, $userId, $createdId) ?? [];
    }

    public function getByIdForUser(int $companyId, int $userId, int $conversationId): ?array
    {
        if ($conversationId <= 0) {
            return null;
        }

        return $this->db->fetchOne(
            'SELECT id, company_id, user_id, provider, model, title, memory_summary, created_at, updated_at, last_message_at
             FROM chat_conversations
             WHERE id = :id
               AND company_id = :company_id
               AND user_id = :user_id
             LIMIT 1',
            [
                'id' => $conversationId,
                'company_id' => $companyId,
                'user_id' => $userId,
            ]
        );
    }

    public function listForUser(int $companyId, int $userId, int $limit = 20): array
    {
        $limit = max(1, min(50, $limit));
        return $this->db->fetchAll(
            'SELECT c.id, c.title, c.provider, c.model, c.memory_summary, c.last_message_at,
                    (
                        SELECT m.content_json
                        FROM chat_messages m
                        WHERE m.conversation_id = c.id
                        ORDER BY m.id DESC
                        LIMIT 1
                    ) AS last_message_json
             FROM chat_conversations c
             WHERE c.company_id = :company_id
               AND c.user_id = :user_id
             ORDER BY c.last_message_at DESC, c.id DESC
             LIMIT ' . $limit,
            [
                'company_id' => $companyId,
                'user_id' => $userId,
            ]
        );
    }

    public function getMessages(int $companyId, int $userId, int $conversationId, int $limit = 120): array
    {
        $limit = max(1, min(300, $limit));
        return $this->db->fetchAll(
            'SELECT m.id, m.role, m.content_json, m.tool_calls_json, m.created_at
             FROM chat_messages m
             INNER JOIN chat_conversations c ON c.id = m.conversation_id
             WHERE m.company_id = :company_id
               AND m.user_id = :user_id
               AND m.conversation_id = :conversation_id
             ORDER BY m.id ASC
             LIMIT ' . $limit,
            [
                'company_id' => $companyId,
                'user_id' => $userId,
                'conversation_id' => $conversationId,
            ]
        );
    }

    public function appendMessage(
        int $companyId,
        int $userId,
        int $conversationId,
        string $role,
        array $content,
        array $toolCalls = []
    ): int {
        $this->db->execute(
            'INSERT INTO chat_messages (conversation_id, company_id, user_id, role, content_json, tool_calls_json)
             VALUES (:conversation_id, :company_id, :user_id, :role, :content_json, :tool_calls_json)',
            [
                'conversation_id' => $conversationId,
                'company_id' => $companyId,
                'user_id' => $userId,
                'role' => substr($role, 0, 30),
                'content_json' => json_encode($content, JSON_UNESCAPED_UNICODE),
                'tool_calls_json' => $toolCalls !== [] ? json_encode($toolCalls, JSON_UNESCAPED_UNICODE) : null,
            ]
        );

        $messageId = $this->db->lastInsertId();
        $this->touchConversation($conversationId);
        return $messageId;
    }

    public function updateMemorySummary(int $conversationId, string $summary): void
    {
        $this->db->execute(
            'UPDATE chat_conversations
             SET memory_summary = :memory_summary,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            [
                'memory_summary' => $summary,
                'id' => $conversationId,
            ]
        );
    }

    private function touchConversation(int $conversationId): void
    {
        $this->db->execute(
            'UPDATE chat_conversations
             SET last_message_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            ['id' => $conversationId]
        );
    }
}
