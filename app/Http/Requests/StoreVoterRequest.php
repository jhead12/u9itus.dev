<?php

namespace App\Http\Requests;

use App\Http\Middleware\CaptureEarlyBankReferral;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Validates voter registration requests.
 */
class StoreVoterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Fall back to the earlybank_ref cookie if the form field is not present.
     * This preserves attribution when the voter clicked the referral link,
     * browsed around, and returned later without the ?ref= query parameter.
     * Form field always wins over cookie when both are present.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('earlybank_member_id')) {
            $cookie = $this->cookie(CaptureEarlyBankReferral::COOKIE_NAME);
            if (is_string($cookie) && Str::isUuid($cookie)) {
                $this->merge(['earlybank_member_id' => $cookie]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name'                  => 'required|string|max:255',
            'email'                      => 'required|email|unique:voters,email',
            'phone'                      => 'nullable|string|max:20',
            'state'                      => 'nullable|string|max:2',
            'city'                       => 'nullable|string|max:255',
            'zip_code'                   => 'nullable|string|max:10',
            'referral_code'              => 'nullable|string|max:16',
            'earlybank_member_id'        => 'nullable|uuid',
            'payment_method'             => 'nullable|in:wallet,paypal,cashapp,stripe',
            'paypal_email'               => 'nullable|required_if:payment_method,paypal|email',
            'cashapp_tag'                => 'nullable|required_if:payment_method,cashapp|string|max:50',
            'wix_member_id'              => 'nullable|string',
            'wix_site_id'                => 'nullable|integer|exists:wix_sites,id',
            'preferred_governance_levels' => 'nullable|array',
            'preferred_governance_levels.*' => 'string|in:' . implode(',', array_keys(config('u9itus.governance_levels', []))),
        ];
    }
}
