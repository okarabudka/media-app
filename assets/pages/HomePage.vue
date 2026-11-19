<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AppPagination from '@/components/AppPagination.vue'
import MovieList from '@/components/MovieList.vue'
import SearchBar from '@/components/SearchBar.vue'
import { useAuthStore } from '@/stores/auth'
import { useMoviesStore } from '@/stores/movies'

const auth = useAuthStore()
const movies = useMoviesStore()
const router = useRouter()

onMounted(() => movies.load(movies.page))

async function changePage(page) {
    await movies.load(page)
    window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function logout() {
    await auth.logout()
    await router.push({ name: 'login' })
}
</script>

<template>
  <div class="mx-auto max-w-6xl px-4 py-8">
    <header class="mb-8 flex flex-wrap items-center justify-between gap-4">
      <h1 class="text-3xl font-bold">
        <span class="text-tmdb-green">Media</span> App
      </h1>
      <div class="flex items-center gap-4 text-sm">
        <span class="text-slate-400">Signed in as <strong class="text-slate-100">{{ auth.username }}</strong></span>
        <button
          type="button"
          class="rounded-lg bg-slate-800 px-3 py-1.5 hover:bg-slate-700"
          @click="logout"
        >
          Log out
        </button>
      </div>
    </header>

    <SearchBar
      :model-value="movies.query"
      class="mb-6"
      @search="movies.search"
    />

    <p class="mb-4 text-sm text-slate-400">
      <template v-if="movies.query">
        {{ movies.totalResults }} results for “{{ movies.query }}”
      </template>
      <template v-else>
        Popular movies
      </template>
    </p>

    <MovieList
      :movies="movies.movies"
      :loading="movies.loading"
      :error="movies.error"
      class="mb-8"
    />

    <AppPagination
      :page="movies.page"
      :total-pages="movies.totalPages"
      :disabled="movies.loading"
      @change="changePage"
    />

    <footer class="mt-12 text-center text-xs text-slate-500">
      Data from The Movie Database (TMDB). This product uses the TMDB API but is not endorsed or certified by TMDB.
    </footer>
  </div>
</template>
