<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'days' => ['required', 'integer', 'between:1,3'],
            'exams_per_day' => ['required', 'integer', 'between:1,10'],
            'time_preset_id' => ['nullable', 'integer', Rule::exists('time_presets', 'id')],
            'school_year_id' => ['required', 'integer', Rule::exists('school_years', 'id')],
            'quarter_id' => ['required', 'integer', Rule::exists('quarters', 'id')],
            'max_proctors_per_teacher' => ['required', 'integer', 'min:0', 'max:20'],
        ];
    }
}
