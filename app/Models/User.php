<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_global_admin',
        'is_pepite_member',
        'org_type',
        'email_verified_at',
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
            'is_global_admin' => 'boolean',
            'is_pepite_member' => 'boolean',
        ];
    }

    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(Owner::class)
            ->withPivot('role');
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class);
    }

    public function authProviders(): HasMany
    {
        return $this->hasMany(UserAuthProvider::class);
    }

    /**
     * Rooms this user has direct access to (via room_user pivot).
     */
    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Entreprises auxquelles cet utilisateur appartient (La Pépite).
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class);
    }

    /**
     * Vrai si l'utilisateur a accès à la salle via une de ses entreprises.
     */
    public function hasCompanyAccessToRoom(Room $room): bool
    {
        $companyIds = $this->companies()->pluck('companies.id');

        if ($companyIds->isEmpty()) {
            return false;
        }

        return $room->companies()->whereIn('companies.id', $companyIds)->exists();
    }

    // ─── New unified role methods (UserRole) ────────────────────────────

    /**
     * Get the user's role for a specific owner.
     */
    public function getOwnerRole(Owner $owner): ?UserRole
    {
        $pivot = $this->owners()
            ->where('owners.id', $owner->id)
            ->first()
            ?->pivot;

        if (! $pivot) {
            return null;
        }

        return UserRole::tryFrom($pivot->role);
    }

    /**
     * Get the user's direct role for a specific room.
     */
    public function getDirectRoomRole(Room $room): ?UserRole
    {
        $pivot = $this->rooms()
            ->where('rooms.id', $room->id)
            ->first()
            ?->pivot;

        if (! $pivot) {
            return null;
        }

        return UserRole::tryFrom($pivot->role);
    }

    /**
     * Get the effective role for a room (max of owner role and direct room role).
     */
    public function getEffectiveRoomRole(Room $room): ?UserRole
    {
        $ownerRole = $this->getOwnerRole($room->owner);
        $directRole = $this->getDirectRoomRole($room);

        if ($ownerRole === null && $directRole === null) {
            return null;
        }

        if ($ownerRole === null) {
            return $directRole;
        }

        if ($directRole === null) {
            return $ownerRole;
        }

        return $ownerRole->hasAtLeast($directRole) ? $ownerRole : $directRole;
    }

    public function canAdminOwner(Owner $owner): bool
    {
        $role = $this->getOwnerRole($owner);

        return $role !== null && $role->hasAtLeast(UserRole::ADMIN);
    }

    public function canModerateOwner(Owner $owner): bool
    {
        $role = $this->getOwnerRole($owner);

        return $role !== null && $role->hasAtLeast(UserRole::MODERATOR);
    }

    public function canViewOwner(Owner $owner): bool
    {
        $role = $this->getOwnerRole($owner);

        return $role !== null && $role->hasAtLeast(UserRole::VIEWER);
    }

    public function canViewRoom(Room $room): bool
    {
        $role = $this->getEffectiveRoomRole($room);

        if ($role !== null && $role->hasAtLeast(UserRole::VIEWER)) {
            return true;
        }

        // Accès accordé via une entreprise de l'utilisateur.
        return $this->hasCompanyAccessToRoom($room);
    }

    public function canModerateRoom(Room $room): bool
    {
        $role = $this->getEffectiveRoomRole($room);

        return $role !== null && $role->hasAtLeast(UserRole::MODERATOR);
    }

    public function canAdminRoom(Room $room): bool
    {
        $role = $this->getEffectiveRoomRole($room);

        return $role !== null && $role->hasAtLeast(UserRole::ADMIN);
    }

    public function canAdminAnyOwner(): bool
    {
        return $this->owners()->wherePivot('role', UserRole::ADMIN->value)->exists();
    }

    public function canModerateAnyOwner(): bool
    {
        return $this->owners()
            ->wherePivotIn('role', [UserRole::ADMIN->value, UserRole::MODERATOR->value])
            ->exists();
    }

    public function canAdminAnyRoom(): bool
    {
        // Admin on any owner OR admin direct on any room
        return $this->canAdminAnyOwner()
            || $this->rooms()->wherePivot('role', UserRole::ADMIN->value)->exists();
    }

    public function canModerateAnyRoom(): bool
    {
        // Moderator+ on any owner OR moderator+ direct on any room
        return $this->canModerateAnyOwner()
            || $this->rooms()->wherePivotIn('role', [UserRole::ADMIN->value, UserRole::MODERATOR->value])->exists();
    }

    /**
     * Get owner IDs where user has at least the given role.
     *
     * @return Collection<int, int>
     */
    public function getOwnerIdsWithMinUserRole(UserRole $minRole): Collection
    {
        if ($this->is_global_admin) {
            return Owner::pluck('id');
        }

        $roles = collect(UserRole::cases())
            ->filter(fn (UserRole $r) => $r->hasAtLeast($minRole))
            ->map(fn (UserRole $r) => $r->value)
            ->values()
            ->all();

        return $this->owners()
            ->wherePivotIn('role', $roles)
            ->pluck('owners.id');
    }

    /**
     * Get room IDs where user has at least the given direct role.
     *
     * @return Collection<int, int>
     */
    public function getRoomIdsWithMinDirectRole(UserRole $minRole): Collection
    {
        $roles = collect(UserRole::cases())
            ->filter(fn (UserRole $r) => $r->hasAtLeast($minRole))
            ->map(fn (UserRole $r) => $r->value)
            ->values()
            ->all();

        return $this->rooms()
            ->wherePivotIn('role', $roles)
            ->pluck('rooms.id');
    }

    /**
     * Get all room IDs where user has at least the given effective role
     * (via owner role OR direct room role).
     * When minRole is VIEWER, active public rooms are included.
     *
     * @return Collection<int, int>
     */
    public function getAccessibleRoomIds(UserRole $minRole): Collection
    {
        if ($this->is_global_admin) {
            return Room::pluck('id');
        }

        $ownerIds = $this->getOwnerIdsWithMinUserRole($minRole);
        $roomIdsFromOwners = Room::whereIn('owner_id', $ownerIds)->pluck('id');
        $directRoomIds = $this->getRoomIdsWithMinDirectRole($minRole);

        $ids = $roomIdsFromOwners->merge($directRoomIds);

        if ($minRole === UserRole::VIEWER) {
            $publicRoomIds = Room::where('active', true)->where('is_public', true)->pluck('id');
            $ids = $ids->merge($publicRoomIds);

            // Salles accessibles via les entreprises de l'utilisateur.
            $companyIds = $this->companies()->pluck('companies.id');
            if ($companyIds->isNotEmpty()) {
                $companyRoomIds = Room::where('active', true)
                    ->whereHas('companies', fn ($q) => $q->whereIn('companies.id', $companyIds))
                    ->pluck('id');
                $ids = $ids->merge($companyRoomIds);
            }
        }

        return $ids->unique()->values();
    }

    /**
     * Get owner IDs where user has any role.
     *
     * @return Collection<int, int>
     */
    public function getOwnerIdsWithAnyRole(): Collection
    {
        return $this->owners()->pluck('owners.id');
    }

    public function canAccessContact(Contact $contact): bool
    {
        return $this->is_global_admin || $this->contacts()->where('contacts.id', $contact->id)->exists();
    }

    public function canAccessUser(User $user): bool
    {
        return $this->is_global_admin;
    }
}
