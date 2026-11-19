import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import { createAppRouter } from './router'
import { setUnauthorizedHandler } from './api/client'
import { useAuthStore } from './stores/auth'
import './styles/app.css'

const app = createApp(App)
const pinia = createPinia()
app.use(pinia)

const router = createAppRouter()
app.use(router)

// Any 401 from the API means the cookie is gone or expired: forget the user and go to the login page.
setUnauthorizedHandler(() => {
    useAuthStore(pinia).clear()
    const current = router.currentRoute.value
    if (current.name !== 'login') {
        router.push({ name: 'login', query: { redirect: current.fullPath } })
    }
})

app.mount('#app')
