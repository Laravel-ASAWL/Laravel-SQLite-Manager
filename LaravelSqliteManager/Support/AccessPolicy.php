<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager\Support;

use Illuminate\Support\Facades\Gate;

class AccessPolicy
{
    public function canAccess(): bool
    {
        $environments = config('sqlite-manager.security.allowed_environments', ['local', 'testing']);

        if (is_array($environments) && $environments !== [] && ! in_array(app()->environment(), array_filter($environments, is_string(...)), true)) {
            return false;
        }

        return $this->allowsConfiguredGate('access', 'authorization_gate');
    }

    public function can(string $action): bool
    {
        if ($this->isReadOnlyAction($action) && $this->readOnly()) {
            return false;
        }

        return $this->allowsConfiguredGate($action, $action);
    }

    public function readOnly(): bool
    {
        return (bool) config('sqlite-manager.security.read_only', false);
    }

    private function isReadOnlyAction(string $action): bool
    {
        return in_array($action, ['create', 'update', 'delete', 'bulk_delete', 'import'], true);
    }

    private function allowsConfiguredGate(string $action, string $key): bool
    {
        $gate = config('sqlite-manager.security.gates.'.$key);
        $gate = is_string($gate) && $gate !== '' ? $gate : config('sqlite-manager.security.'.$key);

        if (! is_string($gate) || $gate === '') {
            return true;
        }

        return Gate::allows($gate, [$action]);
    }
}
