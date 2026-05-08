<?php

namespace App\Providers;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Submission;
use App\Policies\AssignmentPolicy;
use App\Policies\CoursePolicy;
use App\Policies\LessonPolicy;
use App\Policies\SubmissionPolicy;
use App\Repositories\Contracts\EnrollmentRepositoryInterface;
use App\Repositories\EnrollmentRepository;
use App\Services\Authorization\AbacChecker;
use App\Services\Authorization\AuthorizationService;
use App\Services\Authorization\Contracts\AbacCheckerInterface;
use App\Services\Authorization\Contracts\AuthorizationServiceInterface;
use App\Services\Authorization\Contracts\RbacCheckerInterface;
use App\Services\Authorization\RbacChecker;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Course::class     => CoursePolicy::class,
        Lesson::class     => LessonPolicy::class,
        Assignment::class => AssignmentPolicy::class,
        Submission::class => SubmissionPolicy::class,
    ];

    public function register(): void
    {
        $this->app->bind(EnrollmentRepositoryInterface::class, EnrollmentRepository::class);
        $this->app->bind(RbacCheckerInterface::class, RbacChecker::class);
        $this->app->bind(AbacCheckerInterface::class, AbacChecker::class);
        $this->app->bind(AuthorizationServiceInterface::class, AuthorizationService::class);
    }

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(function ($user, $ability): ?bool {
            if ($user->hasRole('admin', 'api')) {
                return true;
            }

            return null;
        });
    }
}
