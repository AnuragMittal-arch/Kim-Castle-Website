#!/bin/bash
# Run once. Double-click this file in Finder to set up a clean git repo.
# This wipes any broken .git directory and re-clones from GitHub.

set -e
cd "$(dirname "$0")"

echo "==> Cleaning broken .git directory (if any)"
sudo rm -rf .git

echo "==> Initializing fresh git repo pointing at GitHub"
git init -b main
git remote add origin https://github.com/AnuragMittal-arch/Kim-Castle-Website.git
git fetch origin
git reset --hard origin/main

echo "==> Caching credentials in macOS Keychain"
git config credential.helper osxkeychain

echo ""
echo "All done. You can close this window."
echo "From here on, just ask Claude: \"change X on the kimcastle site and push it.\""
read -p "Press Enter to close..."
