import React, { useEffect } from "react";
import { DarkTheme, NavigationContainer } from "@react-navigation/native";
import { createStackNavigator } from "@react-navigation/stack";
import { ActivityIndicator, View } from "react-native";
import { SafeAreaProvider } from "react-native-safe-area-context";
import { useAuthStore } from "../stores/authStore";
import { PoliticianProfileScreen } from "../screens/PoliticianProfileScreen";
import { AdViewingRoomScreen } from "../screens/AdViewingRoomScreen";
import { CreateCampaignScreen } from "../screens/CreateCampaignScreen";
import { LoginScreen } from "../screens/LoginScreen";
import { RegisterScreen } from "../screens/RegisterScreen";
import { VoterOnboardingWelcomeScreen } from "../screens/VoterOnboardingWelcomeScreen";
import { VoterOnboardingProfileScreen } from "../screens/VoterOnboardingProfileScreen";
import { VoterOnboardingVerificationScreen } from "../screens/VoterOnboardingVerificationScreen";

const Stack = createStackNavigator();

const navTheme = {
    ...DarkTheme,
    colors: {
        ...DarkTheme.colors,
        background: "#020617",
        card: "#0f172a",
        text: "#ffffff",
        border: "#1e293b",
        primary: "#10b981",
    },
};

/**
 * Root Navigator for U9itus Mobile App
 *
 * Handles:
 * - Authentication flow (login/register vs authenticated)
 * - Main app navigation
 * - Campaign viewing
 * - Video question submission
 */
export const RootNavigator: React.FC = () => {
    const {
        isAuthenticated,
        isLoading,
        restoreToken,
        role,
        hasCompletedVoterOnboarding,
    } = useAuthStore();

    useEffect(() => {
        restoreToken();
    }, []);

    if (isLoading) {
        return (
            <SafeAreaProvider>
                <SplashScreen />
            </SafeAreaProvider>
        );
    }

    return (
        <SafeAreaProvider>
            <NavigationContainer theme={navTheme}>
                <Stack.Navigator
                    screenOptions={{
                        headerShown: true,
                        cardStyle: {
                            backgroundColor: "#020617",
                        },
                        headerStyle: {
                            backgroundColor: "#0f172a",
                        },
                        headerTintColor: "#10b981",
                        headerTitleStyle: {
                            color: "#ffffff",
                            fontSize: 18,
                            fontWeight: "bold",
                        },
                    }}
                >
                    {isAuthenticated ? (
                        // Authenticated Stack
                        <Stack.Group>
                            {role === "voter" &&
                            !hasCompletedVoterOnboarding ? (
                                <>
                                    <Stack.Screen
                                        name="VoterOnboardingWelcome"
                                        component={VoterOnboardingWelcomeScreen}
                                        options={{
                                            title: "Voter Onboarding",
                                        }}
                                    />
                                    <Stack.Screen
                                        name="VoterOnboardingProfile"
                                        component={VoterOnboardingProfileScreen}
                                        options={{
                                            title: "Profile Setup",
                                        }}
                                    />
                                    <Stack.Screen
                                        name="VoterOnboardingVerification"
                                        component={
                                            VoterOnboardingVerificationScreen
                                        }
                                        options={{
                                            title: "Verification",
                                        }}
                                    />
                                </>
                            ) : role === "voter" ? (
                                <Stack.Screen
                                    name="AdViewingRoom"
                                    component={AdViewingRoomScreen}
                                    options={{
                                        title: "Ad Viewing Room",
                                    }}
                                />
                            ) : (
                                <Stack.Screen
                                    name="CreateCampaign"
                                    component={CreateCampaignScreen}
                                    options={{
                                        title: "Create Campaign",
                                    }}
                                />
                            )}
                            <Stack.Screen
                                name="Home"
                                component={PoliticianProfileScreen}
                                options={{
                                    title: "Politician Profile",
                                }}
                            />
                            {/* More screens would be added here */}
                        </Stack.Group>
                    ) : (
                        // Auth Stack
                        <Stack.Group
                            screenOptions={{
                                headerShown: false,
                            }}
                        >
                            <Stack.Screen
                                name="Login"
                                component={LoginScreen}
                            />
                            <Stack.Screen
                                name="Register"
                                component={RegisterScreen}
                            />
                        </Stack.Group>
                    )}
                </Stack.Navigator>
            </NavigationContainer>
        </SafeAreaProvider>
    );
};

const SplashScreen: React.FC = () => {
    return (
        <View
            style={{
                flex: 1,
                alignItems: "center",
                justifyContent: "center",
                backgroundColor: "#0b1220",
            }}
        >
            <ActivityIndicator size="large" color="#10b981" />
        </View>
    );
};

export default RootNavigator;
