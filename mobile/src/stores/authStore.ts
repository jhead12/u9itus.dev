import { create } from 'zustand';
import AsyncStorage from '@react-native-async-storage/async-storage';
import ApiClient from '@/services/ApiClient';

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

  // Actions
  register: (email: string, password: string, fullName: string) => Promise<boolean>;
  login: (email: string, password: string) => Promise<boolean>;
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

  register: async (email, password, fullName) => {
    try {
      set({ isLoading: true, error: null });
      const result = await ApiClient.registerVoter(email, password, fullName);
      
      if (result) {
        set({
          voter: result.voter,
          token: result.token,
          isAuthenticated: true,
        });
        return true;
      }
      return false;
    } catch (error) {
      const errorMsg = error instanceof Error ? error.message : 'Registration failed';
      set({ error: errorMsg, isLoading: false });
      return false;
    } finally {
      set({ isLoading: false });
    }
  },

  login: async (email, password) => {
    try {
      set({ isLoading: true, error: null });
      const result = await ApiClient.loginVoter(email, password);
      
      if (result) {
        set({
          voter: result.voter,
          token: result.token,
          isAuthenticated: true,
        });
        return true;
      }
      return false;
    } catch (error) {
      const errorMsg = error instanceof Error ? error.message : 'Login failed';
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
      console.error('Logout error:', error);
    } finally {
      set({
        voter: null,
        token: null,
        isAuthenticated: false,
        error: null,
      });
    }
  },

  restoreToken: async () => {
    try {
      const token = await AsyncStorage.getItem('auth_token');
      if (token) {
        set({
          token,
          isAuthenticated: true,
        });
      }
    } catch (error) {
      console.error('Token restoration error:', error);
    } finally {
      set({ isLoading: false });
    }
  },

  setError: (error) => set({ error }),
}));
