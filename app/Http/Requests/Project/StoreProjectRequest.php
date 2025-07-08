<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\Project;
use App\Models\Team;

class StoreProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        if (!$this->user()->can('create', Project::class)) {
                        return false;
        }

        $team = Team::find($this->input('team_id'));
        if (!$team) {
            return false;
        }

        if ($this->user()->hasRole('admin')) {
            return true;
        }

        return $this->user()->ownsTeam($team) || $this->user()->belongsToTeam($team);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'team_id' => 'required|exists:teams,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:pending,in_progress,completed',
            'due_date' => 'nullable|date|after_or_equal:today',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'team_id.required' => 'A team must be selected for the project.',
            'team_id.exists' => 'The selected team does not exist.',
            'name.required' => 'The project name is required.',
            'due_date.date' => 'The due date must be a valid date.',
            'due_date.after_or_equal' => 'The due date cannot be in the past.',
            'status.in' => 'The selected status is invalid.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'team_id' => 'team',
            'name' => 'project name',
            'due_date' => 'due date',
            'status' => 'status',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validation Error',
            'errors' => $validator->errors()
        ], 422));
    }

    /**
     * Handle a passed validation attempt.
     *
     * @return void
     */
    protected function passedValidation()
    {
    }
}