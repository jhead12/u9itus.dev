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

    // Actions
    register: (
        email: string,
        password: string,
        fullName: string,
    ) => Promise<boolean>;
    login: (role: UserRole, email: string, password: string) => Promise<boolean>;
    logout: () => Promise<void>;
    restoreToken: () => Promise<void>;
    setError: (error: string | null) => void;
}

export const useAuthStore = create<AuthState>((set) => ({
    voter: null,
    token: null,
    isAuthenticated: false,
    isLoading: true,
    error: null,
    role: null,

    register: async (email, password, fullName) => {
        try {
            set({ isLoading: true, error: null });
            const result = await ApiClient.registerVoter(
                email,
                password,
                fullName,
            );

            if (result) {
                await AsyncStorage.setItem("auth_role", "voter");
                set({
                    voter: result.voter,
                    token: result.token,
                    isAuthenticated: true,
                    role: "voter",
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
                set({
                    voter: result.user,
                    token: result.token,
                    isAuthenticated: true,
                    role,
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
            set({
                voter: null,
                token: null,
                isAuthenticated: false,
                error: null,
                role: null,
            });
        }
    },

    restoreToken: async () => {
        try {
            const token = await AsyncStorage.getItem("auth_token");
            const role = await AsyncStorage.getItem("auth_role");
            if (token) {
                set({
                    token,
                    isAuthenticated: true,
                    role: role === "politician" ? "politician" : "voter",
                });
            }
        } catch (error) {
            console.error("Token restoration error:", error);
        } finally {
            set({ isLoading: false });
        }
    },

    setError: (error) => set({ error }),
}));
