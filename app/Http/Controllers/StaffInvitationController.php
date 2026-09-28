<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptStaffInvitationRequest;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Services\Staff\StaffInvitationAcceptanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class StaffInvitationController extends Controller
{
    public function open(Request $request, StaffInvitation $invitation, string $token, StaffInvitationAcceptanceService $acceptance): RedirectResponse
    {
        $proof = $acceptance->verifyToken($invitation, $token);
        $request->session()->put('staff_invitation_proofs.'.$invitation->uuid, $proof);
        $request->session()->put('staff_invitation_uuid', $invitation->uuid);

        return redirect()->route('staff-invitations.show', $invitation->uuid)
            ->withHeaders(['Referrer-Policy' => 'no-referrer', 'Cache-Control' => 'no-store']);
    }

    public function show(Request $request, StaffInvitation $invitation, StaffInvitationAcceptanceService $acceptance): Response
    {
        $acceptance->inspect($invitation, $request->session()->get('staff_invitation_proofs.'.$invitation->uuid));
        $existingAccount = User::query()->whereRaw('LOWER(email) = ?', [$invitation->email])->exists();

        return response()->view('staff-invitations.show', compact('invitation', 'existingAccount'))
            ->withHeaders(['Referrer-Policy' => 'no-referrer', 'Cache-Control' => 'no-store']);
    }

    public function accept(AcceptStaffInvitationRequest $request, StaffInvitation $invitation, StaffInvitationAcceptanceService $acceptance): RedirectResponse
    {
        $membership = $acceptance->accept($invitation, $request->session()->get('staff_invitation_proofs.'.$invitation->uuid),
            $request->user(), $request->validated());
        Auth::login($membership->user);
        $request->session()->regenerate();
        $request->session()->forget(['staff_invitation_proofs.'.$invitation->uuid, 'staff_invitation_uuid']);

        return redirect('/company/'.$membership->company->uuid);
    }
}
