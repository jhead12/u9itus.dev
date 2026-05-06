<?php

namespace App\Services;

use App\Exceptions\OcrCandidateImportException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class OcrCandidateImportService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function extractCandidatesFromFile(string $filePath, array $defaults = []): array
    {
        $extension = strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'json') {
            return $this->normalizeJsonRows($filePath, $defaults);
        }

        $text = $this->extractText($filePath, $extension);
        $records = $this->parseCandidateRowsFromText($text, $defaults);

        if ($records === []) {
            throw new OcrCandidateImportException('No candidate rows were detected in the scanned package.');
        }

        return $records;
    }

    protected function extractText(string $filePath, string $extension): string
    {
        if ($extension === 'txt') {
            $raw = file_get_contents($filePath);

            return trim((string) $raw);
        }

        if ($extension === 'pdf') {
            $text = $this->extractPdfText($filePath);
            if ($text !== '') {
                return $text;
            }
        }

        if (in_array($extension, ['png', 'jpg', 'jpeg', 'tif', 'tiff', 'bmp', 'webp', 'pdf'], true)) {
            $text = $this->runCommand(['tesseract', $filePath, 'stdout', '-l', 'eng']);
            if ($text !== '') {
                return $text;
            }
        }

        throw new OcrCandidateImportException('Could not extract OCR text from upload. Install pdftotext and/or tesseract, or upload JSON/TXT.');
    }

    protected function extractPdfText(string $filePath): string
    {
        $txtDir  = storage_path('app/imports/uploads');
        if (! is_dir($txtDir)) {
            mkdir($txtDir, 0755, true);
        }
        $txtPath = $txtDir . '/ocr-' . uniqid('', true) . '.txt';

        try {
            $output = $this->runCommand(['pdftotext', '-layout', $filePath, $txtPath], false);
            if ($output === '' && is_file($txtPath)) {
                $raw = file_get_contents($txtPath);

                return trim((string) $raw);
            }
        } catch (OcrCandidateImportException $e) {
            Log::info('pdftotext unavailable or failed, will attempt tesseract fallback', [
                'message' => $e->getMessage(),
            ]);
        } finally {
            if (is_file($txtPath)) {
                @unlink($txtPath);
            }
        }

        return '';
    }

    protected function runCommand(array $command, bool $requireStdout = true): string
    {
        $process = new Process($command);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new OcrCandidateImportException(trim($process->getErrorOutput()) ?: 'OCR command failed.');
        }

        $output = trim((string) $process->getOutput());

        if ($requireStdout && $output === '') {
            throw new OcrCandidateImportException('OCR command returned empty output.');
        }

        return $output;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeJsonRows(string $filePath, array $defaults): array
    {
        $raw = file_get_contents($filePath);
        $decoded = json_decode((string) $raw, true);

        if (! is_array($decoded)) {
            throw new OcrCandidateImportException('Uploaded JSON is invalid.');
        }

        $rows = [];
        foreach ($decoded as $idx => $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['full_name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $rows[] = $this->buildRecord($idx, $name, [
                'party_affiliation' => (string) ($row['party_affiliation'] ?? $defaults['party_affiliation'] ?? ''),
                'political_office' => (string) ($row['political_office'] ?? $defaults['political_office'] ?? ''),
                'district' => (string) ($row['district'] ?? $defaults['district'] ?? ''),
                'state' => (string) ($row['state'] ?? $defaults['state'] ?? ''),
                'city' => (string) ($row['city'] ?? $defaults['city'] ?? ''),
                'county' => (string) ($row['county'] ?? $defaults['county'] ?? ''),
                'governance_level' => (string) ($row['governance_level'] ?? $defaults['governance_level'] ?? ''),
                'election_date' => (string) ($row['election_date'] ?? $defaults['election_date'] ?? ''),
            ]);
        }

        if ($rows === []) {
            throw new OcrCandidateImportException('Uploaded JSON does not contain valid candidate rows.');
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function parseCandidateRowsFromText(string $text, array $defaults): array
    {
        $lines = preg_split('/\R/u', $text) ?: [];

        $state = strtoupper(trim((string) ($defaults['state'] ?? '')));
        $district = trim((string) ($defaults['district'] ?? ''));
        $office = trim((string) ($defaults['political_office'] ?? ''));
        $governanceLevel = trim((string) ($defaults['governance_level'] ?? ''));
        $electionDate = trim((string) ($defaults['election_date'] ?? ''));
        $partyFromContext = trim((string) ($defaults['party_affiliation'] ?? ''));
        $county = trim((string) ($defaults['county'] ?? ''));
        $city = trim((string) ($defaults['city'] ?? ''));

        $rows = [];
        $seen = [];
        $index = 0;

        foreach ($lines as $line) {
            $normalized = $this->normalizeLine($line);
            if ($normalized === '') {
                continue;
            }

            $office = $this->captureContextValue($normalized, [
                '/^office\s*[:\-]\s*(.+)$/i',
                '/^contest\s*[:\-]\s*(.+)$/i',
            ]) ?: $office;

            $district = $this->captureContextValue($normalized, [
                '/^district\s*[:\-]\s*(.+)$/i',
                '/^(assembly|senate|ward|precinct|board)\s+district\s*[:\-]?\s*(.+)$/i',
            ]) ?: $district;

            $electionDate = $this->captureContextValue($normalized, [
                '/^election\s+date\s*[:\-]\s*(.+)$/i',
            ]) ?: $electionDate;

            $state = strtoupper($this->captureState($normalized) ?: $state);

            $candidate = $this->extractCandidateFromLine($normalized);
            if ($candidate === null) {
                continue;
            }

            $name = $candidate['name'];
            $party = $candidate['party'] !== '' ? $candidate['party'] : $partyFromContext;
            $key = mb_strtolower($name . '|' . $office . '|' . $district . '|' . $state);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $rows[] = $this->buildRecord($index++, $name, [
                'party_affiliation' => $party,
                'political_office' => $office,
                'district' => $district,
                'state' => $state,
                'city' => $city,
                'county' => $county,
                'governance_level' => $governanceLevel,
                'election_date' => $electionDate,
            ]);
        }

        return $rows;
    }

    protected function normalizeLine(string $line): string
    {
        $line = preg_replace('/^[\s\d\)\.\-]+/u', '', $line) ?? $line;
        $line = preg_replace('/\s+/u', ' ', $line) ?? $line;

        return trim($line);
    }

    protected function captureContextValue(string $line, array $patterns): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $line, $matches) === 1) {
                $value = trim((string) ($matches[count($matches) - 1] ?? ''));

                return $value !== '' ? $value : null;
            }
        }

        return null;
    }

    protected function captureState(string $line): ?string
    {
        if (preg_match('/\b([A-Z]{2})\b/u', strtoupper($line), $matches) === 1) {
            $candidate = strtoupper((string) $matches[1]);
            $validStates = [
                'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
                'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
                'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
                'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
                'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY',
                'DC',
            ];

            if (in_array($candidate, $validStates, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array{name: string, party: string}|null
     */
    protected function extractCandidateFromLine(string $line): ?array
    {
        $partyPattern = 'Democratic|Republican|Independent|Libertarian|Green|Nonpartisan|No Party Preference|American Independent|Peace and Freedom';

        if (preg_match('/^([A-Za-z\'\.\-]+(?:\s+[A-Za-z\'\.\-]+){1,6})\s*[\-–,]\s*(' . $partyPattern . ')$/i', $line, $matches) === 1) {
            return [
                'name' => $this->toDisplayName($matches[1]),
                'party' => $this->toDisplayName($matches[2]),
            ];
        }

        if (preg_match('/^([A-Za-z\'\.\-]+(?:\s+[A-Za-z\'\.\-]+){1,6})\s*\((' . $partyPattern . ')\)$/i', $line, $matches) === 1) {
            return [
                'name' => $this->toDisplayName($matches[1]),
                'party' => $this->toDisplayName($matches[2]),
            ];
        }

        if (preg_match('/^([A-Za-z\'\.\-]+(?:\s+[A-Za-z\'\.\-]+){1,6})$/u', $line, $matches) === 1) {
            $name = $this->toDisplayName($matches[1]);
            if ($this->looksLikeCandidateName($name)) {
                return [
                    'name' => $name,
                    'party' => '',
                ];
            }
        }

        return null;
    }

    protected function looksLikeCandidateName(string $value): bool
    {
        if (mb_strlen($value) < 5 || mb_strlen($value) > 80) {
            return false;
        }

        $bannedWords = ['office', 'district', 'election', 'ballot', 'county', 'state', 'measure', 'proposition'];
        $lower = mb_strtolower($value);
        foreach ($bannedWords as $word) {
            if (str_contains($lower, $word)) {
                return false;
            }
        }

        return preg_match('/^[A-Z][A-Za-z\'\.\-]+(?:\s+[A-Z][A-Za-z\'\.\-]+)+$/u', $value) === 1;
    }

    /**
     * @param  array<string, string>  $details
     * @return array<string, mixed>
     */
    protected function buildRecord(int $idx, string $name, array $details): array
    {
        $office = trim((string) ($details['political_office'] ?? ''));
        $state = strtoupper(trim((string) ($details['state'] ?? '')));
        $district = trim((string) ($details['district'] ?? ''));

        return [
            'external_candidate_id' => substr(hash('sha256', $name . '|' . $office . '|' . $state . '|' . $district . '|' . $idx), 0, 32),
            'full_name' => $name,
            'political_office' => $office,
            'governance_level' => trim((string) ($details['governance_level'] ?? '')),
            'state' => $state,
            'county' => trim((string) ($details['county'] ?? '')),
            'city' => trim((string) ($details['city'] ?? '')),
            'district' => $district,
            'party_affiliation' => trim((string) ($details['party_affiliation'] ?? '')),
            'election_date' => trim((string) ($details['election_date'] ?? '')),
        ];
    }

    protected function toDisplayName(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }
}
