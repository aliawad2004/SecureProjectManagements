<?php

namespace App\Http\Requests\Attachment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\Project;
use App\Models\Task;
use App\Models\Comment;
use App\Models\Attachment;

class StoreAttachmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $attachableModel = null;
        $attachableType = $this->input('attachable_type');
        $attachableId = $this->input('attachable_id');

        if ($attachableType === 'project') {
            $attachableModel = Project::find($attachableId);
        } elseif ($attachableType === 'task') {
            $attachableModel = Task::find($attachableId);
        } elseif ($attachableType === 'comment') {
            $attachableModel = Comment::find($attachableId);
        }

        if (!$attachableModel) {

            return true;
        }

        return $this->user()->can('createOnAttachable', [Attachment::class, $attachableModel]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => 'required|file|max:10240|mimes:jpeg,png,gif,pdf,doc,docx,xlsx,pptx,txt,zip,rar',
            'attachable_type' => 'required|string|in:project,task,comment',
            'attachable_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    $type = $this->input('attachable_type');
                    if ($type === 'project') {
                        if (!Project::where('id', $value)->exists()) {
                            $fail('The selected project does not exist.');
                        }
                    } elseif ($type === 'task') {
                        if (!Task::where('id', $value)->exists()) {
                            $fail('The selected task does not exist.');
                        }
                    } elseif ($type === 'comment') {
                        if (!Comment::where('id', $value)->exists()) {
                            $fail('The selected comment does not exist.');
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
            'file.required' => 'A file is required for the attachment.',
            'file.file' => 'The uploaded file is not valid.',
            'file.max' => 'The file size must not exceed :max kilobytes.',
            'file.mimes' => 'The file type is not supported. Supported types are: :values.',
            'attachable_type.required' => 'The attachable type is required.',
            'attachable_type.in' => 'The attachable type must be "project", "task", or "comment".',
            'attachable_id.required' => 'The attachable ID is required.',
            'attachable_id.integer' => 'The attachable ID must be an integer.',
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
            'file' => 'file',
            'attachable_type' => 'attachable type',
            'attachable_id' => 'attachable ID',
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
