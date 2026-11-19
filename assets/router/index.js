import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import HomePage from '@/pages/HomePage.vue'
import LoginPage from '@/pages/LoginPage.vue'
import NotFoundPage from '@/pages/NotFoundPage.vue'

export const routes = [
    { path: '/', name: 'home', component: HomePage, meta: { requiresAuth: true } },
    { path: '/login', name: 'login', component: LoginPage, meta: { guestOnly: true } },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFoundPage },
]

/**
 * Only same-app paths are allowed as a post-login target ("//evil.test" would leave the site).
 */
export function safeRedirect(target) {
    return typeof target === 'string' && target.startsWith('/') && !target.startsWith('//') ? target : '/'
}

export function createAppRouter(history = createWebHistory()) {
    const router = createRouter({ history, routes })

    router.beforeEach(async (to) => {
        const auth = useAuthStore()
        await auth.fetchUser()

        if (to.meta.requiresAuth && !auth.isAuthenticated) {
            return { name: 'login', query: { redirect: to.fullPath } }
        }
        if (to.meta.guestOnly && auth.isAuthenticated) {
            return { name: 'home' }
        }

        return true
    })

    return router
}
