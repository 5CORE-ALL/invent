<?php

namespace App\Http\Requests\Crm;

use App\Models\Crm\FollowUp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeFollowUpStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                FollowUp::STATUS_PENDING,
                FollowUp::STATUS_COMPLETED,
                FollowUp::STATUS_POSTPONED,
                FollowUp::STATUS_CANCELLED,
            ])],
        ];
    }
}
