import React, { useEffect, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  ActivityIndicator,
  FlatList,
  TouchableOpacity,
  SafeAreaView,
  Image,
  SectionList,
} from 'react-native';
import ApiClient, { PoliticianProfile, VideoQuestion } from '@/services/ApiClient';
import { PlayIcon, CheckIcon, ErrorIcon } from '@/components/Icons';

interface PoliticianProfileScreenProps {
  campaignId: number;
  route?: {
    params?: {
      campaignId: number;
    };
  };
  navigation?: any;
}

export const PoliticianProfileScreen: React.FC<PoliticianProfileScreenProps> = ({
  campaignId: propsCampaignId,
  route,
}) => {
  const [politician, setPolitician] = useState<PoliticianProfile | null>(null);
  const [videoQuestions, setVideoQuestions] = useState<VideoQuestion[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [selectedVideo, setSelectedVideo] = useState<VideoQuestion | null>(null);
  const [error, setError] = useState<string | null>(null);

  const campaignId = propsCampaignId || route?.params?.campaignId;

  useEffect(() => {
    fetchPoliticianData();
  }, [campaignId]);

  const fetchPoliticianData = async () => {
    try {
      setIsLoading(true);
      setError(null);

      if (!campaignId) {
        setError('Campaign ID is required');
        return;
      }

      const [profileData, questionsData] = await Promise.all([
        ApiClient.getPoliticianProfile(campaignId),
        ApiClient.getVideoQuestions(campaignId),
      ]);

      if (!profileData) {
        setError('Failed to load politician profile');
        return;
      }

      setPolitician(profileData);
      setVideoQuestions(questionsData);
    } catch (err) {
      const errorMsg = err instanceof Error ? err.message : 'Failed to load data';
      setError(errorMsg);
      console.error('Profile fetch error:', err);
    } finally {
      setIsLoading(false);
    }
  };

  const handlePlayVideo = (video: VideoQuestion) => {
    setSelectedVideo(video);
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'open':
        return <PlayIcon color="#0ea5e9" />;
      case 'resolved':
        return <CheckIcon color="#10b981" />;
      default:
        return <ErrorIcon color="#f59e0b" />;
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'open':
        return '#0c4a6e';
      case 'resolved':
        return '#064e3b';
      case 'in_review':
        return '#5e3b1f';
      default:
        return '#334155';
    }
  };

  if (isLoading) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.centerContent}>
          <ActivityIndicator size="large" color="#10b981" />
          <Text style={styles.loadingText}>Loading politician profile...</Text>
        </View>
      </SafeAreaView>
    );
  }

  if (error) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.centerContent}>
          <ErrorIcon size={48} color="#ef4444" />
          <Text style={styles.errorText}>{error}</Text>
          <TouchableOpacity style={styles.retryButton} onPress={fetchPoliticianData}>
            <Text style={styles.retryButtonText}>Retry</Text>
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    );
  }

  if (!politician) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.centerContent}>
          <Text style={styles.notFoundText}>Politician not found</Text>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView style={styles.scrollView} showsVerticalScrollIndicator={false}>
        {/* Politician Header */}
        <View style={styles.header}>
          {politician.avatar_url && (
            <Image
              source={{ uri: politician.avatar_url }}
              style={styles.avatar}
              defaultSource={require('@/assets/placeholder-avatar.png')}
            />
          )}
          <View style={styles.headerInfo}>
            <Text style={styles.fullName}>{politician.full_name}</Text>
            <Text style={styles.office}>{politician.office}</Text>
            <Text style={styles.district}>{politician.governance_level} • {politician.district}</Text>
          </View>
        </View>

        {/* Bio */}
        {politician.bio && (
          <View style={styles.bioSection}>
            <Text style={styles.bioText}>{politician.bio}</Text>
          </View>
        )}

        {/* Stats */}
        <View style={styles.statsSection}>
          <View style={styles.stat}>
            <Text style={styles.statValue}>{politician.total_campaigns}</Text>
            <Text style={styles.statLabel}>Campaigns</Text>
          </View>
          <View style={styles.stat}>
            <Text style={styles.statValue}>{videoQuestions.length}</Text>
            <Text style={styles.statLabel}>Video Questions</Text>
          </View>
        </View>

        {/* Video Questions List */}
        <View style={styles.questionsSection}>
          <Text style={styles.sectionTitle}>🎥 Voter Video Questions ({videoQuestions.length})</Text>

          {videoQuestions.length === 0 ? (
            <View style={styles.emptyState}>
              <Text style={styles.emptyStateText}>No video questions yet.</Text>
              <Text style={styles.emptyStateHint}>Be the first to submit a question!</Text>
            </View>
          ) : (
            <FlatList
              data={videoQuestions}
              scrollEnabled={false}
              keyExtractor={(item) => item.id.toString()}
              renderItem={({ item }) => (
                <TouchableOpacity
                  style={[
                    styles.questionCard,
                    { borderLeftColor: getStatusColor(item.status) },
                  ]}
                  onPress={() => handlePlayVideo(item)}
                  activeOpacity={0.7}
                >
                  <View style={styles.questionHeader}>
                    <View style={styles.questionTitle}>
                      {getStatusIcon(item.status)}
                      <Text style={styles.voterName}>{item.voter.full_name}</Text>
                    </View>
                    <View style={[styles.statusBadge, { backgroundColor: getStatusColor(item.status) }]}>
                      <Text style={styles.statusBadgeText}>{item.status.toUpperCase()}</Text>
                    </View>
                  </View>

                  {item.body && (
                    <Text style={styles.questionCaption} numberOfLines={2}>
                      "{item.body}"
                    </Text>
                  )}

                  <View style={styles.questionMeta}>
                    <Text style={styles.questionDate}>
                      {new Date(item.created_at).toLocaleDateString ('en-US', {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric',
                      })}
                    </Text>
                    {item.media_duration && (
                      <Text style={styles.videoDuration}>
                        Duration: {Math.floor(item.media_duration / 60)}:{String(item.media_duration % 60).padStart(2, '0')}
                      </Text>
                    )}
                  </View>
                </TouchableOpacity>
              )}
            />
          )}
        </View>

        {/* Action Button */}
        <TouchableOpacity style={styles.askQuestionButton}>
          <Text style={styles.askQuestionButtonText}>🎥 Ask a Video Question</Text>
        </TouchableOpacity>
      </ScrollView>

      {/* Video Player Modal would go here in a full implementation */}
      {selectedVideo && (
        <View style={styles.videoModal}>
          <TouchableOpacity
            style={styles.closeButton}
            onPress={() => setSelectedVideo(null)}
          >
            <Text style={styles.closeButtonText}>✕</Text>
          </TouchableOpacity>
          <View style={styles.videoPlayer}>
            {/* Actual video player component would be integrated here */}
            <View style={styles.videoPlaceholder}>
              <PlayIcon size={64} color="#ffffff" />
              <Text style={styles.videoPlaceholderText}>
                Video from {selectedVideo.voter.full_name}
              </Text>
            </View>
          </View>
        </View>
      )}
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#0f172a',
  },
  scrollView: {
    flex: 1,
  },
  centerContent: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  loadingText: {
    color: '#94a3b8',
    fontSize: 14,
    marginTop: 12,
  },
  errorText: {
    color: '#ef4444',
    fontSize: 16,
    marginTop: 16,
    textAlign: 'center',
    paddingHorizontal: 24,
  },
  notFoundText: {
    color: '#94a3b8',
    fontSize: 14,
  },
  retryButton: {
    marginTop: 20,
    paddingHorizontal: 20,
    paddingVertical: 10,
    backgroundColor: '#10b981',
    borderRadius: 6,
  },
  retryButtonText: {
    color: '#0f172a',
    fontSize: 14,
    fontWeight: '600',
  },
  header: {
    flexDirection: 'row',
    padding: 16,
    borderBottomColor: '#1e293b',
    borderBottomWidth: 1,
  },
  avatar: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: '#1e293b',
    marginRight: 16,
  },
  headerInfo: {
    flex: 1,
    justifyContent: 'center',
  },
  fullName: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#ffffff',
    marginBottom: 4,
  },
  office: {
    fontSize: 14,
    color: '#10b981',
    fontWeight: '600',
    marginBottom: 2,
  },
  district: {
    fontSize: 12,
    color: '#94a3b8',
  },
  bioSection: {
    padding: 16,
    borderBottomColor: '#1e293b',
    borderBottomWidth: 1,
  },
  bioText: {
    color: '#cbd5e1',
    fontSize: 13,
    lineHeight: 20,
  },
  statsSection: {
    flexDirection: 'row',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderBottomColor: '#1e293b',
    borderBottomWidth: 1,
  },
  stat: {
    flex: 1,
    alignItems: 'center',
    paddingVertical: 12,
  },
  statValue: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#10b981',
  },
  statLabel: {
    fontSize: 11,
    color: '#94a3b8',
    marginTop: 4,
  },
  questionsSection: {
    padding: 16,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: '#ffffff',
    marginBottom: 12,
  },
  emptyState: {
    alignItems: 'center',
    paddingVertical: 32,
  },
  emptyStateText: {
    color: '#94a3b8',
    fontSize: 14,
    marginBottom: 4,
  },
  emptyStateHint: {
    color: '#64748b',
    fontSize: 12,
  },
  questionCard: {
    backgroundColor: '#1e293b',
    borderRadius: 8,
    padding: 12,
    marginBottom: 12,
    borderLeftWidth: 4,
  },
  questionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  questionTitle: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    flex: 1,
  },
  voterName: {
    color: '#cbd5e1',
    fontSize: 13,
    fontWeight: '500',
  },
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 4,
  },
  statusBadgeText: {
    fontSize: 10,
    fontWeight: '600',
    color: '#ffffff',
  },
  questionCaption: {
    color: '#94a3b8',
    fontSize: 12,
    marginBottom: 8,
    fontStyle: 'italic',
  },
  questionMeta: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  questionDate: {
    fontSize: 11,
    color: '#64748b',
  },
  videoDuration: {
    fontSize: 11,
    color: '#10b981',
    fontWeight: '500',
  },
  askQuestionButton: {
    marginHorizontal: 16,
    marginVertical: 16,
    backgroundColor: '#10b981',
    paddingVertical: 14,
    borderRadius: 8,
    alignItems: 'center',
  },
  askQuestionButtonText: {
    color: '#0f172a',
    fontSize: 14,
    fontWeight: '600',
  },
  videoModal: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(0, 0, 0, 0.9)',
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 100,
  },
  closeButton: {
    position: 'absolute',
    top: 16,
    right: 16,
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: 'rgba(255, 255, 255, 0.1)',
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 101,
  },
  closeButtonText: {
    color: '#ffffff',
    fontSize: 20,
    fontWeight: 'bold',
  },
  videoPlayer: {
    width: '90%',
    aspectRatio: 16 / 9,
    backgroundColor: '#0f172a',
    borderRadius: 8,
    overflow: 'hidden',
  },
  videoPlaceholder: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#1e293b',
  },
  videoPlaceholderText: {
    color: '#cbd5e1',
    fontSize: 14,
    marginTop: 12,
    textAlign: 'center',
  },
});
