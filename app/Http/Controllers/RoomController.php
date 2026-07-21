<?php

namespace App\Http\Controllers;

use App\Enums\CalendarViewModes;
use App\Enums\ReservationStatus;
use App\Enums\RoomCurrentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Concerns\RedirectsBack;
use App\Models\Company;
use App\Models\Image;
use App\Models\Owner;
use App\Models\Room;
use App\Models\SystemSettings;
use App\Services\Availability\AvailabilityService;
use App\Validation\RoomRules;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RoomController extends Controller
{
    use RedirectsBack;

    /**
     * Display a listing of the resource.
     *
     * Two views available:
     * - "mine": Rooms the user can manage (moderator+ on owner) - for reservation management
     * - "available": All rooms the user can access (public + private with access)
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $view = $request->input('view', 'available'); // 'available' or 'mine'
        $display = $request->input('display', 'cards'); // 'cards' or 'list'

        // Check if user can access "mine" view (must have moderator+ role on at least one owner)
        $canViewAdmin = $user && $user->can('viewAdmin', Room::class);

        if ($view === 'mine' && ! $canViewAdmin) {
            $view = 'available';
        }

        if ($view === 'mine') {
            // "mine" view: rooms where user has moderator+ effective role (global admins see all via model method)
            $manageableRoomIds = $user->getAccessibleRoomIds(UserRole::MODERATOR);

            $query = Room::with(['owner.contact', 'discounts', 'options', 'images'])
                ->whereIn('id', $manageableRoomIds);

            // Owners for filter: those with rooms in the manageable set
            $manageableOwnerIds = Room::whereIn('id', $manageableRoomIds)->pluck('owner_id')->unique();
            $owners = Owner::with('contact')->whereIn('id', $manageableOwnerIds)->get()
                ->sortBy(fn ($o) => $o->contact?->display_name())->values();
        } else {
            // "available" view: all rooms accessible to the user (public + private with access)
            if ($user) {
                $accessibleRoomIds = $user->getAccessibleRoomIds(UserRole::VIEWER);
            } else {
                $accessibleRoomIds = Room::where('active', true)->where('is_public', true)->pluck('id');
            }

            $query = Room::with(['owner.contact', 'discounts', 'options', 'images'])
                ->where('active', true)
                ->whereIn('id', $accessibleRoomIds);

            // Owners for filter: those with rooms in the accessible set
            $accessibleOwnerIds = Room::where('active', true)->whereIn('id', $accessibleRoomIds)->pluck('owner_id')->unique();
            $owners = Owner::with('contact')->whereIn('id', $accessibleOwnerIds)->get()
                ->sortBy(fn ($o) => $o->contact?->display_name())->values();
        }

        // Filter by owner
        if ($request->filled('owner_id')) {
            $query->where('owner_id', $request->input('owner_id'));
        }

        $query->orderBy('name', 'asc');
        $rooms = $query->paginate(15)->appends($request->except('page'));

        return view('rooms.index', [
            'rooms' => $rooms,
            'owners' => $owners,
            'user' => $user,
            'view' => $view,
            'display' => $display,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $this->authorize('create', Room::class);

        $user = auth()->user();

        // Get owners where user has admin rights
        $ownerIds = $user->getOwnerIdsWithMinUserRole(UserRole::ADMIN);
        $owners = Owner::with('contact')->whereIn('id', $ownerIds)->get()
            ->sortBy(fn ($o) => $o->contact?->display_name())->values();

        // Get owner timezones for JavaScript
        $ownerTimezones = $owners->mapWithKeys(function ($owner) {
            return [$owner->id => $owner->getTimezone()];
        });

        // Get owner caldav validity for JavaScript
        $ownersCaldavValid = $owners->mapWithKeys(function ($owner) {
            return [$owner->id => $owner->use_caldav && $owner->caldavSettings()->valid()];
        });

        return view('rooms.form', [
            'room' => null,
            'owners' => $owners,
            'ownerTimezones' => $ownerTimezones,
            'ownersCaldavValid' => $ownersCaldavValid,
            'systemSettings' => app(SystemSettings::class),
            'companies' => Company::orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Room::class);

        // Validate
        RoomRules::prepare($request);
        $validated = $request->validate(RoomRules::rules($request));

        // Check if user has admin rights for the selected owner
        $owner = Owner::findOrFail($validated['owner_id']);
        $this->authorize('manageRooms', $owner);

        // Generate slug from name
        $validated['slug'] = Str::slug($validated['name']);

        // Ensure slug is unique
        $baseSlug = $validated['slug'];
        $counter = 1;
        while (Room::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $baseSlug.'-'.$counter;
            $counter++;
        }

        // Create room
        $room = Room::create($validated);

        // Entreprises ayant accès (La Pépite)
        $request->validate(['companies' => ['array'], 'companies.*' => ['exists:companies,id']]);
        $room->companies()->sync($request->input('companies', []));

        // Handle image uploads and ordering
        $this->handleImageUploadsAndOrder($request, $room);

        return $this->redirectBack('rooms.show', ['room' => $room])
            ->with('success', __('Room created successfully.'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Room $room): View
    {
        $this->authorize('view', $room);

        $room->load(['owner.contact', 'discounts', 'options', 'customFields', 'images']);

        $user = auth()->user();

        return view('rooms.show', compact('room', 'user'));
    }

    /**
     * Display the current availability status of the room.
     */
    public function available(Room $room, AvailabilityService $availabilityService): View
    {
        $this->authorize('view', $room);

        $room->load(['owner.contact', 'unavailabilities']);
        $timezone = $room->getTimezone();
        $nowUtc = now('UTC');
        $nowLocal = $nowUtc->copy()->setTimezone($timezone);

        // Load busy slots from AvailabilityService (handles both local DB and CalDAV)
        // Request in UTC for comparisons
        $busySlots = $availabilityService->loadBusySlots($room, 'UTC', $nowUtc->copy()->subDay(), $nowUtc->copy()->addWeeks(2));

        // Determine current status
        $status = RoomCurrentStatus::FREE;
        $currentSlot = null;
        $currentUnavailability = null;
        $freeUntil = null;
        $freeFrom = null;

        // 1. Check if currently occupied by a reservation (compare in UTC)
        $currentSlot = collect($busySlots)
            ->first(fn ($slot) => $slot['start'] <= $nowUtc && $slot['end'] > $nowUtc);
        if ($currentSlot) {
            $status = RoomCurrentStatus::OCCUPIED;
            // Find when room will be free again (next gap or end of current event)
            $freeFrom = $this->findNextFreeTime($room, $currentSlot['end']->copy(), $timezone, $busySlots);
        }

        // 2. Check if currently in an unavailability period (compare in UTC)
        if ($status === RoomCurrentStatus::FREE) {
            $currentUnavailability = $room->unavailabilities
                ->first(fn ($u) => $u->start <= $nowUtc && $u->end > $nowUtc);

            if ($currentUnavailability) {
                $status = RoomCurrentStatus::UNAVAILABLE;
                $freeFrom = $this->findNextFreeTime($room, $currentUnavailability->end, $timezone, $busySlots);
            }
        }

        // 3. Check if outside bookable hours (compare in room timezone)
        if ($status === RoomCurrentStatus::FREE) {
            if ($this->isOutsideBookableHours($room, $nowLocal)) {
                $status = RoomCurrentStatus::OUTSIDE_HOURS;
                $freeFrom = $this->findNextBookableTime($room, $nowLocal);
            }
        }

        // 4. If free, find until when
        if ($status === RoomCurrentStatus::FREE) {
            $freeUntil = $this->findFreeUntil($room, $nowUtc, $timezone, $busySlots);
        }

        // Prepare event info based on calendar_view_mode
        $eventInfo = null;
        if ($currentSlot && $room->calendar_view_mode !== CalendarViewModes::SLOT) {
            $eventInfo = [
                'title' => $currentSlot['title'] ?? __('Occupied'),
                'start' => $currentSlot['start']->copy()->setTimezone($timezone),
                'end' => $currentSlot['end']->copy()->setTimezone($timezone),
            ];
            if ($room->calendar_view_mode === CalendarViewModes::FULL) {
                $eventInfo['tenant'] = $currentSlot['tenant'] ?? null;
                $eventInfo['description'] = $currentSlot['description'] ?? null;
            }
        }

        // Prepare bookable hours info
        $bookableHoursInfo = $this->getBookableHoursInfo($room);

        // Convert freeFrom/freeUntil to local timezone for display
        $freeFromLocal = $freeFrom?->copy()->setTimezone($timezone);
        $freeUntilLocal = $freeUntil?->copy()->setTimezone($timezone);

        return view('rooms.available', [
            'room' => $room,
            'status' => $status,
            'currentSlot' => $currentSlot,
            'eventInfo' => $eventInfo,
            'currentUnavailability' => $currentUnavailability,
            'freeUntil' => $freeUntilLocal,
            'freeFrom' => $freeFromLocal,
            'bookableHoursInfo' => $bookableHoursInfo,
            'now' => $nowLocal,
        ]);
    }

    /**
     * Check if the given time is outside the room's bookable hours.
     * IMPORTANT: $time must be in the room's timezone since bookable hours are defined in room TZ.
     */
    private function isOutsideBookableHours(Room $room, Carbon $timeInRoomTz): bool
    {
        // Check weekday
        $dayOfWeek = $timeInRoomTz->dayOfWeekIso; // 1=Mon, 7=Sun
        if (! in_array($dayOfWeek, $room->allowed_weekdays)) {
            return true;
        }

        // Check time range
        $currentTime = $timeInRoomTz->format('H:i');
        if ($room->day_start_time && $currentTime < substr($room->day_start_time, 0, 5)) {
            return true;
        }
        if ($room->day_end_time && $currentTime >= substr($room->day_end_time, 0, 5)) {
            return true;
        }

        return false;
    }

    /**
     * Find the next time the room becomes bookable.
     * Works in room timezone and returns UTC.
     */
    private function findNextBookableTime(Room $room, Carbon $fromInRoomTz): ?Carbon
    {
        $current = $fromInRoomTz->copy();
        $maxDays = 14; // Look up to 2 weeks ahead

        for ($i = 0; $i < $maxDays; $i++) {
            // Check if this day is allowed
            if (! in_array($current->dayOfWeekIso, $room->allowed_weekdays)) {
                $current->addDay()->startOfDay();

                continue;
            }

            // If we're on an allowed day, find the start time
            $startTime = $room->day_start_time ? substr($room->day_start_time, 0, 5) : '00:00';
            $dayStart = $current->copy()->setTimeFromTimeString($startTime);

            // If the start time is in the future, return it (converted to UTC)
            if ($dayStart > $fromInRoomTz) {
                return $dayStart->copy()->utc();
            }

            // Otherwise, try next day
            $current->addDay()->startOfDay();
        }

        return null;
    }

    /**
     * Find the next free time after a given point.
     * All comparisons are in UTC. Returns UTC.
     */
    private function findNextFreeTime(Room $room, Carbon $fromUtc, string $timezone, array $busySlots): Carbon
    {
        $current = $fromUtc->copy();
        $slotsCollection = collect($busySlots);

        // Check for consecutive events or unavailabilities
        for ($i = 0; $i < 100; $i++) { // Safety limit
            // Check for busy slot at this time (slots are in UTC)
            $overlappingSlot = $slotsCollection
                ->first(fn ($slot) => $slot['start'] <= $current && $slot['end'] > $current);

            if ($overlappingSlot) {
                $current = $overlappingSlot['end']->copy();

                continue;
            }

            // Check for unavailability at this time (use UTC accessors)
            $nextUnavail = $room->unavailabilities
                ->first(fn ($u) => $u->start <= $current && $u->end > $current);
            if ($nextUnavail) {
                $current = $nextUnavail->end;

                continue;
            }

            // Check if outside bookable hours (convert to room TZ for this check)
            $currentInRoomTz = $current->copy()->setTimezone($timezone);
            if ($this->isOutsideBookableHours($room, $currentInRoomTz)) {
                $nextBookable = $this->findNextBookableTime($room, $currentInRoomTz);
                if ($nextBookable) {
                    $current = $nextBookable; // Already in UTC from findNextBookableTime

                    continue;
                }
            }

            // Found a free time
            break;
        }

        return $current;
    }

    /**
     * Find until when the room is free.
     * All comparisons are in UTC. Returns UTC.
     */
    private function findFreeUntil(Room $room, Carbon $fromUtc, string $timezone, array $busySlots): ?Carbon
    {
        $limits = [];

        // Next busy slot (slots are in UTC)
        $nextSlot = collect($busySlots)
            ->filter(fn ($slot) => $slot['start'] > $fromUtc)
            ->sortBy(fn ($slot) => $slot['start'])
            ->first();

        if ($nextSlot) {
            $limits[] = $nextSlot['start'];
        }

        // Next unavailability (use UTC accessors)
        $nextUnavail = $room->unavailabilities
            ->filter(fn ($u) => $u->start > $fromUtc)
            ->sortBy(fn ($u) => $u->start)
            ->first();

        if ($nextUnavail) {
            $limits[] = $nextUnavail->start;
        }

        // End of bookable hours today (room TZ, convert to UTC)
        if ($room->day_end_time) {
            $fromInRoomTz = $fromUtc->copy()->setTimezone($timezone);
            $endToday = $fromInRoomTz->copy()->setTimeFromTimeString(substr($room->day_end_time, 0, 5));
            if ($endToday > $fromInRoomTz) {
                $limits[] = $endToday->copy()->utc();
            }
        }

        return empty($limits) ? null : min($limits);
    }

    /**
     * Get human-readable bookable hours info.
     */
    private function getBookableHoursInfo(Room $room): array
    {
        $info = [];

        if (! $room->openedEveryday()) {
            $days = array_map(fn ($d) => $this->dayName($d), $room->allowed_weekdays);
            if (empty($days)) {
                $days = [__('None.days')];
            }
            $info['days'] = $days;
        }

        if ($room->day_start_time || $room->day_end_time) {
            $info['hours'] = [
                'start' => $room->day_start_time ? substr($room->day_start_time, 0, 5) : '00:00',
                'end' => $room->day_end_time ? substr($room->day_end_time, 0, 5) : '00:00',
            ];
        }

        return $info;
    }

    /**
     * Get day name from ISO day number.
     */
    private function dayName(int $day): string
    {
        return match ($day) {
            1 => __('Monday'),
            2 => __('Tuesday'),
            3 => __('Wednesday'),
            4 => __('Thursday'),
            5 => __('Friday'),
            6 => __('Saturday'),
            7 => __('Sunday'),
            default => '',
        };
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Room $room): View
    {
        $this->authorize('update', $room);

        $user = auth()->user();

        // Get owners where user has admin rights
        $ownerIds = $user->getOwnerIdsWithMinUserRole(UserRole::ADMIN);
        $owners = Owner::with('contact')->whereIn('id', $ownerIds)->get();

        // Get owner timezones for JavaScript
        $ownerTimezones = $owners->mapWithKeys(function ($owner) {
            return [$owner->id => $owner->getTimezone()];
        });

        // Get owner caldav validity for JavaScript
        $ownersCaldavValid = $owners->mapWithKeys(function ($owner) {
            return [$owner->id => $owner->use_caldav && $owner->caldavSettings()->valid()];
        });

        return view('rooms.form', [
            'room' => $room,
            'systemSettings' => app(SystemSettings::class),
            'owners' => $owners,
            'ownerTimezones' => $ownerTimezones,
            'ownersCaldavValid' => $ownersCaldavValid,
            'companies' => Company::orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Room $room): RedirectResponse
    {
        $this->authorize('update', $room);

        // Validate
        RoomRules::prepare($request);
        $validated = $request->validate(RoomRules::rules($request));

        // Check if user has admin rights for the selected owner (in case it changed)
        $owner = Owner::findOrFail($validated['owner_id']);
        $this->authorize('manageRooms', $owner);

        // Generate slug from name if name changed
        if ($validated['name'] !== $room->name) {
            $validated['slug'] = Str::slug($validated['name']);

            // Ensure slug is unique
            $baseSlug = $validated['slug'];
            $counter = 1;
            while (Room::where('slug', $validated['slug'])->where('id', '!=', $room->id)->exists()) {
                $validated['slug'] = $baseSlug.'-'.$counter;
                $counter++;
            }
        }
        // Handle image removals first
        $this->handleImageRemovals($request, $room);

        // Handle new image uploads and reordering
        $this->handleImageUploadsAndOrder($request, $room);

        // Update room
        $room->update($validated);

        // Entreprises ayant accès (La Pépite)
        $request->validate(['companies' => ['array'], 'companies.*' => ['exists:companies,id']]);
        $room->companies()->sync($request->input('companies', []));

        return $this->redirectBack('rooms.show', ['room' => $room])
            ->with('success', __('Room updated successfully.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Room $room): RedirectResponse
    {
        $this->authorize('delete', $room);

        // Check for active reservations
        $activeReservations = $room->reservations()
            ->whereIn('status', [ReservationStatus::PENDING, ReservationStatus::CONFIRMED])
            ->exists();

        if ($activeReservations) {
            return redirect()->route('rooms.edit', $room)
                ->with('error', __('This room has active reservations (pending or confirmed). Please cancel them before deleting the room.'));
        }

        // Check for unpaid/uncancelled invoices
        $unpaidInvoices = $room->reservations()
            ->whereHas('invoice', function ($query) {
                $query->whereNull('paid_at')
                    ->whereNull('cancelled_at');
            })
            ->exists();

        if ($unpaidInvoices) {
            return redirect()->route('rooms.edit', $room)
                ->with('error', __('This room has unpaid invoices. Please mark them as paid or cancel them before deleting the room.'));
        }

        $room->delete();

        return $this->redirectBack('rooms.index', ['view' => 'mine'])
            ->with('success', __('Room deleted successfully.'));
    }

    /**
     * Handle image uploads and ordering for a room.
     *
     * The image_order array contains entries like:
     * - "existing:5" for existing images with id 5
     * - "new:0" for new images (index in the uploaded files array)
     */
    private function handleImageUploadsAndOrder(Request $request, Room $room): void
    {
        $imageOrder = $request->input('image_order', []);
        $uploadedFiles = $request->file('images', []);
        $maxImages = 3;

        // Track which new file index we're on
        $newFileIndex = 0;
        $order = 0;

        foreach ($imageOrder as $orderEntry) {
            if ($order >= $maxImages) {
                break;
            }

            [$type, $id] = explode(':', $orderEntry);

            if ($type === 'existing') {
                // Update order for existing image
                $image = $room->images()->find($id);
                if ($image) {
                    $image->update(['order' => $order]);
                    $order++;
                }
            } elseif ($type === 'new' && isset($uploadedFiles[$newFileIndex])) {
                // Upload new image with correct order
                $file = $uploadedFiles[$newFileIndex];
                $path = $file->store('rooms/'.$room->id, 'public');

                $room->images()->create([
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'order' => $order,
                ]);

                $newFileIndex++;
                $order++;
            }
        }

        // If no image_order provided but files were uploaded (fallback for simple uploads)
        if (empty($imageOrder) && ! empty($uploadedFiles)) {
            $existingCount = $room->images()->count();

            foreach ($uploadedFiles as $index => $file) {
                if ($existingCount + $index >= $maxImages) {
                    break;
                }

                $path = $file->store('rooms/'.$room->id, 'public');

                $room->images()->create([
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'order' => $existingCount + $index,
                ]);
            }
        }
    }

    /**
     * Handle image removals for a room.
     */
    private function handleImageRemovals(Request $request, Room $room): void
    {
        if (! $request->filled('remove_images')) {
            return;
        }

        $imageIds = $request->input('remove_images', []);

        $images = Image::whereIn('id', $imageIds)
            ->where('imageable_type', Room::class)
            ->where('imageable_id', $room->id)
            ->get();

        foreach ($images as $image) {
            $image->delete();
        }
    }
}
