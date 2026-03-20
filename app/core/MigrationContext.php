<?php

namespace App\Core;

class MigrationContext
{
    private Database $database;
    private string $driver;

    public function __construct(Database $database, string $driver)
    {
        $this->database = $database;
        $this->driver = strtolower(trim($driver));
    }

    public function getDatabase(): Database
    {
        return $this->database;
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function execute(string $sql, array $params = []): bool
    {
        return $this->database->execute($sql, $params);
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        return $this->database->fetchOne($sql, $params);
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->database->fetchAll($sql, $params);
    }

    public function tableExists(string $table): bool
    {
        $safeTable = $this->normalizeIdentifier($table);

        if ($this->driver === 'sqlite') {
            $row = $this->database->fetchOne(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name LIMIT 1",
                ['name' => $safeTable]
            );

            return $row !== null;
        }

        $row = $this->database->fetchOne(
            'SELECT TABLE_NAME
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = :schema
               AND TABLE_NAME = :table_name
             LIMIT 1',
            [
                'schema' => \Config::DB_NAME,
                'table_name' => $safeTable,
            ]
        );

        return $row !== null;
    }

    public function columnExists(string $table, string $column): bool
    {
        $safeTable = $this->normalizeIdentifier($table);
        $safeColumn = $this->normalizeIdentifier($column);

        if ($this->driver === 'sqlite') {
            $columns = $this->database->fetchAll(sprintf('PRAGMA table_info(%s)', $safeTable));
            foreach ($columns as $row) {
                if (($row['name'] ?? '') === $safeColumn) {
                    return true;
                }
            }

            return false;
        }

        $row = $this->database->fetchOne(
            'SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = :schema
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name
             LIMIT 1',
            [
                'schema' => \Config::DB_NAME,
                'table_name' => $safeTable,
                'column_name' => $safeColumn,
            ]
        );

        return $row !== null;
    }

    public function addColumnIfNotExists(string $table, string $column, string $definition): void
    {
        $safeTable = $this->normalizeIdentifier($table);
        $safeColumn = $this->normalizeIdentifier($column);

        if ($this->columnExists($safeTable, $safeColumn)) {
            return;
        }

        $this->database->execute(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $safeTable, $safeColumn, trim($definition)));
    }

    private function normalizeIdentifier(string $identifier): string
    {
        $trimmed = trim($identifier);
        if ($trimmed === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $trimmed)) {
            throw new \InvalidArgumentException('Identifiant SQL invalide: ' . $identifier);
        }

        return $trimmed;
    }
}
