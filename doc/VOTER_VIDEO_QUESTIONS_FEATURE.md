# Voter Video Questions Feature - Phase 12 Mobile Branch

## Overview

Implemented a complete voter-to-politician video question system that allows voters to upload video messages alongside text questions on the campaign watch page. This feature enhances voter engagement and creates richer interaction data for politicians.

---

## Architecture

### Data Model

The feature extends the existing `voter_watch_reports` table with video support:

```
voter_watch_reports
├── id
├── voter_id (FK)
├── campaign_id (FK)
├── type: 'message' | 'issue'
├── message_type: 'text' | 'video' ← NEW
├── body: text (caption or question)
├── media_url: nullable ← NEW (video URL)
├── media_duration: nullable ← NEW (seconds)
├── status: 'open' | 'in_review' | 'resolved' | 'dismissed'
├── created_at / updated_at
```

### Relationships

- **Voter** → has many VoterWatchReport
- **VoterWatchReport** → belongs to Voter, Campaign, PoliticalCampaign

---

## Implementation Details

### 1. Database Migration

**File:** `database/migrations/2026_03_29_000001_add_video_questions_to_voter_watch_reports.php`

Adds three columns to existing table:

- `media_url` (text, nullable) - stores full URL to uploaded video
- `media_duration` (integer, nullable) - video length in seconds
- `message_type` (string, default='text') - distinguishes between text and video

⚠️ **Run migration:**

```bash
php artisan migrate
```

### 2. Model Enhancements

**File:** `app/Models/VoterWatchReport.php`

**New Helper Methods:**

- `isTextQuestion()` - is this a text question?
- `isVideoQuestion()` - is this a video question?
- `hasMedia()` - does it have an attached video?

**New Scopes:**

- `scopeTextQuestions()` - filters to text-only questions
- `scopeVideoQuestions()` - filters to video-only questions

**Example Usage:**

```php
// Get all video questions for a campaign
$videos = VoterWatchReport::videoQuestions()
    ->where('campaign_id', $campaign->id)
    ->get();

// Get open video questions
$open = VoterWatchReport::videoQuestions()
    ->where('status', 'open')
    ->get();
```

### 3. Controller: Video Upload Handler

**File:** `app/Http/Controllers/Standalone/VoterController.php`

**New Method:** `uploadVideoQuestion(Request $request, string $token)`

**Validates:**

- Video file: MP4, WebM, MOV (MIME types)
- File size: ≤ 50MB (configurable)
- Optional caption: max 500 characters

**Process:**

1. Extract video file and caption
2. Store video in `storage/app/voter-questions/{voter_id}/{campaign_id}/`
3. Extract video duration using ffprobe (if available)
4. Create VoterWatchReport with `message_type='video'`
5. Send notification email to politician

**Example Request:**

```bash
POST /voter/watch/{token}/video-question
Content-Type: multipart/form-data

video: <file.mp4>
body: "Why do you support this policy?"
view_session_uuid: "uuid-string"
```

**Example Handling (in VoterController):**

```php
public function uploadVideoQuestion(Request $request, string $token)
{
    $validated = $request->validate([
        'video'             => 'required|file|mimes:mp4,webm,quicktime|max:51200',
        'body'              => 'nullable|string|max:500',
        'view_session_uuid' => 'nullable|string|max:36',
    ]);
    // ... store video, create record, notify politician
}
```

### 4. Routes

**File:** `routes/standalone.php`

**New Route:**

```php
Route::post('/watch/{token}/video-question', [VoterController::class, 'uploadVideoQuestion'])
    ->name('voter.watch.video-question');
```

Requires:

- Valid watch token (confirms ad viewing session)
- Voter middleware authentication
- Multipart form-data encoding

### 5. UI: Voter Watch Page

**File:** `resources/views/standalone/voter/watch.blade.php`

**Enhanced Modal** with tabbed interface:

```
┌─────────────────────────────────────────┐
│ Ask a Question                          │
│ Send to [Politician Name]         [X]   │
├─────────────────────────────────────────┤
│ [📝 Text Question] [🎥 Video Question] │
├─────────────────────────────────────────┤
│                                         │
│ Tab 1 - Text Question:                  │
│ ├─ Textarea for question (1000 chars)  │
│ ├─ Cancel / Send buttons               │
│                                         │
│ Tab 2 - Video Question:                 │
│ ├─ File input (MP4/WebM, 50MB)        │
│ ├─ Caption textarea (optional, 500ch)  │
│ ├─ Cancel / Submit Video buttons       │
│                                         │
└─────────────────────────────────────────┘
```

**Features:**

- Alpine.js tab switching
- Real-time form submission via fetch API
- Error and success alerts
- Loading states during upload

---

## Display in Politician Analytics

**File:** `resources/views/standalone/politician/analytics/campaign.blade.php`

Voter questions section now displays:

- **Message Type Badges:**
    - 📝 Text: Gray badge
    - 🎥 Video: Blue badge

- **Video Questions Include:**
    - Embedded HTML5 video player (controls enabled)
    - Max height: 192px (responsive)
    - Duration metadata (if available)
    - Optional caption text below

- **Sorting & Filtering:**
    - Displayed chronologically (newest first)
    - Pagination (10 per page)
    - Status indicators (Open, In Review, Resolved, Dismissed)

**Example Display:**

```
Voter Questions
───────────────────────────────────────────
[Time: Mar 29, 2:15 PM] [🎥 Video] [Open]
[Video player with controls]
Duration: 45s
Caption text: "Why do you support..."
From: Jane Doe (jane@example.com)
───────────────────────────────────────────
[Time: Mar 29, 1:30 PM] [📝 Text] [Resolved]
"What is your education policy?"
From: John Smith (john@example.com)
```

---

## File Storage

**Location:** `storage/app/voter-questions/{voter_id}/{campaign_id}/`

**Example Path:**

```
storage/app/voter-questions/42/157/filename.mp4
```

**Configuration:**

```php
// config/filesystems.php (default disk)
'default' => env('FILESYSTEM_DISK', 'local'),

// For S3 in production
'disks' => [
    's3' => [
        'driver' => 's3',
        'bucket' => env('AWS_BUCKET'),
        // ...
    ],
]
```

---

## Email Notifications

When a voter submits a video question, politician receives:

**Subject:** `[U9itus] 🎥 Voter Video Question – [Campaign Title]`

**Body:**

```
A voter submitted a VIDEO QUESTION regarding your campaign "[Campaign Title]".

Question text:
[voter's caption text, if provided]

Video: [URL to uploaded video]
Sent by voter: [Full Name] ([Email])
Platform: U9itus
```

⚠️ **Note:** Text questions keep "Voter Question" subject; video questions use emoji (🎥) for easy filtering.

---

## Query Examples

### Fetch all video questions for a politician

```php
$videoQuestions = VoterWatchReport::query()
    ->videoQuestions()
    ->whereHas('campaign', fn($q) => $q->where('politician_id', $politician->id))
    ->with('voter', 'campaign')
    ->latest('created_at')
    ->paginate(15);
```

### Count text vs video for analytics

```php
$textCount = VoterWatchReport::textQuestions()
    ->where('campaign_id', $campaign->id)
    ->count();

$videoCount = VoterWatchReport::videoQuestions()
    ->where('campaign_id', $campaign->id)
    ->count();
```

### Get open video questions requiring response

```php
$open = VoterWatchReport::videoQuestions()
    ->where('status', 'open')
    ->where('campaign_id', $campaign->id)
    ->with('voter')
    ->latest()
    ->get();
```

---

## Configuration

All configurable defaults in `config/u9itus.php`:

```php
'video' => [
    'max_size_mb' => 50,  // Voter videos
    'formats' => ['mp4', 'webm', 'mov'],
    'storage_path' => 'voter-questions',
]
```

---

## Testing Checklist

- [ ] Migration runs without errors
- [ ] Voter can see "Video Question" tab on watch page
- [ ] Video file upload works (drag & drop + click)
- [ ] Optional caption saves correctly
- [ ] Video URL appears in database
- [ ] Politician receives email notification
- [ ] Video displays in analytics with player
- [ ] Duration metadata extracted (if ffprobe available)
- [ ] Status can be updated (open → resolved)
- [ ] Pagination works with mixed text/video questions

---

## Future Enhancements

- [ ] **Transcription:** Auto-generate closed captions from video audio
- [ ] **Reactions:** Allow politicians to react/respond with their own video
- [ ] **Compression:** Queue job to optimize video files before storage
- [ ] **Thumbnail:** Generate and display video thumbnail in listings
- [ ] **Analytics:** Track video play-through rate, pause points
- [ ] **Mobile Apps:** React Native video upload via camera or gallery
- [ ] **Live Streaming:** Extend to Phase 12 WebRTC video streams

---

## Troubleshooting

### Video upload fails with storage error

**Solution:** Verify `config/filesystems.php` disk is configured and writable:

```bash
php artisan storage:link
chmod -R 775 storage/app/voter-questions
```

### FFprobe not found (duration extraction fails)

**Solution:** Install ffmpeg (includes ffprobe):

```bash
# macOS
brew install ffmpeg

# Ubuntu/Debian
sudo apt-get install ffmpeg
```

If unavailable, duration will be `null` (non-blocking).

### Video not playing in politician dashboard

**Solution:** Check CORS headers if S3 is used:

```php
// config/aws.php
'cors' => [
    'AllowedOrigins' => [env('FRONTEND_URL')],
    'AllowedMethods' => ['GET'],
]
```

---

## Related Features

- **Phase 11:** Laravel Reverb WebSocket infrastructure (notification channels)
- **Phase 12 Mobile:** React Native video upload via camera + localStorage sync
- **Future:** WebRTC live streams, video transcription, AI moderation
