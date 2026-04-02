<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAccessLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class UserAccessTrackingService
{
    public function track(Request $request, ?User $user, string $source = 'request'): void
    {
        if (!$user) {
            return;
        }

        $ipAddress = $request->ip();
        $userAgent = (string) $request->userAgent();
        $isMobile = $this->isMobileUserAgent($userAgent);
        $vpnSignal = $this->vpnSignal($request, $userAgent);
        $isVpnSuspected = $vpnSignal !== null;

        // Avoid writing near-duplicate logs repeatedly for bursty request traffic.
        $recentDuplicate = UserAccessLog::query()
            ->where('user_id', $user->id)
            ->where('source', $source)
            ->where('ip_address', $ipAddress)
            ->where('is_mobile', $isMobile)
            ->where('is_vpn_suspected', $isVpnSuspected)
            ->where('request_path', '/' . ltrim($request->path(), '/'))
            ->where('created_at', '>=', Carbon::now()->subMinutes(10))
            ->exists();

        if (!$recentDuplicate) {
            UserAccessLog::create([
                'user_id' => $user->id,
                'source' => $source,
                'ip_address' => $ipAddress,
                'user_agent' => Str::limit($userAgent, 65000, ''),
                'is_mobile' => $isMobile,
                'is_vpn_suspected' => $isVpnSuspected,
                'vpn_signal' => $vpnSignal,
                'request_path' => '/' . ltrim($request->path(), '/'),
                'accessed_at' => now(),
            ]);
        }

        $user->forceFill([
            'last_seen_ip' => $ipAddress,
            'last_seen_user_agent' => $userAgent,
            'last_seen_is_mobile' => $isMobile,
            'last_seen_is_vpn_suspected' => $isVpnSuspected,
            'last_seen_at' => now(),
        ])->save();
    }

    private function isMobileUserAgent(string $userAgent): bool
    {
        if ($userAgent === '') {
            return false;
        }

        return (bool) preg_match('/iphone|ipad|ipod|android|blackberry|iemobile|opera mini|mobile/i', $userAgent);
    }

    private function vpnSignal(Request $request, string $userAgent): ?string
    {
        $headers = [
            'cf-ipcity',
            'cf-connecting-ip',
            'x-forwarded-for',
            'x-real-ip',
            'via',
            'forwarded',
            'x-vpn',
            'x-proxy-id',
        ];

        if (preg_match('/\b(vpn|proxy|tor browser)\b/i', $userAgent) === 1) {
            return 'user-agent-indicates-vpn-or-proxy';
        }

        $xff = (string) $request->headers->get('x-forwarded-for', '');
        if ($xff !== '' && count(array_filter(array_map('trim', explode(',', $xff)))) > 1) {
            return 'multiple-forwarded-ips';
        }

        foreach ($headers as $header) {
            if ($request->headers->has($header) && in_array($header, ['x-vpn', 'x-proxy-id', 'via'], true)) {
                return 'proxy-related-header:' . $header;
            }
        }

        return null;
    }
}
