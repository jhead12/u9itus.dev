# S3 Direct Upload & Background Transcoding Setup

This document describes the S3 direct upload and FFmpeg background transcoding infrastructure for campaign videos.

## Architecture Overview

**Problem:** Large video uploads (>100MB) block the web process and are prone to timeout on Railway's 15-minute request limit.

**Solution:** 
1. Browser uploads directly to S3 using presigned URLs
2. Backend queues `TranscodeS3VideoJob` for background processing
3. FFmpeg transcodes uploaded video to H.264 MP4 in background
4. Transcoded video is stored back to S3
5. Campaign record is updated with transcoded video URL

## Environment Configuration

Add these environment variables to your `.env` and Railway project settings:

```env
# AWS / S3 Configuration
AWS_ACCESS_KEY_ID=your_aws_key_id
AWS_SECRET_ACCESS_KEY=your_aws_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket-name
AWS_URL=https://your-bucket.s3.amazonaws.com  # Optional: CloudFront URL
AWS_ENDPOINT=                                   # Optional: For S3-compatible services

# FFmpeg Configuration
FFMPEG_BIN=/usr/bin/ffmpeg          # Path to ffmpeg binary (auto-detected if not set)
FFPROBE_BIN=/usr/bin/ffprobe        # Path to ffprobe binary (auto-detected if not set)
VIDEO_ENCODE_PRESET=medium          # fast, medium, slow (quality vs speed tradeoff)

# Queue Configuration
QUEUE_CONNECTION=database           # Use database queue (or redis for production)
DB_QUEUE_TABLE=jobs
```

## Dependencies

### PHP Packages
- `php-ffmpeg/php-ffmpeg:^1.0` — FFmpeg PHP wrapper
- `aws/aws-sdk-php:^3.0` — AWS SDK for S3 operations
- `league/flysystem-aws-s3-v3:^3.0` — Laravel Storage S3 driver

### System Packages
- `ffmpeg` — Video encoding/transcoding tool
- `ffprobe` — FFmpeg utility for extracting metadata

### Docker
The [Dockerfile](../../Dockerfile) already includes:
- FFmpeg system library installation
- Increased PHP upload limits (1 GB)
- proper timeout settings for large transcoding jobs

## API Endpoints

### Get S3 Upload Presigned URL
```
POST /politician/campaigns/{campaign}/s3-upload-url
```

**Request:**
```json
{
  "filename": "campaign_video.mp4",
  "content_type": "video/mp4"
}
```

**Response:**
```json
{
  "presigned_url": "https://bucket.s3.amazonaws.com/upload?...",
  "s3_path": "campaigns/123/uploads/1234567890-campaign_video.mp4",
  "expires_in": 1200
}
```

### Process S3 Uploaded Video
Called after successful S3 upload to validate and queue transcoding.

```
POST /politician/campaigns/{campaign}/process-s3-video
```

**Request:**
```json
{
  "s3_path": "campaigns/123/uploads/1234567890-campaign_video.mp4",
  "filename": "campaign_video.mp4",
  "file_size": 1073741824
}
```

**Response:**
- On success: Redirect with success message "Video uploaded! Your file is now being processed..."
- On error: Redirect with error message describing the issue

## Video Validation

### Duration Checks
- **Minimum:** 30 seconds (configurable: `MIN_VIDEO_DURATION`)
- **Maximum:** 300 seconds (configurable: `MAX_VIDEO_DURATION`)

If FFprobe is available:
- Extracted from uploaded video during S3 processing
- Stored as `media_duration` on campaign record

### File Size Limits
- **Maximum:** 1 GB (configurable: `MAX_VIDEO_SIZE_MB=1024`)
- Enforced at PHP level (`upload_max_filesize`, `post_max_size`)

### Encoding
- **Direct upload:** Any format accepted (MP4, WebM, MOV on iOS)
- **After transcoding:** H.264 MP4 (cross-platform compatible)

MOV files are iOS-only at upload time but are transcoded to MP4 for universal playback.

## Background Job Processing

### Job Class
`App\Jobs\TranscodeS3VideoJob`

### Processing Steps
1. Download uploaded video from S3 to temp directory
2. Extract duration via FFprobe
3. Transcode to H.264 MP4 using FFmpeg (`medium` preset by default)
4. Upload transcoded video back to S3
5. Update campaign with transcoded video URL and metadata
6. Clean up temporary files

### Duration
- Short videos (< 5 minutes): ~30 seconds
- Medium videos (5-10 minutes): ~2-5 minutes
- Large videos (> 100 MB): ~10-30 minutes (depending on encoding preset)

### Failure Handling
- Failed jobs are logged with full context
- Job failures are retried up to `QUEUE_MAX_ATTEMPTS` times
- On final failure, `TranscodeS3VideoJob::failed()` is called
- Optionally sends notification to politician (currently commented out)

## Broadcasting / User Feedback

When transcoding completes:
1. Campaign `media_url` and `media_duration` are updated
2. Politician can see updated campaign with playable video
3. (Optional) WebSocket notification can be added to `TranscodeS3VideoJob::failed()`

If transcoding fails:
1. Campaign retains temporary video URL from initial upload
2. Politician can retry upload or contact support

## Testing

### Manual Testing
1. Create a campaign as politician
2. Upload a video file via form (tests direct app-server upload)
3. Or use S3 presigned URL endpoint to test S3 direct upload

### Unit Tests
```bash
php artisan test tests/Feature/Campaign/CampaignCrudTest.php
php artisan test tests/Feature/Campaign/CampaignWorkflowTest.php
```

### Queue Testing
During development, use synchronous queue in `.env`:
```env
QUEUE_CONNECTION=sync
```

This processes jobs immediately instead of queuing.

## Performance Considerations

### Encoding Presets
- `ultrafast` / `superfast` / `veryfast`: Smallest file, lower quality (30-60 seconds for 10 MB)
- `faster` / `fast`: Balanced (1-5 minutes for 10 MB)
- **`medium`** (default): Good quality, reasonable speed (2-10 minutes for 10 MB)
- `slow` / `slower` / `veryslow`: High quality, slow (10-30+ minutes for 10 MB)

Adjust `VIDEO_ENCODE_PRESET` based on your quality vs time requirements.

### S3 Costs
- **Put/Post requests:** $0.005 per 1,000 requests (both upload and download operations)
- **Data transfer out:** ~$0.09 per GB (depends on region)
- For typical political ad campaigns: negligible cost

### Railway Considerations
- Background jobs run on the same dyno/instance as the web server
- For production, consider separate worker dyno to avoid blocking web requests
- Configure `QUEUE_CONNECTION=redis` for high-volume transcoding

## Troubleshooting

### FFmpeg Not Available
- Check: `which ffmpeg` on the server
- Error will be logged but transcoding will be skipped
- Solution: Install FFmpeg on the container

### S3 Access Errors
- Verify AWS credentials are set correctly
- Check S3 bucket permissions (public upload access if using presigned URLs)
- Check CORS configuration if uploading from browser

### Transcoding Slow or Hanging
- Check FFmpeg is installed and accessible
- Monitor disk space for temp directory (`storage/app/temp`)
- Increase transcoding timeout in `VideoTranscodingService` if needed
- Check server CPU/memory during transcoding

### Queue Jobs Not Processing
- Verify queue worker is running: `php artisan queue:work`
- Check `jobs` table for failed jobs: SELECT * FROM jobs WHERE status = 'failed'
- Monitor logs for queue worker errors

## Future Enhancements

1. **Presigned URL Expiration:** Currently 20 minutes; could be made configurable
2. **Progress Tracking:** Add WebSocket progress updates during transcoding
3. **Multiple Quality Tiers:** Generate HLS stream variants for adaptive bitrate
4. **Chunked Upload:** Resume capability for very large files
5. **Webhook Notifications:** Notify external systems when transcoding completes
6. **Dedicated Worker:** Separate dyno for transcoding on production

## References

- Laravel Storage: https://laravel.com/docs/12.x/filesystem
- PHP-FFmpeg: https://github.com/PHP-FFmpeg/PHP-FFmpeg
- AWS S3 Presigned URLs: https://docs.aws.amazon.com/AmazonS3/latest/userguide/PresignedUrlUploadObject.html
- FFmpeg Documentation: https://ffmpeg.org/documentation.html
