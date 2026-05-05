<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'avatar',
        'avatar_position_x',
        'avatar_position_y',
        'avatar_zoom',
        'password',
        'google_id',
        'role',
        'designation',
        'is_active',
        'show_in_salary',
        'deactivated_at',
        'logined',
        'resource_department_id',
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
        'is_active' => 'boolean',
        'show_in_salary' => 'boolean',
        'deactivated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function permission()
    {
        return $this->hasOne(Permission::class);
    }

    /**
     * Get the user's Role & Responsibility.
     */
    public function userRR()
    {
        return $this->hasOne(UserRR::class);
    }

    /**
     * Get the user's salary information.
     */
    public function userSalary()
    {
        return $this->hasOne(UserSalary::class);
    }

    /**
     * R&R portfolio document assignments (shared file may apply to many users).
     */
    public function rrPortfolioAssignments()
    {
        return $this->hasMany(RrPortfolioUser::class);
    }

    /**
     * Whether the user is a 5Core team member (internal support agent access).
     */
    public function is5CoreMember(): bool
    {
        return str_ends_with(strtolower($this->email ?? ''), '@5core.com');
    }

    /**
     * Get performance reviews where this user is the employee
     */
    public function performanceReviews()
    {
        return $this->hasMany(PerformanceReview::class, 'employee_id');
    }

    /**
     * Get performance reviews where this user is the reviewer
     */
    public function reviewedPerformanceReviews()
    {
        return $this->hasMany(PerformanceReview::class, 'reviewer_id');
    }

    /**
     * Get the user's designation model (if designation field matches)
     */
    public function designationModel()
    {
        return $this->belongsTo(Designation::class, 'designation', 'name');
    }

    public function resourceDepartment()
    {
        return $this->belongsTo(ResourceDepartment::class, 'resource_department_id');
    }
}
