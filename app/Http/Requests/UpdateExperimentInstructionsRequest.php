<?php

namespace App\Http\Requests;

use App\Models\Experiment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExperimentInstructionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $experiment = $this->route('experiment');

        return $experiment instanceof Experiment
            && ($this->user()?->can('update', $experiment) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sender_instructions' => ['nullable', 'string', 'max:2000'],
            'receiver_instructions' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['sender_instructions', 'receiver_instructions'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $trimmed = trim($value);
                $this->merge([$field => $trimmed === '' ? null : $trimmed]);
            }
        }
    }
}
