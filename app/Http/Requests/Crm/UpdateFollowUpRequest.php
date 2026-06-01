<?php

namespace App\Http\Requests\Crm;

use App\Models\Crm\FollowUp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('company_id') && ($this->input('company_id') === '' || $this->input('company_id') === '0')) {
            $this->merge(['company_id' => null]);
        }

        foreach (['scheduled_at', 'reminder_at', 'next_follow_up_at'] as $key) {
            if ($this->has($key) && $this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'company_id' => ['sometimes', 'nullable', 'integer', 'exists:companies,id'],
            'assigned_user_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'title' => ['sometimes', 'required', 'string', 'min:1', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],

            'follow_up_type' => ['sometimes', 'required', Rule::in($this->followUpTypes())],

            'priority' => ['sometimes', 'required', Rule::in($this->priorities())],

            'status' => ['sometimes', 'required', Rule::in($this->followUpStatuses())],

            'scheduled_at' => ['nullable', 'date'],
            'reminder_at' => ['nullable', 'date'],
            'next_follow_up_at' => ['nullable', 'date'],

            'outcome' => ['nullable', Rule::in($this->outcomes())],
        ];
    }

    public function attributes(): array
    {
        return [
            'assigned_user_id' => 'assignee',
            'follow_up_type' => 'follow-up type',
            'scheduled_at' => 'scheduled date',
            'reminder_at' => 'reminder date',
            'next_follow_up_at' => 'next follow-up date',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function followUpTypes(): array
    {
        return [
            FollowUp::TYPE_CALL,
            FollowUp::TYPE_EMAIL,
            FollowUp::TYPE_WHATSAPP,
            FollowUp::TYPE_MEETING,
            FollowUp::TYPE_SMS,
            FollowUp::TYPE_OTHER,
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function priorities(): array
    {
        return [
            FollowUp::PRIORITY_LOW,
            FollowUp::PRIORITY_MEDIUM,
            FollowUp::PRIORITY_HIGH,
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function followUpStatuses(): array
    {
        return [
            FollowUp::STATUS_PENDING,
            FollowUp::STATUS_COMPLETED,
            FollowUp::STATUS_POSTPONED,
            FollowUp::STATUS_CANCELLED,
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function outcomes(): array
    {
        return [
            FollowUp::OUTCOME_INTERESTED,
            FollowUp::OUTCOME_NOT_INTERESTED,
            FollowUp::OUTCOME_CALLBACK,
            FollowUp::OUTCOME_CONVERTED,
        ];
    }
}
