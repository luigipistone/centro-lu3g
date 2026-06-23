<?php

namespace App\Http\Requests;

use App\Services\CentroNotificationService;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'completion_effect' => ['nullable', Rule::in(['balloons', 'fireworks', 'snow', 'glitch'])],
            'notification_preferences' => ['nullable', 'array'],
            'notification_preferences.*.category' => ['required', Rule::in(CentroNotificationService::CATEGORIES)],
            'notification_preferences.*.in_app' => ['boolean'],
            'notification_preferences.*.browser' => ['boolean'],
            'notification_preferences.*.mail' => ['boolean'],
        ];
    }
}
