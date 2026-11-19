<script setup>
defineProps({
    page: { type: Number, required: true },
    totalPages: { type: Number, required: true },
    disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['change'])
</script>

<template>
  <nav
    v-if="totalPages > 1"
    aria-label="Pagination"
    class="flex items-center justify-center gap-4"
  >
    <button
      type="button"
      data-test="previous"
      class="rounded-lg bg-slate-800 px-4 py-2 hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
      :disabled="disabled || page <= 1"
      @click="emit('change', page - 1)"
    >
      ← Previous
    </button>
    <span class="text-sm text-slate-400">Page {{ page }} of {{ totalPages }}</span>
    <button
      type="button"
      data-test="next"
      class="rounded-lg bg-slate-800 px-4 py-2 hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
      :disabled="disabled || page >= totalPages"
      @click="emit('change', page + 1)"
    >
      Next →
    </button>
  </nav>
</template>
