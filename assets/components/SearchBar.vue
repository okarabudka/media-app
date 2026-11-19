<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { MAX_QUERY_LENGTH, MIN_QUERY_LENGTH } from '@/stores/movies'

const props = defineProps({
    modelValue: { type: String, default: '' },
    debounce: { type: Number, default: 400 },
})

const emit = defineEmits(['search'])

const text = ref(props.modelValue)
let timer = null

const tooShort = computed(() => {
    const length = text.value.trim().length

    return length > 0 && length < MIN_QUERY_LENGTH
})

function cancelPending() {
    clearTimeout(timer)
    timer = null
}

// Waits until typing pauses; one character never triggers a search, an empty field returns to popular movies.
function onInput() {
    cancelPending()
    const term = text.value.trim()
    if (term.length > 0 && term.length < MIN_QUERY_LENGTH) {
        return
    }
    timer = setTimeout(() => emit('search', term), props.debounce)
}

function clear() {
    cancelPending()
    text.value = ''
    emit('search', '')
}

onBeforeUnmount(cancelPending)
</script>

<template>
  <div class="w-full">
    <label
      for="search"
      class="sr-only"
    >Search movies</label>
    <div class="relative">
      <input
        id="search"
        v-model="text"
        type="search"
        :maxlength="MAX_QUERY_LENGTH"
        autocomplete="off"
        placeholder="Search movies by title…"
        class="w-full rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 pr-12 text-slate-100 placeholder-slate-500 focus:border-tmdb-blue focus:outline-none"
        @input="onInput"
      >
      <button
        v-if="text !== ''"
        type="button"
        aria-label="Clear search"
        class="absolute top-1/2 right-3 -translate-y-1/2 rounded-full px-2 text-xl text-slate-400 hover:text-slate-100"
        @click="clear"
      >
        ×
      </button>
    </div>
    <p
      v-if="tooShort"
      class="mt-2 text-sm text-slate-400"
    >
      Type at least {{ MIN_QUERY_LENGTH }} characters.
    </p>
  </div>
</template>
