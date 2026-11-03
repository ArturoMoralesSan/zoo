<?php

namespace App\Policies;

use App\Models\QuizAttempt;
use App\Models\User;

class QuizAttemptPolicy
{
    /**
     * Solo el usuario que inició el duelo puede responderlo.
     */
    public function submit(User $user, QuizAttempt $attempt): bool
    {
        return $attempt->user_id === $user->id;
    }
}
