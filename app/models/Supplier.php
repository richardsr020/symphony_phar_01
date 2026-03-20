<?php

namespace App\Models;

class Supplier extends Model
{
    public function getSupplierLotsByDateRange(int $companyId, string $fromDate, string $toDate): array
    {
        if ($companyId <= 0 || $fromDate === '' || $toDate === '') {
            return [];
        }

        $sql = 'SELECT l.id,
                       l.product_id,
                       p.name AS product_name,
                       p.unit AS base_unit,
                       l.lot_code,
                       l.supplier,
                       l.quantity_initial_base,
                       l.quantity_remaining_base,
                       l.unit_cost_base,
                       l.expiration_date,
                       l.opened_at,
                       l.exhausted_at,
                       COALESCE(l.is_declassified, 0) AS is_declassified
                FROM stock_lots l
                INNER JOIN products p ON p.id = l.product_id
                WHERE l.company_id = :company_id
                  AND p.company_id = :company_id
                  AND l.supplier IS NOT NULL
                  AND TRIM(l.supplier) <> \'\'
                  AND DATE(l.opened_at) BETWEEN :from_date AND :to_date
                ORDER BY l.opened_at DESC, l.id DESC';

        return $this->db->fetchAll($sql, [
            'company_id' => $companyId,
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]);
    }
}
