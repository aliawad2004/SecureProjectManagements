<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Log;

class TeamPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Team $team): bool
    {
        return $user->ownsTeam($team) || $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {

        return $user->hasRole('admin') ||
            $user->hasRole('project_manager') ||
            $user->ownedTeams()->exists() ||
            $user->teams()->wherePivot('role', 'team_admin')->exists();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Team $team): bool
    {
        return $user->ownsTeam($team) || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Team $team): bool
    {
        return $user->ownsTeam($team) || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Team $team): bool
    {
        return $user->ownsTeam($team) || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Team $team): bool
    {
        return $user->ownsTeam($team) || $user->hasRole('admin');
    }

    public function addMember(User $user, Team $team): bool
    {
        return $user->ownsTeam($team) || $user->hasRole('admin');
    }
    public function manageMembers(User $user, Team $team): bool
    {
        Log::info("TeamPolicy@manageMembers: User {$user->id} ({$user->email}) on team {$team->id} ({$team->name})");
        Log::info("TeamPolicy@manageMembers: User is admin? " . ($user->hasRole('admin') ? 'Yes' : 'No'));
        Log::info("TeamPolicy@manageMembers: User owns team? " . ($user->ownsTeam($team) ? 'Yes' : 'No'));
        Log::info("TeamPolicy@manageMembers: User is team_admin? " . ($user->hasTeamRole($team, 'team_admin') ? 'Yes' : 'No'));

        return $user->hasRole('admin') || $user->ownsTeam($team) || $user->hasTeamRole($team, 'team_admin');
    }
}
