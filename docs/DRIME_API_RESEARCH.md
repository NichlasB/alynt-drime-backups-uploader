# Drime API Research

Updated: 2026-06-22

Sources:

- https://docs.drime.cloud/introduction
- https://docs.drime.cloud/authentication
- https://docs.drime.cloud/uploads-guide
- https://docs.drime.cloud/api-reference/uploads/upload-file
- https://docs.drime.cloud/api-reference/uploads/validate-upload
- https://docs.drime.cloud/api-reference/files/get-available-name
- https://docs.drime.cloud/api-reference/uploads/create-s3-entry
- https://docs.drime.cloud/api-reference/multipart/create-multipart
- https://docs.drime.cloud/api-reference/multipart/sign-part-urls
- https://docs.drime.cloud/api-reference/multipart/complete-multipart
- https://docs.drime.cloud/api-reference/multipart/get-uploaded-parts
- https://docs.drime.cloud/api-reference/users/get-logged-user
- https://docs.drime.cloud/api-reference/user/get-workspaces
- https://docs.drime.cloud/api-reference/folders/get-user-folders
- https://docs.drime.cloud/api-reference/files/list-file-entries
- https://docs.drime.cloud/api-reference/folders/get-folder-path

## Verified From Current Docs

- Base API URL is `https://app.drime.cloud/api/v1`.
- Requests use Bearer token authentication in the `Authorization` header.
- Personal/default workspace uses `workspaceId=0`.
- Workspace discovery uses `GET /me/workspaces` and returns the workspaces available to the authenticated token, including IDs, names, member counts, and current-user role data.
- Direct upload endpoint is `POST /uploads` using `multipart/form-data`.
- Direct upload accepts `file`, `workspaceId`, optional `parentId`, and optional `relativePath`.
- Direct upload response should include `status` and `fileEntry`.
- Duplicate validation endpoint is `POST /uploads/validate?workspaceId=0`.
- Duplicate validation request body is `files`, with file objects containing `name`, `size`, and `relativePath`.
- Duplicate validation response should include `status`, `errors`, and `duplicates`.
- Available-name endpoint is `POST /entry/getAvailableName`.
- Available-name request body includes `name`, `parentId`, and `workspaceId`.
- Available-name response should include `name`.
- Multipart create endpoint is `POST /s3/multipart/create`.
- Multipart create request body includes `filename`, `mime`, `size`, `extension`, `workspaceId`, and optionally `relativePath` or `parentId`.
- Multipart create response should include `key`, `uploadId`, `acl`, and `status`.
- Part signing endpoint is `POST /s3/multipart/batch-sign-part-urls`.
- Part signing request body includes `key`, `uploadId`, and 1-indexed `partNumbers`.
- Part signing response should include `urls`, with each item containing `partNumber` and `url`.
- Complete multipart endpoint is `POST /s3/multipart/complete`.
- Complete multipart request body includes `key`, `uploadId`, and `parts`.
- Each completed part must use `PartNumber` and `ETag`; docs show ETags with quotes included.
- Complete multipart response should include `location` and `status`.
- Abort multipart endpoint is `POST /s3/multipart/abort`.
- Abort multipart request body includes `key` and `uploadId`; docs say it cancels in-progress uploads and cleans up uploaded parts.
- Abort multipart response should include `status`.
- Resume endpoint is `POST /s3/multipart/get-uploaded-parts`.
- Resume response should include `parts`, with `PartNumber`, `ETag`, and `Size`.
- S3 entry registration endpoint is `POST /s3/entries`.
- S3 entry body includes `filename`, `size`, `clientName`, `clientMime`, `clientExtension`, `workspaceId`, and optionally `parentId` or `relativePath`.
- S3 entry response should include `status` and `fileEntry`.
- Folder browsing can use `GET /cli/loggedUser`, `GET /users/{userId}/folders?workspaceId=0`, `GET /drive/file-entries?workspaceId=0&type=folder&folderId={folderHash}`, and `GET /folders/{folderHash}/path`.
- The folder browser stores non-secret display metadata only: numeric folder ID, folder hash, and display path. It does not expose bearer tokens in JavaScript or AJAX responses.
- The workspace picker stores only the selected numeric `workspace_id`; AJAX responses expose sanitized workspace IDs, names, member counts, and role labels, not tokens or raw account payloads.

## Code Updates Made

- Direct small-file uploads now pass `relativePath` only for legacy relative-path settings without a selected base folder.
- Selected-base uploads now resolve or create the final relative-path folder during upload and then target that concrete parent folder ID for direct and multipart uploads. Live E2E showed Drime direct upload does not reliably honor `relativePath` alongside `parentId`.
- The Drime client now includes read-only folder browser methods for logged-user lookup, user-folder listing, child folder listing, and folder breadcrumb lookup.
- The Drime client now includes a read-only workspace listing method for `GET /me/workspaces`.
- The admin settings screen can load workspaces into a native selector while preserving manual workspace ID entry.
- Changing the configured workspace clears selected base-folder metadata so a folder ID from another workspace is not reused accidentally.
- Destination preview is read-only and reports existing or missing relative-path segments without creating folders or uploading backup bytes.
- The Drime client now treats non-empty, non-JSON responses as malformed.
- Duplicate validation now requires a `duplicates` array.
- Available-name lookup now accepts Drime's live `available` response key and falls back to the documented `name` key.
- Duplicate validation can send a top-level `parentId` when the plugin has resolved a concrete Drime parent folder for a configured relative path.
- Successful relative-path uploads now remember the returned `fileEntry.parent_id` for later duplicate validation.
- Direct upload and S3 registration now require a `fileEntry` object.
- Multipart create now requires `key` and `uploadId`.
- Part signing now requires a `urls` array.
- Multipart signed URL extraction now rejects non-scalar `url` values before any backup bytes are uploaded.
- Multipart completion now requires `location`.
- Resume lookup now requires a `parts` array.
- Multipart active state stores the S3 `key`, Drime `uploadId`, completed part metadata, local filename, remote name, queue signature, and timestamps.
- Presigned part URLs are not persisted.
- Resume asks Drime for uploaded parts, skips already completed part numbers, uploads missing parts, completes the multipart upload, and creates the S3 entry.
- Admin recovery can clear local active upload state without exposing raw multipart state in the UI.
- Admin recovery and stale active-state recovery now abort the remote multipart upload when the active state includes a Drime `key` and `uploadId`.
- Multipart chunk size is configurable from 5 MB to 256 MB and defaults to 32 MB. The 32 MB recommendation remains conservative until larger part-size validation has repeated live evidence. The uploader checks PHP memory before reading a part so oversized settings fail gracefully instead of exhausting memory.
- The uploader now runs a JSON API token preflight before any duplicate validation or file-byte upload.
- Malformed response handling now records a redacted diagnostics event and returns `WP_Error`.
- HTTP `429` rate-limit responses return `alynt_drime_api_error` with status `429`; upload preflight failures stop before duplicate validation or byte upload.
- Remote retention uses the live-verified `POST /file-entries/delete` endpoint with `deleteForever=false` and a single plugin-owned Drime file entry ID per request. No production code path sends permanent remote deletion.

## Live Token Findings

Live API checks were run on 2026-06-20 from `plugin-tester.local` using a user-provided Drime bearer token. The token is intentionally not recorded here.

- `GET /drive/file-entries?workspaceId=0&perPage=1` succeeded and returned a paginated response with keys including `current_page`, `data`, `per_page`, and `total`.
- `POST /uploads/validate?workspaceId=0` succeeded and returned `errors`, `duplicates`, and `status`.
- `POST /entry/getAvailableName` returned `available`, not the documented `name` key.
- Direct small-file upload via `POST /uploads` succeeded for a real WPvivid database backup using `relativePath=/Alynt WPvivid Test Backups/plugin-tester.local`.
- Direct upload response included `status` and `fileEntry`; the uploaded file entry included `id`, `name`, `file_size`, `parent_id`, `workspace_id`, `extension`, and path metadata.
- After relative-path upload, Drime returned a concrete `parent_id` for the created/used folder, while `backup_relative_path` was `null`.
- Duplicate validation did not detect the uploaded file when `relativePath` was sent inside each file object, when `relativePath` was sent at the top level, or when `parentId` was sent inside each file object.
- Duplicate validation did detect the uploaded file when the resolved `parentId` was sent at the top level with `files` containing only `name` and `size`.
- `getAvailableName` returned a suffixed `available` name for both an existing filename and a random unique filename, even when called with the resolved `parentId`; do not treat it as a reliable duplicate detector without more evidence.
- The plugin now stores resolved relative-path parent folder IDs in `alynt_drime_backups_drime_locations`.
- LocalWP duplicate-skip validation confirmed that a copied backup with the same basename queued on the second stable scan, then returned `alynt_drime_duplicate_skipped` without creating another uploaded registry entry when the cached top-level `parentId` was used.
- Live multipart upload through the plugin uploader succeeded for a temporary 5,373,952-byte `.zip` fixture. Drime returned a multipart key and S3 entry `fileEntry` id `759842062` under parent folder id `759829073`.
- Live interrupted multipart resume succeeded for a temporary 10,551,296-byte `.zip` fixture. The first part was manually uploaded through the plugin Drime client, `get-uploaded-parts` returned one uploaded part before resume, the plugin uploader reused the same multipart key, uploaded the remaining parts, and created S3 entry `fileEntry` id `759845354` under parent folder id `759829073`.
- Live remote abort succeeded for an in-progress multipart upload after one part was uploaded manually. Calling the plugin uploader's clear-active path recorded `manual_active_upload_abort_succeeded` and left active upload state empty.
- Live abortable part-size probing found Drime accepted signed multipart `PUT` uploads with 32 MB and 64 MB parts and returned ETags for both. The 32 MB multipart session aborted cleanly; the 64 MB session accepted the part but the follow-up abort request timed out, so 32 MB is the safer default. Larger 128 MB, 192 MB, and 256 MB settings are allowed for validation but still need fresh LocalWP/Drime E2E evidence before being recommended broadly.
- Live LocalWP/Drime validation on 2026-06-22 found 64 MB, 128 MB, and 192 MB multipart chunk settings uploaded disposable fixtures successfully through the plugin uploader. The 256 MB setting was blocked by the plugin memory guard under the current `plugin-tester.local` PHP `256M` memory limit before a remote file entry was created.
- Repeated selected-base relative-path uploads exposed a stale saved folder-hash hazard: listing children for the saved base hash returned unexpected top-level folders, and duplicate folder creation returned HTTP `422`. The uploader now prefers the cached concrete Drime destination folder ID for the configured relative path before attempting folder browser traversal or folder creation.
- Invalid bearer token against `GET /drive/file-entries?workspaceId=0&perPage=1` returned HTTP `401` with message `Unauthenticated.` and plugin error code `alynt_drime_api_error`.
- Live testing found Drime's direct upload path could still create a file when the plugin reached it with an invalid token, so the uploader now blocks every upload behind the JSON token preflight.
- After that hardening, invalid-token upload retry behavior is verified: the queued item remains queued, attempts increment to `1`, a failed-registry item is recorded with `Unauthenticated.`, active state stays empty, and uploaded registry count does not change.
- Temporary live Drime test files created during multipart and auth/error testing were moved to trash through `POST /file-entries/delete` with `deleteForever=false`; the real WPvivid test backup entry was not deleted.
- Remote retention implementation currently relies on that live trash behavior and limits candidates to plugin-owned uploaded-registry records with a stored `fileEntry.id`.

## Still Unverified

- Malformed multipart create/sign response handling has unit coverage; HTTP `429` rate-limit handling has unit coverage. Broader live malformed payload shapes and live rate-limit payload shapes still need live testing if a safe, non-abusive trigger becomes available.
- Duplicate validation for relative-path-only workflows is not reliable from current live evidence; the plugin now works around this after the first successful relative-path upload by caching and reusing the resolved Drime parent folder ID.
- Direct small-file upload still uses PHP cURL because WordPress HTTP API does not natively model multipart/form-data file uploads cleanly.
- Fresh multipart upload, interrupted multipart resume, remote multipart abort on manual active-state clear, invalid-token auth failure, and invalid-token retry handling are live-validated.
