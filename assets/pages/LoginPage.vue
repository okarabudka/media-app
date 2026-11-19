<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { errorMessage } from '@/api/client'
import { safeRedirect } from '@/router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const username = ref('')
const password = ref('')
const submitting = ref(false)
const error = ref(auth.sessionError)

async function submit() {
    submitting.value = true
    error.value = null
    try {
        await auth.login(username.value, password.value)
        await router.push(safeRedirect(route.query.redirect))
    } catch (e) {
        error.value = errorMessage(e, 'Login failed. Please try again.')
    } finally {
        submitting.value = false
    }
}
</script>

<template>
  <main class="flex min-h-screen items-center justify-center bg-tmdb-navy px-4">
    <form
      class="w-full max-w-sm space-y-5 rounded-2xl bg-slate-900 p-8 shadow-2xl"
      @submit.prevent="submit"
    >
      <h1 class="text-2xl font-bold">
        Media App
      </h1>
      <p class="text-sm text-slate-400">
        Sign in to browse and search movies.
      </p>
      <div
        v-if="error"
        role="alert"
        class="rounded-lg bg-red-950/60 px-3 py-2 text-sm text-red-200"
      >
        {{ error }}
      </div>
      <label class="block">
        <span class="text-sm text-slate-300">Username</span>
        <input
          v-model="username"
          name="username"
          autocomplete="username"
          required
          class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 focus:border-tmdb-blue focus:outline-none"
        >
      </label>
      <label class="block">
        <span class="text-sm text-slate-300">Password</span>
        <input
          v-model="password"
          name="password"
          type="password"
          autocomplete="current-password"
          required
          class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 focus:border-tmdb-blue focus:outline-none"
        >
      </label>
      <button
        type="submit"
        :disabled="submitting"
        class="w-full rounded-lg bg-tmdb-blue py-2 font-semibold text-tmdb-navy hover:opacity-90 disabled:opacity-50"
      >
        {{ submitting ? 'Signing in…' : 'Sign in' }}
      </button>
    </form>
  </main>
</template>
