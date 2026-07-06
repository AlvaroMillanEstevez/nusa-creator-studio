import { createRouter, createWebHistory } from 'vue-router'

import HomeView from '../views/HomeView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView,
    },
    {
      path: '/tools',
      name: 'tools',
      component: () => import('../views/ToolsView.vue'),
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('../views/DashboardView.vue'),
    },
    {
      path: '/video-enhancer/new',
      name: 'video-enhancer-create',
      component: () => import('../views/NewVideoEnhancerView.vue'),
    },
    {
      path: '/campaigns/new',
      name: 'campaign-create',
      component: () => import('../views/NewCampaignView.vue'),
    },
    {
      path: '/projects/:id',
      name: 'project-detail',
      component: () => import('../views/ProjectDetailView.vue'),
    },
  ],
  scrollBehavior: () => ({ top: 0 }),
})

export default router
