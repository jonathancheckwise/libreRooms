<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Room;
use App\Models\User;

class RoomPolicy
{
    /**
     * Determine whether the user can view any rooms.
     * Always true - filtering is done in the query.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the "mine" tab (manageable rooms).
     * Requires moderator+ role on at least one owner or direct moderator+ on a room.
     */
    public function viewAdmin(User $user): bool
    {
        return $user->canModerateAnyRoom();
    }

    /**
     * Determine whether the user can view the room.
     * Public rooms: everyone can view.
     * Private rooms: viewer+ effective role required.
     */
    public function view(?User $user, Room $room): bool
    {
        if ($room->is_public) {
            return true;
        }

        return $user && $user->canViewRoom($room);
    }

    /**
     * Determine whether the user can create rooms.
     * Requires admin role on at least one owner.
     */
    public function create(User $user): bool
    {
        return $user->canAdminAnyOwner();
    }

    /**
     * Determine whether the user can update the room.
     * Requires admin effective role on the room.
     */
    public function update(User $user, Room $room): bool
    {
        return $user->canAdminRoom($room);
    }

    /**
     * Determine whether the user can delete the room.
     * Requires admin effective role on the room.
     */
    public function delete(User $user, Room $room): bool
    {
        return $user->canAdminRoom($room);
    }

    /**
     * Determine whether the user can make a reservation on the room.
     * Requires view access and room must be active.
     */
    public function reserve(?User $user, Room $room): bool
    {
        if (! $room->active) {
            return false;
        }

        // Salle non réservable (La Pépite : La Garderie) : jamais réservable
        // par les utilisateurs (les responsables gardent l'accès admin).
        if (! $room->bookable && ! ($user && $user->can('manageReservations', $room))) {
            return false;
        }

        // Salle réservée aux membres (La Pépite) : un visiteur non connecté ne
        // peut pas réserver (cadenas + CTA « Devenir membre »).
        if ($room->members_only && ! $user) {
            return false;
        }

        if ($room->is_public) {
            return true;
        }

        return $user && $user->canViewRoom($room);
    }

    /**
     * Determine whether the user can manage reservations for the room.
     * Requires moderator+ effective role.
     */
    public function manageReservations(User $user, Room $room): bool
    {
        return $user->canModerateRoom($room);
    }

    /**
     * Determine whether the user can manage unavailabilities for the room.
     * Requires moderator+ effective role.
     */
    public function manageUnavailabilities(User $user, Room $room): bool
    {
        return $user->canModerateRoom($room);
    }

    /**
     * Determine whether the user can manage users for the room.
     * Requires moderator+ effective role.
     */
    public function manageUsers(User $user, Room $room): bool
    {
        return $user->canModerateRoom($room);
    }

    /**
     * Determine whether the user can add a user with the given role to the room.
     * Admins can add any role, moderators can only add viewers.
     */
    public function addRoomUser(User $user, Room $room, string $role): bool
    {
        if ($user->canAdminRoom($room)) {
            return true;
        }

        // Moderators can only add viewers
        if ($user->canModerateRoom($room)) {
            return $role === UserRole::VIEWER->value;
        }

        return false;
    }

    /**
     * Determine whether the user can remove another user from the room.
     * Can't remove yourself. Admins can remove anyone. Moderators can only remove viewers.
     */
    public function removeRoomUser(User $user, Room $room, User $targetUser): bool
    {
        // Can't remove yourself
        if ($user->id === $targetUser->id) {
            return false;
        }

        if ($user->canAdminRoom($room)) {
            return true;
        }

        // Moderators can only remove viewers
        if ($user->canModerateRoom($room)) {
            $targetRole = $targetUser->getDirectRoomRole($room);

            return $targetRole === UserRole::VIEWER;
        }

        return false;
    }

    // ─── Sub-entity listing abilities ───────────────────────────────────

    /**
     * Determine whether the user can view the discounts index.
     */
    public function viewAnyDiscounts(User $user): bool
    {
        return $user->canAdminAnyRoom();
    }

    /**
     * Determine whether the user can view the options index.
     */
    public function viewAnyOptions(User $user): bool
    {
        return $user->canAdminAnyRoom();
    }

    /**
     * Determine whether the user can view the custom fields index.
     */
    public function viewAnyCustomFields(User $user): bool
    {
        return $user->canAdminAnyRoom();
    }

    /**
     * Determine whether the user can view the unavailabilities index.
     */
    public function viewAnyUnavailabilities(User $user): bool
    {
        return $user->canModerateAnyRoom();
    }

    // ─── Sub-entity management abilities ────────────────────────────────

    /**
     * Determine whether the user can manage discounts for the room.
     */
    public function manageDiscounts(User $user, Room $room): bool
    {
        return $user->canAdminRoom($room);
    }

    /**
     * Determine whether the user can manage options for the room.
     */
    public function manageOptions(User $user, Room $room): bool
    {
        return $user->canAdminRoom($room);
    }

    /**
     * Determine whether the user can manage custom fields for the room.
     */
    public function manageCustomFields(User $user, Room $room): bool
    {
        return $user->canAdminRoom($room);
    }
}
