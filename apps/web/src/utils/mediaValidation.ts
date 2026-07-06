export const MEDIA_MAX_SIZE_BYTES = 50 * 1024 * 1024

export const MEDIA_ACCEPT =
  '.jpg,.jpeg,.png,.webp,.mp4,.mov,.webm,.mp3,.wav,.m4a,.pdf,.txt,.csv'

export const SUPPORTED_MEDIA_MESSAGE =
  'This file type is not supported. Please upload JPG, PNG, WEBP, MP4, MOV, WEBM, MP3, WAV, M4A, PDF, TXT or CSV.'

export const MEDIA_TOO_LARGE_MESSAGE = 'This file is larger than the 50 MB upload limit.'

const allowedExtensions = new Set([
  'jpg',
  'jpeg',
  'png',
  'webp',
  'mp4',
  'mov',
  'webm',
  'mp3',
  'wav',
  'm4a',
  'pdf',
  'txt',
  'csv',
])

const dangerousExtensions = new Set([
  'php',
  'js',
  'html',
  'htm',
  'svg',
  'xml',
  'sh',
  'bat',
  'cmd',
  'exe',
  'jar',
  'zip',
  'rar',
  '7z',
])

export function validateMediaFile(file: File): string | null {
  const segments = file.name.toLowerCase().split('.')
  const extension = segments[segments.length - 1] ?? ''

  if (
    segments.length < 2 ||
    !allowedExtensions.has(extension) ||
    segments.slice(1).some((segment) => dangerousExtensions.has(segment)) ||
    segments.slice(1, -1).some((segment) => allowedExtensions.has(segment))
  ) {
    return SUPPORTED_MEDIA_MESSAGE
  }

  if (file.size > MEDIA_MAX_SIZE_BYTES) {
    return MEDIA_TOO_LARGE_MESSAGE
  }

  return null
}