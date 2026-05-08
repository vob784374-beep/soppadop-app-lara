<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\User;
use App\Repositories\Contracts\EnrollmentRepositoryInterface;

class AssignmentPolicy
{
    public function __construct(
        private readonly EnrollmentRepositoryInterface $enrollments,
    ) {}

    public function view(User $user, Assignment $assignment): bool
    {
        if ($user->hasRole('instructor', 'api')) {
            $assignment->loadMissing('course');

            return $assignment->course->instructor_id === $user->id;
        }

        return $this->enrollments->isEnrolled($user->id, $assignment->course_id);
    }

    public function submit(User $user, Assignment $assignment): bool
    {
        if (! $this->enrollments->isEnrolled($user->id, $assignment->course_id)) {
            return false;
        }

        return now()->lessThan($assignment->deadline);
    }
}
