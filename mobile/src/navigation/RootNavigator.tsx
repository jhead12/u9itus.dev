import React, { useEffect } from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/stack';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { useAuthStore } from '@/stores/authStore';
import { PoliticianProfileScreen } from '@/screens/PoliticianProfileScreen';

const Stack = createNativeStackNavigator();

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
  const { isAuthenticated, isLoading, restoreToken } = useAuthStore();

  useEffect(() => {
    restoreToken();
  }, []);

  if (isLoading) {
    return (
      <SafeAreaProvider>
        <Stack.Navigator
          screenOptions={{
            headerShown: false,
          }}
        >
          <Stack.Screen
            name="Splash"
            component={SplashScreen}
          />
        </Stack.Navigator>
      </SafeAreaProvider>
    );
  }

  return (
    <SafeAreaProvider>
      <NavigationContainer>
        <Stack.Navigator
          screenOptions={{
            headerShown: true,
            headerStyle: {
              backgroundColor: '#0f172a',
            },
            headerTintColor: '#10b981',
            headerTitleStyle: {
              color: '#ffffff',
              fontSize: 18,
              fontWeight: 'bold',
            },
          }}
        >
          {isAuthenticated ? (
            // Authenticated Stack
            <Stack.Group>
              <Stack.Screen
                name="Home"
                component={PoliticianProfileScreen}
                options={{
                  title: 'Politician Profile',
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

/**
 * Placeholder Screens (to be implemented)
 */
const SplashScreen: React.FC = () => {
  return null;  // Use native splash screen
};

const LoginScreen: React.FC = () => {
  return null;  // To be implemented
};

const RegisterScreen: React.FC = () => {
  return null;  // To be implemented
};

export default RootNavigator;
