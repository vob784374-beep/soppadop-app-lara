<?php

namespace App\Services;

use App\Models\Course;
use App\Models\User;
use App\Services\Authorization\Contracts\AuthorizationServiceInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class CourseService
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
    ) {}

    public function list(User $user, int $perPage = 15): LengthAwarePaginator
    {
        if ($user->hasRole('admin', 'api')) {
            return Course::paginate($perPage);
        }

        if ($user->hasRole('instructor', 'api')) {
            return Course::where('instructor_id', $user->id)->paginate($perPage);
        }

        return Course::whereHas('enrollments', fn ($q) => $q->where('student_id', $user->id))
            ->paginate($perPage);
    }

    public function get(User $user, int $id): Course
    {
        $course = Course::findOrFail($id);

        if (! $this->authorization->authorize($user, 'view-courses', $course)) {
            throw new AuthorizationException();
        }

        return $course;
    }

    public function create(User $user, array $data): Course
    {
        return Course::create([
            ...Arr::only($data, ['title', 'description', 'published']),
            'instructor_id' => $user->id,
        ]);
    }

    public function update(User $user, int $id, array $data): Course
    {
        $course = Course::findOrFail($id);

        if (! $this->authorization->authorize($user, 'edit-courses', $course)) {
            throw new AuthorizationException();
        }

        $course->update(Arr::only($data, ['title', 'description', 'published']));

        return $course->refresh();
    }

    public function delete(User $user, int $id): void
    {
        $course = Course::findOrFail($id);

        if (! $this->authorization->authorize($user, 'delete-courses', $course)) {
            throw new AuthorizationException();
        }

        $course->delete();
    }
}
