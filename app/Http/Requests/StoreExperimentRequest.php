<?php

namespace App\Http\Requests;

use App\Models\Experiment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreExperimentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Experiment::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'max_turns' => [
                'required',
                'integer',
                'min:1',
                'max:'.config('agent.maximum_turns'),
            ],
            'sender_instructions' => ['nullable', 'string', 'max:2000'],
            'receiver_instructions' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->trimNullableInstructions();
    }

    private function trimNullableInstructions(): void
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
