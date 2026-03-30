import React from "react";
import { StatusBar } from "react-native";
import RootNavigator from "@/navigation/RootNavigator";

/**
 * Main App Entry Point
 *
 * U9itus Mobile App - Phase 12
 * ─────────────────────────────────────────────────────
 *
 * Framework: React Native 0.73 + Metro Bundler
 * Platforms: iOS, Android, macOS
 *
 * Features:
 * - Voter authentication (Sanctum tokens)
 * - Campaign browsing
 * - Video question upload & submission
 * - Live politician profile viewing
 * - WebRTC integration (Phase 12.2)
 * - Push notifications (FCM/APN)
 */
const App: React.FC = () => {
    return (
        <>
            <StatusBar
                barStyle="light-content"
                backgroundColor="#0f172a"
                translucent={false}
            />
            <RootNavigator />
        </>
    );
};

export default App;
