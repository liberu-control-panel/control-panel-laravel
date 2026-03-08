<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;

class TeamManagementService
{
    /**
     * Assign the user to a default team or create a personal team.
     */
    public function assignUserToDefaultTeam(User $user): void
    {
        if ($user->currentTeam) {
            return;
        }

        $this->createPersonalTeamForUser($user);
    }

    /**
     * Create a personal team for the user and set it as their current team.
     */
    public function createPersonalTeamForUser(User $user): Team
    {
        $team = $user->ownedTeams()->create([
            'name'          => $user->name . "'s Team",
            'personal_team' => true,
        ]);

        $user->update(['current_team_id' => $team->id]);

        return $team;
    }
}
