


<?php $__env->startSection('content'); ?>
    <div class="container mt-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="text-primary fw-bold mb-1">Roles Management</h2>
                <p class="text-muted">Manage user roles and permissions</p>
            </div>
        </div>

        <!-- Search Form -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="input-group">
                    <span class="input-group-text bg-light border-0">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input type="text" id="searchInput" class="form-control form-control-lg border-0 bg-light" 
                        placeholder="Search by name or email" onkeyup="filterTable()">
                </div>
            </div>
        </div>

        <!-- Table Card -->
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="rolesTable">
                    <thead class="bg-light">
                        <tr>
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Role</th>
                        </tr>
                    </thead>
                    <tbody>
                <?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php if(auth()->id() !== $user->id): ?>
                        <tr class="border-bottom">
                            <td class="px-4 py-3">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle bg-primary bg-opacity-10 me-3">
                                        <?php echo e(strtoupper(substr($user->name, 0, 1))); ?>

                                    </div>
                                    <div>
                                        <h6 class="mb-0"><?php echo e($user->name); ?></h6>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted"><?php echo e($user->email); ?></td>
                            <td class="px-4 py-3">
                                <form method="POST" action="<?php echo e(route('roles.update', $user->id)); ?>"
                                    class="d-flex align-items-center role-update-form">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('PUT'); ?>
                                    <select name="role" class="form-select form-select-sm border-0 bg-light me-2 rounded-pill px-3">
                                        <option value="user" <?php echo e($user->role === 'user' ? 'selected' : ''); ?>>
                                            <i class="fas fa-user me-2"></i>User
                                        </option>
                                        <option value="admin" <?php echo e($user->role === 'admin' ? 'selected' : ''); ?>>
                                            <i class="fas fa-shield-alt me-2"></i>Admin
                                        </option>
                                        <option value="superadmin" <?php echo e($user->role === 'superadmin' || $user->role === 'superadmin' ? 'selected' : ''); ?>>
                                            <i class="fas fa-crown me-2"></i>SuperAdmin
                                        </option>
                                        <option value="manager" <?php echo e($user->role === 'manager' ? 'selected' : ''); ?>>
                                            <i class="fas fa-crown me-2"></i>Manager
                                        </option>
                                        <option value="viewer" <?php echo e($user->role === 'viewer' ? 'selected' : ''); ?>>
                                            <i class="fas fa-crown me-2"></i>Viewer
                                        </option>
                                    </select>
                                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3">
                                        <i class="fas fa-check me-1"></i>Update
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>

    </div>
    </div>

    <style>
        .avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: var(--bs-primary);
        }

        .role-update-form select {
            transition: all 0.3s ease;
        }

        .role-update-form select:focus {
            box-shadow: none;
            border-color: var(--bs-primary);
            background-color: var(--bs-light);
        }

        .table tr {
            transition: all 0.3s ease;
        }

        .table tr:hover {
            background-color: var(--bs-light);
        }

        .form-control:focus {
            box-shadow: none;
            border-color: var(--bs-primary);
        }
    </style>

    <script>
        function filterTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('rolesTable');
            const rows = table.getElementsByTagName('tr');

            for (let i = 1; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let match = false;

                for (let j = 0; j < cells.length; j++) {
                    if (cells[j]) {
                        const text = cells[j].textContent || cells[j].innerText;
                        if (text.toLowerCase().indexOf(filter) > -1) {
                            match = true;
                            break;
                        }
                    }
                }

                if (match) {
                    rows[i].style.display = '';
                    rows[i].style.opacity = '1';
                } else {
                    rows[i].style.opacity = '0';
                    setTimeout(() => {
                        rows[i].style.display = 'none';
                    }, 300);
                }
            }
        }

        // Add success notification after role update
        document.querySelectorAll('.role-update-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const button = this.querySelector('button[type="submit"]');
                button.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i>';
                button.disabled = true;
            });
        });
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.vertical', ['title' => 'Roles'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\5core_inventory\resources\views/pages/roles.blade.php ENDPATH**/ ?>