import { watch } from 'vue'
import { createApp } from 'vue'

import App from './App.vue'
import { i18n, syncLocaleFromUser } from './i18n'
import router from './router'
import { pinia } from './stores'
import { useAuthStore } from './stores/auth'
import './assets/main.css'

const app = createApp(App)

app.use(pinia)
app.use(i18n)

const auth = useAuthStore(pinia)

void auth.initialize().then(() => {
  syncLocaleFromUser(auth.user?.preferred_locale)
})

watch(
  () => auth.user?.preferred_locale,
  (preferredLocale) => {
    syncLocaleFromUser(preferredLocale)
  },
)

app.use(router)
app.mount('#app')
