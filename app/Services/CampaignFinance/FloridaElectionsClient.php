<?php

namespace App\Services\CampaignFinance;

use Illuminate\Support\Facades\Http;

/**
 * Reads the Florida Division of Elections campaign finance database
 * (dos.elections.myflorida.com). Florida has no bulk export of transactions, so this uses
 * the public query forms' "tab delimited text file" output, one committee at a time:
 *
 *  - committee(): name and status from the Committee Tracking System detail page.
 *  - activeCommittees(): the downloadable list of active committees (account number,
 *    name, type), used to recognize donors and payees that are themselves committees.
 *  - contributions() / expenditures(): every contribution to, or expenditure by, a
 *    committee since a date. The forms search by name (first 30 characters, "starts
 *    with"), so rows for other committees sharing that prefix are filtered out here.
 *
 * Requests are spaced by $pauseMs to stay polite to the state's server.
 */
class FloridaElectionsClient
{
    public const BASE_URL = 'https://dos.elections.myflorida.com';

    /** A committee's public detail page; used as the evidence link for suggested committees. */
    public const COMMITTEE_URL = self::BASE_URL.'/committees/ComDetail.asp?account=%s';

    private const USER_AGENT = 'u9itus-civic-data/1.0 (+https://u9itus.dev)';

    /** The query forms keep the row limit in a 16-bit integer; anything larger is an "Overflow Error". */
    public const ROW_LIMIT = 32767;

    public function __construct(private readonly int $pauseMs = 1500) {}

    /** @return array{name: string, type: ?string, status: ?string}|null */
    public function committee(string $accountNumber): ?array
    {
        $html = $this->get(sprintf(self::COMMITTEE_URL, rawurlencode($accountNumber)));
        $decoded = html_entity_decode(strip_tags((string) preg_replace('#<(script|style)\b.*?</\1>#is', '', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // The page pads names with &nbsp;, which \s only matches in Unicode mode.
        $text = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $decoded));

        if (! preg_match('/Committee Tracking System\s*(.+?)\s*Type:\s*(.*?)\s*Status:\s*(\S+)/', $text, $m) || trim($m[1]) === '') {
            return null;
        }

        return ['name' => trim($m[1]), 'type' => trim($m[2]), 'status' => trim($m[3])];
    }

    /** @return array<string, array{name: string, type: string}> account number => committee */
    public function activeCommittees(): array
    {
        $committees = [];
        foreach ($this->rows($this->post('/committees/extractComList.asp', ['FormSubmit' => 'Download'])) as $row) {
            if (($row['AcctNum'] ?? '') !== '') {
                $committees[$row['AcctNum']] = ['name' => trim($row['Name'] ?? ''), 'type' => trim($row['Type'] ?? '')];
            }
        }

        return $committees;
    }

    /** @return list<array<string, string>> */
    public function contributions(string $committeeName, string $since): array
    {
        return $this->committeeRows('/cgi-bin/contrib.exe', $committeeName, $since);
    }

    /** @return list<array<string, string>> */
    public function expenditures(string $committeeName, string $since): array
    {
        return $this->committeeRows('/cgi-bin/expend.exe', $committeeName, $since, ['cpurpose' => '']);
    }

    /**
     * @param  array<string, string>  $extra
     * @return list<array<string, string>>
     */
    private function committeeRows(string $path, string $committeeName, string $since, array $extra = []): array
    {
        $body = $this->post($path, $extra + [
            'election' => 'All', 'search_on' => '4',
            'CanFName' => '', 'CanLName' => '', 'CanNameSrch' => '2', 'office' => 'All', 'cdistrict' => '', 'cgroup' => '', 'party' => 'All',
            'ComName' => mb_substr($committeeName, 0, 30), 'ComNameSrch' => '2', 'committee' => 'All',
            'cfname' => '', 'clname' => '', 'namesearch' => '2', 'ccity' => '', 'cstate' => '', 'czipcode' => '', 'coccupation' => '',
            'cdollar_minimum' => '', 'cdollar_maximum' => '', 'rowlimit' => (string) self::ROW_LIMIT,
            'csort1' => 'DAT', 'csort2' => 'CAN',
            'cdatefrom' => date('m/d/Y', strtotime($since)), 'cdateto' => '',
            'queryformat' => '2', 'Submit' => 'Submit',
        ]);

        // Errors come back as HTTP 200 with an HTML message after the header row.
        if (str_contains($body, 'Error in /cgi-bin/')) {
            throw new \RuntimeException("Florida Division of Elections query failed for \"{$committeeName}\": ".trim(strip_tags(substr($body, strpos($body, 'Error in'), 300))));
        }

        $wanted = self::normalizeName($committeeName);

        return array_values(array_filter(
            $this->rows($body),
            // Rows name the committee with its type appended: "Smart & Safe Florida (PAC)".
            fn (array $row) => self::normalizeName((string) preg_replace('/\s*\([A-Z]{2,4}\)\s*$/', '', $row['Candidate/Committee'] ?? '')) === $wanted,
        ));
    }

    /** @return list<array<string, string>> */
    private function rows(string $body): array
    {
        $body = mb_check_encoding($body, 'UTF-8') ? $body : mb_convert_encoding($body, 'UTF-8', 'Windows-1252');
        $lines = preg_split('/\r\n|\n|\r/', trim($body)) ?: [];
        $header = array_map('trim', explode("\t", (string) array_shift($lines)));

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $fields = array_pad(array_slice(explode("\t", $line), 0, count($header)), count($header), '');
            $rows[] = array_combine($header, array_map('trim', $fields));
        }

        return $rows;
    }

    public static function normalizeName(string $name): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($name)));
    }

    private function get(string $url): string
    {
        $this->pause();

        return Http::withUserAgent(self::USER_AGENT)->timeout(120)->retry(2, 5000)->get($url)->throw()->body();
    }

    /** @param  array<string, string>  $form */
    private function post(string $path, array $form): string
    {
        $this->pause();

        return Http::withUserAgent(self::USER_AGENT)->asForm()->timeout(300)->retry(2, 5000)->post(self::BASE_URL.$path, $form)->throw()->body();
    }

    private function pause(): void
    {
        if ($this->pauseMs > 0) {
            usleep($this->pauseMs * 1000);
        }
    }
}
