#!/usr/bin/env python3
"""Build the theme-installable ZIP and checksum from the block metadata version."""
import hashlib
import json
import re
import zipfile
from pathlib import Path

root = Path(__file__).resolve().parents[1]
version = json.loads((root / 'alphablock/block.json').read_text())['version']
if not re.fullmatch(r'\d+\.\d+\.\d+', version):
    raise ValueError('Expected a semantic release version in block.json')
files = ['alphablock.json', 'block.json', 'block.php', 'template.php', 'tokens.php', 'args.php', 'prompt.php']
dist = root / 'dist'
dist.mkdir(exist_ok=True)
asset = dist / f'as-alphablock-{version}.zip'
with zipfile.ZipFile(asset, 'w', compression=zipfile.ZIP_DEFLATED) as archive:
    for name in sorted(files):
        source = root / 'alphablock' / name
        if name.endswith('.json'):
            json.loads(source.read_text())
        entry = zipfile.ZipInfo(f'alphablock/{name}')
        entry.compress_type = zipfile.ZIP_DEFLATED
        entry.external_attr = 0o100644 << 16
        archive.writestr(entry, source.read_bytes())
with zipfile.ZipFile(asset) as archive:
    assert len(archive.namelist()) == len(files)
    assert archive.testzip() is None
    for name in files:
        assert archive.read(f'alphablock/{name}') == (root / 'alphablock' / name).read_bytes()
checksum = hashlib.sha256(asset.read_bytes()).hexdigest()
asset.with_suffix('.zip.sha256').write_text(f'{checksum}  {asset.name}\n')
print(asset)
print(f'SHA256 {checksum}')
