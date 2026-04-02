import { AppRegistry } from "react-native";
import { firebase } from "@react-native-firebase/app";
import messaging, {
    FirebaseMessagingTypes,
} from "@react-native-firebase/messaging";
import App from "./src/App";
import { name as appName } from "./app.json";

if (firebase.apps.length > 0) {
    messaging().setBackgroundMessageHandler(
        async (remoteMessage: FirebaseMessagingTypes.RemoteMessage) => {
            console.log("FCM background message:", remoteMessage.messageId);
        },
    );
} else {
    console.warn(
        "Firebase is not configured yet. Skipping background messaging handler.",
    );
}

AppRegistry.registerComponent(appName, () => App);
