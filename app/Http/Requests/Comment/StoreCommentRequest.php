<?php

namespace App\Http\Requests\Comment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\Project;
use App\Models\Task;
use App\Models\Comment;

class StoreCommentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $commentableModel = null;
        $commentableType = $this->input('commentable_type');
        $commentableId = $this->input('commentable_id');

        if ($commentableType === 'project') {
            $commentableModel = Project::find($commentableId);
        } elseif ($commentableType === 'task') {
            $commentableModel = Task::find($commentableId);
        }

        if (!$commentableModel) {

            return true;
        }

        return $this->user()->can('createOnCommentable', [Comment::class, $commentableModel]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => 'required|string',
            'commentable_type' => 'required|string|in:project,task',
            'commentable_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    $type = $this->input('commentable_type');
                    if ($type === 'project') {
                        if (!Project::where('id', $value)->exists()) {
                            $fail('The selected project does not exist.');
                        }
                    } elseif ($type === 'task') {
                        if (!Task::where('id', $value)->exists()) {
                            $fail('The selected task does not exist.');
                        }
                    }
                },
            ],
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
            'content.required' => 'The comment content is required.',
            'commentable_type.required' => 'The commentable type is required.',
            'commentable_type.in' => 'The commentable type must be either "project" or "task".',
            'commentable_id.required' => 'The commentable ID is required.',
            'commentable_id.integer' => 'The commentable ID must be an integer.',
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
            'content' => 'comment content',
            'commentable_type' => 'commentable type',
            'commentable_id' => 'commentable ID',
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
