import React from "react";
import { View, Text, StyleSheet } from "react-native";

export const AdViewingRoomScreen: React.FC = () => {
    return (
        <View style={styles.container}>
            <Text style={styles.title}>Ad Viewing Room</Text>
            <Text style={styles.subtitle}>
                This is the first page voter users see after login.
            </Text>
        </View>
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: "#020617",
        alignItems: "center",
        justifyContent: "center",
        paddingHorizontal: 24,
    },
    title: {
        color: "#f8fafc",
        fontSize: 26,
        fontWeight: "700",
        marginBottom: 10,
    },
    subtitle: {
        color: "#94a3b8",
        fontSize: 15,
        textAlign: "center",
        lineHeight: 22,
    },
});

export default AdViewingRoomScreen;