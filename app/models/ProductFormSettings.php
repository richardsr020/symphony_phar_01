<?php

namespace App\Models;

use Throwable;

class ProductFormSettings extends Model
{
    public const CONFIG_VERSION = 1;

    public function getForCompany(int $companyId): array
    {
        if ($companyId <= 0) {
            return self::defaultConfig();
        }

        try {
            $row = $this->db->fetchOne(
                'SELECT config_json
                 FROM company_product_form_settings
                 WHERE company_id = :company_id
                 LIMIT 1',
                ['company_id' => $companyId]
            );
        } catch (Throwable $exception) {
            return self::defaultConfig();
        }

        if (!is_array($row) || trim((string) ($row['config_json'] ?? '')) === '') {
            return self::defaultConfig();
        }

        $decoded = json_decode((string) $row['config_json'], true);
        if (!is_array($decoded)) {
            return self::defaultConfig();
        }

        return self::normalizeConfig($decoded);
    }

    public function saveForCompany(int $companyId, array $config): bool
    {
        if ($companyId <= 0) {
            throw new \InvalidArgumentException('Company invalide.');
        }

        $normalized = self::normalizeConfig($config);
        $json = json_encode($normalized, JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || $json === '') {
            throw new \InvalidArgumentException('Configuration invalide.');
        }

        $driver = strtolower((string) (\Config::DB_DRIVER ?? 'sqlite'));
        if ($driver === 'sqlite') {
            $this->db->execute(
                'INSERT INTO company_product_form_settings (company_id, config_json, created_at, updated_at)
                 VALUES (:company_id, :config_json, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                 ON CONFLICT(company_id)
                 DO UPDATE SET config_json = excluded.config_json, updated_at = CURRENT_TIMESTAMP',
                [
                    'company_id' => $companyId,
                    'config_json' => $json,
                ]
            );

            return true;
        }

        $this->db->execute(
            'INSERT INTO company_product_form_settings (company_id, config_json)
             VALUES (:company_id, :config_json)
             ON DUPLICATE KEY UPDATE config_json = VALUES(config_json), updated_at = CURRENT_TIMESTAMP',
            [
                'company_id' => $companyId,
                'config_json' => $json,
            ]
        );

        return true;
    }

    public static function fromPost(array $post): array
    {
        $baseUnitsRaw = (string) ($post['pf_base_units'] ?? '');
        $formesRaw = (string) ($post['pf_formes'] ?? '');

        $baseUnits = self::parseBaseUnits($baseUnitsRaw);
        $formes = self::parseSimpleList($formesRaw, 80);

        $fields = [
            'name' => [
                'enabled' => true,
                'required' => true,
                'label' => self::normalizeLabel((string) ($post['pf_field_name_label'] ?? 'Nom du produit')),
                'placeholder' => self::normalizePlaceholder((string) ($post['pf_field_name_placeholder'] ?? 'Ex: Produit A')),
            ],
            'supplier' => [
                'enabled' => isset($post['pf_field_supplier_enabled']),
                'required' => isset($post['pf_field_supplier_required']),
                'label' => self::normalizeLabel((string) ($post['pf_field_supplier_label'] ?? 'Fournisseur')),
                'placeholder' => self::normalizePlaceholder((string) ($post['pf_field_supplier_placeholder'] ?? 'Ex: Fournisseur A')),
            ],
            'dosage' => [
                'enabled' => isset($post['pf_field_dosage_enabled']),
                'required' => isset($post['pf_field_dosage_required']),
                'label' => self::normalizeLabel((string) ($post['pf_field_dosage_label'] ?? 'Spécification')),
                'placeholder' => self::normalizePlaceholder((string) ($post['pf_field_dosage_placeholder'] ?? 'Ex: Variante / dimension / référence')),
            ],
            'forme' => [
                'enabled' => isset($post['pf_field_forme_enabled']),
                'required' => isset($post['pf_field_forme_required']),
                'label' => self::normalizeLabel((string) ($post['pf_field_forme_label'] ?? 'Forme')),
                'placeholder' => self::normalizePlaceholder((string) ($post['pf_field_forme_placeholder'] ?? 'Ex: Type / variante')),
                'input' => self::normalizeInputType((string) ($post['pf_field_forme_input'] ?? 'text')),
            ],
            'presentation' => [
                'enabled' => isset($post['pf_field_presentation_enabled']),
                'required' => isset($post['pf_field_presentation_required']),
                'label' => self::normalizeLabel((string) ($post['pf_field_presentation_label'] ?? 'Présentation')),
                'placeholder' => self::normalizePlaceholder((string) ($post['pf_field_presentation_placeholder'] ?? 'Ex: Conditionnement / détail')),
            ],
            'base_unit' => [
                'enabled' => true,
                'required' => true,
                'label' => self::normalizeLabel((string) ($post['pf_field_base_unit_label'] ?? 'Unité de base')),
            ],
        ];

        $defaultBaseUnit = self::normalizeUnitCode((string) ($post['pf_default_base_unit'] ?? ''));
        if ($defaultBaseUnit === '' && $baseUnits !== []) {
            $defaultBaseUnit = (string) ($baseUnits[0]['code'] ?? 'unite');
        }
        if ($defaultBaseUnit === '') {
            $defaultBaseUnit = 'unite';
        }

        return self::normalizeConfig([
            'version' => self::CONFIG_VERSION,
            'base_units' => $baseUnits,
            'formes' => $formes,
            'defaults' => [
                'base_unit_code' => $defaultBaseUnit,
            ],
            'fields' => $fields,
        ]);
    }

    public static function defaultConfig(): array
    {
        return [
            'version' => self::CONFIG_VERSION,
            'base_units' => [
                ['code' => 'unite', 'label' => 'Unité'],
                ['code' => 'kg', 'label' => 'Kilogramme'],
                ['code' => 'g', 'label' => 'Gramme'],
                ['code' => 'l', 'label' => 'Litre'],
                ['code' => 'ml', 'label' => 'Millilitre'],
            ],
            'formes' => [],
            'defaults' => [
                'base_unit_code' => 'unite',
            ],
            'fields' => [
                'name' => [
                    'enabled' => true,
                    'required' => true,
                    'label' => 'Nom du produit',
                    'placeholder' => 'Ex: Produit A',
                ],
                'supplier' => [
                    'enabled' => true,
                    'required' => false,
                    'label' => 'Fournisseur',
                    'placeholder' => 'Ex: Fournisseur A',
                ],
                'dosage' => [
                    'enabled' => true,
                    'required' => false,
                    'label' => 'Spécification',
                    'placeholder' => 'Ex: Variante / dimension / référence',
                ],
                'forme' => [
                    'enabled' => true,
                    'required' => false,
                    'label' => 'Forme',
                    'placeholder' => 'Ex: Type / variante',
                    'input' => 'text',
                ],
                'presentation' => [
                    'enabled' => true,
                    'required' => false,
                    'label' => 'Présentation',
                    'placeholder' => 'Ex: Conditionnement / détail',
                ],
                'base_unit' => [
                    'enabled' => true,
                    'required' => true,
                    'label' => 'Unité de base',
                ],
            ],
        ];
    }

    public static function normalizeConfig(array $config): array
    {
        $defaults = self::defaultConfig();

        $version = (int) ($config['version'] ?? self::CONFIG_VERSION);
        if ($version <= 0) {
            $version = self::CONFIG_VERSION;
        }

        $baseUnits = is_array($config['base_units'] ?? null) ? $config['base_units'] : [];
        $normalizedBaseUnits = [];
        $seen = [];
        foreach ($baseUnits as $unit) {
            if (!is_array($unit)) {
                continue;
            }
            $code = self::normalizeUnitCode((string) ($unit['code'] ?? ''));
            if ($code === '') {
                continue;
            }
            if (isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            $label = trim((string) ($unit['label'] ?? ''));
            if ($label === '') {
                $label = $code;
            }
            $normalizedBaseUnits[] = [
                'code' => $code,
                'label' => substr($label, 0, 60),
            ];
        }
        if ($normalizedBaseUnits === []) {
            $normalizedBaseUnits = $defaults['base_units'];
        }

        $formes = is_array($config['formes'] ?? null) ? $config['formes'] : [];
        $normalizedFormes = [];
        $seenFormes = [];
        foreach ($formes as $forme) {
            $value = trim((string) $forme);
            if ($value === '') {
                continue;
            }
            $key = mb_strtolower($value);
            if (isset($seenFormes[$key])) {
                continue;
            }
            $seenFormes[$key] = true;
            $normalizedFormes[] = substr($value, 0, 80);
        }

        $fields = is_array($config['fields'] ?? null) ? $config['fields'] : [];
        $fieldDefaults = $defaults['fields'];
        $normalizedFields = [];

        foreach ($fieldDefaults as $key => $definition) {
            $incoming = is_array($fields[$key] ?? null) ? $fields[$key] : [];
            $normalized = [
                'enabled' => (bool) ($incoming['enabled'] ?? $definition['enabled'] ?? true),
                'required' => (bool) ($incoming['required'] ?? $definition['required'] ?? false),
                'label' => self::normalizeLabel((string) ($incoming['label'] ?? $definition['label'] ?? ucfirst($key))),
            ];

            if (isset($definition['placeholder']) || array_key_exists('placeholder', $incoming)) {
                $normalized['placeholder'] = self::normalizePlaceholder((string) ($incoming['placeholder'] ?? $definition['placeholder'] ?? ''));
            }

            if ($key === 'forme') {
                $normalized['input'] = self::normalizeInputType((string) ($incoming['input'] ?? $definition['input'] ?? 'text'));
            }

            if ($key === 'name' || $key === 'base_unit') {
                $normalized['enabled'] = true;
                $normalized['required'] = true;
            }

            if ($normalized['enabled'] !== true) {
                $normalized['required'] = false;
            }

            $normalizedFields[$key] = $normalized;
        }

        $incomingDefaults = is_array($config['defaults'] ?? null) ? $config['defaults'] : [];
        $defaultBaseUnit = self::normalizeUnitCode((string) ($incomingDefaults['base_unit_code'] ?? ($defaults['defaults']['base_unit_code'] ?? 'unite')));
        if ($defaultBaseUnit === '') {
            $defaultBaseUnit = 'unite';
        }
        $allowedCodes = array_column($normalizedBaseUnits, 'code');
        if (!in_array($defaultBaseUnit, $allowedCodes, true)) {
            $defaultBaseUnit = (string) ($normalizedBaseUnits[0]['code'] ?? 'unite');
        }

        return [
            'version' => $version,
            'base_units' => $normalizedBaseUnits,
            'formes' => $normalizedFormes,
            'defaults' => [
                'base_unit_code' => $defaultBaseUnit,
            ],
            'fields' => $normalizedFields,
        ];
    }

    private static function parseBaseUnits(string $raw): array
    {
        $lines = preg_split('/\R/', (string) $raw) ?: [];
        $result = [];
        $seen = [];

        foreach ($lines as $line) {
            $trimmed = trim((string) $line);
            if ($trimmed === '') {
                continue;
            }

            $parts = preg_split('/[|:=]/', $trimmed, 2);
            $code = self::normalizeUnitCode((string) ($parts[0] ?? ''));
            if ($code === '' || isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            $label = trim((string) ($parts[1] ?? ''));
            if ($label === '') {
                $label = $code;
            }

            $result[] = [
                'code' => $code,
                'label' => substr($label, 0, 60),
            ];
        }

        return $result;
    }

    private static function parseSimpleList(string $raw, int $maxLen): array
    {
        $lines = preg_split('/\R/', (string) $raw) ?: [];
        $result = [];
        $seen = [];
        foreach ($lines as $line) {
            $value = trim((string) $line);
            if ($value === '') {
                continue;
            }
            $key = mb_strtolower($value);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = substr($value, 0, $maxLen);
        }
        return $result;
    }

    private static function normalizeUnitCode(string $unit): string
    {
        $code = trim((string) $unit);
        $code = strtolower($code);
        $code = preg_replace('/[^a-z0-9_\-]/', '', $code);
        return $code;
    }

    private static function normalizeLabel(string $label): string
    {
        $value = trim(preg_replace('/\s+/', ' ', (string) $label));
        if ($value === '') {
            return 'Champ';
        }
        return substr($value, 0, 60);
    }

    private static function normalizePlaceholder(string $placeholder): string
    {
        $value = trim(preg_replace('/\s+/', ' ', (string) $placeholder));
        return substr($value, 0, 120);
    }

    private static function normalizeInputType(string $type): string
    {
        $normalized = strtolower(trim($type));
        return in_array($normalized, ['text', 'select'], true) ? $normalized : 'text';
    }
}

