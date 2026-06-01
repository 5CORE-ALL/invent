<?php

namespace App\Http\Requests\Crm;

use App\Models\Crm\FollowUp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShopifyCustomerFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $uid = $this->user()?->id;
        $this->merge([
            'assigned_user_id' => $this->input('assigned_user_id', $uid),
            'title' => $this->input('title', 'Shopify customer follow-up'),
            'follow_up_type' => $this->input('follow_up_type', FollowUp::TYPE_CALL),
            'priority' => $this->input('priority', FollowUp::PRIORITY_MEDIUM),
        ]);

        if ($this->has('scheduled_at') && $this->input('scheduled_at') === '') {
            $this->merge(['scheduled_at' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'assigned_user_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'min:1', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
            'follow_up_type' => ['required', Rule::in($this->followUpTypes())],
            'priority' => ['required', Rule::in($this->priorities())],
            'scheduled_at' => ['nullable', 'date'],
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
}
