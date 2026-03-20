<?php
$db = new PDO('sqlite:' . __DIR__ . '/../database/symphony.sqlite');
$checksum = '3519545529114b917a33af85716393107d5bff088ac4f36b8b7a71c62576d288';
$db->exec("UPDATE schema_migrations SET checksum = '$checksum' WHERE migration = '20260306120000_add_invoices_downloaded_at'");
echo "Updated checksum for migration 20260306120000_add_invoices_downloaded_at\n";
