<?php

namespace App\Http\Requests;

use App\Services\Staff\StaffInvitationAcceptanceService;
use Illuminate\Foundation\Http\FormRequest;

class AcceptStaffInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->session()->has('staff_invitation_proofs.'.$this->route('invitation')->uuid);
    }

    public function rules(): array
    {
        return $this->user() ? [] : StaffInvitationAcceptanceService::accountRules();
    }
}
