<?php

namespace App\Support;

use App\Models\Organization;
use LogicException;

final class OrganizationContext
{
    private ?Organization $organization = null;
    public function set(Organization $organization): void { $this->organization = $organization; }
    public function get(): Organization { return $this->organization ?? throw new LogicException('Organization context has not been resolved.'); }
    public function id(): int { return $this->get()->getKey(); }
}
