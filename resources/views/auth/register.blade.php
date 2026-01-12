<!DOCTYPE html>
<html lang="en">

<head>
    @include('layouts.shared/title-meta', ['title' => 'Register'])

    @include('layouts.shared/head-css', ['mode' => $mode ?? '', 'demo' => $demo ?? ''])
</head>

<body class="authentication-bg">

    <div class="account-pages pt-2 pt-sm-5 pb-4 pb-sm-5 position-relative">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xxl-8 col-lg-10">
                    <div class="card overflow-hidden bg-opacity-25">
                        <div class="row g-0">
                            <div class="col-lg-6 d-none d-lg-block p-2">
                                <img src="/images/auth-img.jpg" alt="" class="img-fluid rounded h-100">
                            </div>
                            <div class="col-lg-6">
                                <div class="d-flex flex-column h-100">
                                    <div class="auth-brand p-4">
                                        <h4 class="fs-20 text-center">5Core ProductMaster Login </h4>

                                        {{-- <a href="{{ route('any', 'index') }}" class="logo-light">
                                            <img src="/images/logo.png" alt="logo" height="22">
                                        </a>
                                        <a href="{{ route('any', 'index') }}" class="logo-dark">
                                            <img src="/images/logo-dark.png" alt="dark logo" height="22">
                                        </a> --}}
                                    </div>
                                    <div class="p-4 my-auto">
                                        <h4 class="fs-20">Free Sign Up</h4>
                                        <p class="text-muted mb-3">Enter your email address and password to access
                                            account.</p>

                                        <!-- form -->
                                        {{-- <form method="POST" action="{{ route('register') }}">
                                            @csrf

                                            @if ($errors->any())
                                                <div class="alert alert-danger">
                                                    <ul>
                                                        @foreach ($errors->all() as $error)
                                                            <li>{{ $error }}</li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif

                                <div class="mb-3">
                                                <label for="fullname" class="form-label">Full Name</label>
                                                <input class="form-control" type="text" id="fullname" name="name"
                                                    placeholder="Enter your name" required value="{{ old('name') }}">
                                                @error('name')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>

                                            <div class="mb-3">
                                                <label for="emailaddress" class="form-label">Email address</label>
                                                <input class="form-control" type="email" id="emailaddress"
                                                    name="email" required placeholder="Enter your email"
                                                    value="{{ old('email') }}">
                                                @error('email')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>

                                            <div class="mb-3">
                                                <label for="password" class="form-label">Password</label>
                                                <div class="input-group">
                                                    <input class="form-control" type="password" required id="password"
                                                        name="password" placeholder="Enter your password">
                                                    <button class="btn btn-outline-secondary" type="button"
                                                        id="togglePassword">
                                                        <i class="ri-eye-fill"></i>
                                                    </button>
                                                </div>
                                                @error('password')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>

                                            <div class="mb-3">
                                                <label for="password_confirmation" class="form-label">Confirm
                                                    Password</label>
                                                <input class="form-control" type="password" required
                                                    id="password_confirmation" name="password_confirmation"
                                                    placeholder="Confirm your password">
                                            </div>--                                         <div class="mb-3">
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input" id="checkbox-signup"
                                                        name="terms" required>
                                                    <label class="form-check-label" for="checkbox-signup">I accept <a
                                                            href="#" class="text-muted">Terms and
                                                            Conditions</a></label>
                                                </div>
                                                @error('terms')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>                                         <div class="mb-0 d-grid text-center">
                                                <button class="btn btn-primary fw-semibold" type="submit">Sign
                                                    Up</button>
                                            </div>
                                        </form> --}}
                                        <!-- end form-->
                                    </div>
                                </div>
                            </div> <!-- end col -->
                        </div>
                    </div>
                </div>
                <!-- end row -->
            </div>
            {{-- <div class="row">
                <div class="col-12 text-center">
                    <p class="text-dark-emphasis">Already have account? <a
                            href="{{ route('second', ['auth', 'login']) }}"
                            class="text-dark fw-bold ms-1 link-offset-3 text-decoration-underline"><b>Log In</b></a></p>
                </div> <!-- end col -->
            </div> --}}
            <!-- end row -->
        </div>
        <!-- end container -->
    </div>
    <!-- end page -->

    <footer class="footer footer-alt fw-medium">
        <span class="text-dark-emphasis">
            <script>
                document.write(new Date().getFullYear())
            </script> © 5Core - Developed By ❤
        </span>
    </footer>

    @include('layouts.shared/footer-scripts')

</body>

</html>
<script>
    document.getElementById('togglePassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('password');
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.querySelector('i').classList.toggle('ri-eye-off-fill');
    });
</script>
