import React, { useState } from "react";
import {
    ActivityIndicator,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View,
} from "react-native";
import { useAuthStore } from "../stores/authStore";

interface RegisterScreenProps {
    navigation: {
        navigate: (screen: string) => void;
    };
}

export const RegisterScreen: React.FC<RegisterScreenProps> = ({
    navigation,
}) => {
    const { register, isLoading, error, setError } = useAuthStore();
    const [fullName, setFullName] = useState("");
    const [email, setEmail] = useState("");
    const [password, setPassword] = useState("");
    const [confirmPassword, setConfirmPassword] = useState("");
    const [isRegisteredToVote, setIsRegisteredToVote] = useState<
        boolean | null
    >(null);

    const handleRegister = async () => {
        setError(null);

        if (!fullName.trim() || !email.trim() || !password.trim()) {
            setError("Name, email, and password are required.");
            return;
        }

        if (password !== confirmPassword) {
            setError("Passwords do not match.");
            return;
        }

        if (isRegisteredToVote === null) {
            setError("Please answer whether you are registered to vote.");
            return;
        }

        await register(
            email.trim(),
            password,
            fullName.trim(),
            isRegisteredToVote,
        );
    };

    return (
        <View style={styles.container}>
            <Text style={styles.title}>Create account</Text>
            <Text style={styles.subtitle}>Register as a voter</Text>

            <TextInput
                style={styles.input}
                placeholder="Full name"
                placeholderTextColor="#94a3b8"
                value={fullName}
                onChangeText={setFullName}
            />

            <TextInput
                style={styles.input}
                placeholder="Email"
                placeholderTextColor="#94a3b8"
                keyboardType="email-address"
                autoCapitalize="none"
                value={email}
                onChangeText={setEmail}
            />

            <TextInput
                style={styles.input}
                placeholder="Password"
                placeholderTextColor="#94a3b8"
                secureTextEntry
                value={password}
                onChangeText={setPassword}
            />

            <TextInput
                style={styles.input}
                placeholder="Confirm password"
                placeholderTextColor="#94a3b8"
                secureTextEntry
                value={confirmPassword}
                onChangeText={setConfirmPassword}
            />

            <Text style={styles.questionLabel}>
                Are you registered to vote?
            </Text>
            <View style={styles.choiceRow}>
                <TouchableOpacity
                    style={[
                        styles.choiceButton,
                        isRegisteredToVote === true &&
                            styles.choiceButtonActive,
                    ]}
                    onPress={() => setIsRegisteredToVote(true)}
                    disabled={isLoading}
                >
                    <Text
                        style={[
                            styles.choiceButtonText,
                            isRegisteredToVote === true &&
                                styles.choiceButtonTextActive,
                        ]}
                    >
                        Yes
                    </Text>
                </TouchableOpacity>

                <TouchableOpacity
                    style={[
                        styles.choiceButton,
                        isRegisteredToVote === false &&
                            styles.choiceButtonActive,
                    ]}
                    onPress={() => setIsRegisteredToVote(false)}
                    disabled={isLoading}
                >
                    <Text
                        style={[
                            styles.choiceButtonText,
                            isRegisteredToVote === false &&
                                styles.choiceButtonTextActive,
                        ]}
                    >
                        No
                    </Text>
                </TouchableOpacity>
            </View>

            {error ? <Text style={styles.errorText}>{error}</Text> : null}

            <TouchableOpacity
                style={[styles.button, isLoading && styles.buttonDisabled]}
                onPress={handleRegister}
                disabled={isLoading}
            >
                {isLoading ? (
                    <ActivityIndicator color="#ffffff" />
                ) : (
                    <Text style={styles.buttonText}>Create Account</Text>
                )}
            </TouchableOpacity>

            <TouchableOpacity
                style={styles.linkButton}
                onPress={() => navigation.navigate("Login")}
                disabled={isLoading}
            >
                <Text style={styles.linkText}>
                    Already have an account? Log in
                </Text>
            </TouchableOpacity>
        </View>
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
        justifyContent: "center",
        padding: 24,
        backgroundColor: "#0b1220",
    },
    title: {
        fontSize: 30,
        fontWeight: "700",
        color: "#f8fafc",
        marginBottom: 8,
    },
    subtitle: {
        fontSize: 16,
        color: "#94a3b8",
        marginBottom: 24,
    },
    input: {
        backgroundColor: "#111b2e",
        borderColor: "#334155",
        borderWidth: 1,
        borderRadius: 12,
        color: "#f8fafc",
        paddingHorizontal: 14,
        paddingVertical: 12,
        marginBottom: 12,
    },
    questionLabel: {
        color: "#cbd5e1",
        fontSize: 14,
        fontWeight: "600",
        marginBottom: 8,
        marginTop: 2,
    },
    choiceRow: {
        flexDirection: "row",
        gap: 10,
        marginBottom: 12,
    },
    choiceButton: {
        flex: 1,
        borderWidth: 1,
        borderColor: "#334155",
        backgroundColor: "#111b2e",
        borderRadius: 10,
        paddingVertical: 10,
        alignItems: "center",
    },
    choiceButtonActive: {
        borderColor: "#10b981",
        backgroundColor: "#063b2f",
    },
    choiceButtonText: {
        color: "#cbd5e1",
        fontWeight: "700",
    },
    choiceButtonTextActive: {
        color: "#34d399",
    },
    errorText: {
        color: "#fb7185",
        marginBottom: 12,
    },
    button: {
        backgroundColor: "#10b981",
        borderRadius: 12,
        paddingVertical: 14,
        alignItems: "center",
        marginTop: 4,
    },
    buttonDisabled: {
        opacity: 0.7,
    },
    buttonText: {
        color: "#ffffff",
        fontSize: 16,
        fontWeight: "700",
    },
    linkButton: {
        marginTop: 16,
        alignItems: "center",
    },
    linkText: {
        color: "#7dd3fc",
        fontSize: 14,
        fontWeight: "600",
    },
});
