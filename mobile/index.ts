import { AppRegistry } from "react-native";
import messaging, {
    FirebaseMessagingTypes,
} from "@react-native-firebase/messaging";
import App from "./src/App";
import { name as appName } from "./app.json";

messaging().setBackgroundMessageHandler(
    async (remoteMessage: FirebaseMessagingTypes.RemoteMessage) => {
        console.log("FCM background message:", remoteMessage.messageId);
    },
);

AppRegistry.registerComponent(appName, () => App);
