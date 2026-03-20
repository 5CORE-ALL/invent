<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected $userPermissions = [];

    public function __construct()
    {
        if (Auth::check()) {
            $userRole = Auth::user()->role;
            $this->userPermissions = Permission::where('role', $userRole)->value('permissions') ?? [];
        }
    }

    protected function getUserPermissions()
    {
        return $this->userPermissions;
    }
}
