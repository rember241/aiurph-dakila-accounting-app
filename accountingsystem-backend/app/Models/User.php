<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;
use Modules\Users\Models\Company;
use Modules\Users\Models\OrgUnit;
use Modules\Users\Models\Tenant;
use Modules\Users\Models\TenantMembership;
use Modules\Users\Models\Role;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'currency_code',
        'password',
        'status',
        'department',
        'access_level',
        'avatar_url',
        'avatar_path',
        'avatar_mime',
        'avatar_size',
        'avatar_width',
        'avatar_height',
        'avatar_updated_at',
        'last_active_at',
        'company_id',
        'primary_org_unit_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_active_at' => 'datetime',
            'avatar_size' => 'integer',
            'avatar_width' => 'integer',
            'avatar_height' => 'integer',
            'avatar_updated_at' => 'datetime',
        ];
    }

    /**
     * Assigned roles for RBAC.
     *
     * Roles define module permissions which are resolved into Sanctum token abilities.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withTimestamps();
    }

    /**
     * Primary company assignment for the single-company architecture.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * User's primary organization unit inside the company.
     */
    public function primaryOrgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'primary_org_unit_id');
    }

    /**
     * All org units assigned to the user inside the company.
     */
    public function orgUnits(): BelongsToMany
    {
        return $this->belongsToMany(OrgUnit::class, 'user_org_unit_assignments', 'user_id', 'org_unit_id')
            ->withPivot(['company_id'])
            ->withTimestamps();
    }

    /**
     * Membership rows linking this user to tenants.
     */
    public function tenantMemberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class, 'user_id');
    }

    /**
     * Tenants this user can access.
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_memberships', 'user_id', 'tenant_id')
            ->withPivot(['id', 'status', 'invited_by_user_id', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Company membership rows for the current company-domain model.
     */
    public function companyMemberships(): HasMany
    {
        return $this->tenantMemberships();
    }

    /**
     * Company relation alias over legacy tenant memberships.
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'tenant_memberships', 'user_id', 'tenant_id')
            ->withPivot(['id', 'status', 'invited_by_user_id', 'joined_at'])
            ->withTimestamps();
    }
}
