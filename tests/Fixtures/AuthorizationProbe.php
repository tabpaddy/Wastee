<?php

namespace Tests\Fixtures;

use App\Services\Auth\AuthorizationContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('phase2::layout')]
class AuthorizationProbe extends Component
{
    public ?int $companyId = null;

    public bool $allowed = false;

    public function mount(): void
    {
        $this->inspect();
    }

    public function inspect(): void
    {
        $this->companyId = app(AuthorizationContext::class)->companyId();
        $this->allowed = auth()->user()->can('staff.create');
    }

    public function render(): string
    {
        return '<div>company:{{ $companyId }} permission:{{ $allowed ? "yes" : "no" }}</div>';
    }
}
