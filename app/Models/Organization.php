<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Db;
use App\Core\Iban;

/**
 * Multi-tenant (migrations/024) — one row per tenant (`ruler`, UNIQUE), not
 * a single always-id=1 row anymore. get() is get-or-create: a tenant with no
 * row yet (freshly registered, or predates this migration) gets a blank one
 * inserted on first read, so every caller keeps getting a plain array back
 * instead of having to null-check.
 */
final class Organization
{
    public const CURRENCIES = ['GEL', 'USD'];

    public static function get(int $ruler): array
    {
        $existing = Db::all('SELECT * FROM organization WHERE ruler = ?', [$ruler]);
        if ($existing !== []) {
            return $existing[0];
        }

        Db::conn()->prepare('INSERT INTO organization (ruler) VALUES (?)')->execute([$ruler]);

        return Db::all('SELECT * FROM organization WHERE ruler = ?', [$ruler])[0];
    }

    public static function save(array $data, int $ruler): void
    {
        Db::conn()->prepare(
            'UPDATE organization SET
                name = ?, tax_id = ?, email = ?, website = ?, phone = ?,
                address = ?, invoice_prefix = ?, bank_details = ?,
                vat_rate = ?, currency = ?
             WHERE ruler = ?'
        )->execute([
            $data['name'],
            self::orNull($data['tax_id']),
            self::orNull($data['email']),
            self::orNull($data['website']),
            self::orNull($data['phone']),
            self::orNull($data['address']),
            self::orNull($data['invoice_prefix']),
            self::orNull(implode("\n", array_filter($data['bank_ibans'], static fn(string $v): bool => $v !== ''))),
            $data['vat_rate'],
            $data['currency'],
            $ruler,
        ]);
    }

    /** @return list<string> non-blank IBANs, in the order they were saved */
    public static function bankIbans(array $org): array
    {
        $raw = (string) ($org['bank_details'] ?? '');

        return $raw === '' ? [] : preg_split('/\r?\n/', $raw);
    }

    public static function updateLogo(string $filename, int $ruler): void
    {
        Db::conn()->prepare('UPDATE organization SET logo = ? WHERE ruler = ?')->execute([$filename, $ruler]);
    }

    public static function updateSignature(string $filename, int $ruler): void
    {
        Db::conn()->prepare('UPDATE organization SET signature = ? WHERE ruler = ?')->execute([$filename, $ruler]);
    }

    /** @return array{0: array<string,string>, 1: array<string,string>} [clean, errors] */
    public static function validate(array $input): array
    {
        $clean = [
            'name'           => trim((string) ($input['name'] ?? '')),
            'tax_id'         => trim((string) ($input['tax_id'] ?? '')),
            'email'          => trim((string) ($input['email'] ?? '')),
            'website'        => trim((string) ($input['website'] ?? '')),
            'phone'          => trim((string) ($input['phone'] ?? '')),
            'address'        => trim((string) ($input['address'] ?? '')),
            'invoice_prefix' => trim((string) ($input['invoice_prefix'] ?? '')),
            'vat_rate'       => trim((string) ($input['vat_rate'] ?? '')),
            'currency'       => trim((string) ($input['currency'] ?? '')),
            'bank_ibans'     => array_map(
                static fn($v): string => Iban::normalize(trim((string) $v)),
                array_values((array) ($input['bank_ibans'] ?? []))
            ),
        ];
        $errors = [];

        if ($clean['name'] === '') {
            $errors['name'] = terr('org.err_name_required');
        }

        if ($clean['email'] !== '' && !filter_var($clean['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = terr('auth.err_email_invalid');
        }

        if (!is_numeric($clean['vat_rate']) || (float) $clean['vat_rate'] < 0 || (float) $clean['vat_rate'] > 100) {
            $errors['vat_rate'] = terr('org.err_vat_rate');
        }

        if (!in_array($clean['currency'], self::CURRENCIES, true)) {
            $errors['currency'] = terr('org.err_currency');
        }

        foreach ($clean['bank_ibans'] as $i => $iban) {
            if ($iban !== '' && !Iban::isValid($iban)) {
                $errors['bank_ibans_' . $i] = terr('org.err_iban_invalid');
            }
        }

        return [$clean, $errors];
    }

    private static function orNull(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
