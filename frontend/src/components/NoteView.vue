<script setup>
import { formatDate } from '../utils/format'

// просмотр одной заметки. кнопки только сообщают в App.vue, что нажали

defineProps({
  note: { type: Object, required: true },
  busy: Boolean,
})

const emit = defineEmits(['edit', 'delete'])
</script>

<template>
  <article class="note-detail">
    <div class="panel-topline">
      <p class="panel-label">Заметка #{{ note.id }}</p>
      <div class="detail-actions">
        <button class="text-button" :disabled="busy" @click="emit('edit')">Редактировать</button>
        <button class="text-button danger" :disabled="busy" @click="emit('delete')">Удалить</button>
      </div>
    </div>

    <h2>{{ note.title }}</h2>
    <p class="detail-date">Изменено {{ formatDate(note.updated_at) }}</p>

    <!-- текст выводится обычной интерполяцией, так что html внутри заметки покажется просто текстом -->
    <div class="note-body" :class="{ muted: !note.content }">{{ note.content || 'Нет текста' }}</div>

    <p class="created-date">Создано {{ formatDate(note.created_at) }}</p>
  </article>
</template>
