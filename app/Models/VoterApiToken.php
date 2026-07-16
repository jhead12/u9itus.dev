<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * SEC-4: an opaque bearer token issued to a Voter for stateless API auth.
 *
 * Only the SHA-256 hash of the plaintext token is persisted; the plaintext is
 * returned to the caller exactly once at issuance / rotation time and is never
 * retrievable afterwards.
 */
class VoterApiToken extends Model
{
    protected $table = 'voter_api_tokens';

    protected $fillable = [
        'voter_id',
        'name',
        'token_hash',
        'abilities',
        'last_used_at',
        'expires_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function voter(): BelongsTo
    {
        return $this->belongsTo(Voter::class);
    }

    /**
     * A token is valid unless it has an expiry that has already passed.
     */
    public function isValid(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * Stamp last-used at most once per minute to avoid a write on every heartbeat.
     */
    public function touchUsage(): void
    {
        if ($this->last_used_at === null || $this->last_used_at->lt(now()->subMinute())) {
            $this->forceFill(['last_used_at' => now()])->save();
        }
    }

    /**
     * Mint a new token for a voter. Returns the plaintext (shown once) and the
     * persisted model (which carries only the hash).
     *
     * @param  list<string>  $abilities
     * @return array{token: string, model: self}
     */
    public static function createToken(Voter $voter, string $name = 'voter-api', array $abilities = ['*']): array
    {
        $plain = Str::random(60);

        $model = $voter->apiTokens()->create([
            'name' => $name,
            'token_hash' => hash('sha256', $plain),
            'abilities' => $abilities,
            'expires_at' => null, // long-lived; rotate to revoke the previous one
        ]);

        return ['token' => $plain, 'model' => $model];
    }

    /**
     * Look up a token record by its plaintext (hashed lookup). Eager-loads the
     * voter so the guard does not issue a second query.
     */
    public static function findToken(string $plain): ?self
    {
        if ($plain === '') {
            return null;
        }

        return static::with('voter')
            ->where('token_hash', hash('sha256', $plain))
            ->first();
    }
}