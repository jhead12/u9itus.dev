import React, { useState } from "react";
import {
    ActivityIndicator,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View,
} from "react-native";
import ApiClient, { CreateCampaignPayload } from "../services/ApiClient";
import { useAuthStore } from "../stores/authStore";

interface CreateCampaignScreenProps {
    navigation: {
        navigate: (screen: string, params?: Record<string, unknown>) => void;
    };
}

export const CreateCampaignScreen: React.FC<CreateCampaignScreenProps> = ({
    navigation,
}) => {
    const { voter } = useAuthStore();
    const [title, setTitle] = useState("");
    const [summary, setSummary] = useState("");
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const handleContinue = async () => {
        const trimmedTitle = title.trim();
        const trimmedSummary = summary.trim();

        if (!trimmedTitle) {
            setError("Campaign title is required.");
            return;
        }

        const politicianUuid = (voter as { uuid?: string } | null)?.uuid;
        if (!politicianUuid) {
            setError(
                "Missing politician profile UUID. Please log out and back in.",
            );
            return;
        }

        setError(null);
        setIsSubmitting(true);

        const payload: CreateCampaignPayload = {
            title: trimmedTitle,
            message_summary: trimmedSummary || undefined,
            campaign_type: "video",
            governance_level: "city",
            media_url: "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
            media_type: "youtube",
            media_duration: 30,
            total_budget: 10,
            total_views_requested: 10,
        };

        const result = await ApiClient.createPoliticianCampaign(
            politicianUuid,
            payload,
        );

        setIsSubmitting(false);

        if (!result) {
            setError("Could not create campaign. Please try again.");
            return;
        }

        navigation.navigate("Home");
    };

    return (
        <ScrollView
            style={styles.container}
            contentContainerStyle={styles.content}
        >
            <Text style={styles.heading}>Create Campaign</Text>
            <Text style={styles.subheading}>
                Start a new campaign to reach voters in your target district.
            </Text>

            <View style={styles.card}>
                <Text style={styles.label}>Campaign Title</Text>
                <TextInput
                    style={styles.input}
                    placeholder="Enter campaign title"
                    placeholderTextColor="#94a3b8"
                    value={title}
                    onChangeText={setTitle}
                />

                <Text style={styles.label}>Message Summary</Text>
                <TextInput
                    style={[styles.input, styles.textarea]}
                    placeholder="Describe your campaign message"
                    placeholderTextColor="#94a3b8"
                    value={summary}
                    onChangeText={setSummary}
                    multiline
                />

                {error ? <Text style={styles.errorText}>{error}</Text> : null}

                <TouchableOpacity
                    style={[
                        styles.primaryButton,
                        isSubmitting && styles.buttonDisabled,
                    ]}
                    onPress={handleContinue}
                    disabled={isSubmitting}
                >
                    {isSubmitting ? (
                        <ActivityIndicator color="#ffffff" />
                    ) : (
                        <Text style={styles.primaryButtonText}>Continue</Text>
                    )}
                </TouchableOpacity>
            </View>
        </ScrollView>
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: "#020617",
    },
    content: {
        padding: 16,
        paddingBottom: 28,
    },
    heading: {
        color: "#f8fafc",
        fontSize: 28,
        fontWeight: "800",
    },
    subheading: {
        color: "#94a3b8",
        marginTop: 8,
        marginBottom: 16,
        fontSize: 15,
        lineHeight: 22,
    },
    card: {
        backgroundColor: "#0f172a",
        borderColor: "#1e293b",
        borderWidth: 1,
        borderRadius: 14,
        padding: 14,
    },
    label: {
        color: "#e2e8f0",
        marginBottom: 8,
        fontWeight: "600",
    },
    input: {
        backgroundColor: "#111b2e",
        borderColor: "#334155",
        borderWidth: 1,
        borderRadius: 12,
        color: "#f8fafc",
        paddingHorizontal: 12,
        paddingVertical: 11,
        marginBottom: 14,
    },
    textarea: {
        minHeight: 110,
        textAlignVertical: "top",
    },
    primaryButton: {
        backgroundColor: "#10b981",
        borderRadius: 12,
        paddingVertical: 13,
        alignItems: "center",
        marginTop: 2,
    },
    buttonDisabled: {
        opacity: 0.7,
    },
    primaryButtonText: {
        color: "#ffffff",
        fontSize: 16,
        fontWeight: "700",
    },
    errorText: {
        color: "#fb7185",
        marginBottom: 10,
    },
});

export default CreateCampaignScreen;
