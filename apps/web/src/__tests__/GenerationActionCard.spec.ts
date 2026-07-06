import { describe, expect, it } from 'vitest'

import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

import GenerationActionCard from '@/components/GenerationActionCard.vue'
import { useAuthStore } from '@/stores/auth'

describe('GenerationActionCard', () => {
  it('shows zero-charge access for unlimited users and the normal cost for free users', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)
    const auth = useAuthStore(pinia)

    auth.user = {
      id: 1,
      name: 'Internal Creator',
      email: 'internal@example.test',
      role: 'internal_creator',
      role_label: 'Internal Creator',
      plan_key: 'internal_unlimited',
      plan_label: 'Internal Unlimited',
      is_unlimited: true,
      access_label: 'Unlimited internal access',
      monthly_credit_allowance: 20,
      credits_balance: 0,
      created_at: null,
    }

    const wrapper = mount(GenerationActionCard, {
      props: {
        projectType: 'affiliate_campaign',
        hasGeneration: false,
        isGenerating: false,
        error: '',
        remainingCredits: 0,
        creditCost: 3,
      },
      global: { plugins: [pinia] },
    })

    expect(wrapper.text()).toContain('Unlimited access')
    expect(wrapper.text()).toContain('No credit charge for this account')
    expect(wrapper.text()).not.toContain('0 credits remaining')

    auth.user = {
      ...auth.user,
      role: 'free_user',
      role_label: 'Free User',
      plan_key: 'free',
      plan_label: 'Free',
      is_unlimited: false,
      access_label: 'Current plan credits',
      credits_balance: 20,
    }
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('3 credits')
    expect(wrapper.text()).toContain('0 credits remaining')
  })
})
