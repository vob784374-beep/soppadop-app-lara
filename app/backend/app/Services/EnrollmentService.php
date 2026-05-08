<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Repositories\Contracts\EnrollmentRepositoryInterface;

class EnrollmentService
{
    public function __construct(
        private readonly EnrollmentRepositoryInterface $enrollments,
    ) {}

    public function enroll(User $user, int $courseId): Enrollment
    {
        Course::findOrFail($courseId);

        $enrollment = Enrollment::firstOrCreate([
            'student_id' => $user->id,
            'course_id'  => $courseId,
        ], [
            'enrolled_at' => now(),
        ]);

        $this->enrollments->invalidateCache($user->id, $courseId);

        return $enrollment;
    }
}
