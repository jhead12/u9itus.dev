<?php

namespace App\Services;

use App\Exceptions\OcrCandidateImportException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

/**
 * Pulls ballot measures out of a voter guide — a local PDF/scan/text file or a link to one.
 *
 * Text comes from the PDF's own text layer when it has one (pdftotext), else from OCR
 * (pdftoppm + tesseract per page, capped). The parser is deliberately heuristic — county and
 * city guides all format differently — so callers show the result for review before saving.
 */
class VoterGuideMeasureExtractor
{
    private const MAX_DOWNLOAD_BYTES = 30 * 1024 * 1024;

    private const MAX_OCR_PAGES = 40;

    private const HEADING = '/^\s*(?:Proposition|Prop\.?|Measure|Question|Issue|Amendment|Referendum|Ordinance)\s+((?-i:[A-Z]{1,3}\d{0,2})|\d{1,3}(?-i:[A-Z])?)\b\s*(.*)$/i';

    public function __construct(private readonly OcrCandidateImportService $ocr)
    {
    }

    /**
     * @return array<int, array{title: string, measure_number: ?string, summary: ?string, yes_meaning: ?string, no_meaning: ?string}>
     */
    public function fromFile(string $path): array
    {
        return $this->parse($this->textFromFile($path));
    }

    /**
     * @return array<int, array{title: string, measure_number: ?string, summary: ?string, yes_meaning: ?string, no_meaning: ?string}>
     */
    public function fromUrl(string $url): array
    {
        return $this->parse($this->textFromUrl($url));
    }

    public function textFromFile(string $path): string
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        try {
            $text = $this->ocr->extractText($path, $extension);
        } catch (OcrCandidateImportException $e) {
            $text = '';
        }

        // A scan has no text layer; render its pages to images and OCR those.
        // pdftotext leaves a bare form feed for an image-only page, hence the \f in the trim set.
        $isBlank = fn (string $t) => trim($t, " \t\n\r\0\x0B\f") === '';
        if ($isBlank($text) && $extension === 'pdf') {
            $text = $this->ocrPdfPages($path);
        }

        if ($isBlank($text)) {
            throw new OcrCandidateImportException('Could not read any text from that file. Is it a scan? Install pdftoppm and tesseract, or upload a text version.');
        }

        return $text;
    }

    public function textFromUrl(string $url): string
    {
        $url = trim($url);
        if (! $this->isSafeUrl($url)) {
            throw new OcrCandidateImportException('That link is not a public http(s) address.');
        }

        $response = Http::timeout(45)
            ->withHeaders(['User-Agent' => 'U9itus-civic-enrichment/1.0 (+https://u9itus.dev/about)'])
            ->get($url);

        if (! $response->ok()) {
            throw new OcrCandidateImportException("The link returned HTTP {$response->status()}.");
        }

        $body = $response->body();
        if (strlen($body) > self::MAX_DOWNLOAD_BYTES) {
            throw new OcrCandidateImportException('That file is too large to import (30 MB limit).');
        }

        $type = strtolower((string) $response->header('Content-Type'));
        $isPdf = str_contains($type, 'pdf') || str_starts_with($body, '%PDF');
        $isImage = str_starts_with($type, 'image/');

        if ($isPdf || $isImage) {
            $extension = $isPdf ? 'pdf' : (preg_replace('/[^a-z]/', '', substr($type, 6)) ?: 'png');
            $tmp = tempnam(sys_get_temp_dir(), 'guide-').'.'.$extension;
            file_put_contents($tmp, $body);

            try {
                return $this->textFromFile($tmp);
            } finally {
                @unlink($tmp);
            }
        }

        return $this->htmlToText($body);
    }

    protected function htmlToText(string $html): string
    {
        $html = preg_replace('#<(script|style|nav|footer|noscript)\b.*?</\1>#is', ' ', $html) ?? $html;
        $html = preg_replace('#<(?:br\s*/?|/p|/div|/li|/h[1-6]|/tr|/table)>#i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[ \t]+/', ' ', $text) ?? $text);
    }

    protected function ocrPdfPages(string $path): string
    {
        $dir = sys_get_temp_dir().'/guide-ocr-'.uniqid('', true);
        mkdir($dir, 0755, true);

        try {
            $render = new Process(['pdftoppm', '-r', '200', '-l', (string) self::MAX_OCR_PAGES, '-png', $path, $dir.'/page']);
            $render->setTimeout(300);
            $render->run();
            if (! $render->isSuccessful()) {
                return '';
            }

            $pages = glob($dir.'/page*.png') ?: [];
            sort($pages);
            $text = '';
            foreach ($pages as $page) {
                $ocr = new Process(['tesseract', $page, 'stdout', '-l', 'eng', '--psm', '4']);
                $ocr->setTimeout(120);
                $ocr->run();
                $text .= $ocr->isSuccessful() ? "\n".$ocr->getOutput() : '';
            }

            return trim($text);
        } catch (\Throwable) {
            return '';
        } finally {
            array_map('unlink', glob($dir.'/*') ?: []);
            @rmdir($dir);
        }
    }

    /** The link is typed by an admin, but never let it point the server at its own network. */
    protected function isSafeUrl(string $url): bool
    {
        if (! preg_match('#^https?://#i', $url)) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return false;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($ips === []) {
            return false;
        }
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Split guide text into measures. A measure starts at a line like "Measure A", "Proposition 12:
     * Title" or "QUESTION 3"; its body runs to the next such line. A guide usually lists each
     * measure twice (contents page, then the full entry), and page headers repeat it, so
     * candidates are merged by number, keeping the fullest entry.
     *
     * @return array<int, array{title: string, measure_number: ?string, summary: ?string, yes_meaning: ?string, no_meaning: ?string}>
     */
    public function parse(string $text): array
    {
        $lines = preg_split('/\R/u', str_replace("\f", "\n", $text)) ?: [];
        $blocks = [];
        $current = null;

        foreach ($lines as $line) {
            $heading = $this->matchHeading($line);
            if ($heading !== null) {
                if ($current !== null) {
                    $blocks[] = $current;
                }
                $current = $heading + ['body' => []];

                continue;
            }
            if ($current !== null) {
                $current['body'][] = trim($line);
            }
        }
        if ($current !== null) {
            $blocks[] = $current;
        }

        $byNumber = [];
        foreach ($blocks as $block) {
            $measure = $this->buildMeasure($block);
            $key = strtoupper((string) $measure['measure_number']);
            $weight = strlen((string) $measure['summary']) + strlen((string) $measure['yes_meaning']) + strlen((string) $measure['no_meaning']);

            if (! isset($byNumber[$key]) || $weight > $byNumber[$key]['weight']) {
                $byNumber[$key] = ['measure' => $measure, 'weight' => $weight];
            }
        }

        return array_values(array_map(fn ($entry) => $entry['measure'], $byNumber));
    }

    /** @return array{number: string, label: string, rest: string}|null */
    protected function matchHeading(string $line): ?array
    {
        $line = trim($line);
        if ($line === '' || mb_strlen($line) > 140 || ! preg_match(self::HEADING, $line, $m)) {
            return null;
        }

        // Table-of-contents lines: "Measure A ........ 12".
        $rest = trim((string) preg_replace('/[\s.·…_-]{3,}\s*\d+$/u', '', $m[2]), " \t:.-–—");
        if (preg_match('/^\d+$/', $rest)) {
            $rest = '';
        }
        // "Measure A would authorize…" is a sentence about the measure, not its heading.
        if ($rest !== '' && ! preg_match('/^[\p{Lu}\d"“(]/u', $rest) && ! preg_match('/^[:.\-–—]/', trim($m[2]))) {
            return null;
        }
        if ($rest !== '' && preg_match('/\b(would|will|shall|requires?|passes|passed|failed)\b/i', $rest) && mb_strlen($rest) > 60) {
            return null;
        }

        return [
            'number' => strtoupper($m[1]),
            'label' => ucfirst(strtolower(strtok($line, ' ') ?: 'Measure')).' '.strtoupper($m[1]),
            'rest' => $rest,
        ];
    }

    /**
     * @param  array{number: string, label: string, rest: string, body: array<int, string>}  $block
     * @return array{title: string, measure_number: ?string, summary: ?string, yes_meaning: ?string, no_meaning: ?string}
     */
    protected function buildMeasure(array $block): array
    {
        $body = $block['body'];
        $rest = $block['rest'];

        // "Measure A" alone on its line: the title is the next short, sentence-less line.
        if ($rest === '') {
            while ($body !== [] && $body[0] === '') {
                array_shift($body);
            }
            if ($body !== [] && mb_strlen($body[0]) <= 120 && ! preg_match('/[.?]$/', $body[0])) {
                $rest = array_shift($body);
            }
        }

        $text = trim(preg_replace('/\s+/u', ' ', implode(' ', $body)) ?? '');
        [$yes, $no] = $this->splitVotes($text);

        $summary = $text;
        if ($yes !== null || $no !== null) {
            $summary = trim((string) preg_replace('/\ba\s+[“"]?yes[”"]?\s+vote\b.*$/i', '', $text));
        }

        $clean = fn (?string $v, int $len) => ($v !== null && trim($v) !== '') ? mb_substr(trim($v), 0, $len) : null;

        return [
            'title' => mb_substr($rest !== '' ? "{$block['label']}: {$rest}" : $block['label'], 0, 255),
            'measure_number' => $block['number'],
            'summary' => $clean($summary, 1000),
            'yes_meaning' => $clean($yes, 1000),
            'no_meaning' => $clean($no, 1000),
        ];
    }

    /** @return array{0: ?string, 1: ?string} What a Yes and a No vote mean, from "A YES vote means…" wording. */
    protected function splitVotes(string $text): array
    {
        $yes = preg_match('/\ba\s+[“"]?yes[”"]?\s+vote(?:\s+on\s+this\s+(?:measure|proposition))?\s+(?:means|would|will)\s*[:,]?\s*(.+?)(?=\ba\s+[“"]?no[”"]?\s+vote\b|$)/is', $text, $m) ? $m[1] : null;
        $no = preg_match('/\ba\s+[“"]?no[”"]?\s+vote(?:\s+on\s+this\s+(?:measure|proposition))?\s+(?:means|would|will)\s*[:,]?\s*(.+?)(?=\ba\s+[“"]?yes[”"]?\s+vote\b|\b(?:Proposition|Measure|Question)\s+[A-Z0-9]{1,3}\b|$)/is', $text, $m) ? $m[1] : null;

        return [$yes, $no];
    }
}
