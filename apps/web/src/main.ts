import { createApp } from 'vue'

import App from './App.vue'
import router from './router'
import { pinia } from './stores'
import { useAuthStore } from './stores/auth'
import './assets/main.css'

const app = createApp(App)

app.use(pinia)

const auth = useAuthStore(pinia)
void auth.initialize()

app.use(router)
app.mount('#app')
