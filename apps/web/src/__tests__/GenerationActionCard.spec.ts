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
      plan_key: 'internal_unlimited',
      is_unlimited: true,
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
      },
      global: { plugins: [pinia] },
    })

    expect(wrapper.text()).toContain('Unlimited access')
    expect(wrapper.text()).toContain('No credit charge for this account')
    expect(wrapper.text()).not.toContain('0 credits remaining')

    auth.user = {
      ...auth.user,
      role: 'free_user',
      plan_key: 'free',
      is_unlimited: false,
      credits_balance: 20,
    }
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('1 credit')
    expect(wrapper.text()).toContain('0 credits remaining')
  })
})
