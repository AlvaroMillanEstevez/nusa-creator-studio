export type ToolStatus = 'available' | 'coming_soon'

export type ToolCategory = 'Campaign creation' | 'Video production' | 'Audio' | 'Fashion'

export type ToolId =
  | 'affiliate_campaign'
  | 'video_enhancer'
  | 'product_to_video'
  | 'ai_voiceover'
  | 'fashion_try_on'
  | 'motion_ai_video'

export interface CreatorTool {
  id: ToolId
  title: string
  short_description: string
  long_description: string
  status: ToolStatus
  category: ToolCategory
  credit_cost_label: string
  route?: `/${string}`
  badge?: string
  recommended?: boolean
}

export const tools = [
  {
    id: 'affiliate_campaign',
    title: 'Affiliate Campaign Generator',
    short_description: 'Generate hooks, TikTok script, caption, hashtags, CTA and filming notes.',
    long_description:
      'Turn a product brief into a complete, structured TikTok affiliate campaign you can film and publish.',
    status: 'available',
    category: 'Campaign creation',
    credit_cost_label: '1 credit',
    route: '/campaigns/new',
    badge: 'Available now',
    recommended: true,
  },
  {
    id: 'video_enhancer',
    title: 'Video Enhancer / Caption Pack',
    short_description:
      'Turn a recorded product video brief into subtitles, cut ideas, captions, CTA and posting text.',
    long_description:
      'Polish recorded product videos with a practical edit plan and a complete posting copy pack.',
    status: 'available',
    category: 'Video production',
    credit_cost_label: '1 credit',
    route: '/video-enhancer/new',
    badge: 'Available now',
  },
  {
    id: 'product_to_video',
    title: 'Product-to-Video Script Pack',
    short_description:
      'Create a storyboard, scene plan, voiceover directions and on-screen text from product details.',
    long_description:
      'Build a production-ready vertical video plan before recording any product footage.',
    status: 'coming_soon',
    category: 'Video production',
    credit_cost_label: 'Credit cost coming soon',
    badge: 'Coming soon',
  },
  {
    id: 'ai_voiceover',
    title: 'AI Voiceover',
    short_description: 'Generate natural product ad voiceovers.',
    long_description:
      'Create clear voice tracks for product campaigns in creator-friendly styles and languages.',
    status: 'coming_soon',
    category: 'Audio',
    credit_cost_label: 'Credit cost coming soon',
    badge: 'Coming soon',
  },
  {
    id: 'fashion_try_on',
    title: 'Fashion Try-On',
    short_description: 'Create try-on visuals for fashion products.',
    long_description:
      'Prepare fashion-focused visual concepts that show products in creator-led try-on scenarios.',
    status: 'coming_soon',
    category: 'Fashion',
    credit_cost_label: 'Credit cost coming soon',
    badge: 'Coming soon',
  },
  {
    id: 'motion_ai_video',
    title: 'Motion AI Video',
    short_description: 'Create motion-based AI videos from product or creator assets.',
    long_description:
      'Transform product or creator assets into short motion-led concepts for vertical campaigns.',
    status: 'coming_soon',
    category: 'Video production',
    credit_cost_label: 'Credit cost coming soon',
    badge: 'Coming soon',
  },
] as const satisfies readonly CreatorTool[]

export const availableTools = tools.filter((tool) => tool.status === 'available')
export const comingSoonTools = tools.filter((tool) => tool.status === 'coming_soon')
