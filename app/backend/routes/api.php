<?php

use App\Http\Controllers\Api\V1\AssignmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\EnrollmentController;
use App\Http\Controllers\Api\V1\ItemController;
use App\Http\Controllers\Api\V1\LessonController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\SubmissionController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware('jwt.authenticate')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::patch('/profile', [ProfileController::class, 'update']);

    Route::get('/items', [ItemController::class, 'index']);
    Route::post('/items', [ItemController::class, 'store']);
    Route::get('/items/{id}', [ItemController::class, 'show']);
    Route::patch('/items/{id}', [ItemController::class, 'update']);
    Route::delete('/items/{id}', [ItemController::class, 'destroy']);

    // LMS — Courses
    Route::get('/courses', [CourseController::class, 'index'])
        ->middleware('permission:view-courses');
    Route::post('/courses', [CourseController::class, 'store'])
        ->middleware('permission:create-courses');
    Route::get('/courses/{id}', [CourseController::class, 'show'])
        ->middleware('permission:view-courses');
    Route::patch('/courses/{id}', [CourseController::class, 'update'])
        ->middleware('permission:edit-courses');
    Route::delete('/courses/{id}', [CourseController::class, 'destroy'])
        ->middleware('permission:delete-courses');

    // LMS — Lessons (nested under courses)
    Route::get('/courses/{courseId}/lessons', [LessonController::class, 'index'])
        ->middleware('permission:view-lessons');
    Route::post('/courses/{courseId}/lessons', [LessonController::class, 'store'])
        ->middleware('permission:create-lessons');
    Route::get('/lessons/{id}', [LessonController::class, 'show'])
        ->middleware('permission:view-lessons');
    Route::patch('/lessons/{id}', [LessonController::class, 'update'])
        ->middleware('permission:edit-lessons');

    // LMS — Assignments (nested under courses)
    Route::get('/courses/{courseId}/assignments', [AssignmentController::class, 'index'])
        ->middleware('permission:view-assignments');
    Route::post('/courses/{courseId}/assignments', [AssignmentController::class, 'store'])
        ->middleware('permission:create-assignments');
    Route::get('/assignments/{id}', [AssignmentController::class, 'show'])
        ->middleware('permission:view-assignments');

    // LMS — Submissions (nested under assignments)
    Route::get('/assignments/{assignmentId}/submissions', [SubmissionController::class, 'index'])
        ->middleware('permission:grade-submissions');
    Route::post('/assignments/{assignmentId}/submissions', [SubmissionController::class, 'store'])
        ->middleware('permission:submit-assignments');
    Route::get('/submissions/{id}', [SubmissionController::class, 'show']);
    Route::patch('/submissions/{id}/grade', [SubmissionController::class, 'grade'])
        ->middleware('permission:grade-submissions');

    // LMS — Enrollments
    Route::post('/courses/{courseId}/enroll', [EnrollmentController::class, 'store'])
        ->middleware('permission:enroll-courses');
});
