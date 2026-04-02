import { PermissionsAndroid, Platform } from "react-native";
import { firebase } from "@react-native-firebase/app";
import messaging, {
    FirebaseMessagingTypes,
} from "@react-native-firebase/messaging";

class NotificationService {
    private foregroundUnsubscribe?: () => void;

    async initialize(): Promise<void> {
        if (firebase.apps.length === 0) {
            console.warn(
                "Firebase is not configured yet. Skipping notification initialization.",
            );
            return;
        }

        await this.requestPermission();

        // Register foreground notifications once at app bootstrap.
        if (!this.foregroundUnsubscribe) {
            this.foregroundUnsubscribe = messaging().onMessage(
                async (remoteMessage: FirebaseMessagingTypes.RemoteMessage) => {
                    console.log(
                        "FCM foreground message:",
                        remoteMessage.messageId,
                    );
                },
            );
        }

        try {
            const token = await messaging().getToken();
            if (token) {
                console.log("FCM token:", token);
            }
        } catch (error) {
            console.warn("Failed to fetch FCM token:", error);
        }
    }

    teardown(): void {
        if (this.foregroundUnsubscribe) {
            this.foregroundUnsubscribe();
            this.foregroundUnsubscribe = undefined;
        }
    }

    private async requestPermission(): Promise<boolean> {
        if (Platform.OS === "android" && Platform.Version >= 33) {
            const status = await PermissionsAndroid.request(
                PermissionsAndroid.PERMISSIONS.POST_NOTIFICATIONS,
            );

            if (status !== PermissionsAndroid.RESULTS.GRANTED) {
                return false;
            }
        }

        const authStatus = await messaging().requestPermission();

        return (
            authStatus === messaging.AuthorizationStatus.AUTHORIZED ||
            authStatus === messaging.AuthorizationStatus.PROVISIONAL
        );
    }
}

export default new NotificationService();
