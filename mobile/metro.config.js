const {getDefaultConfig, mergeConfig} = require('@react-native/metro-config');

/**
 * Metro Bundler Configuration for U9itus Mobile App
 * 
 * Optimizations:
 * - Shared code reuse between iOS, Android, macOS
 * - WebRTC optimizations for live streaming (Phase 12)
 * - Video processing for bandwidth optimization
 */
const config = {
  project: {
    ios: {},
    android: {},
    macos: {},
  },
  transformer: {
    getTransformOptions: async () => ({
      transform: {
        experimentalImportSupport: false,
        inlineRequires: true,
      },
    }),
    // Enable platform-specific bundles
    minifierPath: 'metro-minify-terser',
  },
  resolver: {
    // Platform-specific resolution
    sourceExts: ['ios.js', 'android.js', 'macos.js', 'js', 'jsx', 'json', 'ts', 'tsx'],
    extraNodeModules: {
      'react-native-webrtc': require.resolve('react-native-webrtc'),
    },
  },
  // Cache configuration for faster builds
  cacheStores: [
    // First cache store
    new (require('metro').Cache)(),
  ],
};

module.exports = mergeConfig(getDefaultConfig(__dirname), config);
