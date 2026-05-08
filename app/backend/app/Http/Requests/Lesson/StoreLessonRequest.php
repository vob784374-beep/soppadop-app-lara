<?php

namespace App\Http\Requests\Lesson;

use Illuminate\Foundation\Http\FormRequest;

class StoreLessonRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'     => ['required', 'string', 'max:255'],
            'content'   => ['nullable', 'string'],
            'order'     => ['integer', 'min:0'],
            'published' => ['boolean'],
        ];
    }
}
