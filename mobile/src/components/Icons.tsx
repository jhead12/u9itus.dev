import React from 'react';
import { View, Text, StyleSheet } from 'react-native';

interface IconProps {
  size?: number;
  color?: string;
}

export const CameraIcon: React.FC<IconProps> = ({ size = 24, color = '#ffffff' }) => (
  <Text style={{ fontSize: size, color }}>📷</Text>
);

export const UploadIcon: React.FC<IconProps> = ({ size = 24, color = '#ffffff' }) => (
  <Text style={{ fontSize: size, color }}>📤</Text>
);

export const PlayIcon: React.FC<IconProps> = ({ size = 24, color = '#ffffff' }) => (
  <Text style={{ fontSize: size, color }}>▶️</Text>
);

export const CheckIcon: React.FC<IconProps> = ({ size = 24, color = '#ffffff' }) => (
  <Text style={{ fontSize: size, color }}>✓</Text>
);

export const ErrorIcon: React.FC<IconProps> = ({ size = 24, color = '#ef4444' }) => (
  <Text style={{ fontSize: size, color }}>⚠️</Text>
);

export const LoadingIcon: React.FC<IconProps> = ({ size = 24, color = '#ffffff' }) => (
  <Text style={{ fontSize: size, color }}>⏳</Text>
);
