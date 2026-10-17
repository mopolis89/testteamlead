<script setup>
import { onMounted, ref } from 'vue'

// форма создания и редактирования. title и content живут в App.vue и приходят сюда через v-model

defineProps({
  isNew: Boolean,
  changed: Boolean,
  busy: Boolean,
  errors: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['submit', 'cancel'])

const title = defineModel('title', { type: String, required: true })
const content = defineModel('content', { type: String, required: true })

const titleInput = ref(null)

// как только форма появилась, ставим курсор в заголовок
onMounted(() => titleInput.value.focus())
</script>

<template>
  <form class="editor" @submit.prevent="emit('submit')">
    <div class="panel-topline">
      <p class="panel-label">{{ isNew ? 'Новая заметка' : 'Редактирование' }}</p>
      <span v-if="changed" class="edit-state">Есть несохранённые изменения</span>
    </div>

    <!-- под каждым полем место для ошибки, которую вернул сервер -->
    <label for="title">Заголовок</label>
    <input id="title" ref="titleInput" v-model="title" maxlength="200" :disabled="busy" required />
    <div class="field-error">{{ errors.title }}</div>

    <label for="content">Текст</label>
    <textarea id="content" v-model="content" maxlength="20000" :disabled="busy"></textarea>
    <div class="field-error">{{ errors.content }}</div>

    <div class="editor-actions">
      <button class="button primary" type="submit" :disabled="busy">
        {{ busy ? 'Сохранение...' : 'Сохранить' }}
      </button>
      <button class="button ghost" type="button" :disabled="busy" @click="emit('cancel')">Отмена</button>
    </div>
  </form>
</template>
