"""Packages the redirect plugin as a WordPress plugin zip.

    python tools/build.py

Bump TXH_VERSION in wordpress/travelx-home.php before building an update.
Upload the zip under Plugins → Add New → Upload Plugin ("Replace current with
uploaded").
"""
import re
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PLUGIN = ROOT / "wordpress" / "travelx-home.php"

php = PLUGIN.read_text(encoding="utf-8")
version = re.search(r"const TXH_VERSION\s*=\s*'([^']+)'", php).group(1)
php = re.sub(r"(\* Version:\s*)\S+", rf"\g<1>{version}", php, count=1)

out = ROOT / "dist" / f"travelx-home-{version}.zip"
out.parent.mkdir(exist_ok=True)
with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as z:
    z.writestr("travelx-home/travelx-home.php", php)

print(f"{out.relative_to(ROOT).as_posix()}  {out.stat().st_size / 1024:.1f} KB  (v{version})")
