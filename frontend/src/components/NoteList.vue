<script setup>
import { computed } from 'vue'
import { formatDate } from '../utils/format'

// список слева: поиск, карточки заметок и страницы.
// сам ничего не загружает, только отправляет события в App.vue

const props = defineProps({
  notes: { type: Array, required: true },
  total: { type: Number, default: 0 },
  offset: { type: Number, default: 0 },
  limit: { type: Number, required: true },
  loading: Boolean,
  busy: Boolean,
  selectedId: { type: Number, default: null },
  activeSearch: { type: String, default: '' },
})

const emit = defineEmits(['select', 'search', 'page', 'refresh'])

// строка поиска, в App.vue подключена через v-model:search
const search = defineModel('search', { type: String, default: '' })

// для подписи вида "1–12 из 30"
const pageEnd = computed(() => Math.min(props.offset + props.limit, props.total))
const isLastPage = computed(() => props.offset + props.limit >= props.total)
</script>

<template>
  <aside class="sidebar">
    <div class="list-heading">
      <h2>Все заметки</h2>
      <span class="count">{{ total }}</span>
      <button
        class="refresh"
        :disabled="loading"
        title="Обновить"
        aria-label="Обновить список"
        @click="emit('refresh')"
      >
        ↻
      </button>
    </div>

    <!-- поиск запускается по Enter или по кнопке -->
    <form class="search" @submit.prevent="emit('search')">
      <input id="search" v-model="search" type="search" placeholder="Поиск" aria-label="Поиск по заметкам" />
      <button type="submit" aria-label="Найти">⌕</button>
    </form>

    <div v-if="loading" class="list-message">Загрузка...</div>
    <div v-else-if="!notes.length" class="list-message">
      {{ activeSearch ? 'Ничего не найдено' : 'Заметок пока нет' }}
    </div>
    <ul v-else class="note-list">
      <li v-for="note in notes" :key="note.id">
        <button
          class="note-card"
          :class="{ active: note.id === selectedId }"
          :disabled="busy"
          @click="emit('select', note)"
        >
          <span class="note-title">{{ note.title }}</span>
          <span class="note-preview">{{ note.content || 'Без текста' }}</span>
          <span class="note-date">{{ formatDate(note.updated_at) }}</span>
        </button>
      </li>
    </ul>

    <!-- страницы показываем, только если заметки не влезают на одну -->
    <nav v-if="total > limit" class="pagination">
      <button :disabled="offset === 0 || loading" aria-label="Предыдущая страница" @click="emit('page', -1)">←</button>
      <span>{{ offset + 1 }}–{{ pageEnd }} из {{ total }}</span>
      <button :disabled="isLastPage || loading" aria-label="Следующая страница" @click="emit('page', 1)">→</button>
    </nav>
  </aside>
</template>
