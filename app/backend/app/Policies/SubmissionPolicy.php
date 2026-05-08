<?php

namespace App\Policies;

use App\Models\Submission;
use App\Models\User;

class SubmissionPolicy
{
    public function view(User $user, Submission $submission): bool
    {
        if ($user->hasRole('instructor', 'api')) {
            $submission->loadMissing('assignment.course');

            return $submission->assignment->course->instructor_id === $user->id;
        }

        return $submission->student_id === $user->id;
    }

    public function grade(User $user, Submission $submission): bool
    {
        $submission->loadMissing('assignment.course');

        return $submission->assignment->course->instructor_id === $user->id;
    }
}
