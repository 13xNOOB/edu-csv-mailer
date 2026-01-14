<?php

namespace App\Services;

use App\Models\Import;
use App\Models\ImportRow;
use Illuminate\Support\Str;

class StudentEmailGenerator
{
    /**
     * Template example:
     * "{first}.{last}.{department}.{semesterprefix}@{domain}"
     */
    public function generateForImport(
        Import $import,
        string $template,
        string $semesterPrefix,
        string $domain
    ): int {
        $rows = $import->rows()->orderBy('id')->get();

        // Track already-used emails within this import run
        $used = [];

        // Also preload existing generated emails in DB for this import
        $existing = $import->rows()
            ->whereNotNull('generated_email')
            ->pluck('generated_email')
            ->map(fn($e) => strtolower($e))
            ->toArray();

        foreach ($existing as $e) {
            $used[$e] = true;
        }

        $updated = 0;

        foreach ($rows as $row) {
            // you can skip if already generated if you want:
            // if ($row->generated_email) continue;

            $email = $this->buildFromTemplate($row, $template, $semesterPrefix, $domain);
            $email = $this->ensureUnique($import, $row, $email, $template, $semesterPrefix, $domain, $used);

            $row->generated_email = $email;
            $row->email_generation_attempts = $row->email_generation_attempts + 1;
            $row->save();

            $used[strtolower($email)] = true;
            $updated++;
        }

        return $updated;
    }

    private function buildFromTemplate(
        ImportRow $row,
        string $template,
        string $semesterPrefix,
        string $domain
    ): string {
        $first = $this->cleanToken($row->first_name ?? $row->FirstName ?? '');
        $last  = $this->cleanToken($row->last_name ?? $row->LastName ?? '');
        $dept  = $this->cleanToken($row->DepartmentName ?? '');
        $middle = $this->cleanToken($row->middle_name ?? '');
        $firstInitial = $first !== '' ? substr($first, 0, 1) : '';
        $middleInitial = $middle !== '' ? substr($middle, 0, 1) : '';
        $lastInitial = $last !== '' ? substr($last, 0, 1) : '';

        $replacements = [
            '{first}' => $first,
            '{middle}' => $middle,
            '{last}' => $last,

            '{fi}' => $firstInitial,
            '{mi}' => $middleInitial,
            '{li}' => $lastInitial,

            '{department}' => $dept,
            '{semesterprefix}' => $this->cleanToken($semesterPrefix),
            '{domain}' => strtolower(trim($domain)),
        ];

        $email = strtr($template, $replacements);
        $email = strtolower($email);

        // remove accidental double dots
        $email = preg_replace('/\.+/', '.', $email) ?? $email;

        // remove dot before @ if any
        $email = str_replace('.@', '@', $email);

        return $email;
    }

    private function ensureUnique(
        Import $import,
        ImportRow $row,
        string $email,
        string $template,
        string $semesterPrefix,
        string $domain,
        array &$used
    ): string {
        $candidate = strtolower($email);

        if (!$this->exists($import, $candidate, $used)) {
            return $candidate;
        }

        // Strategy 1: swap first/last in the local part (only if template uses first/last)
        $swapTemplate = str_replace(['{first}', '{last}'], ['{last}', '{first}'], $template);
        $candidate = $this->buildFromTemplate($row, $swapTemplate, $semesterPrefix, $domain);

        if (!$this->exists($import, $candidate, $used)) {
            return $candidate;
        }

        // Strategy 2: add middle initial if available
        $middleInitial = '';
        if (!empty($row->middle_name)) {
            $middleInitial = $this->cleanToken(mb_substr(trim($row->middle_name), 0, 1));
        }

        if ($middleInitial !== '') {
            $first = $this->cleanToken($row->first_name ?? $row->FirstName ?? '');
            $candidate = str_replace($first, $first . $middleInitial, $candidate);

            if (!$this->exists($import, $candidate, $used)) {
                return $candidate;
            }
        }

        // Strategy 3 (guaranteed): numeric suffix
        $suffix = 2;
        while (true) {
            $withSuffix = $this->appendNumericSuffix($candidate, $suffix);
            if (!$this->exists($import, $withSuffix, $used)) {
                return $withSuffix;
            }
            $suffix++;
        }
    }

    private function appendNumericSuffix(string $email, int $n): string
    {
        // Split local-part and domain
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) return $email . $n;

        [$local, $dom] = $parts;
        return "{$local}{$n}@{$dom}";
    }

    private function exists(Import $import, string $candidate, array $used): bool
    {
        if (isset($used[$candidate])) return true;

        // Check DB uniqueness within import (important if multiple runs)
        return $import->rows()
            ->whereRaw('LOWER(generated_email) = ?', [$candidate])
            ->exists();
    }

    private function cleanToken(string $value): string
    {
        $value = trim($value);
        $value = strtolower($value);

        // Convert spaces to nothing, keep letters/numbers/dot/underscore/hyphen
        $value = Str::of($value)
            ->replaceMatches('/\s+/', '')
            ->replaceMatches('/[^a-z0-9._-]/', '')
            ->toString();

        return $value;
    }
}
