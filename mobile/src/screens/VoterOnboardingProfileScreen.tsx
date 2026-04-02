import React, { useState } from "react";
import {
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View,
} from "react-native";

interface VoterOnboardingProfileScreenProps {
    navigation: {
        navigate: (screen: string) => void;
    };
}

export const VoterOnboardingProfileScreen: React.FC<
    VoterOnboardingProfileScreenProps
> = ({ navigation }) => {
    const [city, setCity] = useState("");
    const [state, setState] = useState("");
    const [zipCode, setZipCode] = useState("");

    return (
        <View style={styles.container}>
            <Text style={styles.title}>Profile setup</Text>
            <Text style={styles.subtitle}>
                Add the basics so campaigns can be matched to your local area.
            </Text>

            <TextInput
                style={styles.input}
                placeholder="City"
                placeholderTextColor="#94a3b8"
                value={city}
                onChangeText={setCity}
            />
            <View style={styles.row}>
                <TextInput
                    style={[styles.input, styles.rowInput]}
                    placeholder="State"
                    placeholderTextColor="#94a3b8"
                    maxLength={2}
                    autoCapitalize="characters"
                    value={state}
                    onChangeText={setState}
                />
                <TextInput
                    style={[styles.input, styles.rowInput]}
                    placeholder="ZIP"
                    placeholderTextColor="#94a3b8"
                    keyboardType="number-pad"
                    value={zipCode}
                    onChangeText={setZipCode}
                />
            </View>

            <TouchableOpacity
                style={styles.button}
                onPress={() =>
                    navigation.navigate("VoterOnboardingVerification")
                }
            >
                <Text style={styles.buttonText}>Continue</Text>
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
    row: {
        flexDirection: "row",
        gap: 10,
    },
    rowInput: {
        flex: 1,
    },
    button: {
        marginTop: 8,
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

export default VoterOnboardingProfileScreen;
