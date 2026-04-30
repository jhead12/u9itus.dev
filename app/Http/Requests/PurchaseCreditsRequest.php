<?php

namespace App\Http\Requests;

use App\Models\Politician;
use Illuminate\Foundation\Http\FormRequest;

class PurchaseCreditsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        /** @var Politician|null $politician */
        $politician = $this->route('politician');

        return $user !== null
            && $politician !== null
            && (int) $politician->user_id === (int) $user->id;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1', 'max:100000', 'regex:/^\d+(\.\d{1,2})?$/'],
            'payment_method_id' => ['nullable', 'string'],
        ];
    }
}
