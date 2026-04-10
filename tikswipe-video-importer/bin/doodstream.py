#!/usr/bin/env python3
"""
TikSwipe Video Importer — Doodstream native extractor.

Uses curl_cffi (which is already installed as a yt-dlp dependency) to
bypass Cloudflare anti-bot protection and extract the direct MP4 URL
from Doodstream/playmogo embed pages.

Usage:
    python3 doodstream.py <embed_url>

Output (stdout):
    JSON with video_url, title, source_url (on success)

Output (stderr):
    Error message (on failure)

Exit code: 0 on success, 1 on failure.
"""

import json
import random
import re
import string
import sys
import time
from urllib.parse import urlparse

try:
    from curl_cffi import requests
except ImportError:
    print("ERROR: curl_cffi not installed. Run: pip install -U curl-cffi --break-system-packages", file=sys.stderr)
    sys.exit(1)


def extract(url):
    """Extract direct MP4 URL from a Doodstream/playmogo embed page."""
    session = requests.Session(impersonate="chrome")

    # Step 1: Fetch the embed page.
    try:
        response = session.get(url, timeout=25, allow_redirects=True)
    except Exception as e:
        print(f"ERROR: fetch failed: {e}", file=sys.stderr)
        sys.exit(1)

    if response.status_code >= 400:
        print(f"ERROR: HTTP {response.status_code} fetching {url}", file=sys.stderr)
        sys.exit(1)

    html = response.text
    final_url = str(response.url)

    # Step 2: Find the pass_md5 path in the HTML.
    m = re.search(r"(/pass_md5/[a-f0-9-]+/([a-z0-9]+))", html, re.IGNORECASE)
    if not m:
        print(f"ERROR: pass_md5 not found in HTML (length={len(html)})", file=sys.stderr)
        sys.exit(1)

    pass_md5_path = m.group(1)
    token = m.group(2)

    # Step 3: Fetch pass_md5 with Referer header to get the base URL.
    host = urlparse(final_url).netloc
    pass_md5_url = f"https://{host}{pass_md5_path}"

    try:
        md5_response = session.get(
            pass_md5_url,
            headers={
                "Referer": final_url,
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "*/*",
            },
            timeout=20,
        )
    except Exception as e:
        print(f"ERROR: pass_md5 fetch failed: {e}", file=sys.stderr)
        sys.exit(1)

    if md5_response.status_code >= 400:
        print(f"ERROR: pass_md5 HTTP {md5_response.status_code}", file=sys.stderr)
        sys.exit(1)

    base_url = md5_response.text.strip()

    if not base_url.startswith("http"):
        print(f"ERROR: pass_md5 returned non-URL: {base_url[:200]}", file=sys.stderr)
        sys.exit(1)

    # Step 4: Build final video URL with random suffix and token.
    random_suffix = "".join(random.choices(string.ascii_lowercase + string.digits, k=10))
    expiry = int(time.time() * 1000)
    video_url = f"{base_url}{random_suffix}?token={token}&expiry={expiry}"

    # Step 5: Extract title from HTML.
    title = ""
    title_match = re.search(r"<title>(.*?)</title>", html, re.IGNORECASE | re.DOTALL)
    if title_match:
        title = title_match.group(1).strip()
        # Remove common Doodstream suffixes.
        title = re.sub(r"\s*[-|]\s*Doodstream.*$", "", title, flags=re.IGNORECASE)
        title = re.sub(r"\s*on\s+Doodstream.*$", "", title, flags=re.IGNORECASE)
        title = re.sub(r"\s*[-|]\s*Playmogo.*$", "", title, flags=re.IGNORECASE)
        title = re.sub(r"^Watch\s+", "", title, flags=re.IGNORECASE)
        title = title.strip()

    # Try to extract duration and thumbnail from meta tags.
    duration = 0
    dur_match = re.search(r'meta\s+itemprop="duration"\s+content="PT(\d+)M(\d+)S"', html, re.IGNORECASE)
    if dur_match:
        duration = int(dur_match.group(1)) * 60 + int(dur_match.group(2))

    thumbnail = ""
    thumb_match = re.search(r'meta\s+itemprop="thumbnailUrl"\s+content="([^"]+)"', html, re.IGNORECASE)
    if thumb_match:
        thumbnail = thumb_match.group(1)
    else:
        thumb_match = re.search(r'<meta\s+property="og:image"\s+content="([^"]+)"', html, re.IGNORECASE)
        if thumb_match:
            thumbnail = thumb_match.group(1)

    result = {
        "video_url": video_url,
        "title": title,
        "source_url": url,
        "duration": duration,
        "thumbnail": thumbnail,
    }

    print(json.dumps(result))
    sys.exit(0)


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python3 doodstream.py <embed_url>", file=sys.stderr)
        sys.exit(1)

    extract(sys.argv[1])
