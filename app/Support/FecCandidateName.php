<?php

namespace App\Support;

/**
 * FEC writes candidate names as "LAST, FIRST MIDDLE HONORIFIC" in capitals
 * ("WATERS, MAXINE MS", "SMITH, JOHN Q JR"). display() turns that into how a
 * person's name reads on a profile; matching goes through
 * MapCandidateHygiene::identityKey(), which already understands "Last, First".
 */
class FecCandidateName
{
    private const HONORIFICS = '/^(MR|MRS|MS|MISS|DR|HON|REV|PROF|SEN|REP)\.?$/i';

    private const SUFFIXES = '/^(JR|SR|II|III|IV)\.?$/i';

    public static function display(?string $fecName): string
    {
        $name = trim((string) preg_replace('/\s+/', ' ', (string) $fecName));
        if ($name === '') {
            return '';
        }

        $parts = array_map('trim', explode(',', $name));
        if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
            return self::titleCase($name);
        }

        $last = $parts[0];
        $tokens = preg_split('/\s+/', $parts[1]) ?: [];
        $suffix = isset($parts[2]) && $parts[2] !== '' ? [$parts[2]] : [];

        // Trailing honorific ("MS") and suffix ("JR") ride along after the first name.
        while (count($tokens) > 1 && preg_match(self::HONORIFICS, (string) end($tokens))) {
            array_pop($tokens);
        }
        while (count($tokens) > 1 && preg_match(self::SUFFIXES, (string) end($tokens))) {
            array_unshift($suffix, array_pop($tokens));
        }

        return self::titleCase(implode(' ', [...$tokens, $last, ...$suffix]));
    }

    private static function titleCase(string $name): string
    {
        $name = mb_convert_case(mb_strtolower($name), MB_CASE_TITLE);

        $name = preg_replace_callback("/\\b(O'|D')([a-z])/u", fn ($m) => $m[1].mb_strtoupper($m[2]), $name) ?? $name;
        $name = preg_replace_callback('/\bMc([a-z])/u', fn ($m) => 'Mc'.mb_strtoupper($m[1]), $name) ?? $name;
        $name = preg_replace_callback('/\b(Ii|Iii|Iv)\b/u', fn ($m) => mb_strtoupper($m[1]), $name) ?? $name;
        $name = preg_replace('/\b(Jr|Sr)\b\.?/u', '$1.', $name) ?? $name;

        // A lone letter is a middle initial: "John Q Smith" → "John Q. Smith".
        return preg_replace("/(?<![\\w'])([A-Z])(?![\\w'.])/u", '$1.', $name) ?? $name;
    }
}
