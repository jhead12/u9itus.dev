<?php

namespace App\Services\CampaignFinance;

use RuntimeException;
use ZipArchive;

/**
 * Streams tables out of the California Secretary of State's daily CAL-ACCESS export
 * (https://campaignfinance.cdn.sos.ca.gov/dbwebexport.zip, ~1.6 GB). Each table is a
 * tab-delimited file at CalAccess/DATA/<TABLE>.TSV with a header row, CRLF line endings and
 * Windows-1252 text. Rows are read straight from the zip, so nothing is extracted to disk.
 *
 * Fields are split on tabs, not parsed as CSV: the export doesn't quote fields, and names
 * often contain stray double quotes that would break a CSV parser.
 */
class CalAccessExport
{
    public const DOWNLOAD_URL = 'https://campaignfinance.cdn.sos.ca.gov/dbwebexport.zip';

    private ZipArchive $zip;

    public function __construct(string $path)
    {
        $this->zip = new ZipArchive;
        if ($this->zip->open($path) !== true) {
            throw new RuntimeException("Can't open CAL-ACCESS export at {$path}.");
        }
    }

    /**
     * Rows of one table as column => value arrays. $filter sees the raw field list (in
     * header order) before the row is built, so a cheap column check can skip most rows.
     *
     * @param  (callable(list<string>, array<string, int>): bool)|null  $filter
     * @return \Generator<int, array<string, string>>
     */
    public function rows(string $table, ?callable $filter = null): \Generator
    {
        $stream = $this->zip->getStream("CalAccess/DATA/{$table}.TSV");
        if ($stream === false) {
            throw new RuntimeException("The CAL-ACCESS export has no {$table} table.");
        }

        try {
            $header = $this->split((string) fgets($stream));
            $columns = array_flip($header);
            $width = count($header);

            while (($line = fgets($stream)) !== false) {
                $fields = $this->split($line);
                if ($filter !== null && ! $filter($fields, $columns)) {
                    continue;
                }

                $fields = array_pad(array_slice($fields, 0, $width), $width, '');

                yield array_combine($header, array_map(
                    fn (string $v) => mb_check_encoding($v, 'UTF-8') ? $v : mb_convert_encoding($v, 'UTF-8', 'Windows-1252'),
                    $fields,
                ));
            }
        } finally {
            fclose($stream);
        }
    }

    /** @return list<string> */
    private function split(string $line): array
    {
        return explode("\t", rtrim($line, "\r\n"));
    }
}
