<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a new OrganizationEndorsement. Ownership is checked by the
 * controller via OrganizationPolicy; this only validates shape plus the
 * 501(c)(3)-can't-endorse-a-candidate rule (Organization::canEndorseCandidates()) —
 * enforced here, in code, rather than left to sales/support judgment.
 */
class StoreOrganizationEndorsementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'politician_id' => ['nullable', 'required_without:ballot_measure_id', 'integer', 'exists:politicians,id'],
            'ballot_measure_id' => ['nullable', 'required_without:politician_id', 'integer', 'exists:ballot_measures,id'],
            'position' => ['required', 'in:endorse,oppose,neutral'],
            'label' => ['required', 'string', 'max:128'],
            'note' => ['nullable', 'string', 'max:1000'],
            'is_published' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $organization = $this->route('organization');

            if ($this->filled('politician_id') && $organization && ! $organization->canEndorseCandidates()) {
                $validator->errors()->add(
                    'politician_id',
                    'This organization type cannot endorse candidates — only ballot-measure positions are allowed.'
                );
            }
        });
    }
}
