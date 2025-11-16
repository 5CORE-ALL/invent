@extends('layouts.vertical', ['title' => 'Permission Overview'])

@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    .permission-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }

    .permission-table th {
        background-color: #f8f9fa;
        padding: 15px;
        font-weight: 600;
        text-align: center;
        border-bottom: 2px solid #dee2e6;
    }

    .permission-table td {
        padding: 12px;
        border: 1px solid #dee2e6;
        text-align: center;
    }

    .module-header {
        background-color: #e9ecef;
        font-weight: 600;
    }

    .permission-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.875rem;
    }

    .permission-granted {
        background-color: #d4edda;
        color: #155724;
    }

    .permission-denied {
        background-color: #f8d7da;
        color: #721c24;
    }

    .edit-button {
        position: absolute;
        top: 20px;
        right: 20px;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body position-relative">
                    <h4 class="header-title mb-4">Role Permissions Overview</h4>
                    
                    <a href="{{ route('permissions.edit') }}" class="btn btn-primary edit-button">
                        <i class="fas fa-edit me-1"></i> Edit Permissions
                    </a>

                    <div class="table-responsive">
                        <table class="table permission-table">
                            <thead>
                                <tr>
                                    <th>Module/Feature</th>
                                    <th>Viewer</th>
                                    <th>User</th>
                                    <th>Manager</th>
                                    <th>Admin</th>
                                    <th>Superadmin</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($modules as $module)
                                    <tr class="module-header">
                                        <td colspan="6">{{ ucfirst($module) }}</td>
                                    </tr>
                                    <tr>
                                        <td>{{ ucfirst($module) }} Actions</td>
                                        @foreach(['viewer', 'user', 'manager', 'admin', 'superadmin'] as $role)
                                            <td>
                                                @php
                                                    $currentRolePermissions = [];
                                                    if(isset($rolePermissions[$module][$role])) {
                                                        $currentRolePermissions = is_array($rolePermissions[$module][$role]) 
                                                            ? $rolePermissions[$module][$role] 
                                                            : json_decode($rolePermissions[$module][$role], true);
                                                    }
                                                @endphp
                                                
                                                @if(!empty($currentRolePermissions))
                                                    <span class="permission-badge permission-granted">
                                                        <i class="fas fa-check"></i>
                                                        {{ implode(', ', array_map('ucfirst', $currentRolePermissions)) }}
                                                    </span>
                                                @else
                                                    <span class="permission-badge permission-denied">
                                                        <i class="fas fa-times"></i>
                                                        No Access
                                                    </span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection