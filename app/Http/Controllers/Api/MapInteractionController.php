<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MapInteractionEvent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Records anonymous click/interaction events from the 3-D U.S. map.
 *
 * This endpoint is deliberately lightweight — fire-and-forget from the
 * client. Failures are swallowed so they never block the user experience.
 * No authentication required; rate-limited in routes/api.php.
 *
 * Privacy notes:
 *   - IPs are SHA-256 hashed before storage; raw IPs are never persisted.
 *   - session_id comes from a localStorage UUID — no cookies, no accounts.
 *   - user_agent is MD5-hashed for bot filtering only.
 */
class MapInteractionController extends Controller
{
    /** Maximum allowed length for free-text meta fields. */
    private const MAX_META_BYTES = 512;

    /** Maps common full party names to the single-letter codes we store. */
    private const PARTY_MAP = [
        'republican' => 'R',
        'gop'        => 'R',
        'democratic' => 'D',
        'democrat'   => 'D',
        'libertarian'=> 'L',
        'green'      => 'G',
        'independent'=> 'I',
        'unknown'    => null,
        'unaffiliated'=> null,
        'u'          => 'U',
    ];

    /**
     * POST /api/v1/map/interaction
     *
     * Accepts a JSON body with the following shape (all fields optional
     * except session_id and event_type):
     *
     *  {
     *    "session_id":      "uuid-v4",          // localStorage anonymous ID
     *    "event_type":      "state_click",       // see migration for enum
     *    "region":          "South",
     *    "state":           "Texas",
     *    "state_abbr":      "TX",
     *    "district":        "TX-7",
     *    "party":           "R",
     *    "candidate_name":  "Jane Smith",
     *    "candidate_slug":  "tx-rep-jane-smith",
     *    "meta":            { "layer": "districts", "query": "Texas" }
     *  }
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'session_id'     => ['required', 'string', 'max:64'],
                'event_type'     => ['required', 'string', 'max:64'],
                'region'         => ['nullable', 'string', 'max:32'],
                'state'          => ['nullable', 'string', 'max:64'],
                'state_abbr'     => ['nullable', 'string', 'max:4'],
                'district'       => ['nullable', 'string', 'max:16'],
                'party'          => ['nullable', 'string', 'max:32'],
                'candidate_name' => ['nullable', 'string', 'max:128'],
                'candidate_slug' => ['nullable', 'string', 'max:255'],
                'meta'           => ['nullable', 'array'],
                'referrer'       => ['nullable', 'string', 'max:512'],
            ]);

            // Hash IP for bot-filtering; never store raw IP
            $ip     = $request->ip();
            $ipHash = $ip ? hash('sha256', $ip) : null;

            // Hash user-agent for bot classification only
            $ua     = $request->userAgent();
            $uaHash = $ua ? md5($ua) : null;

            // Truncate meta array values to avoid oversized payloads
            if (!empty($data['meta'])) {
                $data['meta'] = $this->sanitiseMeta($data['meta']);
            }

            if (isset($data['party'])) {
                $data['party'] = $this->normaliseParty($data['party']);
            }

            MapInteractionEvent::create([
                ...$data,
                'ip_hash'          => $ipHash,
                'user_agent_hash'  => $uaHash,
            ]);

            return response()->json(['ok' => true], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            // Never surface internal errors to the client
            Log::warning('[MapInteraction] store failed: ' . $e->getMessage());
            return response()->json(['ok' => false], 500);
        }
    }

    /**
     * Normalise a party value to a single-letter code.
     * Accepts full names from the frontend and maps them to R/D/L/G/I/U.
     * Unknown/unaffiliated values become null so they don't pollute analytics.
     */
    private function normaliseParty(?string $party): ?string
    {
        if ($party === null) {
            return null;
        }

        $key = strtolower(trim($party));
        if (isset(self::PARTY_MAP[$key])) {
            return self::PARTY_MAP[$key];
        }

        // Single-letter fallback (R, D, I, L, G, U, …)
        $first = strtoupper($key[0]);
        return strlen($first) <= 1 ? $first : null;
    }

    /**
     * Recursively truncate string values inside the meta array so a
     * malicious payload can't bloat the database.
     *
     * @param  array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function sanitiseMeta(array $meta): array
    {
        $out = [];
        foreach ($meta as $k => $v) {
            if (is_string($k) && strlen($k) > 64) {
                continue; // drop oversized keys
            }
            if (is_string($v)) {
                $out[$k] = mb_substr($v, 0, self::MAX_META_BYTES);
            } elseif (is_array($v)) {
                $out[$k] = $this->sanitiseMeta($v);
            } elseif (is_int($v) || is_float($v) || is_bool($v) || is_null($v)) {
                $out[$k] = $v;
            }
            // Drop objects and other types
        }
        return $out;
    }
}
