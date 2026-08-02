<?php

namespace App\Http\Controllers;

use App\Models\Owner;
use App\Models\Room;
use App\Models\SpecialRequest;
use App\Services\Mailer\MailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpecialRequestController extends Controller
{
    /**
     * Formulaire de demande spéciale (La Pépite) : salles « sur demande »,
     * catering, créneaux hors horaires. Traité sur devis par l'équipe.
     */
    public function create(Request $request): View
    {
        $user = auth()->user();

        // Salles proposables : celles « sur demande » (Big Room, Place du
        // Village, Atelier…). Une demande générique (catering seul) reste
        // possible sans salle.
        $rooms = Room::where('active', true)
            ->where('on_request', true)
            ->orderBy('name')
            ->get();

        return view('special-requests.create', [
            'rooms' => $rooms,
            'selectedRoomId' => $request->integer('room'),
            'user' => $user,
        ]);
    }

    public function store(Request $request, MailService $mail): RedirectResponse
    {
        $validated = $request->validate([
            'room_id' => ['nullable', 'exists:rooms,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'organization' => ['nullable', 'string', 'max:255'],
            'desired_dates' => ['nullable', 'string', 'max:2000'],
            'people' => ['nullable', 'integer', 'min:1'],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'catering' => ['boolean'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['catering'] = $request->boolean('catering');
        $validated['user_id'] = auth()->id();

        $specialRequest = SpecialRequest::create($validated);

        // Notifie l'équipe (propriétaire de la salle, ou propriétaire par défaut).
        $room = $specialRequest->room;
        $owner = $room?->owner ?? Owner::query()->orderBy('id')->first();
        if ($owner && ! ($room && $room->disable_mailer)) {
            try {
                $mail->sendSpecialRequest($specialRequest, $owner);
            } catch (\Throwable $e) {
                report($e); // La demande est enregistrée même si l'email échoue.
            }
        }

        return redirect()->route('special-requests.create')
            ->with('success', __('Your special request has been sent. The team will get back to you with a quote.'));
    }
}
