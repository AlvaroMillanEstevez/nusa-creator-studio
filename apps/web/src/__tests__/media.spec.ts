import { afterEach, describe, expect, it, vi } from 'vitest'

import { API_BASE_URL } from '@/services/api'
import { deleteMediaAsset, uploadMediaAsset } from '@/services/media'
import type { MediaAsset } from '@/types/media'
import {
  MEDIA_TOO_LARGE_MESSAGE,
  SUPPORTED_MEDIA_MESSAGE,
  validateMediaFile,
} from '@/utils/mediaValidation'

const asset: MediaAsset = {
  id: 12,
  user_id: 1,
  project_id: 42,
  type: 'image',
  disk: 'local',
  storage_identifier: 'media-12',
  original_name: 'product.png',
  mime_type: 'image/png',
  size_bytes: 68,
  size_label: '68 B',
  metadata: {
    detected_extension: 'png',
    uploaded_extension: 'png',
    validation_source: 'server',
  },
  created_at: '2026-07-06T00:00:00.000000Z',
  updated_at: '2026-07-06T00:00:00.000000Z',
}

describe('media service and validation', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('sends uploads as multipart form data without setting a JSON content type', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: asset }), {
        status: 201,
        headers: { 'Content-Type': 'application/json' },
      }),
    )
    vi.stubGlobal('fetch', fetchMock)

    const file = new File(['png-content'], 'product.png', { type: 'image/png' })
    const uploaded = await uploadMediaAsset(file, 42)

    expect(uploaded.id).toBe(12)

    const call = fetchMock.mock.calls[0]
    const options = call?.[1]
    const body = options?.body as FormData
    const headers = options?.headers as Headers

    expect(call?.[0]).toBe(API_BASE_URL + '/media-assets')
    expect(options?.method).toBe('POST')
    expect(body).toBeInstanceOf(FormData)
    expect(body.get('file')).toBe(file)
    expect(body.get('project_id')).toBe('42')
    expect(headers.has('Content-Type')).toBe(false)
  })

  it('calls the correct delete endpoint', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ message: 'Media asset deleted successfully.' }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )
    vi.stubGlobal('fetch', fetchMock)

    await deleteMediaAsset(12)

    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/media-assets/12',
      expect.objectContaining({ method: 'DELETE' }),
    )
  })

  it.each(['payload.php', 'script.js', 'page.html', 'vector.svg', 'run.sh', 'archive.zip'])(
    'rejects the dangerous extension %s before upload',
    (name) => {
      const file = new File(['unsafe'], name)

      expect(validateMediaFile(file)).toBe(SUPPORTED_MEDIA_MESSAGE)
    },
  )

  it('rejects double extensions and files over 50 MB', () => {
    const doubleExtension = new File(['unsafe'], 'image.png.php')
    const oversized = new File(['x'], 'large.mp4')
    Object.defineProperty(oversized, 'size', { value: 50 * 1024 * 1024 + 1 })

    expect(validateMediaFile(doubleExtension)).toBe(SUPPORTED_MEDIA_MESSAGE)
    expect(validateMediaFile(oversized)).toBe(MEDIA_TOO_LARGE_MESSAGE)
  })
})