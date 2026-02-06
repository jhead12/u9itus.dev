<x-app-layout title="Login">
    <div class="mx-auto" style="max-width:420px">
        <div class="card p-4">
            <h1 class="h4 mb-3">Sign in</h1>

            @if ($errors->any())
                <div class="text-danger mb-2">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="form-control" />
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input id="password" name="password" type="password" required class="form-control" />
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <a href="{{ route('password.request') ?? '#forgot' }}">Forgot?</a>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <a href="{{ route('register') ?? '/register' }}">Register</a>
                    <button type="submit" class="btn btn-dark">Log in</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
