# Tour Media — Welcome Narration Assets

This folder holds the optional **video / audio / captions** files used by the
guided tour on the U9itus 3D map (`/map`).

The tour code lives in [`resources/views/standalone/public/us-map.blade.php`](../../../resources/views/standalone/public/us-map.blade.php)
inside the `TOUR_STEPS` array. Each step may declare a `media` block whose
`src` URLs point at this directory. **Files are loaded by URL — no code change
is needed when you add or replace them.** Missing files degrade gracefully:
the player wrapper hides itself and the text transcript stays visible for ADA
compliance.

---

## Expected files

The tour has **8 steps**, each driven by a slug. For every slug you ship the
same set of files:

| File pattern              | Purpose                                              | Required?            |
| ------------------------- | ---------------------------------------------------- | -------------------- |
| `{slug}.webm`             | Primary video (VP9/AV1, best compression)            | Recommended          |
| `{slug}.mp4`              | Fallback video (H.264, Safari/iOS)                   | Recommended          |
| `{slug}.mp3`              | Audio-only version (low bandwidth / screen readers)  | Recommended          |
| `{slug}.en.vtt`           | English captions (WebVTT)                            | **Required for ADA** |
| `{slug}-poster.jpg`       | Thumbnail shown before play                          | Optional             |

If you only ship audio (no video), the player will render an `<audio>`
control instead of `<video>`. Missing files 404 silently — the player wrapper
hides and the always-on text transcript stays visible for compliance.

### Slugs

| # | Slug              | Tour step                          | Captions file (shipped)                   |
| - | ----------------- | ---------------------------------- | ----------------------------------------- |
| 1 | `welcome`         | 🗺 Welcome to U9itus               | [`welcome.en.vtt`](welcome.en.vtt)         |
| 2 | `tilt-rotate`     | ↑↓ Tilt · ←→ Rotate                | [`tilt-rotate.en.vtt`](tilt-rotate.en.vtt) |
| 3 | `zoom`            | 🔍 Zoom                            | [`zoom.en.vtt`](zoom.en.vtt)               |
| 4 | `search`          | / Search                           | [`search.en.vtt`](search.en.vtt)           |
| 5 | `click-state`     | 🖱 Click a State                   | [`click-state.en.vtt`](click-state.en.vtt) |
| 6 | `offices-toggle`  | O — Offices Toggle                 | [`offices-toggle.en.vtt`](offices-toggle.en.vtt) |
| 7 | `reset`           | R — Reset View                     | [`reset.en.vtt`](reset.en.vtt)             |
| 8 | `keyboard-help`   | ? — Keyboard Help                  | [`keyboard-help.en.vtt`](keyboard-help.en.vtt) |

So a complete asset drop for the whole tour looks like:

```
welcome.{webm,mp4,mp3,en.vtt}        welcome-poster.jpg
tilt-rotate.{webm,mp4,mp3,en.vtt}    tilt-rotate-poster.jpg
zoom.{webm,mp4,mp3,en.vtt}           zoom-poster.jpg
search.{webm,mp4,mp3,en.vtt}         search-poster.jpg
click-state.{webm,mp4,mp3,en.vtt}    click-state-poster.jpg
offices-toggle.{webm,mp4,mp3,en.vtt} offices-toggle-poster.jpg
reset.{webm,mp4,mp3,en.vtt}          reset-poster.jpg
keyboard-help.{webm,mp4,mp3,en.vtt}  keyboard-help-poster.jpg
```

---

## Narration scripts

The spoken narration for each step must match the `transcript` argument
passed to `_mediaFor(slug, transcript)` inside
[`us-map.blade.php`](../../../resources/views/standalone/public/us-map.blade.php).
The starter `*.en.vtt` files in this folder are pre-timed at ~150 wpm — read
those for the canonical wording.

**Target length per step:** ~20–30 seconds (welcome is longer, ~75–90 s).

If you rewrite any narration, update **all three** in lockstep for that slug:
1. The `transcript` argument in `_mediaFor('{slug}', '...')` (us-map.blade.php)
2. `{slug}.en.vtt` (re-time cues)
3. Re-record `{slug}.mp4` / `{slug}.webm` / `{slug}.mp3`

---

## How to generate the files

### Option A — Record a real human voice (preferred)
1. Record at 48 kHz, mono, WAV.
2. Export:
   - `welcome.mp3` — 96 kbps CBR or 128 kbps VBR
   - `welcome.mp4` / `welcome.webm` if combining with screen capture

### Option B — Screen recording with voice-over
1. Use QuickTime (macOS) or OBS to capture the `/map` page while you narrate.
2. Export master as `.mov`, then transcode:
   ```bash
   # H.264 MP4 (Safari/iOS-friendly)
   ffmpeg -i master.mov -c:v libx264 -crf 23 -preset slow \
          -c:a aac -b:a 128k -movflags +faststart welcome.mp4

   # VP9 WebM (smaller, modern browsers)
   ffmpeg -i master.mov -c:v libvpx-vp9 -crf 33 -b:v 0 \
          -c:a libopus -b:a 96k welcome.webm

   # Audio-only MP3
   ffmpeg -i master.mov -vn -c:a libmp3lame -b:a 96k welcome.mp3

   # Poster frame at 2-second mark
   ffmpeg -ss 2 -i master.mov -frames:v 1 -q:v 3 welcome-poster.jpg
   ```

### Option C — Quick prototype with macOS text-to-speech
For internal testing only (not a substitute for human narration):
```bash
# 1. Synthesize audio
say -v Samantha -o welcome.aiff -f - <<'EOF'
The U9itus U.S. map is totally interactive. It allows you immediate access to political representatives in all states by simply pointing your mouse on the desired state to review, by clicking on this state. The state will separate away from all other states and will show area divisions populating into districts, and names of political representatives serving in these districts. Find the representatives serving any area in the United States. Click on their names and find out all about them. You can check what they stand for from various reliable resources. You can also find out who is bankrolling their campaigns, right here at U9itus. In upcoming seasons, this information will be vital in your making an intelligent choice when you go to the polls.
EOF

# 2. Convert to MP3
ffmpeg -i welcome.aiff -c:a libmp3lame -b:a 96k welcome.mp3
rm welcome.aiff
```

---

## Captions (`welcome.en.vtt`)

The starter [`welcome.en.vtt`](welcome.en.vtt) ships pre-timed at ~150 wpm.
**You must adjust the timestamps** to match your final recording. Use any
caption editor (e.g. [Subtitle Edit](https://www.nikse.dk/subtitleedit),
[Aegisub](https://aegisub.org/), or just a text editor).

WebVTT cue format:
```
00:00:00.000 --> 00:00:05.000
The U9itus U.S. map is totally interactive.
```

**Requirement:** All audible speech must be captioned to meet
[WCAG 2.1 Success Criterion 1.2.2 (Captions, Prerecorded)](https://www.w3.org/WAI/WCAG21/Understanding/captions-prerecorded.html).

---

## Adding a brand-new tour step

The 8 existing steps are already wired up via the `_mediaFor()` helper. To
add a 9th step, append a new entry to `TOUR_STEPS` with:

```js
{
    target: '#some-selector', pos: 'bottom',
    title: 'My New Step',
    body: `HTML body content for the card.`,
    media: _mediaFor('my-new-slug',
        `Verbatim narration script that screen readers will announce…`
    ),
},
```

Then drop `my-new-slug.{webm,mp4,mp3,en.vtt}` into this folder — no other
code changes required.

---

## File size budget

Keep tour assets light so the overlay loads fast on cellular:

| Asset       | Target  | Hard ceiling |
| ----------- | ------- | ------------ |
| Video (mp4) | < 4 MB  | 8 MB         |
| Video (webm)| < 3 MB  | 6 MB         |
| Audio (mp3) | < 1 MB  | 2 MB         |
| Poster      | < 80 KB | 200 KB       |
| Captions    | < 4 KB  | 16 KB        |
