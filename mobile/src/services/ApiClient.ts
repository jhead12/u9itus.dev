import axios, { AxiosInstance, AxiosError } from "axios";
import AsyncStorage from "@react-native-async-storage/async-storage";
import RNFS from "react-native-fs";

/**
 * API Client for U9itus Backend
 *
 * Handles:
 * - Token-based authentication (Sanctum)
 * - Video question upload
 * - Politician profile data
 * - Campaign viewing
 */
export interface ApiResponse<T = any> {
    success: boolean;
    data?: T;
    message?: string;
    errors?: Record<string, string[]>;
}

export interface VideoQuestionPayload {
    videoPath: string; // Local file path to video
    caption?: string; // Optional caption (max 500 chars)
    sessionUuid?: string;
}

export interface VideoQuestion {
    id: number;
    voter_id: number;
    campaign_id: number;
    media_url: string;
    media_duration?: number;
    body?: string;
    message_type: "video" | "text";
    status: "open" | "in_review" | "resolved";
    created_at: string;
    voter: {
        id: number;
        full_name: string;
        email: string;
    };
}

export interface PoliticianProfile {
    id: number;
    full_name: string;
    office: string;
    governance_level: string;
    district: string;
    bio?: string;
    avatar_url?: string;
    total_campaigns: number;
    video_questions: VideoQuestion[];
}

class ApiClient {
    private client: AxiosInstance;
    private baseURL: string = "http://localhost:8000/api"; // Development
    private token: string | null = null;

    constructor() {
        this.client = axios.create({
            baseURL: this.baseURL,
            timeout: 30000,
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
        });

        // Interceptor to attach token
        this.client.interceptors.request.use(
            async (config) => {
                const token = await AsyncStorage.getItem("auth_token");
                if (token) {
                    config.headers.Authorization = `Bearer ${token}`;
                }
                return config;
            },
            (error) => Promise.reject(error),
        );

        // Interceptor for error handling
        this.client.interceptors.response.use(
            (response) => response,
            async (error: AxiosError) => {
                if (error.response?.status === 401) {
                    // Token expired or invalid
                    await AsyncStorage.removeItem("auth_token");
                }
                return Promise.reject(error);
            },
        );
    }

    /**
     * Upload video question to politician's campaign
     *
     * @param token Watch token (identifies campaign)
     * @param payload Video file + optional caption
     */
    async uploadVideoQuestion(
        token: string,
        payload: VideoQuestionPayload,
    ): Promise<ApiResponse> {
        try {
            const formData = new FormData();

            // Read video file and append
            const videoData = await RNFS.readFile(payload.videoPath, "base64");
            const videoBlob = new Blob([Buffer.from(videoData, "base64")], {
                type: "video/mp4",
            });
            formData.append("video", videoBlob, `question-${Date.now()}.mp4`);

            // Add optional caption
            if (payload.caption) {
                formData.append("body", payload.caption);
            }

            if (payload.sessionUuid) {
                formData.append("view_session_uuid", payload.sessionUuid);
            }

            const response = await this.client.post<ApiResponse>(
                `/voter/watch/${token}/video-question`,
                formData,
                {
                    headers: {
                        "Content-Type": "multipart/form-data",
                    },
                    onUploadProgress: (progressEvent) => {
                        const percentCompleted = Math.round(
                            (progressEvent.loaded * 100) /
                                (progressEvent.total || 1),
                        );
                        console.log(`Upload progress: ${percentCompleted}%`);
                    },
                },
            );

            return response.data;
        } catch (error) {
            if (axios.isAxiosError(error)) {
                return {
                    success: false,
                    message: error.response?.data?.message || error.message,
                    errors: error.response?.data?.errors,
                };
            }
            throw error;
        }
    }

    /**
     * Get politician profile with video questions
     */
    async getPoliticianProfile(
        campaignId: number,
    ): Promise<PoliticianProfile | null> {
        try {
            const response = await this.client.get<
                ApiResponse<PoliticianProfile>
            >(`/politician/${campaignId}/profile`);
            return response.data.data || null;
        } catch (error) {
            console.error("Failed to fetch politician profile:", error);
            return null;
        }
    }

    /**
     * Get video questions for a campaign
     */
    async getVideoQuestions(campaignId: number): Promise<VideoQuestion[]> {
        try {
            const response = await this.client.get<
                ApiResponse<VideoQuestion[]>
            >(`/campaigns/${campaignId}/video-questions`);
            return response.data.data || [];
        } catch (error) {
            console.error("Failed to fetch video questions:", error);
            return [];
        }
    }

    /**
     * Register or login voter
     */
    async registerVoter(
        email: string,
        password: string,
        fullName: string,
    ): Promise<{ token: string; voter: any } | null> {
        try {
            const response = await this.client.post<ApiResponse>(
                "/voter/register",
                { email, password, full_name: fullName },
            );

            if (response.data.success && response.data.data?.token) {
                await AsyncStorage.setItem(
                    "auth_token",
                    response.data.data.token,
                );
                return response.data.data;
            }
            return null;
        } catch (error) {
            console.error("Registration failed:", error);
            return null;
        }
    }

    /**
     * Login voter
     */
    async loginVoter(
        email: string,
        password: string,
    ): Promise<{ token: string; voter: any } | null> {
        try {
            const response = await this.client.post<ApiResponse>(
                "/voter/login",
                { email, password },
            );

            if (response.data.success && response.data.data?.token) {
                await AsyncStorage.setItem(
                    "auth_token",
                    response.data.data.token,
                );
                return response.data.data;
            }
            return null;
        } catch (error) {
            console.error("Login failed:", error);
            return null;
        }
    }

    /**
     * Logout voter
     */
    async logoutVoter(): Promise<boolean> {
        try {
            await this.client.post("/voter/logout");
            await AsyncStorage.removeItem("auth_token");
            return true;
        } catch (error) {
            console.error("Logout failed:", error);
            return false;
        }
    }

    /**
     * Set custom base URL (for different environments)
     */
    setBaseURL(url: string): void {
        this.baseURL = url;
        this.client.defaults.baseURL = url;
    }

    /**
     * Get current auth token
     */
    async getAuthToken(): Promise<string | null> {
        return AsyncStorage.getItem("auth_token");
    }
}

export default new ApiClient();
