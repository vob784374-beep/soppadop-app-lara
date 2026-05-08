<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\User;
use App\Services\Authorization\Contracts\AuthorizationServiceInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class AssignmentService
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
    ) {}

    public function listForCourse(User $user, int $courseId): LengthAwarePaginator
    {
        $course = Course::findOrFail($courseId);

        if (! $this->authorization->authorize($user, 'view-courses', $course)) {
            throw new AuthorizationException();
        }

        return Assignment::where('course_id', $courseId)->paginate(15);
    }

    public function get(User $user, int $id): Assignment
    {
        $assignment = Assignment::findOrFail($id);

        if (! $this->authorization->authorize($user, 'view-assignments', $assignment)) {
            throw new AuthorizationException();
        }

        return $assignment;
    }

    public function create(User $user, int $courseId, array $data): Assignment
    {
        $course = Course::findOrFail($courseId);

        if (! $this->authorization->authorize($user, 'create-assignments', $course)) {
            throw new AuthorizationException();
        }

        return Assignment::create([
            ...Arr::only($data, ['title', 'description', 'deadline']),
            'course_id' => $courseId,
        ]);
    }
}
