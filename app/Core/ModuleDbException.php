<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Thrown by ModuleDb before a statement reaches MySQL — a write outside the
 * module's own tables, or a statement kind it doesn't accept. Carries the
 * module code and the offending SQL so the message points straight at the
 * line to fix.
 */
final class ModuleDbException extends \RuntimeException
{
    public function __construct(
        public readonly string $moduleCode,
        string $reason,
        public readonly string $sql,
    ) {
        parent::__construct("[module:$moduleCode] $reason\n  SQL: " . trim(preg_replace('/\s+/', ' ', $sql) ?? $sql));
    }
}
