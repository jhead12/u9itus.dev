import React, { useState } from "react";
import {
    View,
    Text,
    StyleSheet,
    TouchableOpacity,
    TextInput,
    ActivityIndicator,
    Alert,
    ScrollView,
    Platform,
} from "react-native";
import { CameraIcon, UploadIcon } from "./Icons";
import VideoCaptureService from "../services/VideoCaptureService";
import ApiClient from "../services/ApiClient";

interface VideoQuestionFormProps {
    token: string; // Watch token from backend
    campaignTitle: string;
    politicianName: string;
    onSubmitSuccess?: () => void;
    onCancel?: () => void;
}

export const VideoQuestionForm: React.FC<VideoQuestionFormProps> = ({
    token,
    campaignTitle,
    politicianName,
    onSubmitSuccess,
    onCancel,
}) => {
    const [selectedVideoPath, setSelectedVideoPath] = useState<string | null>(
        null,
    );
    const [caption, setCaption] = useState("");
    const [isUploading, setIsUploading] = useState(false);
    const [uploadProgress, setUploadProgress] = useState(0);

    const handleCameraCapture = async () => {
        try {
            const hasPermission =
                await VideoCaptureService.requestCameraPermission();
            if (!hasPermission) {
                Alert.alert(
                    "Permission Denied",
                    "Camera permission is required to record videos.",
                );
                return;
            }

            // In a real implementation, you'd open the native camera here
            // For now, this is a placeholder
            Alert.alert(
                "Camera",
                "Native camera integration coming in next phase",
            );
        } catch (error) {
            Alert.alert("Error", "Failed to open camera");
            console.error("Camera error:", error);
        }
    };

    const handleSelectFromGallery = async () => {
        try {
            const video = await VideoCaptureService.getVideoFromCameraRoll();
            if (video) {
                setSelectedVideoPath(video.path);
            }
        } catch (error) {
            Alert.alert("Error", "Failed to select video");
            console.error("Gallery error:", error);
        }
    };

    const handleSubmit = async () => {
        if (!selectedVideoPath) {
            Alert.alert("Error", "Please select a video first");
            return;
        }

        if (caption.length > 500) {
            Alert.alert("Error", "Caption must be 500 characters or less");
            return;
        }

        try {
            setIsUploading(true);
            setUploadProgress(10);

            // Save to temp location
            const tempPath =
                await VideoCaptureService.saveTempVideo(selectedVideoPath);
            setUploadProgress(30);

            // Upload video
            const response = await ApiClient.uploadVideoQuestion(token, {
                videoPath: tempPath,
                caption: caption || undefined,
            });

            setUploadProgress(90);

            if (response.success) {
                setUploadProgress(100);
                Alert.alert(
                    "Success",
                    "Your video question has been submitted!",
                );

                // Cleanup
                await VideoCaptureService.deleteTempVideo(tempPath);

                // Reset form
                setSelectedVideoPath(null);
                setCaption("");
                onSubmitSuccess?.();
            } else {
                Alert.alert(
                    "Error",
                    response.message || "Failed to submit video question",
                );
            }
        } catch (error) {
            const errorMsg =
                error instanceof Error ? error.message : "Upload failed";
            Alert.alert("Error", errorMsg);
            console.error("Upload error:", error);
        } finally {
            setIsUploading(false);
            setUploadProgress(0);
        }
    };

    const handleCancel = () => {
        if (selectedVideoPath) {
            Alert.alert("Cancel?", "Your video selection will be cleared.", [
                { text: "Keep Recording", onPress: () => {} },
                {
                    text: "Discard",
                    onPress: () => {
                        setSelectedVideoPath(null);
                        setCaption("");
                        onCancel?.();
                    },
                    style: "destructive",
                },
            ]);
        } else {
            onCancel?.();
        }
    };

    return (
        <ScrollView style={styles.container}>
            <View style={styles.header}>
                <Text style={styles.title}>Ask a Video Question</Text>
                <Text style={styles.subtitle}>
                    Send to{" "}
                    <Text style={styles.politicianName}>{politicianName}</Text>
                </Text>
            </View>

            {/* Video Selection Section */}
            <View style={styles.section}>
                <Text style={styles.sectionTitle}>📹 Video Question</Text>

                {!selectedVideoPath ? (
                    <>
                        <TouchableOpacity
                            style={styles.videoButton}
                            onPress={handleCameraCapture}
                            disabled={isUploading}
                        >
                            <CameraIcon size={32} color="#10b981" />
                            <Text style={styles.videoButtonText}>
                                Record with Camera
                            </Text>
                        </TouchableOpacity>

                        <Text style={styles.divider}>or</Text>

                        <TouchableOpacity
                            style={styles.videoButton}
                            onPress={handleSelectFromGallery}
                            disabled={isUploading}
                        >
                            <UploadIcon size={32} color="#10b981" />
                            <Text style={styles.videoButtonText}>
                                Select from Gallery
                            </Text>
                        </TouchableOpacity>

                        <Text style={styles.hint}>
                            MP4, WebM, or MOV · Max 50MB · Any length
                        </Text>
                    </>
                ) : (
                    <View style={styles.selectedVideo}>
                        <Text style={styles.selectedVideoText}>
                            ✓ Video selected (
                            {VideoCaptureService.formatFileSize(0)})
                        </Text>
                        <TouchableOpacity
                            onPress={() => setSelectedVideoPath(null)}
                            disabled={isUploading}
                        >
                            <Text style={styles.changeVideoText}>
                                Change Video
                            </Text>
                        </TouchableOpacity>
                    </View>
                )}
            </View>

            {/* Caption Section */}
            <View style={styles.section}>
                <Text style={styles.sectionTitle}>📝 Optional Caption</Text>
                <TextInput
                    style={styles.captionInput}
                    placeholder="Add context to your video question..."
                    placeholderTextColor="#64748b"
                    value={caption}
                    onChangeText={setCaption}
                    maxLength={500}
                    multiline
                    numberOfLines={4}
                    editable={!isUploading}
                />
                <Text style={styles.captionCounter}>
                    {caption.length}/500 characters
                </Text>
            </View>

            {/* Upload Progress */}
            {isUploading && (
                <View style={styles.progressSection}>
                    <ActivityIndicator size="large" color="#10b981" />
                    <Text style={styles.progressText}>
                        Uploading... {uploadProgress}%
                    </Text>
                </View>
            )}

            {/* Action Buttons */}
            <View style={styles.buttonGroup}>
                <TouchableOpacity
                    style={[styles.button, styles.cancelButton]}
                    onPress={handleCancel}
                    disabled={isUploading}
                >
                    <Text style={styles.cancelButtonText}>Cancel</Text>
                </TouchableOpacity>

                <TouchableOpacity
                    style={[
                        styles.button,
                        styles.submitButton,
                        (!selectedVideoPath || isUploading) &&
                            styles.disabledButton,
                    ]}
                    onPress={handleSubmit}
                    disabled={!selectedVideoPath || isUploading}
                >
                    {isUploading ? (
                        <ActivityIndicator size="small" color="#0f172a" />
                    ) : (
                        <Text style={styles.submitButtonText}>
                            Submit Video
                        </Text>
                    )}
                </TouchableOpacity>
            </View>

            <Text style={styles.disclaimer}>
                Your question will be emailed to the campaign team and shown in
                their analytics.
            </Text>
        </ScrollView>
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: "#0f172a",
        padding: 16,
    },
    header: {
        marginBottom: 24,
    },
    title: {
        fontSize: 24,
        fontWeight: "bold",
        color: "#ffffff",
        marginBottom: 4,
    },
    subtitle: {
        fontSize: 14,
        color: "#94a3b8",
    },
    politicianName: {
        color: "#10b981",
        fontWeight: "600",
    },
    section: {
        marginBottom: 24,
        backgroundColor: "#1e293b",
        borderRadius: 12,
        padding: 16,
        borderWidth: 1,
        borderColor: "#334155",
    },
    sectionTitle: {
        fontSize: 14,
        fontWeight: "600",
        color: "#cbd5e1",
        marginBottom: 12,
    },
    videoButton: {
        backgroundColor: "#0f172a",
        borderRadius: 8,
        padding: 20,
        alignItems: "center",
        marginBottom: 12,
        borderWidth: 2,
        borderColor: "#10b981",
    },
    videoButtonText: {
        color: "#10b981",
        fontSize: 14,
        fontWeight: "600",
        marginTop: 8,
    },
    divider: {
        textAlign: "center",
        color: "#64748b",
        marginVertical: 12,
        fontSize: 12,
    },
    hint: {
        fontSize: 12,
        color: "#64748b",
        textAlign: "center",
        marginTop: 12,
    },
    selectedVideo: {
        backgroundColor: "#064e3b",
        borderRadius: 8,
        padding: 12,
        flexDirection: "row",
        justifyContent: "space-between",
        alignItems: "center",
    },
    selectedVideoText: {
        color: "#10b981",
        fontSize: 14,
        fontWeight: "500",
    },
    changeVideoText: {
        color: "#10b981",
        fontSize: 12,
        fontWeight: "600",
        textDecorationLine: "underline",
    },
    captionInput: {
        backgroundColor: "#0f172a",
        borderRadius: 8,
        borderWidth: 1,
        borderColor: "#334155",
        color: "#ffffff",
        padding: 12,
        fontSize: 14,
        marginBottom: 8,
        textAlignVertical: "top",
        minHeight: 100,
    },
    captionCounter: {
        fontSize: 12,
        color: "#64748b",
        textAlign: "right",
    },
    progressSection: {
        marginBottom: 24,
        alignItems: "center",
        paddingVertical: 20,
    },
    progressText: {
        color: "#10b981",
        fontSize: 14,
        marginTop: 12,
        fontWeight: "500",
    },
    buttonGroup: {
        flexDirection: "row",
        gap: 12,
        marginBottom: 16,
    },
    button: {
        flex: 1,
        paddingVertical: 12,
        borderRadius: 8,
        alignItems: "center",
        justifyContent: "center",
    },
    cancelButton: {
        backgroundColor: "#334155",
        borderWidth: 1,
        borderColor: "#475569",
    },
    cancelButtonText: {
        color: "#cbd5e1",
        fontSize: 14,
        fontWeight: "600",
    },
    submitButton: {
        backgroundColor: "#10b981",
    },
    submitButtonText: {
        color: "#0f172a",
        fontSize: 14,
        fontWeight: "600",
    },
    disabledButton: {
        opacity: 0.5,
    },
    disclaimer: {
        fontSize: 11,
        color: "#64748b",
        textAlign: "center",
        marginBottom: 20,
        fontStyle: "italic",
    },
});
