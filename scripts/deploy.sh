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
# DreamHost's public host key is kept in scripts/known_hosts, so a deploy doesn't look it up each
# time (that lookup is what failed when DreamHost briefly turned GitHub's machines away). If the
# server ever changes, it's looked up, and a key that doesn't match stops the deploy.
cp scripts/known_hosts ~/.ssh/known_hosts
if ! ssh-keygen -F "$DEPLOY_HOST" -f ~/.ssh/known_hosts >/dev/null; then
  ssh-keyscan -T 15 -H "$DEPLOY_HOST" >> ~/.ssh/known_hosts 2>/dev/null || true
fi
ssh_cmd="ssh -i $HOME/.ssh/deploy_key -o StrictHostKeyChecking=yes -o ConnectTimeout=20"

# Connections to DreamHost occasionally fail; each upload is tried a few times.
retry() {
  for attempt in 1 2 3 4; do
    "$@" && return 0
    [ "$attempt" = 4 ] && break
    echo "Couldn't reach DreamHost; trying again in $((attempt * 20)) seconds."
    sleep $((attempt * 20))
  done
  echo "::error::Couldn't reach DreamHost after 4 tries."
  return 1
}
remote="$DEPLOY_USER@$DEPLOY_HOST"

# rsync --delete only into a folder this script set up (it leaves a .vivahx-deploy file), or an
# empty one. The old PHP site and forum, if they're still there, are never deleted by a deploy.
delete=""
if $ssh_cmd "$remote" "mkdir -p '$DEPLOY_PATH' && { test -e '$DEPLOY_PATH/.vivahx-deploy' || test -z \"\$(ls -A '$DEPLOY_PATH' | grep -v -e '^.dh-diag\$' -e '^.well-known\$' -e '^favicon\.\(ico\|gif\)\$')\"; }"; then
  delete="--delete"
else
  echo "::warning::$DEPLOY_PATH has files this deploy didn't put there, so nothing will be deleted from it. Clear it out, or add a .vivahx-deploy file to it, to allow deletes."
fi
retry rsync -rlvz $delete --delay-updates --chmod=D755,F644 \
  --exclude /.well-known/ --exclude /.dh-diag --exclude /.vivahx-deploy \
  -e "$ssh_cmd" public/ "$remote:$DEPLOY_PATH/"
retry $ssh_cmd "$remote" "touch '$DEPLOY_PATH/.vivahx-deploy'"
