<?php

declare(strict_types=1);

namespace App\Core;

/**
 * IBAN format/checksum validation, plus a small Georgian bank-code lookup
 * for the account-badge UI on /settings/organization. Not an exhaustive
 * bank registry — GE IBAN bank codes (chars 5-6) are only listed here when
 * a logo file for that bank exists in public/assets/images/banks/. Any
 * other code still validates fine, it just renders a generic badge — add
 * a logo + an entry to GE_BANKS to recognize another bank, nothing else
 * changes.
 */
final class Iban
{
    private const GE_LENGTH = 22;

    private const GE_BANKS = [
        'TB' => ['name' => 'თიბისი ბანკი', 'logo' => 'tbc_logo.png'],
        'BG' => ['name' => 'საქართველოს ბანკი', 'logo' => 'bog_logo.png'],
        'PC' => ['name' => 'პროკრედიტ ბანკი', 'logo' => 'prc_logo.png'],
    ];

    public static function normalize(string $iban): string
    {
        return strtoupper(str_replace(' ', '', $iban));
    }

    public static function isValid(string $iban): bool
    {
        $iban = self::normalize($iban);

        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $iban)) {
            return false;
        }

        if (str_starts_with($iban, 'GE') && strlen($iban) !== self::GE_LENGTH) {
            return false;
        }

        if (strlen($iban) < 15 || strlen($iban) > 34) {
            return false;
        }

        return self::checksumValid($iban);
    }

    /** GE-only: the 2-letter bank code at chars 5-6, or null if too short / not GE. */
    public static function extractCode(string $iban): ?string
    {
        $iban = self::normalize($iban);

        return str_starts_with($iban, 'GE') && strlen($iban) >= 6 ? substr($iban, 4, 2) : null;
    }

    /** @return array{code:string,name:string,logo:string}|null null unless the code is a known Georgian bank */
    public static function bankInfo(string $iban): ?array
    {
        $code = self::extractCode($iban);
        if ($code === null || !isset(self::GE_BANKS[$code])) {
            return null;
        }

        return ['code' => $code, ...self::GE_BANKS[$code]];
    }

    /** @return array<string,array{name:string,logo:string}> for the view to hand to the client-side badge preview */
    public static function knownBanks(): array
    {
        return self::GE_BANKS;
    }

    /** mod-97 checksum, ISO 7064: first 4 chars move to the end, letters become A=10..Z=35. */
    private static function checksumValid(string $iban): bool
    {
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $remainder  = 0;

        foreach (str_split($rearranged) as $char) {
            $value = ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
            foreach (str_split($value) as $digit) {
                $remainder = ($remainder * 10 + (int) $digit) % 97;
            }
        }

        return $remainder === 1;
    }
}
