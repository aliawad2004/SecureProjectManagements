<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ProjectService
{
    /**
     * Get a listing of projects based on user access and filters.
     *
     * @param \App\Models\User $user The authenticated user.
     * @param array $filters Optional filters (e.g., 'status' => 'active').
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getProjects(User $user, array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        Log::info("ProjectService: Fetching projects for user ID: {$user->id} with filters: " . json_encode($filters));

        $projectsAsMember = $user->projects()->with('team', 'members')->get();
        $projectsCreated = $user->createdProjects()->with('team', 'members')->get();
        $projectsInOwnedTeams = Project::whereHas('team', function ($query) use ($user) {
            $query->where('owner_id', $user->id);
        })->with('team', 'members')->get();
        $projectsInMemberTeams = Project::whereHas('team.members', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->with('team', 'members')->get();

        $allProjects = $projectsAsMember
                        ->merge($projectsCreated)
                        ->merge($projectsInOwnedTeams)
                        ->merge($projectsInMemberTeams)
                        ->unique('id')
                        ->values();

        if (isset($filters['status']) && $filters['status'] === 'active') {
            $allProjects = $allProjects->filter(fn($project) => $project->status !== 'completed');
        }

        $allProjects->loadCount(['tasks', 'comments']);

        return $allProjects;
    }

    /**
     * Create a new project.
     *
     * @param array $projectData Data containing project details.
     * @param int $creatorId User ID of the project creator.
     * @return \App\Models\Project
     */
    public function createProject(array $projectData, int $creatorId): Project
    {
        Log::info("ProjectService: Creating project '{$projectData['name']}' for creator ID: {$creatorId}");

        $project = Project::create([
            'team_id' => $projectData['team_id'],
            'name' => $projectData['name'],
            'description' => $projectData['description'] ?? null,
            'status' => $projectData['status'] ?? 'pending',
            'due_date' => $projectData['due_date'] ?? null,
            'created_by_user_id' => $creatorId,
        ]);

        $project->members()->attach($creatorId, ['role' => 'project_manager']);

        Log::info("ProjectService: Project '{$project->name}' created successfully with ID: {$project->id}");

        return $project->load('team', 'creator', 'members');
    }

    /**
     * Get a specific project by ID.
     *
     * @param \App\Models\Project $project
     * @param \App\Models\User $user The user requesting the project (for cache key).
     * @return \App\Models\Project
     */
    public function getProjectById(Project $project, User $user): Project
    {
        $cacheKey = 'project_details_' . $project->id . '_' . $user->id;
        $ttl = now()->addMinutes(10);

        $cachedProject = Cache::remember($cacheKey, $ttl, function () use ($project) {
            Log::info("ProjectService: Fetching project {$project->id} from DB (not cache).");
            $project->load('team.owner', 'creator', 'members', 'tasks.assignee', 'comments.user', 'attachments')->loadCount(['tasks', 'comments']);
            $project->completed_tasks_count = $project->tasks()->where('status', 'completed')->count();
            return $project;
        });

        return $cachedProject;
    }

    /**
     * Update an existing project.
     *
     * @param \App\Models\Project $project The project model instance.
     * @param array $data Data to update.
     * @param \App\Models\User $updater The user performing the update.
     * @return \App\Models\Project
     */
    public function updateProject(Project $project, array $data, User $updater): Project
    {
        $oldStatus = $project->status; // Capture old status before update

        $project->update($data);

        // Invalidate cache logic
        $this->invalidateProjectCache($project, $updater);

        // <--- هذا الجزء الخاص بـ Event Dispatching تم حذفه
        // if ($project->status !== $oldStatus) {
        //     event(new \App\Events\ProjectStatusUpdated($project, $oldStatus, $project->status, $updater));
        //     Log::info("ProjectService: ProjectStatusUpdated event dispatched for project ID: {$project->id}. Status changed from {$oldStatus} to {$project->status}.");
        // } else {
        //     Log::info("ProjectService: Project ID: {$project->id} updated, but status did not change. Event not dispatched.");
        // }
        // <--- نهاية الجزء المحذوف

        return $project->load('team', 'creator', 'members');
    }

    /**
     * Delete a project.
     *
     * @param \App\Models\Project $project The project model instance.
     * @param \App\Models\User $deleter The user deleting the project.
     * @return bool
     */
    public function deleteProject(Project $project, User $deleter): bool
    {
        Log::info("ProjectService: Deleting project '{$project->name}' (ID: {$project->id}) by user ID: {$deleter->id}.");

        // Cache invalidation before deletion
        $this->invalidateProjectCache($project, $deleter);

        return $project->delete();
    }

    /**
     * Add a member to a project.
     *
     * @param \App\Models\Project $project
     * @param int $userIdToAdd ID of the user to add.
     * @param string $role Role of the user in the project.
     * @return \App\Models\Project
     * @throws \Exception If user is already a member.
     */
    public function addProjectMember(Project $project, int $userIdToAdd, string $role): Project
    {
        $userToAdd = User::find($userIdToAdd);

        if ($project->members()->where('user_id', $userToAdd->id)->exists()) {
            throw new \Exception('User is already a member of this project.');
        }

        $project->members()->attach($userToAdd->id, ['role' => $role]);

        // Invalidate project cache for the project and new member
        $this->invalidateProjectCache($project, $userToAdd);
        $this->invalidateProjectCache($project, Auth::user());

        return $project->load('members');
    }

    /**
     * Update a member's role in a project.
     *
     * @param \App\Models\Project $project
     * @param \App\Models\User $member The member whose role to update.
     * @param string $newRole The new role.
     * @return \App\Models\Project
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If user is not a member.
     */
    public function updateProjectMemberRole(Project $project, User $member, string $newRole): Project
    {
        if (!$project->members()->where('user_id', $member->id)->exists()) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('User is not a member of this project.');
        }

        $project->members()->updateExistingPivot($member->id, ['role' => $newRole]);

        // Invalidate project cache for the project and updated member
        $this->invalidateProjectCache($project, $member);
        $this->invalidateProjectCache($project, Auth::user());

        return $project->load('members');
    }

    /**
     * Remove a member from a project.
     *
     * @param \App\Models\Project $project
     * @param \App\Models\User $member The member to remove.
     * @return bool
     * @throws \Exception If attempting to remove sole project manager.
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If user is not a member.
     */
    public function removeProjectMember(Project $project, User $member): bool
    {
        // Prevent creator/sole project manager from being removed without transfer
        if ($project->created_by_user_id === $member->id && $project->members()->wherePivot('role', 'project_manager')->count() === 1) {
            throw new \Exception('Cannot remove the sole project manager. Assign another manager first or delete the project.');
        }

        if (!$project->members()->where('user_id', $member->id)->exists()) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('User is not a member of this project.');
        }

        $project->members()->detach($member->id);

        // Invalidate project cache for the project and removed member
        $this->invalidateProjectCache($project, $member);
        $this->invalidateProjectCache($project, Auth::user());

        return true;
    }

    /**
     * Invalidate project-related caches.
     *
     * @param \App\Models\Project $project
     * @param \App\Models\User $user The user (e.g., updater, deleter, new member) whose cache needs invalidation.
     * @return void
     */
    private function invalidateProjectCache(Project $project, User $user): void
    {
        Cache::forget('project_details_' . $project->id . '_' . $user->id);
    }
}
