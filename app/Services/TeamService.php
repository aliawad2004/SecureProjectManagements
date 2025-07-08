<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class TeamService
{
    /**
     * Get a listing of teams based on user access.
     *
     * @param \App\Models\User $user The authenticated user.
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserAccessibleTeams(User $user): Collection
    {
        $cacheKey = 'user_teams_' . $user->id;
        $ttl = now()->addMinutes(5); // Cache for 5 minutes

        return Cache::remember($cacheKey, $ttl, function () use ($user) {
            Log::info("TeamService: Fetching teams for user ID: {$user->id} from DB (not cache).");
            $ownedTeams = $user->ownedTeams;
            $memberTeams = $user->teams;
            return $ownedTeams->merge($memberTeams)->unique('id')->load('owner', 'members', 'projects');
        });
    }

    /**
     * Create a new team.
     *
     * @param string $name The name of the team.
     * @param int $ownerId The ID of the user who will own the team.
     * @return \App\Models\Team
     */
    public function createTeam(string $name, int $ownerId): Team
    {
        Log::info("TeamService: Creating team '{$name}' for owner ID: {$ownerId}.");

        $team = Team::create([
            'name' => $name,
            'owner_id' => $ownerId,
        ]);

        $team->members()->attach($ownerId, ['role' => 'team_admin']);

        Cache::forget('user_teams_' . $ownerId);

        return $team->load('owner', 'members');
    }

    /**
     * Update an existing team.
     *
     * @param \App\Models\Team $team The team model instance.
     * @param string $newName The new name for the team.
     * @param \App\Models\User $updater The user performing the update.
     * @return \App\Models\Team
     */
    public function updateTeam(Team $team, string $newName, User $updater): Team
    {
        Log::info("TeamService: Updating team '{$team->name}' (ID: {$team->id}) to '{$newName}' by user ID: {$updater->id}.");

        $team->update([
            'name' => $newName,
        ]);

        $this->invalidateTeamCache($team, $updater);

        return $team->load('owner', 'members');
    }

    /**
     * Delete a team.
     *
     * @param \App\Models\Team $team The team model instance.
     * @param \App\Models\User $deleter The user performing the deletion.
     * @return bool
     */
    public function deleteTeam(Team $team, User $deleter): bool
    {
        Log::info("TeamService: Deleting team '{$team->name}' (ID: {$team->id}) by user ID: {$deleter->id}.");

        $ownerId = $team->owner_id;
        $memberIds = $team->members->pluck('id');

        $deleted = $team->delete();

        Cache::forget('user_teams_' . $deleter->id);
        Cache::forget('user_teams_' . $ownerId);
        $memberIds->each(function ($userId) {
            Cache::forget('user_teams_' . $userId);
        });

        return $deleted;
    }

    /**
     * Add a member to a team.
     *
     * @param \App\Models\Team $team The team to add member to.
     * @param int $userIdToAdd The ID of the user to add.
     * @param string $role The role of the user in the team.
     * @return \App\Models\Team
     * @throws \Exception If user is already a member.
     */
    public function addTeamMember(Team $team, int $userIdToAdd, string $role): Team
    {
        $userToAdd = User::find($userIdToAdd);

        if ($team->members()->where('user_id', $userToAdd->id)->exists()) {
            throw new \Exception('User is already a member of this team.');
        }

        $team->members()->attach($userToAdd->id, ['role' => $role]);

        Cache::forget('user_teams_' . $userIdToAdd);
        Cache::forget('user_teams_' . Auth::id());

        return $team->load('members');
    }

    /**
     * Update a member's role in a team.
     *
     * @param \App\Models\Team $team The team.
     * @param \App\Models\User $member The member whose role to update.
     * @param string $newRole The new role for the member.
     * @return \App\Models\Team
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If user is not a member.
     */
    public function updateTeamMemberRole(Team $team, User $member, string $newRole): Team
    {
        if (!$team->members()->where('user_id', $member->id)->exists()) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('User is not a member of this team.');
        }

        $team->members()->updateExistingPivot($member->id, ['role' => $newRole]);

        Cache::forget('user_teams_' . $member->id);
        Cache::forget('user_teams_' . Auth::id());

        return $team->load('members');
    }

    /**
     * Remove a member from a team.
     *
     * @param \App\Models\Team $team The team.
     * @param \App\Models\User $member The member to remove.
     * @return bool
     * @throws \Exception If attempting to remove sole team owner.
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If user is not a member.
     */
    public function removeTeamMember(Team $team, User $member): bool
    {
        if ($team->owner_id === $member->id) {
            throw new \Exception('Team owner cannot be removed as a member. Transfer ownership first or delete the team.');
        }

        if (!$team->members()->where('user_id', $member->id)->exists()) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('User is not a member of this team.');
        }

        $team->members()->detach($member->id);

        Cache::forget('user_teams_' . $member->id);
        Cache::forget('user_teams_' . Auth::id());

        return true;
    }

    /**
     * Helper to invalidate team-related caches.
     *
     * @param \App\Models\Team $team
     * @param \App\Models\User $user The user (e.g., updater, deleter, new member) whose cache needs invalidation.
     * @return void
     */
    private function invalidateTeamCache(Team $team, User $user): void
    {
        Cache::forget('user_teams_' . $user->id);
        Cache::forget('user_teams_' . $team->owner_id);
        $team->members->pluck('id')->each(function ($memberId) {
            Cache::forget('user_teams_' . $memberId);
        });
    }
}
