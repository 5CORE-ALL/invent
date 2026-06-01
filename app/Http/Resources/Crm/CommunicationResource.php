<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\Crm\CommunicationLog */
class CommunicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hasAttachment = $this->attachment_path !== null && $this->attachment_path !== '';

        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'follow_up_id' => $this->follow_up_id,
            'user_id' => $this->user_id,

            'type' => $this->type,
            'message' => $this->message,

            'attachment' => $hasAttachment
                ? [
                    'path' => $this->attachment_path,
                    'url' => Storage::disk('public')->url((string) $this->attachment_path),
                ]
                : null,

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            'customer' => $this->whenLoaded('customer', function () {
                if ($this->customer === null) {
                    return null;
                }

                return [
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                ];
            }),

            'follow_up' => $this->whenLoaded('followUp', function () {
                if ($this->followUp === null) {
                    return null;
                }

                return [
                    'id' => $this->followUp->id,
                    'title' => $this->followUp->title,
                    'status' => $this->followUp->status,
                ];
            }),

            'user' => $this->whenLoaded('user', function () {
                if ($this->user === null) {
                    return null;
                }

                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
        ];
    }
}
