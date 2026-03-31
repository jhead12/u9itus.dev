# U9itus Mobile App — Phase 12 (React Native)

> Political Loyalty Ads Platform on iOS, Android & macOS

## 📱 Overview

U9itus Mobile brings the platform to native devices with:

- **Video question submission** from native camera or gallery
- **Politician profile browsing** with live video questions
- **Real-time notifications** via Firebase Cloud Messaging (FCM) & Apple Push Notifications (APN)
- **WebRTC integration** for live feed streaming (Phase 12.2)
- **Offline-first** token-based ad delivery with background sync
- **Biometric auth** (Face ID, Touch ID, fingerprint)

## 🏗️ Architecture

```
mobile/
├── src/
│   ├── components/        # Reusable UI components (VideoQuestionForm, Icons, etc.)
│   ├── screens/          # Full-screen containers (PoliticianProfileScreen, etc.)
│   ├── services/         # API client, video capture, storage
│   ├── stores/           # Zustand state management (auth, campaigns)
│   ├── navigation/       # React Navigation setup
│   ├── types/            # TypeScript type definitions
│   ├── utils/            # Helper functions
│   ├── hooks/            # Custom React hooks
│   └── App.tsx           # Root component
├── index.ts              # App entry point
├── package.json          # Dependencies
├── metro.config.js       # Metro bundler config
├── tsconfig.json         # TypeScript config
└── README.md
```

## 🚀 Getting Started

### Prerequisites

- **Node.js** ≥ 18.0.0
- **npm** ≥ 9.0.0
- **React Native CLI** (`npm install -g react-native`)
- **Xcode** (iOS development on macOS)
- **Android Studio** (Android development)

### Installation

```bash
# Navigate to mobile directory
cd mobile

# Install dependencies
npm install

# Link native modules
npm run pods  # macOS only for iOS pods

# Start Metro bundler
npm start
```

### Run on Device/Emulator

```bash
# iOS
npm run ios

# Android
npm run android

# macOS
npm run macos
```

### Recommended Startup Process (iOS)

Use this flow for the most reliable startup in development:

```bash
# 1) From mobile root, start Metro with a clean cache
cd mobile
npm start -- --reset-cache
```

```bash
# 2) In a second terminal, run on simulator explicitly
cd mobile
npx react-native run-ios --simulator "iPhone 16 Pro"
```

Notes:

- `npm run ios` may target a connected physical iPhone first.
- If you want a real device build from CLI, install `ios-deploy` first:

```bash
brew install ios-deploy
```

### First-Run iOS Native Reset (when Pods/build files are stale)

```bash
cd mobile
rm -rf ios/Pods ios/Podfile.lock ios/build
cd ios && pod install
xcodebuild -workspace U9itusMobile.xcworkspace -scheme U9itusMobile -configuration Debug -sdk iphonesimulator -destination 'generic/platform=iOS Simulator' clean
```

## 📡 API Integration

### Base Configuration

The app communicates with the Laravel backend via `/api` endpoints:

```typescript
// src/services/ApiClient.ts
const baseURL = "http://localhost:8000/api"; // Development
const baseURL = "https://u9itus.dev/api"; // Production
```

### Authentication

- Uses **Laravel Sanctum tokens**
- Tokens stored in **AsyncStorage** (encrypted on device)
- Auto-refresh on 401 (Unauthorized)

```typescript
// src/stores/authStore.ts
const { token, voter, isAuthenticated } = useAuthStore();
```

## 🎥 Video Question Camera Integration

### Component: `VideoQuestionForm`

Located in `src/components/VideoQuestionForm.tsx`

**Props:**

- `token` (string) — Watch token from backend
- `campaignTitle` (string) — Campaign name for UI
- `politicianName` (string) — Politician name for UI
- `onSubmitSuccess?` (callback) — Called after successful upload
- `onCancel?` (callback) — Called when user cancels

**Features:**

- 📷 **Native camera** integration (via `react-native-camera`)
- 📤 **Gallery picker** for video selection
- 🎬 **Video preview** before upload
- 📊 **Upload progress** tracking
- 🎨 **Dark theme UI** (matches web app)

**Example Usage:**

```typescript
import { VideoQuestionForm } from '@/components/VideoQuestionForm';

<VideoQuestionForm
  token="watch_token_xyz"
  campaignTitle="Mayor 2026"
  politicianName="Jane Smith"
  onSubmitSuccess={() => showSuccessAlert()}
  onCancel={() => closeModal()}
/>
```

### Services

#### VideoCaptureService

```typescript
import VideoCaptureService from "@/services/VideoCaptureService";

// Request permissions
const granted = await VideoCaptureService.requestCameraPermission();

// Get video metadata
const metadata =
    await VideoCaptureService.getVideoMetadata("/path/to/video.mp4");

// Save to temp location
const tempPath = await VideoCaptureService.saveTempVideo("/original/path");

// Cleanup
await VideoCaptureService.deleteTempVideo(tempPath);
```

#### ApiClient

```typescript
import ApiClient from "@/services/ApiClient";

// Upload video question
const response = await ApiClient.uploadVideoQuestion(watchToken, {
    videoPath: "/path/to/video.mp4",
    caption: "Optional question text",
    sessionUuid: "session-uuid",
});

if (response.success) {
    console.log("Video submitted!");
}
```

## 🗂️ State Management (Zustand)

### useAuthStore

```typescript
import { useAuthStore } from "@/stores/authStore";

const { voter, token, isAuthenticated, register, login, logout } =
    useAuthStore();

// Register new voter
await useAuthStore.getState().register(email, password, fullName);

// Login
await useAuthStore.getState().login(email, password);

// Logout
await useAuthStore.getState().logout();
```

## 🖼️ Screens

### PoliticianProfileScreen

Displays:

- Politician avatar, name, office, district
- Bio section
- Statistics (campaigns count, video questions)
- List of video questions with embedded player
- "Ask a Video Question" button

**Props:**

```typescript
interface PoliticianProfileScreenProps {
    campaignId: number;
    route?: { params?: { campaignId: number } };
    navigation?: any;
}
```

## 📦 Dependencies (Key)

| Package                    | Purpose              | Version |
| -------------------------- | -------------------- | ------- |
| `react-native`             | Framework            | 0.73.0  |
| `@react-navigation/*`      | Navigation           | 6.x     |
| `axios`                    | HTTP client          | 1.6.0   |
| `zustand`                  | State management     | 4.4.1   |
| `react-native-camera`      | Camera access        | 4.2.1   |
| `react-native-video`       | Video playback       | 5.2.1   |
| `@react-native-firebase/*` | Push notifications   | 18.0.0  |
| `react-native-webrtc`      | WebRTC (Phase 12.2)  | 111.0.0 |
| `react-native-permissions` | Permissions handling | 3.10.0  |

## 🔧 Configuration

### Environment Variables

Create `.env` file in mobile root:

```env
API_URL=http://localhost:8000/api
FIREBASE_PROJECT_ID=u9itus-project
FIREBASE_API_KEY=xxxxx
```

### TypeScript Paths

Aliases configured in `tsconfig.json`:

```typescript
"@/*": ["src/*"]
"@components/*": ["src/components/*"]
"@screens/*": ["src/screens/*"]
// ... etc
```

## 🧪 Testing

```bash
# Unit tests
npm test

# Watch mode
npm run test:watch

# Linting
npm run lint

# Code formatting
npm run format
```

## 📱 Platform-Specific Code

Use platform extensions for device-specific implementations:

```typescript
// VideoQuestionForm.ios.tsx
// VideoQuestionForm.android.tsx
// VideoQuestionForm.macos.tsx
```

## 🚢 Building for Release

### iOS Production Build

```bash
cd ios
xcodebuild -workspace U9itusMobile.xcworkspace \
  -scheme U9itusMobile \
  -configuration Release \
  -derivedDataPath build
```

### Android Production Build

```bash
cd android
./gradlew assembleRelease
```

## 📱 App Store Deployment

### iOS (App Store)

1. Update version in `Info.plist`
2. Build in Xcode: Product → Archive
3. Validate & upload using Organizer

### Android (Google Play)

1. Generate signed APK/AAB in Android Studio
2. Upload to Google Play Console
3. Configure release notes & rollout

## 🐛 Troubleshooting

### "Metro bundler crash"

```bash
npm start -- --reset-cache
```

If port 8081 is stuck:

```bash
lsof -ti :8081 | xargs kill -9
```

Then restart Metro and re-run the simulator command.

### "Failed to install on device: ios-deploy command"

```bash
brew install ios-deploy
```

Or run on simulator explicitly:

```bash
npx react-native run-ios --simulator "iPhone 16 Pro"
```

### "Native module not found"

```bash
npm run pods        # iOS
cd android && ./gradlew clean  # Android
```

### "Permission denied (camera/storage)"

Ensure app has permissions in `AndroidManifest.xml` and `Info.plist`.

## 📖 Documentation

- [React Native Docs](https://reactnative.dev)
- [React Navigation](https://reactnavigation.org)
- [Zustand Docs](https://github.com/pmndrs/zustand)
- [U9itus Backend API](../DEVELOPMENT.md)

## 🔐 Security

- **Tokens:** Stored securely using AsyncStorage with device encryption
- **API:** All requests over HTTPS (production)
- **Permissions:** Minimum necessary permissions requested at runtime
- **Data:** No sensitive data stored unencrypted

## 🤝 Contributing

- Follow TypeScript + ESLint conventions
- Test all features on iOS & Android
- Document new components/screens
- Use meaningful commit messages

## 📄 License

PROPRIETARY — Head Enterprises

---

**Phase 12 Timeline:**

- ✅ Mobile app scaffolding & video question foundation
- 🔲 WebRTC integration for live feeds (Phase 12.2)
- 🔲 Push notifications (FCM/APN) (Phase 12.3)
- 🔲 Biometric authentication (Phase 12.4)
- 🔲 App Store & Play Store deployment (Phase 12.5)
