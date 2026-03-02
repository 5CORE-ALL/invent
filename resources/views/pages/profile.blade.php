@extends('layouts.vertical', ['title' => 'Profile', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="profile-bg-picture" style="background-image:url('/images/bg-profile.jpg')">
                <span class="picture-bg-overlay"></span>
            </div>
            @php
                $avatarUrl = $user->avatar ? asset('storage/' . $user->avatar) : asset('images/users/avatar-2.jpg');
                $avatarPosX = (int) ($user->avatar_position_x ?? 50);
                $avatarPosY = (int) ($user->avatar_position_y ?? 50);
                $avatarZoom = (int) ($user->avatar_zoom ?? 100);
            @endphp
            <div class="profile-user-box">
                <div class="row">
                    <div class="col-sm-6">
                        <div class="profile-user-img position-relative d-inline-block">
                            <div id="profile-avatar-preview" class="avatar-lg rounded-circle avatar-display"
                                style="background-image:url('{{ $avatarUrl }}'); background-position: {{ $avatarPosX }}% {{ $avatarPosY }}%; background-size: {{ $avatarZoom }}% {{ $avatarZoom }}%; background-repeat: no-repeat;"></div>
                        </div>
                        <div>
                            <h4 class="mt-4 fs-17 ellipsis">{{ $user->name }}</h4>
                            <p class="text-muted">{{ $user->email }}</p>
                            <button class="btn btn-sm btn-outline-primary mt-2" data-bs-toggle="modal"
                                data-bs-target="#changePasswordModal">
                                <i class="ri-lock-password-line"></i> Change Password
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12">
            <div class="card p-0">
                <div class="card-body p-0">
                    <div class="profile-content">
                        <ul class="nav nav-underline nav-justified gap-0">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#edit-profile">Settings</a>
                            </li>
                        </ul>

                        <div class="tab-content p-4">
                            <div id="edit-profile" class="tab-pane active">
                                <div class="user-profile-content">
                                    <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                                        @csrf
                                        @method('PUT')

                                        <div class="row row-cols-sm-2 row-cols-1">
                                            <div class="mb-3 col-12">
                                                <label class="form-label">Profile Image</label>
                                                <div class="d-flex align-items-start gap-3 flex-wrap">
                                                    <div class="position-relative">
                                                        <div id="avatar-preview" class="rounded-circle border avatar-display avatar-display-sm"
                                                            style="width: 80px; height: 80px; background-image:url('{{ $avatarUrl }}'); background-position: {{ $avatarPosX }}% {{ $avatarPosY }}%; background-size: {{ $avatarZoom }}% {{ $avatarZoom }}%; background-repeat: no-repeat;"></div>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <input type="file" name="avatar" id="avatar" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                                                            class="form-control form-control-sm @error('avatar') is-invalid @enderror">
                                                        <small class="text-muted">JPG, PNG, GIF or WebP. Max 2 MB.</small>
                                                        @error('avatar')
                                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                </div>
                                                <div class="mt-3 profile-avatar-adjust-section">
                                                    <label class="form-label small text-muted mb-2">Adjust position & focus</label>
                                                    <input type="hidden" name="avatar_position_x" id="avatar_position_x" value="{{ $avatarPosX }}">
                                                    <input type="hidden" name="avatar_position_y" id="avatar_position_y" value="{{ $avatarPosY }}">
                                                    <input type="hidden" name="avatar_zoom" id="avatar_zoom" value="{{ $avatarZoom }}">
                                                    <div class="row g-2">
                                                        <div class="col-12 col-md-4">
                                                            <label class="form-label small mb-0">Horizontal <span id="avatar-pos-x-val">{{ $avatarPosX }}</span>%</label>
                                                            <input type="range" class="form-range avatar-adjust-slider" id="avatar_pos_x_slider" min="0" max="100" value="{{ $avatarPosX }}" data-target="position_x">
                                                        </div>
                                                        <div class="col-12 col-md-4">
                                                            <label class="form-label small mb-0">Vertical <span id="avatar-pos-y-val">{{ $avatarPosY }}</span>%</label>
                                                            <input type="range" class="form-range avatar-adjust-slider" id="avatar_pos_y_slider" min="0" max="100" value="{{ $avatarPosY }}" data-target="position_y">
                                                        </div>
                                                        <div class="col-12 col-md-4">
                                                            <label class="form-label small mb-0">Zoom <span id="avatar-zoom-val">{{ $avatarZoom }}</span>%</label>
                                                            <input type="range" class="form-range avatar-adjust-slider" id="avatar_zoom_slider" min="50" max="200" value="{{ $avatarZoom }}" data-target="zoom">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Full Name</label>
                                                <input type="text" name="name" value="{{ old('name', $user->name) }}"
                                                    class="form-control @error('name') is-invalid @enderror" required>
                                                <small class="text-muted">Use full name (e.g. Akash Nishad) so it appears correctly in Task WhatsApp notifications.</small>
                                                @error('name')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Email</label>
                                                <input type="email" name="email"
                                                    value="{{ old('email', $user->email) }}"
                                                    class="form-control @error('email') is-invalid @enderror" required>
                                                @error('email')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Phone (for Task WhatsApp)</label>
                                                <input type="text" name="phone" placeholder="e.g. 919876543210"
                                                    value="{{ old('phone', $user->phone ?? '') }}"
                                                    class="form-control @error('phone') is-invalid @enderror">
                                                <small class="text-muted">Digits + country code only. Required to receive task notifications on WhatsApp.</small>
                                                @error('phone')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>

                                        <button class="btn btn-primary" type="submit">
                                            <i class="ri-save-line me-1 fs-16 lh-1"></i> Save Changes
                                        </button>

                                        @if (session('success'))
                                            <div class="alert alert-success mt-3">
                                                {{ session('success') }}
                                            </div>
                                        @endif
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changePasswordModalLabel">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="{{ route('password.update') }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="user_id" value="{{ Auth::id() }}">

                    <!-- In your Change Password Modal -->
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password" name="new_password" required
                                    oninput="validatePassword()">
                                <button class="btn btn-outline-secondary toggle-password" type="button">
                                    <i class="ri-eye-line"></i>
                                </button>
                            </div>
                            <div class="password-requirements mt-2">
                                <small class="d-block" id="length-req">
                                    <span class="requirement-icon text-danger">✗</span>
                                    Minimum 8 characters
                                </small>
                                <small class="d-block" id="uppercase-req">
                                    <span class="requirement-icon text-danger">✗</span>
                                    At least 1 uppercase letter (A-Z)
                                </small>
                                <small class="d-block" id="lowercase-req">
                                    <span class="requirement-icon text-danger">✗</span>
                                    At least 1 lowercase letter (a-z)
                                </small>
                                <small class="d-block" id="number-req">
                                    <span class="requirement-icon text-danger">✗</span>
                                    At least 1 number (0-9)
                                </small>
                                <small class="d-block" id="special-req">
                                    <span class="requirement-icon text-danger">✗</span>
                                    At least 1 special character (!@#$%^&*)
                                </small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="new_password_confirmation" class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password_confirmation"
                                    name="new_password_confirmation" required oninput="checkPasswordMatch()">
                                <button class="btn btn-outline-secondary toggle-password" type="button">
                                    <i class="ri-eye-line"></i>
                                </button>
                            </div>
                            <small id="passwordMatchFeedback" class="text-danger d-none">
                                <i class="ri-close-line"></i> Passwords do not match
                            </small>
                            <small id="passwordMatchSuccess" class="text-success d-none">
                                <i class="ri-check-line"></i> Passwords match
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="updatePasswordBtn" disabled>
                            Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        // Profile image: live position/zoom and preview
        (function() {
            const headerAvatar = document.getElementById('profile-avatar-preview');
            const settingsPreview = document.getElementById('avatar-preview');
            const avatarInput = document.getElementById('avatar');
            let currentAvatarUrl = headerAvatar ? (headerAvatar.style.backgroundImage || '').replace(/url\(['"]?([^'"]+)['"]?\)/, '$1') : '';

            function applyAvatarStyle(el, url, posX, posY, zoom) {
                if (!el) return;
                const u = url || currentAvatarUrl;
                if (u) el.style.backgroundImage = "url('" + (u.indexOf("'") >= 0 ? u.replace(/'/g, "\\'") : u) + "')";
                el.style.backgroundPosition = (posX != null ? posX : 50) + '% ' + (posY != null ? posY : 50) + '%';
                const z = (zoom != null ? zoom : 100) + '%';
                el.style.backgroundSize = z + ' ' + z;
            }

            function updateAvatarFromInputs() {
                const posX = parseInt(document.getElementById('avatar_position_x')?.value || 50, 10);
                const posY = parseInt(document.getElementById('avatar_position_y')?.value || 50, 10);
                const zoom = parseInt(document.getElementById('avatar_zoom')?.value || 100, 10);
                applyAvatarStyle(headerAvatar, null, posX, posY, zoom);
                applyAvatarStyle(settingsPreview, null, posX, posY, zoom);
                const xEl = document.getElementById('avatar-pos-x-val');
                const yEl = document.getElementById('avatar-pos-y-val');
                const zEl = document.getElementById('avatar-zoom-val');
                if (xEl) xEl.textContent = posX;
                if (yEl) yEl.textContent = posY;
                if (zEl) zEl.textContent = zoom;
            }

            document.querySelectorAll('.avatar-adjust-slider').forEach(function(slider) {
                slider.addEventListener('input', function() {
                    const target = this.dataset.target;
                    const val = parseInt(this.value, 10);
                    if (target === 'position_x') {
                        document.getElementById('avatar_position_x').value = val;
                        document.getElementById('avatar-pos-x-val').textContent = val;
                    } else if (target === 'position_y') {
                        document.getElementById('avatar_position_y').value = val;
                        document.getElementById('avatar-pos-y-val').textContent = val;
                    } else if (target === 'zoom') {
                        document.getElementById('avatar_zoom').value = val;
                        document.getElementById('avatar-zoom-val').textContent = val;
                    }
                    const posX = parseInt(document.getElementById('avatar_position_x')?.value || 50, 10);
                    const posY = parseInt(document.getElementById('avatar_position_y')?.value || 50, 10);
                    const zoom = parseInt(document.getElementById('avatar_zoom')?.value || 100, 10);
                    applyAvatarStyle(headerAvatar, null, posX, posY, zoom);
                    applyAvatarStyle(settingsPreview, null, posX, posY, zoom);
                });
            });

            avatarInput?.addEventListener('change', function(e) {
                const file = e.target.files?.[0];
                if (!file || !file.type.startsWith('image/')) return;
                const url = URL.createObjectURL(file);
                currentAvatarUrl = url;
                const posX = parseInt(document.getElementById('avatar_position_x')?.value || 50, 10);
                const posY = parseInt(document.getElementById('avatar_position_y')?.value || 50, 10);
                const zoom = parseInt(document.getElementById('avatar_zoom')?.value || 100, 10);
                applyAvatarStyle(headerAvatar, url, posX, posY, zoom);
                applyAvatarStyle(settingsPreview, url, posX, posY, zoom);
            });

            updateAvatarFromInputs();
        })();

        // Toggle password visibility
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const input = this.parentNode.querySelector('input');
                const icon = this.querySelector('i');
                input.type = input.type === 'password' ? 'text' : 'password';
                icon.classList.toggle('ri-eye-line');
                icon.classList.toggle('ri-eye-off-line');
            });
        });

        // Password validation and matching
        function validatePassword() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('new_password_confirmation').value;
            const submitBtn = document.getElementById('updatePasswordBtn');

            // Password requirements
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[!@#$%^&*]/.test(password),
                match: password === confirmPassword && confirmPassword.length > 0
            };

            // Update requirement indicators
            updateRequirement('length', requirements.length);
            updateRequirement('uppercase', requirements.uppercase);
            updateRequirement('lowercase', requirements.lowercase);
            updateRequirement('number', requirements.number);
            updateRequirement('special', requirements.special);

            // Update password match feedback
            if (confirmPassword.length > 0) {
                const match = requirements.match;
                document.getElementById('passwordMatchFeedback').classList.toggle('d-none', match);
                document.getElementById('passwordMatchSuccess').classList.toggle('d-none', !match);
            } else {
                document.getElementById('passwordMatchFeedback').classList.add('d-none');
                document.getElementById('passwordMatchSuccess').classList.add('d-none');
            }

            // Enable/disable submit button
            const allValid = Object.values(requirements).every(Boolean);
            submitBtn.disabled = !allValid;
        }

        function updateRequirement(id, isValid) {
            const element = document.getElementById(`${id}-req`);
            if (element) {
                const icon = element.querySelector('.requirement-icon');
                icon.className = `requirement-icon ${isValid ? 'text-success' : 'text-danger'}`;
                icon.textContent = isValid ? '✓' : '✗';
            }
        }

        // Initialize validation
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('new_password_confirmation');

            newPassword.addEventListener('input', validatePassword);
            confirmPassword.addEventListener('input', validatePassword);

            // Initial validation
            validatePassword();
        });
    </script>
@endsection
