import { createRouter, createWebHistory } from 'vue-router'

import { t } from '@/i18n'
import { pinia } from '@/stores'
import { useAuthStore } from '@/stores/auth'

import HomeView from '../views/HomeView.vue'

function documentTitle(titleKey: unknown): string {
  const appName = t('app.name')

  if (typeof titleKey !== 'string' || titleKey === 'metadata.titles.home') {
    return appName
  }

  return `${t(titleKey)} · ${appName}`
}

export function updateDocumentTitle(titleKey: unknown): void {
  if (typeof document === 'undefined') {
    return
  }

  document.title = documentTitle(titleKey)
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView,
      meta: { titleKey: 'metadata.titles.home' },
    },
    {
      path: '/tools',
      name: 'tools',
      component: () => import('../views/ToolsView.vue'),
      meta: { titleKey: 'metadata.titles.tools' },
    },
    {
      path: '/pricing',
      name: 'pricing',
      component: () => import('../views/PricingView.vue'),
      meta: { titleKey: 'metadata.titles.pricing' },
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/LoginView.vue'),
      meta: { guestOnly: true, titleKey: 'metadata.titles.login' },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('../views/RegisterView.vue'),
      meta: { guestOnly: true, titleKey: 'metadata.titles.register' },
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('../views/DashboardView.vue'),
      meta: { requiresAuth: true, titleKey: 'metadata.titles.dashboard' },
    },
    {
      path: '/account',
      name: 'account',
      component: () => import('../views/AccountView.vue'),
      meta: { requiresAuth: true, titleKey: 'metadata.titles.account' },
    },
    {
      path: '/media',
      name: 'media-library',
      component: () => import('../views/MediaLibraryView.vue'),
      meta: { requiresAuth: true, titleKey: 'metadata.titles.media' },
    },
    {
      path: '/admin',
      name: 'admin',
      component: () => import('../views/AdminView.vue'),
      meta: { requiresAuth: true, requiresAdmin: true, titleKey: 'metadata.titles.admin' },
    },
    {
      path: '/admin/tools',
      name: 'admin-tools',
      component: () => import('../views/AdminToolsView.vue'),
      meta: { requiresAuth: true, requiresAdmin: true, titleKey: 'metadata.titles.adminTools' },
    },
    {
      path: '/admin/plans',
      name: 'admin-plans',
      component: () => import('../views/AdminPlansView.vue'),
      meta: { requiresAuth: true, requiresAdmin: true, titleKey: 'metadata.titles.adminPlans' },
    },
    {
      path: '/admin/users/:id',
      name: 'admin-user',
      component: () => import('../views/AdminUserView.vue'),
      meta: { requiresAuth: true, requiresAdmin: true, titleKey: 'metadata.titles.adminUser' },
    },
    {
      path: '/admin/unavailable',
      name: 'admin-unavailable',
      component: () => import('../views/AdminUnavailableView.vue'),
      meta: { requiresAuth: true, titleKey: 'metadata.titles.adminUnavailable' },
    },
    {
      path: '/video-enhancer/new',
      name: 'video-enhancer-create',
      component: () => import('../views/NewVideoEnhancerView.vue'),
      meta: { requiresAuth: true, titleKey: 'metadata.titles.videoEnhancer' },
    },
    {
      path: '/product-to-video/new',
      name: 'product-to-video-create',
      component: () => import('../views/NewProductToVideoView.vue'),
      meta: { requiresAuth: true, titleKey: 'metadata.titles.productToVideo' },
    },
    {
      path: '/voiceover/new',
      name: 'voiceover-create',
      component: () => import('../views/VoiceoverCreateView.vue'),
      meta: { requiresAuth: true, titleKey: 'metadata.titles.voiceover' },
    },
    {
      path: '/fashion-try-on/new',
      name: 'fashion-try-on-create',
      component: () => import('../views/FashionTryOnCreateView.vue'),
      meta: { requiresAuth: true, titleKey: 'metadata.titles.fashionTryOn' },
    },
    {
      path: '/motion-video/new',
      name: 'motion-video-create',
      component: () => import('../views/MotionVideoCreateView.vue'),
      meta: { requiresAuth: true, titleKey: 'metadata.titles.motionVideo' },
    },
    {
      path: '/campaigns/new',
      name: 'campaign-create',
      component: () => import('../views/NewCampaignView.vue'),
      meta: { requiresAuth: true, titleKey: 'metadata.titles.campaign' },
    },
    {
      path: '/projects/new',
      name: 'project-create',
      component: () => import('../views/NewProjectWorkspaceView.vue'),
      meta: { requiresAuth: true, titleKey: 'metadata.titles.projectCreate' },
    },
    {
      path: '/projects/:id',
      name: 'project-detail',
      component: () => import('../views/ProjectDetailView.vue'),
      meta: { requiresAuth: true, titleKey: 'metadata.titles.project' },
    },
  ],
  scrollBehavior: (to) => {
    if (to.hash) {
      return { el: to.hash, top: 24 }
    }

    return { top: 0 }
  },
})

router.beforeEach(async (to) => {
  const auth = useAuthStore(pinia)

  await auth.initialize()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return {
      name: 'login',
      query: { redirect: to.fullPath },
    }
  }

  if (to.meta.requiresAdmin && auth.user?.role !== 'admin') {
    return { name: 'admin-unavailable' }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }
})

router.afterEach((to) => {
  updateDocumentTitle(to.meta.titleKey)
})

export default router
