<x-app-layout title="Register">
    <div class="mx-auto" style="max-width:520px">
        <div class="card p-4">
            <h1 class="h4 mb-3">Create an account</h1>

            @if ($errors->any())
                <div class="text-danger mb-2">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}">
                @csrf

                <div class="mb-3">
                    <label for="name" class="form-label">Full name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus class="form-control" />
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required class="form-control" />
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input id="password" name="password" type="password" required class="form-control" />
                </div>

                <div class="mb-3">
                    <label for="password_confirmation" class="form-label">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required class="form-control" />
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <a href="{{ route('login') ?? '/login' }}">Already have an account?</a>
                    <button type="submit" class="btn btn-dark">Register</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
