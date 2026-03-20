<?php

namespace App\Core;

class AuditLogger
{
    public static function log(
        int $userId,
        string $action,
        ?string $tableName = null,
        ?int $recordId = null,
        ?array $oldData = null,
        ?array $newData = null
    ): void {
        if ($userId <= 0 || trim($action) === '') {
            return;
        }

        $db = Database::getInstance();
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $encodedOld = $oldData !== null ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null;
        $encodedNew = $newData !== null ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null;

        if ($encodedOld === false) {
            $encodedOld = null;
        }
        if ($encodedNew === false) {
            $encodedNew = null;
        }

        try {
            $db->execute(
                'INSERT INTO audit_logs (user_id, action, table_name, record_id, old_data, new_data, ip_address, user_agent)
                 VALUES (:user_id, :action, :table_name, :record_id, :old_data, :new_data, :ip_address, :user_agent)',
                [
                    'user_id' => $userId,
                    'action' => substr($action, 0, 100),
                    'table_name' => $tableName !== null ? substr($tableName, 0, 50) : null,
                    'record_id' => $recordId,
                    'old_data' => $encodedOld,
                    'new_data' => $encodedNew,
                    'ip_address' => $ip !== '' ? substr($ip, 0, 45) : null,
                    'user_agent' => $userAgent !== '' ? substr($userAgent, 0, 1000) : null,
                ]
            );
        } catch (\Throwable $exception) {
            // Ne jamais bloquer le flux métier si le log échoue.
            if (class_exists('\\App\\Core\\AppLogger')) {
                AppLogger::error('Audit log write failed', [
                    'user_id' => $userId,
                    'action' => $action,
                    'table_name' => $tableName,
                    'record_id' => $recordId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
