"""Optional: copies release files to a DreamObjects bucket, to serve them over plain HTTP.

Not in use: downloads link to each project's own releases. This stays so a mirror can be
switched on later by adding the keys below and files_base_url in config/site.toml.

  python3 -m vivahx.mirror [--minutes 50]

Needs DREAMOBJECTS_ACCESS_KEY, DREAMOBJECTS_SECRET_KEY and DREAMOBJECTS_BUCKET (and optionally
DREAMOBJECTS_ENDPOINT). Without them it says so and does nothing, so the rest of the site still
builds. Uses the AWS command line tool, which GitHub's runners already have.

Each file is recorded in data/releases/<id>.json as soon as it's uploaded, so a run that stops
part way loses nothing; the next run carries on.
"""

import argparse
import hashlib
import mimetypes
import os
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.request

from .common import DATA, load_json, write_json

MAX_BYTES = 1024 * 1024 * 1024
ENDPOINT = "https://objects-us-east-1.dream.io"

# Old Mac browsers and StuffIt Expander go by these.
TYPES = {
    ".hqx": "application/mac-binhex40",
    ".sit": "application/x-stuffit",
    ".sea": "application/x-stuffit",
    ".bin": "application/macbinary",
    ".dmg": "application/x-apple-diskimage",
}


def download(url, dest):
    req = urllib.request.Request(url, headers={"User-Agent": "VivaHX release mirror (http://vivahx.com)"})
    digest = hashlib.sha256()
    size = 0
    with urllib.request.urlopen(req, timeout=120) as r, open(dest, "wb") as out:
        while True:
            chunk = r.read(1024 * 1024)
            if not chunk:
                break
            size += len(chunk)
            if size > MAX_BYTES:
                raise ValueError(f"bigger than {MAX_BYTES // (1024 * 1024)} MB")
            digest.update(chunk)
            out.write(chunk)
    return size, digest.hexdigest()


def upload(path, key, bucket):
    ext = os.path.splitext(key)[1].lower()
    content_type = TYPES.get(ext) or mimetypes.guess_type(key)[0] or "application/octet-stream"
    env = dict(os.environ,
               AWS_ACCESS_KEY_ID=os.environ["DREAMOBJECTS_ACCESS_KEY"],
               AWS_SECRET_ACCESS_KEY=os.environ["DREAMOBJECTS_SECRET_KEY"],
               AWS_DEFAULT_REGION="us-east-1",
               # DreamObjects rejects the extra checksums newer AWS tools add by default.
               AWS_REQUEST_CHECKSUM_CALCULATION="when_required",
               AWS_RESPONSE_CHECKSUM_VALIDATION="when_required")
    subprocess.run([
        "aws", "s3", "cp", path, f"s3://{bucket}/{key}",
        "--endpoint-url", os.environ.get("DREAMOBJECTS_ENDPOINT") or ENDPOINT,
        "--acl", "public-read", "--content-type", content_type, "--only-show-errors",
    ], check=True, env=env)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--minutes", type=float, default=50, help="stop starting new files after this long")
    args = parser.parse_args()

    missing = [n for n in ("DREAMOBJECTS_ACCESS_KEY", "DREAMOBJECTS_SECRET_KEY", "DREAMOBJECTS_BUCKET") if not os.environ.get(n)]
    if missing:
        print(f"{', '.join(missing)} not set yet; not copying any files.")
        return 0
    bucket = os.environ["DREAMOBJECTS_BUCKET"]
    deadline = time.monotonic() + args.minutes * 60
    copied = failed = 0

    for data_file in sorted(DATA.glob("*.json")):
        data = load_json(data_file, {})
        for release in data.get("releases", []):
            for f in release.get("files", []):
                if f.get("mirrored"):
                    continue
                if time.monotonic() > deadline:
                    print(f"Out of time; {copied} copied this run, the rest next run.")
                    return 0
                if f.get("size", 0) > MAX_BYTES:
                    f["skipped"] = "too big to keep"
                    write_json(data_file, data)
                    continue
                with tempfile.TemporaryDirectory() as tmp:
                    local = os.path.join(tmp, "file")
                    try:
                        size, sha = download(f["source_url"], local)
                        upload(local, f["path"], bucket)
                    except (urllib.error.URLError, OSError, ValueError, subprocess.CalledProcessError) as e:
                        print(f"{f['path']}: {e}", file=sys.stderr)
                        f["last_error"] = str(e)
                        failed += 1
                        write_json(data_file, data)
                        continue
                f.update({"mirrored": True, "size": size, "sha256": sha,
                          "mirrored_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())})
                f.pop("last_error", None)
                write_json(data_file, data)
                copied += 1
                print(f"{f['path']}: copied ({size:,} bytes)")
    print(f"Done: {copied} copied, {failed} failed.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
