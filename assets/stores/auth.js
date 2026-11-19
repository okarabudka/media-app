import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { api, errorMessage } from '@/api/client'

export const useAuthStore = defineStore('auth', () => {
    const username = ref(null)
    // Whether the session has been checked with the server since the page was loaded.
    const checked = ref(false)
    const sessionError = ref(null)

    const isAuthenticated = computed(() => username.value !== null)

    /**
     * Restores the session after a reload: the cookie is httpOnly, so only the server can tell whether it is valid.
     */
    async function fetchUser() {
        if (checked.value) {
            return
        }

        try {
            const { data } = await api.get('/auth/me', { skipAuthRedirect: true })
            username.value = data.username
            sessionError.value = null
        } catch (error) {
            username.value = null
            sessionError.value = error.response?.status === 401 ? null : errorMessage(error, 'Could not reach the server.')
        } finally {
            checked.value = true
        }
    }

    async function login(user, password) {
        const { data } = await api.post('/auth/login', { username: user, password }, { skipAuthRedirect: true })
        username.value = data.username
        checked.value = true
        sessionError.value = null
    }

    async function logout() {
        try {
            await api.post('/auth/logout', null, { skipAuthRedirect: true })
        } finally {
            clear()
        }
    }

    function clear() {
        username.value = null
        checked.value = true
    }

    return { username, checked, sessionError, isAuthenticated, fetchUser, login, logout, clear }
})
