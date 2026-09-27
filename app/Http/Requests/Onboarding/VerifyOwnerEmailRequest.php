<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Auth\EmailVerificationRequest;

class VerifyOwnerEmailRequest extends EmailVerificationRequest
{
    public function authorize(): bool
    {
        return hash_equals((string) $this->user()->uuid, (string) $this->route('id'))
            && hash_equals(sha1($this->user()->getEmailForVerification()), (string) $this->route('hash'));
    }
}
