import React, { useState } from "react";
import { Alert, StyleSheet, Text, TouchableOpacity, View } from "react-native";
import { useAuthStore } from "../stores/authStore";

export const VoterOnboardingVerificationScreen: React.FC = () => {
    const { completeVoterOnboarding } = useAuthStore();
    const [idUploaded, setIdUploaded] = useState(false);
    const [proofUploaded, setProofUploaded] = useState(false);

    const mockUpload = (kind: "id" | "proof") => {
        if (kind === "id") setIdUploaded(true);
        if (kind === "proof") setProofUploaded(true);
        Alert.alert("Saved", "Verification placeholder marked complete.");
    };

    const finishOnboarding = async () => {
        await completeVoterOnboarding();
    };

    return (
        <View style={styles.container}>
            <Text style={styles.title}>Verification</Text>
            <Text style={styles.subtitle}>
                Complete voter verification steps similar to web onboarding.
            </Text>

            <View style={styles.card}>
                <Text style={styles.cardTitle}>Government ID</Text>
                <Text style={styles.cardDescription}>
                    Upload a valid photo ID for account verification.
                </Text>
                <TouchableOpacity
                    style={styles.secondaryButton}
                    onPress={() => mockUpload("id")}
                >
                    <Text style={styles.secondaryButtonText}>
                        {idUploaded ? "ID added" : "Add ID"}
                    </Text>
                </TouchableOpacity>
            </View>

            <View style={styles.card}>
                <Text style={styles.cardTitle}>Proof of address</Text>
                <Text style={styles.cardDescription}>
                    Upload proof of address to finalize review.
                </Text>
                <TouchableOpacity
                    style={styles.secondaryButton}
                    onPress={() => mockUpload("proof")}
                >
                    <Text style={styles.secondaryButtonText}>
                        {proofUploaded ? "Proof added" : "Add proof"}
                    </Text>
                </TouchableOpacity>
            </View>

            <TouchableOpacity style={styles.button} onPress={finishOnboarding}>
                <Text style={styles.buttonText}>Finish onboarding</Text>
            </TouchableOpacity>
        </View>
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: "#0b1220",
        padding: 24,
        justifyContent: "center",
    },
    title: {
        color: "#f8fafc",
        fontSize: 30,
        fontWeight: "700",
        marginBottom: 8,
    },
    subtitle: {
        color: "#94a3b8",
        fontSize: 16,
        marginBottom: 20,
    },
    card: {
        backgroundColor: "#111b2e",
        borderColor: "#334155",
        borderWidth: 1,
        borderRadius: 12,
        padding: 14,
        marginBottom: 12,
    },
    cardTitle: {
        color: "#e2e8f0",
        fontSize: 16,
        fontWeight: "700",
        marginBottom: 6,
    },
    cardDescription: {
        color: "#94a3b8",
        marginBottom: 10,
        lineHeight: 20,
    },
    secondaryButton: {
        borderColor: "#1d4ed8",
        borderWidth: 1,
        borderRadius: 10,
        paddingVertical: 10,
        alignItems: "center",
        backgroundColor: "#0d234b",
    },
    secondaryButtonText: {
        color: "#93c5fd",
        fontWeight: "700",
    },
    button: {
        marginTop: 10,
        backgroundColor: "#10b981",
        borderRadius: 12,
        paddingVertical: 14,
        alignItems: "center",
    },
    buttonText: {
        color: "#ffffff",
        fontWeight: "700",
        fontSize: 16,
    },
});

export default VoterOnboardingVerificationScreen;
