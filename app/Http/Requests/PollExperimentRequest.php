<?php

namespace App\Http\Requests;

use App\Models\Experiment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PollExperimentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $experiment = $this->route('experiment');

        return $experiment instanceof Experiment
            && ($this->user()?->can('view', $experiment) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'after_message_id' => ['nullable', 'integer', 'min:0'],
            'after_event_id' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
