<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];


    public function ownedTeams()
    {
        return $this->hasMany(Team::class, 'owner_id');
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_user')
            ->withPivot('role')
            ->withTimestamps();
    }


    public function createdProjects()
    {
        return $this->hasMany(Project::class, 'created_by_user_id');
    }

        public function assignedTasks()
    {
        return $this->hasMany(Task::class, 'assigned_to_user_id');
    }


    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Check if the user has a specific role.
     * This is for the `role` column on the users table (e.g., admin, project_manager, member).
     * @param string $role
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if the user is an owner of a given team.
     * @param Team $team
     * @return bool
     */
    public function ownsTeam(Team $team): bool
    {
        return $this->id === $team->owner_id;
    }

    /**
     * Check if the user is a member of a given team.
     * @param Team $team
     * @return bool
     */
    public function belongsToTeam(Team $team): bool
    {
        return $this->teams->contains($team);
    }

    /**
     * Check if the user has a specific role within a team.
     * @param Team $team
     * @param string $teamRole (e.g., 'team_admin', 'member')
     * @return bool
     */
    public function hasTeamRole(Team $team, string $teamRole): bool
    {
        return $this->teams()->where('team_id', $team->id)->wherePivot('role', $teamRole)->exists();
    }

    /**
     * Check if the user belongs to a given project.
     * @param Project $project
     * @return bool
     */
    public function belongsToProject(Project $project): bool
    {
        return $this->projects->contains($project);
    }

    /**
     * Check if the user has a specific role within a project.
     * @param Project $project
     * @param string $projectRole (e.g., 'project_manager', 'member')
     * @return bool
     */
    public function hasProjectRole(Project $project, string $projectRole): bool
    {
        return $this->projects()->where('project_id', $project->id)->wherePivot('role', $projectRole)->exists();
    }
}