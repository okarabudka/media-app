<script setup>
defineProps({
    movie: { type: Object, required: true },
})
</script>

<template>
  <article class="flex flex-col overflow-hidden rounded-xl bg-slate-900 shadow-lg ring-1 ring-slate-800">
    <img
      v-if="movie.posterUrl"
      :src="movie.posterUrl"
      :alt="`Poster of ${movie.title}`"
      loading="lazy"
      class="aspect-[2/3] w-full object-cover"
    >
    <div
      v-else
      data-test="no-poster"
      class="flex aspect-[2/3] w-full items-center justify-center bg-slate-800 text-sm text-slate-500"
    >
      No poster
    </div>
    <div class="flex flex-1 flex-col gap-2 p-4">
      <div class="flex items-start justify-between gap-2">
        <h2 class="font-semibold leading-tight">
          {{ movie.title }}
        </h2>
        <span
          v-if="movie.voteCount > 0"
          class="shrink-0 rounded-md bg-tmdb-navy px-2 py-0.5 text-sm font-semibold text-tmdb-green"
          :title="`${movie.voteCount} votes`"
        >★ {{ movie.rating.toFixed(1) }}</span>
      </div>
      <p class="text-sm text-slate-400">
        {{ movie.releaseYear ?? 'Release date unknown' }}
      </p>
      <p class="line-clamp-4 text-sm text-slate-300">
        {{ movie.overview || 'No description available.' }}
      </p>
    </div>
  </article>
</template>
