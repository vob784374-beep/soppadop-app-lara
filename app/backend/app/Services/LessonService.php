<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Authorization\Contracts\AuthorizationServiceInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class LessonService
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

        return Lesson::where('course_id', $courseId)->orderBy('order')->paginate(30);
    }

    public function get(User $user, int $id): Lesson
    {
        $lesson = Lesson::findOrFail($id);

        if (! $this->authorization->authorize($user, 'view-lessons', $lesson)) {
            throw new AuthorizationException();
        }

        return $lesson;
    }

    public function create(User $user, int $courseId, array $data): Lesson
    {
        $course = Course::findOrFail($courseId);

        if (! $this->authorization->authorize($user, 'create-lessons', $course)) {
            throw new AuthorizationException();
        }

        return Lesson::create([
            ...Arr::only($data, ['title', 'content', 'order', 'published']),
            'course_id' => $courseId,
        ]);
    }

    public function update(User $user, int $id, array $data): Lesson
    {
        $lesson = Lesson::findOrFail($id);

        if (! $this->authorization->authorize($user, 'edit-lessons', $lesson)) {
            throw new AuthorizationException();
        }

        $lesson->update(Arr::only($data, ['title', 'content', 'order', 'published']));

        return $lesson->refresh();
    }
}
