export type MediaAssetType = 'image' | 'video' | 'audio' | 'document'
export type MediaAssetUsageRole =
  | 'general'
  | 'product_image'
  | 'source_video'
  | 'audio_track'
  | 'model_reference'
  | 'garment_reference'
  | 'background_reference'
  | 'brand_reference'

export interface MediaAsset {
  id: number
  user_id: number
  project_id: number | null
  type: MediaAssetType
  usage_role: MediaAssetUsageRole | null
  disk: string
  storage_identifier: string
  original_name: string | null
  mime_type: string | null
  size_bytes: number | null
  size_label: string | null
  metadata: {
    detected_extension?: string
    uploaded_extension?: string
    validation_source?: 'server'
  } | null
  created_at: string | null
  updated_at: string | null
}

export interface MediaAssetListResponse {
  data: MediaAsset[]
  links?: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }
  meta?: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export interface MediaAssetResponse {
  data: MediaAsset
}

export interface MediaAssetFilters {
  project_id?: number | string
  type?: MediaAssetType
  usage_role?: MediaAssetUsageRole
}

export interface DeleteMediaAssetResponse {
  message: string
}