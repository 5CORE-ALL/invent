<!DOCTYPE html>
<html lang="en">

<head>
    @include('layouts.shared/title-meta', ['title' => 'Log In'])

    @include('layouts.shared/head-css', ['mode' => $mode ?? '', 'demo' => $demo ?? ''])
</head>

<body class="authentication-bg position-relative">
    <div class="account-pages pt-2 pt-sm-5 pb-4 pb-sm-5 position-relative">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xxl-8 col-lg-10">
                    <div class="card overflow-hidden">
                        <div class="row g-0">
                            <div class="col-lg-3 d-none d-lg-block p-2">
                                <!-- <img src="/images/auth-img.jpg" alt="" class="img-fluid rounded h-100"> -->
                            </div>
                            <div class="col-lg-6">
                                <div class="d-flex flex-column h-100">
                                    <!-- <div class="auth-brand p-4">
                                        <a href="{{ route('any', 'index') }}" class="logo-light">
                                            <img src="/images/HR5LOGO.png" alt="logo" height="22">
                                        </a>
                                        <a href="{{ route('any', 'index') }}" class="logo-dark">
                                            <img src="/images/logo-dark.png" alt="dark logo" height="22">
                                        </a>
                                    </div> -->
                                    <div class="p-4 my-auto">
                                        <h4 class="fs-20 text-center">5Core Product Master Login </h4>
                                        <!-- <p class="text-muted mb-3">Enter your email address and password to access
                                            account.
                                        </p> -->

                                        @if ($errors->any())
                                            @foreach ($errors->all() as $error)
                                                <p class="text-danger text-center">{{ $error }}</p>
                                            @endforeach
                                        @endif

                                        <div class="text-center mt-4">
                                            <p class="text-muted fs-16 mb-3">Sign in with your Google account</p>
                                            <a href="{{ route('auth.google') }}" class="btn btn-soft-danger btn-lg w-100">
                                                <i class="ri-google-fill me-1"></i> Continue with Google
                                            </a>
                                        </div>
                                        
                                    </div>
                                </div>
                            </div> <!-- end col -->
                            <div class="col-lg-3 d-none d-lg-block p-2">
                                <!-- <img src="/images/auth-img.jpg" alt="" class="img-fluid rounded h-100"> -->
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end row -->
            </div>
            <!-- end row -->
        </div>
        <!-- end container -->
    </div>
    <!-- end page -->

    <footer class="footer footer-alt fw-medium">
        <span class="text-dark">
            <script>
                document.write(new Date().getFullYear())
            </script> © 5Core - Developed By ❤
        </span>
    </footer>

    @include('layouts.shared/footer-scripts')

</body>

</html>
