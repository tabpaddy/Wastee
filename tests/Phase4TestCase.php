<?php

namespace Tests;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use App\Services\Staff\StaffInvitationAcceptanceService;
use App\Services\Staff\StaffInvitationService;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

abstract class Phase4TestCase extends Phase2TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
    }

    protected function companyRole(Company $company, string $name = 'Waste Collector'): Role
    {
        return Role::query()->where('company_id', $company->id)->where('name', $name)->sole();
    }

    protected function invitation(User $actor, Company $company, string $email = 'staff@example.test', string $role = 'Waste Collector'): array
    {
        $invitation = $this->context()->runForCompany($actor, $company, fn () => app(StaffInvitationService::class)->invite($actor, [
            'email' => $email, 'role_uuid' => $this->companyRole($company, $role)->uuid,
        ]));
        $notifications = Notification::sent(new AnonymousNotifiable, StaffInvitationNotification::class);
        $notification = $notifications->last();
        $this->assertNotNull($notification, 'Invitation must be delivered after the inner transaction commits.');
        $url = $notification->toMail(new AnonymousNotifiable)->actionUrl;
        $token = basename(parse_url($url, PHP_URL_PATH));

        return [$invitation, $token, $url];
    }

    protected function staff(User $owner, Company $company, ?User $user = null, string $role = 'Waste Collector'): CompanyMembership
    {
        $user ??= User::factory()->create();
        [$invitation, $token] = $this->invitation($owner, $company, $user->email, $role);
        $acceptance = app(StaffInvitationAcceptanceService::class);

        return $acceptance->accept($invitation, $acceptance->verifyToken($invitation, $token), $user);
    }

    protected function account(): array
    {
        return ['first_name' => 'Ife', 'last_name' => 'Okoro', 'phone' => '08012345678',
            'password' => 'Good-Staff-Password9!', 'password_confirmation' => 'Good-Staff-Password9!'];
    }
}
