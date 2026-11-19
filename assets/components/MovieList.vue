<script setup>
import MovieCard from './MovieCard.vue'

defineProps({
    movies: { type: Array, required: true },
    loading: { type: Boolean, default: false },
    error: { type: String, default: null },
})

const SKELETON_CARDS = 8
</script>

<template>
  <div
    v-if="error"
    role="alert"
    class="rounded-xl border border-red-800 bg-red-950/50 p-6 text-red-200"
  >
    {{ error }}
  </div>
  <div
    v-else-if="loading && movies.length === 0"
    class="grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-4"
    aria-busy="true"
  >
    <div
      v-for="n in SKELETON_CARDS"
      :key="n"
      data-test="skeleton"
      class="aspect-[2/3] animate-pulse rounded-xl bg-slate-800"
    />
  </div>
  <p
    v-else-if="movies.length === 0"
    class="py-16 text-center text-slate-400"
  >
    No movies found.
  </p>
  <div
    v-else
    class="grid grid-cols-2 gap-6 transition-opacity sm:grid-cols-3 lg:grid-cols-4"
    :class="{ 'opacity-50': loading }"
    :aria-busy="loading"
  >
    <MovieCard
      v-for="movie in movies"
      :key="movie.id"
      :movie="movie"
    />
  </div>
</template>
