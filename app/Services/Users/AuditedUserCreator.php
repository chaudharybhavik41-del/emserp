<?php

namespace App\Services\Users;

use App\Models\User;

class AuditedUserCreator
{
    public function __construct(
        protected UserCreationAuditContext $auditContext
    ) {}

    public function create(array $attributes, array $context = []): User
    {
        return $this->auditContext->runWith($context, function () use ($attributes) {
            return User::create($attributes);
        });
    }
}
