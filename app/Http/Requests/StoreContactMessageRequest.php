<?php

namespace App\Http\Requests;

use App\Enums\ContactTopic;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A message from the public contact form. Anyone may send one; the form is
 * rate limited, and a hidden "website" field catches simple bots.
 */
class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'topic' => ['required', Rule::enum(ContactTopic::class)],
            'name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:254'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'policy' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'topic' => 'Choose what your message is about.',
            'email.required' => 'Enter an email address so we can reply.',
            'email' => 'Enter an email address like name@example.com.',
            'message.required' => 'Write your message.',
            'message.min' => 'Write at least 10 characters.',
            'message.max' => 'Keep your message under 5,000 characters.',
            'name.max' => 'Keep your name under 100 characters.',
        ];
    }

    /**
     * True when the hidden field a person can't see has been filled in.
     */
    public function isFromBot(): bool
    {
        return $this->filled('website');
    }
}
