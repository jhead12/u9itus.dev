<?php

namespace App\Services\CampaignFinance;

use RuntimeException;
use ZipArchive;

/**
 * Streams tables out of the Texas Ethics Commission's nightly campaign finance export
 * (TEC_CF_CSV.zip, ~1 GB): comma-separated files with a header row, where quoted fields
 * (descriptions, addresses) can contain line breaks. Layouts are in CFS-ReadMe.txt inside
 * the zip; codes in CFS-Codes.txt.
 *
 * The contribution and expenditure tables are split across many files (contribs_01.csv …
 * contribs_103.csv) and total ~8 GB uncompressed, so rows() can skip a record on its raw
 * text before parsing it: pass $needles (e.g. ",00085302," for a filer ID, which the export
 * never quotes) and only records containing one are parsed.
 */
class TexasEthicsExport
{
    public const DOWNLOAD_URL = 'https://prd.tecprd.ethicsefile.com/public/cf/public/TEC_CF_CSV.zip';

    private ZipArchive $zip;

    public function __construct(string $path)
    {
        $this->zip = new ZipArchive;
        if ($this->zip->open($path) !== true) {
            throw new RuntimeException("Can't open the Texas Ethics Commission export at {$path}.");
        }
    }

    /**
     * Files in the export whose names match a pattern, e.g. '/^contribs_\d+\.csv$/'.
     *
     * @return list<string>
     */
    public function files(string $pattern): array
    {
        $names = [];
        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $name = (string) $this->zip->getNameIndex($i);
            if (preg_match($pattern, $name)) {
                $names[] = $name;
            }
        }
        sort($names, SORT_NATURAL);

        return $names;
    }

    /**
     * @param  list<string>|null  $needles  only parse records whose raw text contains one of these
     * @return \Generator<int, array<string, string>>
     */
    public function rows(string $file, ?array $needles = null): \Generator
    {
        $stream = $this->zip->getStream($file);
        if ($stream === false) {
            throw new RuntimeException("The Texas Ethics Commission export has no {$file}.");
        }

        try {
            $header = str_getcsv($this->utf8(rtrim((string) fgets($stream), "\r\n")), ',', '"', '');
            $width = count($header);
            $open = false;      // inside a quoted field that continues on the next line
            $collect = false;   // the current record is one we want
            $buffer = '';

            while (($line = fgets($stream)) !== false) {
                if (! $open) {
                    $collect = $needles === null || $this->containsAny($line, $needles);
                    $buffer = '';
                }
                if ($collect) {
                    $buffer .= $line;
                }
                // Escaped quotes come in pairs, so an odd count opens or closes a multi-line field.
                if (substr_count($line, '"') % 2 === 1) {
                    $open = ! $open;
                }
                if ($open || ! $collect) {
                    continue;
                }

                $fields = str_getcsv($this->utf8(rtrim($buffer, "\r\n")), ',', '"', '');
                $fields = array_pad(array_slice($fields, 0, $width), $width, '');
                $collect = false;

                yield array_combine($header, $fields);
            }
        } finally {
            fclose($stream);
        }
    }

    /** @param  list<string>  $needles */
    private function containsAny(string $line, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($line, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function utf8(string $value): string
    {
        return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }
}
