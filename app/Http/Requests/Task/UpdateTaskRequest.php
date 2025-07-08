<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\User; 

class UpdateTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        if (!$this->user()->can('update', $this->route('task'))) {
            return false;
        }

        if ($this->has('assigned_to_user_id') && $this->route('task')->assigned_to_user_id !== (int)$this->input('assigned_to_user_id')) {
            $assignedUser = User::find($this->input('assigned_to_user_id'));
            $task = $this->route('task'); // Get the task instance

            if (!$assignedUser || !$task->project->members->contains($assignedUser->id)) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Validation Error', // Or more specific message
                    'errors' => ['assigned_to_user_id' => ['The new assigned user must be a member of the project.']]
                ], 422));
            }
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'assigned_to_user_id' => 'nullable|exists:users,id',
            'status' => 'sometimes|required|string|in:open,in_progress,completed,cancelled',
            'priority' => 'sometimes|required|string|in:low,medium,high',
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
            'name.required' => 'The task name is required.',
            'assigned_to_user_id.exists' => 'The assigned user does not exist.',
            'status.in' => 'The selected status is invalid.',
            'priority.in' => 'The selected priority is invalid.',
            'due_date.date' => 'The due date must be a valid date.',
            'due_date.after_or_equal' => 'The due date cannot be in the past.',
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
            'name' => 'task name',
            'assigned_to_user_id' => 'assigned user',
            'status' => 'status',
            'priority' => 'priority',
            'due_date' => 'due date',
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