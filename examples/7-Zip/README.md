# Example: 7-Zip

Sample catalogue record for **MECM Packaging Manager** (metadata + silent install/uninstall procedure). **No installer binary** is included.

## Import

1. Sign in as admin.
2. **Software** → **Import recipe**.
3. Upload `7-Zip.json` (format `sccm-pf-software-export`).
4. Open the new record → **Sync** to download the MSI from GitHub Releases.
5. Optionally upload `icon.png` as the Software Center icon.
6. Generate the ZIP / run the lab pipeline.

If a product named 7-Zip already exists, import creates **7-Zip (copie)**.
