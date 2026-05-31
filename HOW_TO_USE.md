# kimcastle.com — How to use this with Claude

**Hi.** This file tells Claude everything it needs to manage the kimcastle.com website for you. Open Claude (Cowork mode) on your Mac, give it access to this folder, then say:

> Read HOW_TO_USE.md and help me with the site.

Claude takes over. The rest of this file is written for Claude.

---

## Instructions for Claude

You are helping a non-technical user manage the website **kimcastle.com**. They don't know git, terminals, or code. Your job is to handle all the technical parts while they describe changes in plain English.

### Project facts

- **Folder on user's Mac:** wherever this file is — use this file's location as the project root.
- **GitHub repo:** `https://github.com/AnuragMittal-arch/Kim-Castle-Website` (branch `main`)
- **Live site:** `https://kimcastle.com`
- **Hosting:** DigitalOcean droplet with aaPanel. CI/CD is already set up; pushing to `main` auto-deploys.
- **Owner:** Anurag (`AnuragMittal-arch` on GitHub). The user is added as a collaborator.

### Key files

- `index.html` — main page markup
- `styles.css` — all styling
- `js/` — JavaScript
- `assets/` — images, fonts, media
- `.user.ini` — **do not touch** (server-managed by aaPanel)
- `.github/workflows/deploy.yml` — **do not edit** unless explicitly asked

### First-time setup (only if the user is brand new)

Check whether git is already set up: run `git -C <project-root> remote -v`. If it returns the GitHub URL, skip to "Daily workflow" below.

If the folder isn't a working git repo:

1. **Install GitHub Desktop** (handles all auth, no terminals needed):
   - Tell the user: "Go to https://desktop.github.com, click Download, open the file, drag the app into Applications."
   - Open GitHub Desktop and sign in with the GitHub account that received the collaborator invite.

2. **Accept the collaborator invite** if not already done:
   - "Check your email for a message from GitHub titled 'AnuragMittal-arch invited you...' Click Accept invitation."
   - Or visit `https://github.com/AnuragMittal-arch/Kim-Castle-Website/invitations`.

3. **Clone the repo via GitHub Desktop:**
   - "In GitHub Desktop, click File → Clone repository. Pick `Kim-Castle-Website`. Local path: your Desktop. Click Clone."

4. **Reopen Claude pointing at the new folder** (`~/Desktop/Kim-Castle-Website` or `~/Desktop/kimcastle.com`).

### Daily workflow — when the user asks for a change

Listen for things like:

- "Change the headline to X"
- "Update the contact email"
- "Replace this image in the assets folder"
- "Make the menu items larger"

Steps:

1. **Make the edit** in the relevant file (`index.html`, `styles.css`, or `js/`). Use Read first, then Edit.
2. **Confirm in plain English** what you changed.
3. **Stage and commit:**
   ```
   git -C <project-root> add -A
   git -C <project-root> commit -m "<short description>"
   ```
4. **Push.** Try `git -C <project-root> push origin main`.
   - If push fails with auth error, tell the user: "Open GitHub Desktop. You'll see your change at the top — click **Push origin**. That sends it live."
5. **Tell them:** "Your change will be live at https://kimcastle.com in about 30 seconds. Hard refresh (Cmd+Shift+R) to see it."

### When the user says "push it live" / "deploy" / "make it live"

That's their signal to commit + push. Don't push before they say so — they may want to preview first.

### Undo

- "Undo the last change": `git -C <project-root> revert HEAD --no-edit && git -C <project-root> push origin main`, then ask the user to click Push in GitHub Desktop if needed.

### Things to refuse politely

- **Don't edit `.user.ini`** — it's locked on the server.
- **Don't edit `.github/workflows/deploy.yml`** unless asked to change CI.
- **No files over 90 MB.** If the user drops a big video/PDF, recommend an external host (Vimeo, S3, etc.).
- **Don't force-push or rewrite history.**

### Verifying a deploy

Workflow runs: `https://github.com/AnuragMittal-arch/Kim-Castle-Website/actions`. Green ✓ = deployed. Red ✗ = read the log and explain plainly.

---

## Daily workflow — for the user (plain English)

You don't need to read the technical part above. Just:

1. Open Claude on your Mac.
2. Make sure it has access to the Kim Castle folder.
3. Say what you want changed. Examples:
   - "Change the headline to 'Welcome to Kim Castle.'"
   - "Use the new banner image I just dropped in the assets folder."
   - "Make the menu links larger."
4. When happy, say: **push it live**
5. If Claude tells you "open GitHub Desktop and click Push origin", do that. One click.
6. Wait ~30 seconds. Refresh `https://kimcastle.com` (hold Shift while clicking reload) to see it.

If you ever want to undo, say: "undo the last change".
