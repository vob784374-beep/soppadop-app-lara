<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Submission;
use App\Models\User;
use App\Services\Authorization\Contracts\AuthorizationServiceInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SubmissionService
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
    ) {}

    public function listForAssignment(User $user, int $assignmentId): LengthAwarePaginator
    {
        $assignment = Assignment::findOrFail($assignmentId);

        if (! $this->authorization->authorize($user, 'grade-submissions', $assignment)) {
            throw new AuthorizationException();
        }

        return Submission::where('assignment_id', $assignmentId)->paginate(15);
    }

    public function get(User $user, int $id): Submission
    {
        $submission = Submission::findOrFail($id);

        if (! $this->authorization->authorize($user, 'view', $submission)) {
            throw new AuthorizationException();
        }

        return $submission;
    }

    public function submit(User $user, int $assignmentId, array $data): Submission
    {
        $assignment = Assignment::findOrFail($assignmentId);

        if (! $this->authorization->authorize($user, 'submit', $assignment)) {
            throw new AuthorizationException();
        }

        return Submission::create([
            'content'       => $data['content'],
            'assignment_id' => $assignmentId,
            'student_id'    => $user->id,
        ]);
    }

    public function grade(User $user, int $id, int $grade): Submission
    {
        $submission = Submission::findOrFail($id);

        if (! $this->authorization->authorize($user, 'grade', $submission)) {
            throw new AuthorizationException();
        }

        $submission->update(['grade' => $grade]);

        return $submission->refresh();
    }
}
