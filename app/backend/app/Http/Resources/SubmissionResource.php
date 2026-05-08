<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'assignment_id' => $this->assignment_id,
            'student_id'    => $this->student_id,
            'content'       => $this->content,
            'grade'         => $this->grade,
            'submitted_at'  => $this->submitted_at?->toIso8601String(),
        ];
    }
}
