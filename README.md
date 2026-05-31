# Kim Castle — Splash Page
## Developer Handoff Notes

---

### Overview
Temporary splash page for kimcastle.com while the full site is in development.
Single page. No CMS. No framework. Pure HTML, CSS, JavaScript.

---

### Folder Structure
```
kim-castle-splash/
  index.html          — Main page
  styles.css          — All styles
  js/
    script.js         — LISTEN toggle + form logic
  assets/
    fonts/
      Faustine.ttf    — Faustina script font (licensed, purchased from Creative Market)
    images/
      k_animated.gif  — Animated brand seal (transparency preserved, reduced from 406 to 136 frames)
      wordmark.svg    — Kim Castle wordmark (vector, white)
    logos/
      wolfgang_puck.png
      abc.png
      onus.png
      gm.png
      funfull.png
      pop.png
      disney.png
      webby.png
```

---

### Fonts
| Font | Source | Usage |
|------|--------|-------|
| IvyPresto Display | Adobe Fonts — embed: `https://use.typekit.net/mtl0bsa.css` | Headlines, opt-in label, closing line, tagline |
| Red Hat Display | Google Fonts | Body copy, titles, form fields, button, footer |
| Faustina | Local file — `assets/fonts/Faustine.ttf` | Not currently in use — reserved for future |

---

### Colors
| Name | Hex |
|------|-----|
| Gold | `#C29E63` |
| White | `#FFFFFF` |
| Dark background | `#2E2216` |

---

### Typography — Exact Illustrator Specs at 1920x1080
All type values were pulled directly from the Illustrator Character panel and converted precisely.

| Element | Font | Weight | Size | Tracking |
|---------|------|--------|------|----------|
| KIM CASTLE | IvyPresto Display | Light | 60pt / 80px | 0 |
| Title lines | Red Hat Display | Medium | 18pt / 24px | 10 (0.01em) |
| Opt-in label | IvyPresto Display | Light Italic | 35pt / 47px | 0 |
| Form fields | Red Hat Display | Light Italic | 13pt / 17px | 0 |
| I'M IN button | Red Hat Display | Medium | 20pt / 27px | 0 |
| Headline | IvyPresto Display | Light Italic | 40pt / 53px | 0 |
| Body copy | Red Hat Display | Regular | 20pt / 27px | 0 |
| Gold lines | Red Hat Display | Regular | 20pt / 27px | 0 |
| Closing line | IvyPresto Display | Light Italic | 28pt / 37px | 0 |
| Tagline | IvyPresto Display | Italic | 18pt / 24px | 62 (0.062em) |
| Footer | Red Hat Display | Medium | 12pt / 16px | 0 |

---

### Primary Viewport
**This page was designed at 1920x1080. All typography and spacing is optimized for that size.**
Responsive views below 1024px will need attention — particularly the top row where the name block and opt-in form may crowd each other on smaller screens. The credentials block (logos) is hidden on mobile screens under 768px. All responsive rules live in the `@media` section at the bottom of `styles.css`.

---

### Video — ACTION REQUIRED
**Current:** Hosted on Rackspace CDN (~20MB uncompressed)
```
https://4f96b036d9448bdcaa33-cbd9f7c6d979a527e15ded24ab5c6c30.ssl.cf1.rackcdn.com/Meet%20Kim%20Castle%20Hero.m4v
```

**Please do the following before final deploy:**
1. Compress video using HandBrake or similar
   - Format: H.264
   - Resolution: 1080p max
   - Target file size: under 8MB
2. Re-host on AWS S3 + CloudFront (migrating off Rackspace to AWS Bedrock)
3. Update the `<source src="...">` URL in `index.html`

---

### Animated Seal — GIF Notes
- White seal on transparent background
- Full 1080x1080 resolution preserved for crispness
- Reduced from 406 to 136 frames for performance
- If rotation appears less fluid than original, swap in the original 7MB GIF
- Displays at 160px on desktop via CSS

---

### LISTEN / Sound Feature
- Video autoplays muted on load (browser compliance requirement — cannot be bypassed)
- Pulsing gold dot + LISTEN label fades in at 2 seconds
- On click: video unmutes, label changes to SILENCE, pulse stops
- On SILENCE click: video mutes, label returns to LISTEN, pulse resumes
- Logic lives in `js/script.js`

---

### Form / Email Capture — ACTION REQUIRED
The opt-in form currently has placeholder submit logic only.
**Must be wired to ActiveCampaign before launch.**

Recommended integration: Use ActiveCampaign API v3 to post form submissions.

Endpoint: POST https://[your-account].api-us1.com/api/3/contacts

Fields to pass: firstName, lastName, email

Important notes:
- Also subscribe the contact to the correct AC list via the List Subscription endpoint
- Store the API key securely — do NOT hardcode in client-side JS
- Use a serverless function (AWS Lambda recommended given AWS migration) to proxy the API call securely
- Alternatively use ActiveCampaign native form embed and restyle to match existing design

Form fields: First Name, Last Name, Email
Button: I'M IN
On successful submit: fields clear, label changes to "You're in. Welcome."
Integration point clearly marked in js/script.js

---

### Deployment Checklist
- [ ] Compress and re-host video
- [ ] Update video URL in `index.html`
- [ ] Wire form to email service provider
- [ ] Verify `.ttf` font served with MIME type `font/truetype`
- [ ] Test video autoplay on iOS Safari (uses `playsinline` attribute)
- [ ] Test LISTEN toggle on Chrome, Safari, Firefox
- [ ] QA responsive views — priority: 1440px, 1280px, 768px, 375px
- [ ] Point domain to `index.html`

---

*Built by Kim Castle + Claude — Anthropic*
*Version: Splash v2 — April 2026*
*Designed at 1920x1080*
