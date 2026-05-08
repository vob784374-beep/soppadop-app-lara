<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;
use App\Repositories\Contracts\EnrollmentRepositoryInterface;

class CoursePolicy
{
    public function __construct(
        private readonly EnrollmentRepositoryInterface $enrollments,
    ) {}

    public function view(User $user, Course $course): bool
    {
        if ($user->hasRole('instructor', 'api')) {
            return $course->instructor_id === $user->id || $course->published;
        }

        return $this->enrollments->isEnrolled($user->id, $course->id);
    }

    public function edit(User $user, Course $course): bool
    {
        return $course->instructor_id === $user->id;
    }

    public function delete(User $user, Course $course): bool
    {
        return false;
    }
}
