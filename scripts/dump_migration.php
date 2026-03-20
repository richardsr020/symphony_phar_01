<?php
$db = new PDO('sqlite:' . __DIR__ . '/../database/symphony.sqlite');
$migrationFile = __DIR__ . '/../database/migrations/20260306120000_add_invoices_downloaded_at.php';
$checksum = hash_file('sha256', $migrationFile);
$stmt = $db->query("SELECT migration, checksum, executed_at FROM schema_migrations WHERE migration = '20260306120000_add_invoices_downloaded_at'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$output = [
    'db' => $rows,
    'file' => ['path' => $migrationFile, 'sha256' => $checksum],
];
echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
