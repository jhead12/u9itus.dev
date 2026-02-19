<?php

namespace App\Contracts;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Authentication Service Interface
 * 
 * Platform-agnostic interface for user authentication.
 * Implemented by StandardAuthService for standalone Laravel auth.
 */
interface AuthServiceInterface
{
    /**
     * Authenticate a user based on credentials or platform-specific token.
     *
     * @param Request $request
     * @return User|null
     */
    public function authenticate(Request $request): ?User;

    /**
     * Register a new user on the platform.
     *
     * @param array $data
     * @return User
     */
    public function register(array $data): User;

    /**
     * Log out the current user.
     *
     * @param Request $request
     * @return void
     */
    public function logout(Request $request): void;

    /**
     * Get the currently authenticated user.
     *
     * @param Request $request
     * @return User|null
     */
    public function getCurrentUser(Request $request): ?User;

    /**
     * Verify if the request is properly authenticated.
     *
     * @param Request $request
     * @return bool
     */
    public function verify(Request $request): bool;

    /**
     * Generate an authentication token/session for the user.
     *
     * @param User $user
     * @return string|array Token or session data
     */
    public function generateToken(User $user): string|array;

    /**
     * Refresh the user's authentication token/session.
     *
     * @param Request $request
     * @return string|array|null
     */
    public function refreshToken(Request $request): string|array|null;
}
