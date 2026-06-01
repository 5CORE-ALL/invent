<?php

namespace App\Http\Requests\Crm;

use App\Models\Crm\FollowUp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'status' => ['nullable', Rule::in([
                FollowUp::STATUS_PENDING,
                FollowUp::STATUS_COMPLETED,
                FollowUp::STATUS_POSTPONED,
                FollowUp::STATUS_CANCELLED,
            ])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }
}
