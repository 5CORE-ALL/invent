<?php

namespace App\Policies;

use App\Models\ResourceMaster;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ResourceMasterPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ResourceMaster $resource): bool
    {
        return $resource->isVisibleToUser($user);
    }

    public function create(User $user): bool
    {
        return $user->can('resources-master.manage');
    }

    public function update(User $user, ResourceMaster $resource): bool
    {
        return $user->can('resources-master.manage');
    }

    public function delete(User $user, ResourceMaster $resource): bool
    {
        return $user->can('resources-master.manage');
    }

    public function restore(User $user, ResourceMaster $resource): bool
    {
        return $user->can('resources-master.manage');
    }

    public function forceDelete(User $user, ResourceMaster $resource): bool
    {
        return $user->can('resources-master.force-delete');
    }
}
