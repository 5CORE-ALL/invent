<?php

namespace App\Http\Requests\Crm;

use App\Models\Crm\CommunicationLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommunicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('follow_up_id') === '' || $this->input('follow_up_id') === '0') {
            $this->merge(['follow_up_id' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],

            'follow_up_id' => [
                'nullable',
                'integer',
                Rule::exists('follow_ups', 'id')->where(function ($query) {
                    $query->where('customer_id', (int) $this->input('customer_id'));
                }),
            ],

            'type' => ['required', Rule::in($this->communicationTypes())],

            'message' => ['required', 'string', 'min:1', 'max:65535'],

            'attachment' => ['nullable', 'file', 'max:10240'],
        ];
    }

    public function attributes(): array
    {
        return [
            'customer_id' => 'customer',
            'follow_up_id' => 'follow-up',
            'type' => 'communication type',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function communicationTypes(): array
    {
        return [
            CommunicationLog::TYPE_CALL,
            CommunicationLog::TYPE_EMAIL,
            CommunicationLog::TYPE_WHATSAPP,
            CommunicationLog::TYPE_MEETING,
            CommunicationLog::TYPE_SMS,
        ];
    }
}
