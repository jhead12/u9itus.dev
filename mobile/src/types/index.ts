/**
 * Common Type Definitions
 */

export interface User {
    id: number;
    email: string;
    full_name: string;
    avatar_url?: string;
    created_at: string;
}

export interface Campaign {
    id: number;
    title: string;
    politician_id: number;
    politician: {
        id: number;
        full_name: string;
    };
    media_url: string;
    media_duration: number;
    campaign_type: "video" | "q_and_a" | "live_feed";
    status: "draft" | "active" | "paused" | "completed";
    views_completed: number;
    created_at: string;
}

export interface VideoQuestion {
    id: number;
    voter_id: number;
    campaign_id: number;
    media_url: string;
    media_duration?: number;
    body?: string;
    message_type: "text" | "video" | "audio";
    status: "open" | "in_review" | "resolved" | "dismissed";
    created_at: string;
    voter?: {
        id: number;
        full_name: string;
        email: string;
    };
}

export interface NotificationPayload {
    type: "campaign_update" | "payout" | "new_question" | "system";
    title: string;
    body: string;
    data?: Record<string, any>;
}

export type AppState =
    | "loading"
    | "authenticated"
    | "unauthenticated"
    | "error";
