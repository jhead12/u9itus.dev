import React, { useCallback, useEffect, useMemo, useState } from "react";
import {
    ActivityIndicator,
    FlatList,
    RefreshControl,
    StyleSheet,
    Text,
    TouchableOpacity,
    View,
} from "react-native";
import ApiClient, { AvailableCampaign } from "../services/ApiClient";
import { useAuthStore } from "../stores/authStore";

export const AdViewingRoomScreen: React.FC = () => {
    const { voter } = useAuthStore();
    const [campaigns, setCampaigns] = useState<AvailableCampaign[]>([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [claimingCampaignUuid, setClaimingCampaignUuid] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [statusMessage, setStatusMessage] = useState<string | null>(null);

    const voterUuid = useMemo(
        () => (voter as { uuid?: string } | null)?.uuid || null,
        [voter],
    );

    const loadCampaigns = useCallback(async () => {
        if (!voterUuid) {
            setError("Voter profile is missing UUID. Please log in again.");
            setCampaigns([]);
            setLoading(false);
            setRefreshing(false);
            return;
        }

        setError(null);

        const items = await ApiClient.getAvailableCampaigns(voterUuid);
        setCampaigns(items);
        setLoading(false);
        setRefreshing(false);
    }, [voterUuid]);

    useEffect(() => {
        loadCampaigns();
    }, [loadCampaigns]);

    const onRefresh = () => {
        setRefreshing(true);
        loadCampaigns();
    };

    const handleClaimCampaign = async (campaignUuid: string) => {
        if (!voterUuid) {
            setError("Unable to claim campaign without voter UUID.");
            return;
        }

        setStatusMessage(null);
        setClaimingCampaignUuid(campaignUuid);
        const result = await ApiClient.startCampaignView(voterUuid, campaignUuid);
        setClaimingCampaignUuid(null);

        if (!result) {
            setError("Could not start this ad. Please try another campaign.");
            return;
        }

        setStatusMessage(result.message || "Ad session started.");
        onRefresh();
    };

    return (
        <View style={styles.container}>
            <Text style={styles.title}>Ad Viewing Room</Text>
            <Text style={styles.subtitle}>Choose a campaign and start watching</Text>

            {statusMessage ? (
                <View style={styles.statusBar}>
                    <Text style={styles.statusText}>{statusMessage}</Text>
                </View>
            ) : null}

            {error ? <Text style={styles.errorText}>{error}</Text> : null}

            {loading ? (
                <View style={styles.loadingWrap}>
                    <ActivityIndicator size="large" color="#10b981" />
                    <Text style={styles.loadingText}>Loading campaigns...</Text>
                </View>
            ) : (
                <FlatList
                    data={campaigns}
                    keyExtractor={(item) => item.uuid}
                    contentContainerStyle={
                        campaigns.length === 0 ? styles.emptyContainer : styles.listContainer
                    }
                    refreshControl={
                        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor="#10b981" />
                    }
                    ListEmptyComponent={
                        <View>
                            <Text style={styles.emptyTitle}>No campaigns available</Text>
                            <Text style={styles.emptySubtitle}>Pull down to refresh and check again.</Text>
                        </View>
                    }
                    renderItem={({ item }) => (
                        <View style={styles.card}>
                            <Text style={styles.cardTitle}>{item.title}</Text>
                            <Text style={styles.cardMeta}>
                                {item.politician || "Unknown Politician"}
                                {item.payout === undefined ? "" : ` • $${Number(item.payout).toFixed(2)}`}
                            </Text>
                            {item.message_summary ? (
                                <Text style={styles.cardSummary} numberOfLines={2}>
                                    {item.message_summary}
                                </Text>
                            ) : null}

                            <TouchableOpacity
                                style={styles.claimButton}
                                onPress={() => handleClaimCampaign(item.uuid)}
                                disabled={claimingCampaignUuid === item.uuid}
                            >
                                {claimingCampaignUuid === item.uuid ? (
                                    <ActivityIndicator color="#ffffff" />
                                ) : (
                                    <Text style={styles.claimButtonText}>Start Ad</Text>
                                )}
                            </TouchableOpacity>
                        </View>
                    )}
                />
            )}
        </View>
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: "#020617",
        paddingHorizontal: 16,
        paddingTop: 20,
    },
    title: {
        color: "#f8fafc",
        fontSize: 26,
        fontWeight: "700",
        marginBottom: 8,
    },
    subtitle: {
        color: "#94a3b8",
        fontSize: 15,
        marginBottom: 12,
    },
    statusBar: {
        backgroundColor: "#064e3b",
        borderColor: "#10b981",
        borderWidth: 1,
        borderRadius: 10,
        paddingHorizontal: 12,
        paddingVertical: 8,
        marginBottom: 10,
    },
    statusText: {
        color: "#d1fae5",
        fontSize: 13,
        fontWeight: "600",
    },
    errorText: {
        color: "#fda4af",
        marginBottom: 10,
    },
    loadingWrap: {
        flex: 1,
        alignItems: "center",
        justifyContent: "center",
    },
    loadingText: {
        color: "#94a3b8",
        marginTop: 10,
    },
    listContainer: {
        paddingBottom: 24,
    },
    emptyContainer: {
        flexGrow: 1,
        alignItems: "center",
        justifyContent: "center",
    },
    emptyTitle: {
        color: "#e2e8f0",
        fontSize: 18,
        fontWeight: "600",
        textAlign: "center",
    },
    emptySubtitle: {
        color: "#94a3b8",
        marginTop: 6,
        textAlign: "center",
    },
    card: {
        backgroundColor: "#0f172a",
        borderColor: "#1e293b",
        borderWidth: 1,
        borderRadius: 14,
        padding: 14,
        marginBottom: 12,
    },
    cardTitle: {
        color: "#f8fafc",
        fontSize: 17,
        fontWeight: "700",
    },
    cardMeta: {
        color: "#93c5fd",
        fontSize: 13,
        marginTop: 4,
    },
    cardSummary: {
        color: "#cbd5e1",
        marginTop: 8,
        lineHeight: 20,
    },
    claimButton: {
        backgroundColor: "#10b981",
        borderRadius: 10,
        paddingVertical: 10,
        alignItems: "center",
        marginTop: 12,
    },
    claimButtonText: {
        color: "#ffffff",
        fontWeight: "700",
        fontSize: 15,
    },
});

export default AdViewingRoomScreen;