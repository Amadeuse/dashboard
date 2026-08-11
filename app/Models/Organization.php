<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Db;
use App\Core\Iban;

/** Single-org app — one row, always id=1 (seeded by migrations/011_create_organization.sql). */
final class Organization
{
    public static function get(): array
    {
        return Db::all('SELECT * FROM organization WHERE id = 1')[0];
    }

    public static function save(array $data): void
    {
        Db::conn()->prepare(
            'UPDATE organization SET
                name = ?, tax_id = ?, email = ?, website = ?, phone = ?,
                address = ?, invoice_prefix = ?, bank_details = ?
             WHERE id = 1'
        )->execute([
            $data['name'],
            self::orNull($data['tax_id']),
            self::orNull($data['email']),
            self::orNull($data['website']),
            self::orNull($data['phone']),
            self::orNull($data['address']),
            self::orNull($data['invoice_prefix']),
            self::orNull(implode("\n", array_filter($data['bank_ibans'], static fn(string $v): bool => $v !== ''))),
        ]);
    }

    /** @return list<string> non-blank IBANs, in the order they were saved */
    public static function bankIbans(array $org): array
    {
        $raw = (string) ($org['bank_details'] ?? '');

        return $raw === '' ? [] : preg_split('/\r?\n/', $raw);
    }

    public static function updateLogo(string $filename): void
    {
        Db::conn()->prepare('UPDATE organization SET logo = ? WHERE id = 1')->execute([$filename]);
    }

    public static function updateSignature(string $filename): void
    {
        Db::conn()->prepare('UPDATE organization SET signature = ? WHERE id = 1')->execute([$filename]);
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
