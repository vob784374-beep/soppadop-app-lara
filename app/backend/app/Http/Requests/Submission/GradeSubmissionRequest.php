<?php

namespace App\Http\Requests\Submission;

use Illuminate\Foundation\Http\FormRequest;

class GradeSubmissionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'grade' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }
}
