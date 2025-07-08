<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Requests\Project\AddProjectMemberRequest;
use App\Http\Requests\Project\UpdateProjectMemberRoleRequest;
use App\Services\ProjectService;

class ProjectController extends Controller
{
    protected $projectService;

    public function __construct(ProjectService $projectService)
    {
        $this->middleware('auth:sanctum');
        $this->projectService = $projectService;
    }

    /**
     * Display a listing of the user's projects within their teams or assigned to them.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $this->authorize('viewAny', Project::class);

        if ($user->hasRole('admin')) {
            return response()->json([
                'projects' => Project::with('team', 'members', 'tasks', 'comments')
                                     ->withCount(['tasks', 'comments'])
                                     ->get()
            ]);
        }

        $filters = $request->only('status');
        $allProjects = $this->projectService->getProjects($user, $filters);

        return response()->json([
            'projects' => $allProjects
        ]);
    }

    /**
     * Store a newly created project in storage.
     *
     * @param  \App\Http\Requests\Project\StoreProjectRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreProjectRequest $request)
    {
        $user = Auth::user();

        try {
            $project = $this->projectService->createProject($request->validated(), $user->id);

            if ($project->wasRecentlyCreated) {
                Log::info('ProjectController: New project created: ' . $project->name);
            }

            return response()->json([
                'message' => 'Project created successfully',
                'project' => $project
            ], 201);

        } catch (\Exception $e) {
            Log::error("ProjectController: Project creation failed: " . $e->getMessage());
            return response()->json(['message' => 'Failed to create project: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified project.
     *
     * @param  \App\Models\Project  $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Project $project)
    {
        $user = Auth::user();

        if ($user->hasRole('admin')) {
             Log::info("ProjectController: Admin user ({$user->email}) is viewing project ID: {$project->id}. Bypassing policy check.");
             return response()->json(['project' => $project->load('team.owner', 'creator', 'members', 'tasks.assignee', 'comments.user', 'attachments')->loadCount(['tasks', 'comments'])], 200);
        }

        $this->authorize('view', $project);

        $cachedProject = $this->projectService->getProjectById($project, $user);

        return response()->json([
            'project' => $cachedProject
        ]);
    }

    /**
     * Update the specified project in storage.
     *
     * @param  \App\Http\Requests\Project\UpdateProjectRequest  $request
     * @param  \App\Models\Project  $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateProjectRequest $request, Project $project)
    {
        $updater = Auth::user();

        try {
            $updatedProject = $this->projectService->updateProject($project, $request->validated(), $updater);

            return response()->json([
                'message' => 'Project updated successfully',
                'project' => $updatedProject
            ]);

        } catch (\Exception $e) {
            Log::error("ProjectController: Project update failed: " . $e->getMessage());
            return response()->json(['message' => 'Failed to update project: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified project from storage.
     *
     * @param  \App\Models\Project  $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);

        $deleter = Auth::user();

        try {
            $this->projectService->deleteProject($project, $deleter);

            return response()->json([
                'message' => 'Project deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error("ProjectController: Project deletion failed: " . $e->getMessage());
            return response()->json(['message' => 'Failed to delete project: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Add a member to a project.
     *
     * @param  \App\Http\Requests\Project\AddProjectMemberRequest  $request
     * @param  \App\Models\Project  $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function addMember(AddProjectMemberRequest $request, Project $project)
    {
        $userToAddId = $request->user_id;
        $role = $request->role;

        try {
            $updatedProject = $this->projectService->addProjectMember($project, $userToAddId, $role);

            return response()->json([
                'message' => 'Member added to project successfully',
                'project' => $updatedProject
            ]);

        } catch (\Exception $e) {
            Log::error("ProjectController: Failed to add member to project: " . $e->getMessage());
            if ($e->getMessage() === 'User is already a member of this project.') {
                return response()->json(['message' => $e->getMessage()], 409);
            }
            return response()->json(['message' => 'Failed to add member to project.'], 500);
        }
    }

    /**
     * Update a member's role in a project.
     *
     * @param  \App\Http\Requests\Project\UpdateProjectMemberRoleRequest  $request
     * @param  \App\Models\Project  $project
     * @param  \App\Models\User  $member
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateMemberRole(UpdateProjectMemberRoleRequest $request, Project $project, User $member)
    {
        $newRole = $request->role;

        try {
            $updatedProject = $this->projectService->updateProjectMemberRole($project, $member, $newRole);

            return response()->json([
                'message' => 'Member role updated successfully',
                'project' => $updatedProject
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            Log::error("ProjectController: Failed to update member role in project: " . $e->getMessage());
            return response()->json(['message' => 'Failed to update member role.'], 500);
        }
    }

    /**
     * Remove a member from a project.
     *
     * @param  \App\Models\Project
     * @param  \App\Models\User  
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeMember(Project $project, User $member)
    {
        $this->authorize('manageMembers', $project);

        try {
            $this->projectService->removeProjectMember($project, $member);

            return response()->json([
                'message' => 'Member removed from project successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            Log::error("ProjectController: Failed to remove member from project: " . $e->getMessage());
            if ($e->getMessage() === 'Cannot remove the sole project manager. Assign another manager first or delete the project.') {
                return response()->json(['message' => $e->getMessage()], 403);
            }
            return response()->json(['message' => 'Failed to remove member from project.'], 500);
        }
    }
}
