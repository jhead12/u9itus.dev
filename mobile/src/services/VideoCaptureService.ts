import { CameraRoll } from '@react-native-camera-roll/camera-roll';
import { Platform, PermissionsAndroid, Alert } from 'react-native';
import RNFS from 'react-native-fs';
import { check, request, PERMISSIONS, RESULTS } from 'react-native-permissions';

/**
 * Video Capture Service
 * 
 * Handles:
 * - Camera permissions
 * - Video recording via native camera
 * - Camera roll selection
 * - Video metadata extraction
 */

export interface VideoCaptureResult {
  path: string;          // Local file path
  duration: number;      // Duration in seconds
  size: number;          // File size in bytes
  mimeType: string;      // video/mp4, etc.
}

class VideoCaptureService {
  /**
   * Request camera permissions
   */
  static async requestCameraPermission(): Promise<boolean> {
    try {
      if (Platform.OS === 'android') {
        const permission = await request(PERMISSIONS.ANDROID.CAMERA);
        return permission === RESULTS.GRANTED;
      } else if (Platform.OS === 'ios') {
        const permission = await request(PERMISSIONS.IOS.CAMERA);
        return permission === RESULTS.GRANTED;
      }
      return false;
    } catch (error) {
      console.error('Camera permission error:', error);
      return false;
    }
  }

  /**
   * Request photo library permissions
   */
  static async requestPhotoLibraryPermission(): Promise<boolean> {
    try {
      if (Platform.OS === 'android') {
        const permission = await request(PERMISSIONS.ANDROID.READ_EXTERNAL_STORAGE);
        return permission === RESULTS.GRANTED;
      } else if (Platform.OS === 'ios') {
        const permission = await request(PERMISSIONS.IOS.PHOTO_LIBRARY);
        return permission === RESULTS.GRANTED;
      }
      return false;
    } catch (error) {
      console.error('Photo library permission error:', error);
      return false;
    }
  }

  /**
   * Check if camera is available
   */
  static async isCameraAvailable(): Promise<boolean> {
    try {
      const permission = await check(
        Platform.OS === 'ios' 
          ? PERMISSIONS.IOS.CAMERA 
          : PERMISSIONS.ANDROID.CAMERA
      );
      return permission === RESULTS.GRANTED;
    } catch {
      return false;
    }
  }

  /**
   * Get video metadata (duration, size)
   */
  static async getVideoMetadata(videoPath: string): Promise<Partial<VideoCaptureResult>> {
    try {
      const stat = await RNFS.stat(videoPath);
      
      // For now, return size. Duration would require video_metadata library
      return {
        path: videoPath,
        size: stat.size,
        mimeType: 'video/mp4',
        // duration would be extracted server-side via ffprobe
      };
    } catch (error) {
      console.error('Failed to get video metadata:', error);
      return {};
    }
  }

  /**
   * Get video from camera roll
   * Requires photo library permission first
   */
  static async getVideoFromCameraRoll(): Promise<VideoCaptureResult | null> {
    try {
      const hasPermission = await this.requestPhotoLibraryPermission();
      if (!hasPermission) {
        Alert.alert('Permission Denied', 'Photos permission is required to select videos.');
        return null;
      }

      // Note: react-native-camera-roll API usage
      // This is a simplified mock - actual implementation depends on your camera setup
      console.log('Launching camera roll video picker...');
      
      // In a real implementation, you'd use native modules or react-native-document-picker
      return null;
    } catch (error) {
      console.error('Camera roll error:', error);
      return null;
    }
  }

  /**
   * Save video to temp cache for upload
   */
  static async saveTempVideo(sourceVideoPath: string): Promise<string> {
    try {
      const timestamp = Date.now();
      const tempPath = `${RNFS.CachesDirectoryPath}/video_question_${timestamp}.mp4`;
      
      await RNFS.copyFile(sourceVideoPath, tempPath);
      return tempPath;
    } catch (error) {
      console.error('Failed to save temp video:', error);
      throw error;
    }
  }

  /**
   * Clean up temp video file
   */
  static async deleteTempVideo(videoPath: string): Promise<void> {
    try {
      const exists = await RNFS.exists(videoPath);
      if (exists) {
        await RNFS.unlink(videoPath);
      }
    } catch (error) {
      console.error('Failed to delete temp video:', error);
    }
  }

  /**
   * Get available space in cache
   */
  static async getCacheSpace(): Promise<{ free: number; total: number }> {
    try {
      const diskFree = await RNFS.getFSInfo();
      return {
        free: diskFree.freeSpace,
        total: diskFree.totalSpace,
      };
    } catch (error) {
      console.error('Failed to get cache space:', error);
      return { free: 0, total: 0 };
    }
  }

  /**
   * Format file size for display
   */
  static formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }
}

export default VideoCaptureService;
