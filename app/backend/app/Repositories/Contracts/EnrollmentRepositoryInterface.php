<?php

namespace App\Repositories\Contracts;

interface EnrollmentRepositoryInterface
{
    public function isEnrolled(int $studentId, int $courseId): bool;

    public function invalidateCache(int $studentId, int $courseId): void;
}
