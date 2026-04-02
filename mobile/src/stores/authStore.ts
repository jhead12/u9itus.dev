import { create } from "zustand";
import AsyncStorage from "@react-native-async-storage/async-storage";
import ApiClient, { UserRole } from "../services/ApiClient";

interface Voter {
    id: number;
    email: string;
    full_name: string;
    avatar_url?: string;
}

interface AuthState {
    voter: Voter | null;
    token: string | null;
    isAuthenticated: boolean;
    isLoading: boolean;
    error: string | null;
    role: UserRole | null;
    hasCompletedVoterOnboarding: boolean;

    // Actions
    register: (
        email: string,
        password: string,
        fullName: string,
        isRegisteredVoter?: boolean,
    ) => Promise<boolean>;
    login: (
        role: UserRole,
        email: string,
        password: string,
    ) => Promise<boolean>;
    logout: () => Promise<void>;
    restoreToken: () => Promise<void>;
    completeVoterOnboarding: () => Promise<void>;
    setError: (error: string | null) => void;
}

export const useAuthStore = create<AuthState>((set) => ({
    voter: null,
    token: null,
    isAuthenticated: false,
    isLoading: true,
    error: null,
    role: null,
    hasCompletedVoterOnboarding: false,

    register: async (email, password, fullName, isRegisteredVoter) => {
        try {
            set({ isLoading: true, error: null });
            const result = await ApiClient.registerVoter(
                email,
                password,
                fullName,
                isRegisteredVoter,
            );

            if (result) {
                await AsyncStorage.setItem("auth_role", "voter");
                await AsyncStorage.setItem(
                    "voter_onboarding_complete",
                    "false",
                );
                set({
                    voter: result.voter,
                    token: result.token,
                    isAuthenticated: true,
                    role: "voter",
                    hasCompletedVoterOnboarding: false,
                });
                return true;
            }
            return false;
        } catch (error) {
            const errorMsg =
                error instanceof Error ? error.message : "Registration failed";
            set({ error: errorMsg, isLoading: false });
            return false;
        } finally {
            set({ isLoading: false });
        }
    },

    login: async (role, email, password) => {
        try {
            set({ isLoading: true, error: null });
            const result = await ApiClient.loginByRole(role, email, password);

            if (result) {
                await AsyncStorage.setItem("auth_role", role);
                const onboardingValue = await AsyncStorage.getItem(
                    "voter_onboarding_complete",
                );
                const hasCompletedVoterOnboarding =
                    role === "voter" ? onboardingValue === "true" : true;

                set({
                    voter: result.user,
                    token: result.token,
                    isAuthenticated: true,
                    role,
                    hasCompletedVoterOnboarding,
                });
                return true;
            }
            return false;
        } catch (error) {
            const errorMsg =
                error instanceof Error ? error.message : "Login failed";
            set({ error: errorMsg, isLoading: false });
            return false;
        } finally {
            set({ isLoading: false });
        }
    },

    logout: async () => {
        try {
            await ApiClient.logoutVoter();
        } catch (error) {
            console.error("Logout error:", error);
        } finally {
            await AsyncStorage.removeItem("auth_role");
            await AsyncStorage.removeItem("voter_onboarding_complete");
            set({
                voter: null,
                token: null,
                isAuthenticated: false,
                error: null,
                role: null,
                hasCompletedVoterOnboarding: false,
            });
        }
    },

    restoreToken: async () => {
        try {
            const token = await AsyncStorage.getItem("auth_token");
            const role = await AsyncStorage.getItem("auth_role");
            const onboardingValue = await AsyncStorage.getItem(
                "voter_onboarding_complete",
            );

            if (token) {
                const normalizedRole =
                    role === "politician" ? "politician" : "voter";
                set({
                    token,
                    isAuthenticated: true,
                    role: normalizedRole,
                    hasCompletedVoterOnboarding:
                        normalizedRole === "voter"
                            ? onboardingValue === "true"
                            : true,
                });
            }
        } catch (error) {
            console.error("Token restoration error:", error);
        } finally {
            set({ isLoading: false });
        }
    },

    completeVoterOnboarding: async () => {
        await AsyncStorage.setItem("voter_onboarding_complete", "true");
        set({ hasCompletedVoterOnboarding: true });
    },

    setError: (error) => set({ error }),
}));
