import React, { useEffect, useRef, useState } from "react";
import {
    ActivityIndicator,
    Animated,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View,
} from "react-native";
import { useAuthStore } from "../stores/authStore";
import { UserRole } from "../services/ApiClient";

interface LoginScreenProps {
    navigation: {
        navigate: (screen: string) => void;
    };
}

export const LoginScreen: React.FC<LoginScreenProps> = ({ navigation }) => {
    const { login, isLoading, error, setError } = useAuthStore();
    const [role, setRole] = useState<UserRole>("voter");
    const [email, setEmail] = useState("");
    const [password, setPassword] = useState("");

    const voterImageUri =
        "https://images.unsplash.com/photo-1541872703-74c5e44368f9?auto=format&fit=crop&w=1600&q=80";
    const politicianImageUri =
        "https://images.unsplash.com/photo-1529107386315-e1a2ed48a620?auto=format&fit=crop&w=1600&q=80";

    const voterOpacity = useRef(new Animated.Value(1)).current;
    const politicianOpacity = useRef(new Animated.Value(0)).current;

    useEffect(() => {
        const isPolitician = role === "politician";

        Animated.parallel([
            Animated.timing(voterOpacity, {
                toValue: isPolitician ? 0 : 1,
                duration: 380,
                useNativeDriver: true,
            }),
            Animated.timing(politicianOpacity, {
                toValue: isPolitician ? 1 : 0,
                duration: 380,
                useNativeDriver: true,
            }),
        ]).start();
    }, [role, politicianOpacity, voterOpacity]);

    const handleLogin = async () => {
        setError(null);

        if (!email.trim() || !password.trim()) {
            setError("Email and password are required.");
            return;
        }

        await login(role, email.trim(), password);
    };

    return (
        <View style={styles.backgroundImage}>
            <Animated.Image
                source={{ uri: voterImageUri }}
                style={[styles.backgroundLayer, { opacity: voterOpacity }]}
                resizeMode="cover"
            />
            <Animated.Image
                source={{ uri: politicianImageUri }}
                style={[styles.backgroundLayer, { opacity: politicianOpacity }]}
                resizeMode="cover"
            />
            <View style={styles.overlay}>
                <View style={styles.brandingWrap}>
                    <Text style={styles.brandWordmark}>U9itus</Text>
                    <Text style={styles.brandTagline}>Dial4Dough Platform</Text>
                </View>
                <View style={styles.container}>
                    <Text style={styles.title}>Welcome back</Text>
                    <Text style={styles.subtitle}>
                        Sign in as voter or politician
                    </Text>

                    <View style={styles.roleRow}>
                        <TouchableOpacity
                            style={[
                                styles.roleButton,
                                role === "voter" && styles.roleButtonActive,
                            ]}
                            onPress={() => setRole("voter")}
                            disabled={isLoading}
                        >
                            <Text
                                style={[
                                    styles.roleButtonText,
                                    role === "voter" &&
                                        styles.roleButtonTextActive,
                                ]}
                            >
                                Voter
                            </Text>
                        </TouchableOpacity>

                        <TouchableOpacity
                            style={[
                                styles.roleButton,
                                role === "politician" &&
                                    styles.roleButtonActive,
                            ]}
                            onPress={() => setRole("politician")}
                            disabled={isLoading}
                        >
                            <Text
                                style={[
                                    styles.roleButtonText,
                                    role === "politician" &&
                                        styles.roleButtonTextActive,
                                ]}
                            >
                                Politician
                            </Text>
                        </TouchableOpacity>
                    </View>

                    <TextInput
                        style={styles.input}
                        placeholder="Email"
                        placeholderTextColor="#cbd5e1"
                        keyboardType="email-address"
                        autoCapitalize="none"
                        value={email}
                        onChangeText={setEmail}
                    />

                    <TextInput
                        style={styles.input}
                        placeholder="Password"
                        placeholderTextColor="#cbd5e1"
                        secureTextEntry
                        value={password}
                        onChangeText={setPassword}
                    />

                    {error ? (
                        <Text style={styles.errorText}>{error}</Text>
                    ) : null}

                    <TouchableOpacity
                        style={[
                            styles.button,
                            isLoading && styles.buttonDisabled,
                        ]}
                        onPress={handleLogin}
                        disabled={isLoading}
                    >
                        {isLoading ? (
                            <ActivityIndicator color="#ffffff" />
                        ) : (
                            <Text style={styles.buttonText}>Log In</Text>
                        )}
                    </TouchableOpacity>

                    <TouchableOpacity
                        style={styles.linkButton}
                        onPress={() => navigation.navigate("Register")}
                        disabled={isLoading}
                    >
                        <Text style={styles.linkText}>
                            Create a voter account
                        </Text>
                    </TouchableOpacity>
                </View>
            </View>
        </View>
    );
};

const styles = StyleSheet.create({
    backgroundImage: {
        flex: 1,
    },
    backgroundLayer: {
        ...StyleSheet.absoluteFillObject,
    },
    overlay: {
        flex: 1,
        backgroundColor: "rgba(2, 6, 23, 0.78)",
    },
    brandingWrap: {
        position: "absolute",
        top: 58,
        left: 24,
        right: 24,
        zIndex: 2,
    },
    brandWordmark: {
        color: "#ffffff",
        fontSize: 30,
        fontWeight: "800",
        letterSpacing: 0.6,
    },
    brandTagline: {
        color: "#cbd5e1",
        marginTop: 2,
        fontSize: 12,
        fontWeight: "600",
        textTransform: "uppercase",
        letterSpacing: 1.2,
    },
    container: {
        flex: 1,
        justifyContent: "center",
        padding: 24,
        paddingTop: 96,
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
    roleRow: {
        flexDirection: "row",
        marginBottom: 16,
        backgroundColor: "#111b2e",
        borderRadius: 12,
        padding: 4,
    },
    roleButton: {
        flex: 1,
        paddingVertical: 10,
        borderRadius: 9,
        alignItems: "center",
    },
    roleButtonActive: {
        backgroundColor: "#10b981",
    },
    roleButtonText: {
        color: "#94a3b8",
        fontWeight: "600",
    },
    roleButtonTextActive: {
        color: "#ffffff",
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
