<?php

namespace App\Repositories;

use App\Models\Enrollment;
use App\Repositories\Contracts\EnrollmentRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class EnrollmentRepository implements EnrollmentRepositoryInterface
{
    public function isEnrolled(int $studentId, int $courseId): bool
    {
        return Cache::remember(
            "enrollment:{$studentId}:{$courseId}",
            now()->addHour(),
            fn () => Enrollment::where([
                'student_id' => $studentId,
                'course_id'  => $courseId,
            ])->exists()
        );
    }

    public function invalidateCache(int $studentId, int $courseId): void
    {
        Cache::forget("enrollment:{$studentId}:{$courseId}");
    }
}
