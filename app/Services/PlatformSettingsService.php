<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Dynamic platform settings manager with config fallback.
 * 
 * Allows admin to adjust pricing, commissions, thresholds, and limits
 * without code deployment — perfect for promotions, early adopter bonuses,
 * and A/B testing.
 * 
 * Settings hierarchy (highest priority first):
 *   1. Active user-tier-specific setting (e.g., early_adopter boost)
 *   2. Active global platform setting
 *   3. Config file default value
 * 
 * Example usage:
 *   PlatformSettingsService::get('revenue_per_view', $userTier)
 *   PlatformSettingsService::set('revenue_per_view', 0.75, [
 *       'effective_from' => now(),
 *       'effective_until' => now()->addWeek(),
 *       'description' => 'Spring promotion - higher payouts'
 *   ])
 */
class PlatformSettingsService
{
    const CACHE_TTL = 300; // 5 minutes
    const CACHE_PREFIX = 'platform_setting:';

    /**
     * Get a platform setting value with config fallback.
     * 
     * Checks: DB (user-tier specific) → DB (global) → config file
     * 
     * @param string $key              Setting key (e.g., 'revenue_per_view')
     * @param string|null $userTier    User tier override ('early_adopter', 'regular', null)
     * @param mixed $default           Fallback value if not found anywhere
     * @return mixed
     */
    public static function get(string $key, ?string $userTier = null, $default = null): mixed
    {
        $cacheKey = self::CACHE_PREFIX . $key . ':' . ($userTier ?? 'global');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key, $userTier, $default) {
            // Priority 1: Active user-tier-specific setting
            if ($userTier) {
                $tierSetting = PlatformSetting::where('key', $key)
                    ->active()
                    ->where('user_tier', $userTier)
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($tierSetting) {
                    return $tierSetting->getTypedValue();
                }
            }

            // Priority 2: Active global setting (user_tier = null)
            $globalSetting = PlatformSetting::where('key', $key)
                ->active()
                ->whereNull('user_tier')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($globalSetting) {
                return $globalSetting->getTypedValue();
            }

            // Priority 3: Config file fallback
            // Map setting keys to config paths
            $configPath = self::mapKeyToConfig($key);
            if ($configPath) {
                return config($configPath, $default);
            }

            return $default;
        });
    }

    /**
     * Set a platform setting (creates or updates).
     * 
     * @param string $key          Setting key
     * @param mixed $value         Setting value
     * @param array $options       Additional options:
     *   - description: Human-readable description
     *   - category: pricing|fraud|video|referral
     *   - effective_from: Carbon|null
     *   - effective_until: Carbon|null
     *   - user_tier: string|null ('early_adopter', 'regular')
     *   - metadata: array|null (promo name, A/B test group, etc.)
     * @return PlatformSetting
     */
    public static function set(string $key, $value, array $options = []): PlatformSetting
    {
        $type = self::inferType($value);
        $category = $options['category'] ?? self::inferCategory($key);
        $userTier = $options['user_tier'] ?? null;

        $attributes = [
            'value' => (string) $value,
            'type' => $type,
            'description' => $options['description'] ?? null,
            'category' => $category,
            'effective_from' => $options['effective_from'] ?? null,
            'effective_until' => $options['effective_until'] ?? null,
            'is_active' => $options['is_active'] ?? true,
            'metadata' => $options['metadata'] ?? null,
        ];

        try {
            $setting = PlatformSetting::updateOrCreate(
                [
                    'key' => $key,
                    'user_tier' => $userTier,
                ],
                $attributes
            );
        } catch (UniqueConstraintViolationException $e) {
            // Handle legacy schemas where `key` is globally unique.
            $setting = PlatformSetting::where('key', $key)->first();

            if (!$setting) {
                throw $e;
            }

            $setting->fill(array_merge($attributes, [
                'user_tier' => $userTier,
            ]));
            $setting->save();

            Log::warning('Platform setting unique key conflict resolved via key-level update fallback', [
                'key' => $key,
                'requested_user_tier' => $userTier,
                'existing_setting_id' => $setting->id,
            ]);
        }

        // Clear cache
        self::clearCache($key, $userTier);

        Log::info('Platform setting updated', [
            'key' => $key,
            'value' => $value,
            'user_tier' => $userTier,
            'effective_from' => $options['effective_from'] ?? null,
            'effective_until' => $options['effective_until'] ?? null,
        ]);

        return $setting;
    }

    /**
     * Delete a platform setting (reverts to config default).
     */
    public static function delete(string $key, ?string $userTier = null): bool
    {
        $query = PlatformSetting::where('key', $key);

        if ($userTier !== null) {
            $query->where('user_tier', $userTier);
        } else {
            $query->whereNull('user_tier');
        }

        $deleted = $query->delete();
        self::clearCache($key, $userTier);

        return $deleted > 0;
    }

    /**
     * Get all settings grouped by category.
     */
    public static function getAllGrouped(): array
    {
        return PlatformSetting::active()
            ->get()
            ->groupBy('category')
            ->map(fn($settings) => $settings->map(fn($s) => [
                'key' => $s->key,
                'value' => $s->getTypedValue(),
                'description' => $s->description,
                'user_tier' => $s->user_tier,
                'effective_from' => $s->effective_from,
                'effective_until' => $s->effective_until,
            ]))
            ->toArray();
    }

    /**
     * Clear cache for a specific setting.
     */
    public static function clearCache(string $key, ?string $userTier = null): void
    {
        Cache::forget(self::CACHE_PREFIX . $key . ':global');
        if ($userTier) {
            Cache::forget(self::CACHE_PREFIX . $key . ':' . $userTier);
        }
        // Also clear all user tier variants to be safe
        Cache::forget(self::CACHE_PREFIX . $key . ':early_adopter');
        Cache::forget(self::CACHE_PREFIX . $key . ':regular');
    }

    /**
     * Clear all platform settings cache.
     */
    public static function clearAllCache(): void
    {
        Cache::flush();
    }

    // ── Private Helpers ─────────────────────────────────────────

    private static function inferType($value): string
    {
        return match (true) {
            is_int($value) => 'int',
            is_float($value) => 'float',
            is_bool($value) => 'boolean',
            default => 'string',
        };
    }

    private static function inferCategory(string $key): string
    {
        return match (true) {
            str_contains($key, 'revenue') || str_contains($key, 'payout') || str_contains($key, 'commission') => 'pricing',
            str_contains($key, 'fraud') || str_contains($key, 'limit') || str_contains($key, 'threshold') => 'fraud',
            str_contains($key, 'video') || str_contains($key, 'duration') || str_contains($key, 'media') => 'video',
            str_contains($key, 'referral') || str_contains($key, 'procurement') => 'referral',
            default => 'general',
        };
    }

    /**
     * Map setting key to config file path.
     * 
     * This allows the service to fall back to config values when no DB override exists.
     */
    private static function mapKeyToConfig(string $key): ?string
    {
        $map = [
            // Pricing
            'revenue_per_view' => 'u9itus.revenue_per_view',
            'viewer_payout_per_view' => 'u9itus.viewer_payout_per_view',
            'referral_commission_percent' => 'u9itus.referral_commission_percent',
            'procurement_commission_percent' => 'u9itus.procurement_commission_percent',
            'batch_payout_min' => 'u9itus.batch_payout_min',
            'min_payout_amount' => 'u9itus.min_payout_amount',
            
            // Video
            'min_video_duration' => 'u9itus.min_video_duration',
            'max_video_duration' => 'u9itus.max_video_duration',
            'max_video_size_mb' => 'u9itus.max_video_size_mb',
            'min_watch_time_percent' => 'u9itus.min_watch_time_percent',
            
            // Fraud
            'fraud_max_views_per_day' => 'u9itus.fraud.max_views_per_voter_per_day',
            'fraud_payout_hold_hours' => 'u9itus.fraud.payout_hold_hours',
            'fraud_auto_flag_threshold' => 'u9itus.fraud.auto_flag_threshold',
            'fraud_suspicious_threshold' => 'u9itus.fraud.suspicious_activity_threshold',
            
            // Other
            'assignment_expiry_hours' => 'u9itus.assignment_expiry_hours',
            'head_enterprises_fee_percent' => 'u9itus.head_enterprises_fee_percent',
            'admin_2fa_enforced' => 'platform.standalone.auth.admin_2fa.enabled_default',
        ];

        return $map[$key] ?? null;
    }
}
