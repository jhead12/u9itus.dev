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

export interface AvailableCampaign {
    uuid: string;
    title: string;
    message_summary?: string;
    campaign_type: string;
    governance_level?: string;
    politician?: string;
    political_office?: string | null;
    payout?: number;
    media_duration?: number | null;
    thumbnail_url?: string | null;
    is_live?: boolean;
    live_scheduled_at?: string | null;
}

export interface StartViewResult {
    message?: string;
    session?: any;
    media_url?: string;
    must_watch?: number;
}

export interface CreateCampaignPayload {
    title: string;
    message_summary?: string;
    campaign_type: "video" | "live_feed" | "q_and_a";
    governance_level:
        | "federal"
        | "state"
        | "county"
        | "city"
        | "school"
        | "special";
    media_url?: string;
    media_type?:
        | "youtube"
        | "vimeo"
        | "direct_file"
        | "s3_cloudfront"
        | "hls_stream";
    media_duration?: number;
    live_feed_url?: string;
    live_scheduled_at?: string;
    total_budget: number;
    total_views_requested: number;
}

export interface CreatedCampaignResult {
    message?: string;
    campaign?: any;
}

export type UserRole = "voter" | "politician";

class ApiClient {
    private readonly client: AxiosInstance;
    private baseURL: string = "http://localhost:8000/api"; // Development

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
            (error) => {
                throw error;
            },
        );

        // Interceptor for error handling
        this.client.interceptors.response.use(
            (response) => response,
            async (error: AxiosError) => {
                if (error.response?.status === 401) {
                    // Token expired or invalid
                    await AsyncStorage.removeItem("auth_token");
                }
                throw error;
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
     * Get campaigns available to a voter in the ad viewing room.
     */
    async getAvailableCampaigns(
        voterUuid: string,
    ): Promise<AvailableCampaign[]> {
        try {
            const response = await this.client.get(
                `/voters/${voterUuid}/campaigns`,
            );
            const campaigns = (
                response.data as { campaigns?: AvailableCampaign[] }
            )?.campaigns;
            return campaigns || [];
        } catch (error) {
            console.error("Failed to fetch available campaigns:", error);
            return [];
        }
    }

    /**
     * Start a voter campaign watch session from the ad room.
     */
    async startCampaignView(
        voterUuid: string,
        campaignUuid: string,
    ): Promise<StartViewResult | null> {
        try {
            const response = await this.client.post<StartViewResult>(
                `/voters/${voterUuid}/campaigns/${campaignUuid}/watch`,
            );
            return response.data;
        } catch (error) {
            console.error("Failed to start campaign view:", error);
            return null;
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
     * Login politician
     */
    async loginPolitician(
        email: string,
        password: string,
    ): Promise<{ token: string; politician: any } | null> {
        try {
            const response = await this.client.post<ApiResponse>(
                "/politician/login",
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
            console.error("Politician login failed:", error);
            return null;
        }
    }

    async loginByRole(
        role: UserRole,
        email: string,
        password: string,
    ): Promise<{ token: string; user: any } | null> {
        if (role === "politician") {
            const result = await this.loginPolitician(email, password);
            if (!result) {
                return null;
            }

            return {
                token: result.token,
                user: result.politician,
            };
        }

        const result = await this.loginVoter(email, password);
        if (!result) {
            return null;
        }

        return {
            token: result.token,
            user: result.voter,
        };
    }

    /**
     * Create a new campaign for the authenticated politician.
     */
    async createPoliticianCampaign(
        politicianUuid: string,
        payload: CreateCampaignPayload,
    ): Promise<CreatedCampaignResult | null> {
        try {
            const response = await this.client.post<CreatedCampaignResult>(
                `/politicians/${politicianUuid}/campaigns`,
                payload,
            );
            return response.data;
        } catch (error) {
            console.error("Create campaign failed:", error);
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
