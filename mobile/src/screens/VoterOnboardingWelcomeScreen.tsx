import React from "react";
import { StyleSheet, Text, TouchableOpacity, View } from "react-native";

interface VoterOnboardingWelcomeScreenProps {
    navigation: {
        navigate: (screen: string) => void;
    };
}

export const VoterOnboardingWelcomeScreen: React.FC<
    VoterOnboardingWelcomeScreenProps
> = ({ navigation }) => {
    return (
        <View style={styles.container}>
            <Text style={styles.title}>Welcome to U9itus</Text>
            <Text style={styles.subtitle}>
                Complete a short onboarding so your voter profile can be
                reviewed.
            </Text>

            <View style={styles.card}>
                <Text style={styles.cardTitle}>What we will collect</Text>
                <Text style={styles.cardItem}>1. Basic location details</Text>
                <Text style={styles.cardItem}>
                    2. Voter verification details
                </Text>
                <Text style={styles.cardItem}>
                    3. Optional profile completion
                </Text>
            </View>

            <TouchableOpacity
                style={styles.button}
                onPress={() => navigation.navigate("VoterOnboardingProfile")}
            >
                <Text style={styles.buttonText}>Start onboarding</Text>
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
        borderRadius: 14,
        padding: 16,
        marginBottom: 20,
    },
    cardTitle: {
        color: "#e2e8f0",
        fontSize: 16,
        fontWeight: "700",
        marginBottom: 10,
    },
    cardItem: {
        color: "#cbd5e1",
        fontSize: 14,
        marginBottom: 6,
    },
    button: {
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

export default VoterOnboardingWelcomeScreen;
