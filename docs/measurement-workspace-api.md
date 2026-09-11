# Measurement workspace API (v1)

Project-scoped, versioned workspace for manual text, measurements, controlled photo/plan assets, and vector drawings. It creates neither quote positions nor any quote; it performs no OCR, image recognition, handwriting AI, or server-side image processing.

All records and blocks expose stable `uuid`, integer `version`, Unix-second `createdAt`/`updatedAt`, and nullable `deletedAt` tombstones for Flutter/offline sync.

## Endpoints

- `GET /projects/{projectId}/measurement-records` — list non-deleted records.
- `POST /projects/{projectId}/measurement-records` `{title}` — create draft.
- `GET /measurement-records/{id}` — `{record, blocks}`.
- `POST /measurement-records/{id}/blocks` `{type,payload}` — types: `text`, `measurement`, `image`, `plan`, `drawing`.
- `PUT /measurement-records/{recordId}/blocks/{id}` `{payload}` — increments block version.
- `DELETE /measurement-records/{recordId}/blocks/{id}` — soft deletes and returns its tombstone.
- `PUT /measurement-records/{id}/blocks/order` `{blockIds:[...]}` — must contain every live block exactly once.
- `POST /projects/{projectId}/measurement-assets` `{content: base64}` — controlled PNG/JPEG/PDF upload; returns `{assetFileId,mimeType}`.

Image/plan block payloads only accept an `assetFileId` previously created through the controlled upload endpoint *for the same project*, with the persisted MIME type. Arbitrary Nextcloud file IDs are rejected.

Payload examples: `text: {text}`, `measurement: {quantity,unit,label?}`, `drawing: {strokes: [[[x,y],...]]}`. Drawing input is bounded client-side and server-side (100 strokes, 10,000 points); it is vector data only.
