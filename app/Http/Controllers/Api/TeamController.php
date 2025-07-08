<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

// Import Form Requests
use App\Http\Requests\Team\StoreTeamRequest;
use App\Http\Requests\Team\UpdateTeamRequest;
use App\Http\Requests\Team\AddTeamMemberRequest;
use App\Http\Requests\Team\UpdateTeamMemberRoleRequest;

use App\Services\TeamService;

class TeamController extends Controller
{
    protected $teamService;

    public function __construct(TeamService $teamService)
    {
        $this->middleware('auth:sanctum');
        $this->teamService = $teamService;
    }

    /**
     * Display a listing of the user's teams.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $user = Auth::user();
        $this->authorize('viewAny', Team::class);

        if ($user->hasRole('admin')) {
            return response()->json([
                'teams' => Team::all()->load('owner', 'members', 'projects')
            ]);
        }

        $allTeams = $this->teamService->getUserAccessibleTeams($user);

        return response()->json([
            'teams' => $allTeams
        ]);
    }

    /**
     * Store a newly created team in storage.
     *
     * @param  \App\Http\Requests\Team\StoreTeamRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreTeamRequest $request)
    {
        $user = Auth::user();

        try {
            $team = $this->teamService->createTeam($request->name, $user->id);

            return response()->json([
                'message' => 'Team created successfully',
                'team' => $team
            ], 201);

        } catch (\Exception $e) {
            Log::error("TeamController: Team creation failed: " . $e->getMessage());
            return response()->json(['message' => 'Failed to create team: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified team.
     *
     * @param  \App\Models\Team  $team
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Team $team)
    {
        $user = Auth::user();
        if ($user->hasRole('admin')) {
            Log::info("Admin user ({$user->email}) is viewing team ID: {$team->id}. Bypassing policy check.");
            return response()->json(['team' => $team->load('owner', 'members', 'projects')], 200);
        }
        $this->authorize('view', $team);
        return response()->json([
            'team' => $team->load('owner', 'members', 'projects')
        ]);
    }

    /**
     * Update the specified team in storage.
     *
     * @param  \App\Http\Requests\Team\UpdateTeamRequest  $request
     * @param  \App\Models\Team  $team
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateTeamRequest $request, Team $team)
    {
        $updater = Auth::user();

        try {
            $updatedTeam = $this->teamService->updateTeam($team, $request->name, $updater);

            return response()->json([
                'message' => 'Team updated successfully',
                'team' => $updatedTeam
            ]);

        } catch (\Exception $e) {
            Log::error("TeamController: Team update failed: " . $e->getMessage());
            return response()->json(['message' => 'Failed to update team: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified team from storage.
     *
     * @param  \App\Models\Team  $team
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Team $team)
    {
        $this->authorize('delete', $team);
        $deleter = Auth::user();

        try {
            $this->teamService->deleteTeam($team, $deleter);

            return response()->json([
                'message' => 'Team deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error("TeamController: Team deletion failed: " . $e->getMessage());
            return response()->json(['message' => 'Failed to delete team: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Add a member to a team.
     *
     * @param  \App\Http\Requests\Team\AddTeamMemberRequest  $request
     * @param  \App\Models\Team  $team
     * @return \Illuminate\Http\JsonResponse
     */
    public function addMember(AddTeamMemberRequest $request, Team $team)
    {
        $userToAddId = $request->user_id;
        $role = $request->role;

        try {
            $updatedTeam = $this->teamService->addTeamMember($team, $userToAddId, $role);

            return response()->json([
                'message' => 'Member added to team successfully',
                'team' => $updatedTeam
            ]);

        } catch (\Exception $e) {
            Log::error("TeamController: Failed to add member to team: " . $e->getMessage());
            if ($e->getMessage() === 'User is already a member of this team.') {
                return response()->json(['message' => $e->getMessage()], 409);
            }
            return response()->json(['message' => 'Failed to add member to team.'], 500);
        }
    }

    /**
     * Update a member's role in a team.
     *
     * @param  \App\Http\Requests\Team\UpdateTeamMemberRoleRequest  $request
     * @param  \App\Models\Team  $team
     * @param  \App\Models\User  $member
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateMemberRole(UpdateTeamMemberRoleRequest $request, Team $team, User $member)
    {
        $newRole = $request->role;

        try {
            $updatedTeam = $this->teamService->updateTeamMemberRole($team, $member, $newRole);

            return response()->json([
                'message' => 'Member role updated successfully',
                'team' => $updatedTeam
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            Log::error("TeamController: Failed to update member role in team: " . $e->getMessage());
            return response()->json(['message' => 'Failed to update member role.'], 500);
        }
    }

    /**
     * Remove a member from a team.
     *
     * @param  \App\Models\Team  $team
     * @param  \App\Models\User  $member
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeMember(Team $team, User $member)
    {
        $this->authorize('addMember', $team);

        try {
            $this->teamService->removeTeamMember($team, $member);

            return response()->json([
                'message' => 'Member removed from team successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            Log::error("TeamController: Failed to remove member from team: " . $e->getMessage());
            if ($e->getMessage() === 'Team owner cannot be removed as a member. Transfer ownership first or delete the team.') {
                return response()->json(['message' => $e->getMessage()], 403);
            }
            return response()->json(['message' => 'Failed to remove member from team.'], 500);
        }
    }
}