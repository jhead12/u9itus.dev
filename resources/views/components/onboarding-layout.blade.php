@props(['progress', 'phases', 'totalPhases', 'currentPhase', 'title', 'description'])

<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} - U9itus Onboarding</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="min-h-screen flex flex-col">
        <!-- Progress Bar -->
        <div class="bg-gray-800 border-b border-gray-700">
            <div class="max-w-4xl mx-auto px-6 py-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm text-gray-400">
                        Step {{ count($progress->completed_phases ?? []) + 1 }} of {{ $totalPhases }}
                    </span>
                    <span class="text-sm text-gray-400">
                        {{ $progress->getProgressPercentage($totalPhases) }}% Complete
                    </span>
                </div>
                <div class="w-full bg-gray-700 rounded-full h-2">
                    <div class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                         style="width: {{ $progress->getProgressPercentage($totalPhases) }}%">
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex items-center justify-center px-6 py-12">
            <div class="w-full max-w-2xl">
                <!-- Header -->
                <div class="text-center mb-8">
                    <h1 class="text-4xl font-bold text-white mb-3">{{ $title }}</h1>
                    <p class="text-lg text-gray-400">{{ $description }}</p>
                </div>

                <!-- Card -->
                <div class="bg-gray-800 rounded-lg shadow-xl border border-gray-700 p-8">
                    {{ $slot }}
                </div>

                <!-- Skip Option -->
                <div class="mt-6 text-center">
                    <form method="POST" action="{{ route(Request::route()->getName() === 'voter.onboarding.welcome' ? 'voter.onboarding.skip' : (Request::route()->getName() === 'politician.onboarding.welcome' ? 'politician.onboarding.skip' : 'admin.onboarding.skip')) }}">
                        @csrf
                        <button type="submit" class="text-sm text-gray-400 hover:text-gray-300 underline">
                            Skip onboarding for now
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
