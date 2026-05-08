<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;
use App\Repositories\Contracts\EnrollmentRepositoryInterface;

class LessonPolicy
{
    public function __construct(
        private readonly EnrollmentRepositoryInterface $enrollments,
    ) {}

    public function view(User $user, Lesson $lesson): bool
    {
        $lesson->loadMissing('course');

        if ($user->hasRole('instructor', 'api')) {
            return $lesson->course->instructor_id === $user->id;
        }

        return $this->enrollments->isEnrolled($user->id, $lesson->course_id);
    }

    public function edit(User $user, Lesson $lesson): bool
    {
        $lesson->loadMissing('course');

        return $lesson->course->instructor_id === $user->id;
    }
}
