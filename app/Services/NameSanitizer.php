<?php

namespace App\Services;

class NameSanitizer
{
    /**
     * Common prefixes to remove from names
     */
    protected array $prefixes = [
        // Honorifics and titles (with/without punctuation)
        'md', 'md.', 'md:', 'm.d.', 'm.d','al-', 'Al-',
        'mst', 'mst.', 'mst:', 'm.s.t.', 'm.s.t',
        'mr', 'mr.', 'mrs', 'mrs.', 'miss', 'ms', 'ms.',
        // Religious prefixes (common in Bangladesh)
        //'mohammad', 'muhammad', 'mohammed', 'mohamad', 'mohamed',
        //'abdul', 'al-', 'alhaj', 'hajji', 'haji',
        // Other common Bangladeshi prefixes
        //'sheikh', 'choudhury', 'chowdhury', 'mia', 'miah',
    ];

    /**
     * Remove prefixes and normalize name
     */
    public function sanitizeNameString(?string $fullName): string
    {
        $full = trim($fullName ?? '');
        $full = preg_replace('/\s+/', ' ', $full) ?? '';
        if ($full === '') return '';

        $tokens = array_values(array_filter(explode(' ', $full)));

        // Remove prefixes at the start repeatedly (md, mst, etc.)
        while (!empty($tokens)) {
            $t = strtolower(rtrim($tokens[0], '.:'));
            if (in_array($t, $this->prefixes, true)) {
                array_shift($tokens);
                continue;
            }
            break;
        }

        $clean = trim(implode(' ', $tokens));
        return mb_convert_case($clean, MB_CASE_TITLE, 'UTF-8');
    }


    /**
     * Split full name into first, middle, last
     */
    public function smartSplit(string $cleanFullName): array
    {
        $parts = array_values(array_filter(explode(' ', trim($cleanFullName))));
        if (count($parts) === 0) return ['first'=>null,'middle'=>null,'last'=>null];

        // remove obvious non-name tokens
        $parts = array_values(array_filter($parts, function($p){
            $low = strtolower($p);
            if (preg_match('/^\d+$/', $p)) return false;          // pure numbers
            if (str_contains($low, '@')) return false;           // emails
            if (preg_match('/^[^a-zA-Z]+$/', $p)) return false;  // punctuation-only
            return true;
        }));

        $count = count($parts);
        if ($count === 0) return ['first'=>null,'middle'=>null,'last'=>null];
        if ($count === 1) return ['first'=>$parts[0],'middle'=>null,'last'=>null];

        // connectors that can be part of last name
        $connectors = [
            'bin','binti','ibn','al',
            'de','del','della','da','di',
            'van','von','der','den','dos','das'
        ];

        $first = $parts[0];

        // Decide last name
        $last = $parts[$count - 1];
        $lastLower = strtolower($last);

        // If the token before last is a connector, last name becomes "connector + last"
        if ($count >= 3) {
            $prev = strtolower($parts[$count - 2]);
            if (in_array($prev, $connectors, true)) {
                $last = $parts[$count - 2] . ' ' . $parts[$count - 1];
                $middleParts = array_slice($parts, 1, -2);
            } else {
                $middleParts = array_slice($parts, 1, -1);
            }
        } else {
            $middleParts = [];
        }

        $middle = count($middleParts) ? implode(' ', $middleParts) : null;

        return ['first' => $first, 'middle' => $middle, 'last' => $last];
    }

}
