<?php

namespace App\Models;

class AuditLog extends Model
{
    public function getByCompanyFiltered(int $companyId, array $filters = [], ?int $limit = 200, int $offset = 0): array
    {
        $params = ['company_id' => $companyId];
        $where = ['u.company_id = :company_id'];

        $userId = (int) ($filters['user_id'] ?? 0);
        if ($userId > 0) {
            $where[] = 'l.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        $dateFrom = $this->normalizeDate((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== null) {
            $where[] = 'l.created_at >= :date_from';
            $params['date_from'] = $dateFrom . ' 00:00:00';
        }

        $dateTo = $this->normalizeDate((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== null) {
            $where[] = 'l.created_at <= :date_to';
            $params['date_to'] = $dateTo . ' 23:59:59';
        }

        $hasMatricule = false;
        try {
            $columns = $this->db->fetchAll('PRAGMA table_info(users)');
            foreach ($columns as $column) {
                if ((string) ($column['name'] ?? '') === 'matricule') {
                    $hasMatricule = true;
                    break;
                }
            }
        } catch (\Throwable $exception) {
            $hasMatricule = false;
        }

        $matriculeSelect = $hasMatricule ? 'u.matricule' : 'NULL AS matricule';

        $sql = 'SELECT l.id,
                       l.user_id,
                       l.action,
                       l.table_name,
                       l.record_id,
                       l.old_data,
                       l.new_data,
                       l.ip_address,
                       l.user_agent,
                       l.created_at,
                       u.first_name,
                       u.last_name,
                       ' . $matriculeSelect . ',
                       u.email
                FROM audit_logs l
                LEFT JOIN users u ON u.id = l.user_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY l.created_at DESC, l.id DESC';

        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) max(1, min(5000, $limit));
        }
        if ($offset > 0) {
            $sql .= ' OFFSET ' . (int) max(0, $offset);
        }

        return $this->db->fetchAll($sql, $params);
    }

    private function normalizeDate(string $value): ?string
    {
        $raw = trim($value);
        if ($raw === '') {
            return null;
        }
        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return null;
        }
        return date('Y-m-d', $timestamp);
    }
}
