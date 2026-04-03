<?php

use App\Models\ReferralEarning;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_earnings', function (Blueprint $table) {
            $table->string('payment_mode', 10)
                ->default(ReferralEarning::PAYMENT_MODE_TEST)
                ->after('commission_amount');

            $table->index('payment_mode');
        });

        $fallbackMode = $this->fallbackPaymentMode();

        DB::table('referral_earnings')
            ->select(['id', 'view_session_id', 'politician_id'])
            ->orderBy('id')
            ->chunkById(100, function ($earnings) use ($fallbackMode): void {
                foreach ($earnings as $earning) {
                    DB::table('referral_earnings')
                        ->where('id', $earning->id)
                        ->update([
                            'payment_mode' => $this->resolvePaymentModeForEarning($earning, $fallbackMode),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('referral_earnings', function (Blueprint $table) {
            $table->dropIndex(['payment_mode']);
            $table->dropColumn('payment_mode');
        });
    }

    private function resolvePaymentModeForEarning(object $earning, string $fallbackMode): string
    {
        if (! empty($earning->view_session_id)) {
            $campaignId = DB::table('view_sessions')
                ->where('id', $earning->view_session_id)
                ->value('political_campaign_id');

            if ($campaignId) {
                $mode = $this->paymentModeFromTransactionQuery(
                    DB::table('campaign_transactions')
                        ->where('campaign_id', $campaignId)
                        ->orderByDesc('id')
                );

                if ($mode !== null) {
                    return $mode;
                }
            }
        }

        if (! empty($earning->politician_id)) {
            $mode = $this->paymentModeFromTransactionQuery(
                DB::table('campaign_transactions')
                    ->where('politician_id', $earning->politician_id)
                    ->whereNull('campaign_id')
                    ->where('amount', '>', 0)
                    ->where('transaction_type', '!=', 'refund')
                    ->orderBy('id')
            );

            if ($mode !== null) {
                return $mode;
            }
        }

        return $fallbackMode;
    }

    private function paymentModeFromTransactionQuery($query): ?string
    {
        foreach ($query->get(['metadata']) as $transaction) {
            $mode = $this->paymentModeFromMetadata($transaction->metadata ?? null);

            if ($mode !== null) {
                return $mode;
            }
        }

        return null;
    }

    private function paymentModeFromMetadata(mixed $metadata): ?string
    {
        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            $metadata = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($metadata)) {
            return null;
        }

        $mode = $metadata['payment_mode'] ?? null;
        $resolvedMode = null;

        if ($mode === ReferralEarning::PAYMENT_MODE_LIVE) {
            $resolvedMode = ReferralEarning::PAYMENT_MODE_LIVE;
        } elseif ($mode === ReferralEarning::PAYMENT_MODE_TEST) {
            $resolvedMode = ReferralEarning::PAYMENT_MODE_TEST;
        }

        return $resolvedMode;
    }

    private function fallbackPaymentMode(): string
    {
        $secret = (string) config('services.stripe.secret', '');

        return str_starts_with($secret, 'sk_live_')
            ? ReferralEarning::PAYMENT_MODE_LIVE
            : ReferralEarning::PAYMENT_MODE_TEST;
    }
};
