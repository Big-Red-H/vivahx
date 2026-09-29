#!/usr/bin/env bash
# Builds the site and uploads it to DreamHost over SSH.
#
# Needs DEPLOY_SSH_KEY, DEPLOY_HOST, DEPLOY_USER and DEPLOY_PATH. When they aren't set yet it
# builds anyway (so a broken post still fails the run) and skips the upload.
set -euo pipefail
cd "$(dirname "$0")/.."

python3 -m vivahx.build

for name in DEPLOY_SSH_KEY DEPLOY_HOST DEPLOY_USER DEPLOY_PATH; do
  if [ -z "${!name:-}" ]; then
    echo "$name isn't set yet; skipping the upload."
    exit 0
  fi
done

mkdir -p ~/.ssh
printf '%s\n' "$DEPLOY_SSH_KEY" > ~/.ssh/deploy_key
chmod 600 ~/.ssh/deploy_key
ssh-keyscan -H "$DEPLOY_HOST" >> ~/.ssh/known_hosts 2>/dev/null
ssh_cmd="ssh -i $HOME/.ssh/deploy_key"
remote="$DEPLOY_USER@$DEPLOY_HOST"

# rsync --delete only into a folder this script set up (it leaves a .vivahx-deploy file), or an
# empty one. The old PHP site and forum, if they're still there, are never deleted by a deploy.
delete=""
if $ssh_cmd "$remote" "mkdir -p '$DEPLOY_PATH' && { test -e '$DEPLOY_PATH/.vivahx-deploy' || test -z \"\$(ls -A '$DEPLOY_PATH' | grep -v -e '^.dh-diag\$' -e '^.well-known\$' -e '^favicon.ico\$')\"; }"; then
  delete="--delete"
else
  echo "::warning::$DEPLOY_PATH has files this deploy didn't put there, so nothing will be deleted from it. Clear it out, or add a .vivahx-deploy file to it, to allow deletes."
fi
rsync -rlvz $delete --delay-updates --chmod=D755,F644 \
  --exclude /.well-known/ --exclude /.dh-diag --exclude /.vivahx-deploy \
  -e "$ssh_cmd" public/ "$remote:$DEPLOY_PATH/"
$ssh_cmd "$remote" "touch '$DEPLOY_PATH/.vivahx-deploy'"
