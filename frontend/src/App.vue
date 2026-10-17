<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import * as api from './api/notes'
import NoteList from './components/NoteList.vue'
import NoteEditor from './components/NoteEditor.vue'
import NoteView from './components/NoteView.vue'

// тут всё состояние приложения и все запросы к API,
// остальные компоненты только показывают данные и сообщают о действиях

// сколько заметок на одной странице списка
const PAGE_SIZE = 12

// список слева
const notes = ref([])
const total = ref(0)
const offset = ref(0)
// search - то, что сейчас в поле ввода, activeSearch - по чему реально искали
const search = ref('')
const activeSearch = ref('')
const loading = ref(false)

// правая панель: empty | view | create | edit
const mode = ref('empty')
const current = ref(null)
const form = reactive({ title: '', content: '' })
// значения формы на момент открытия, по ним понимаем, что есть несохранённые изменения
const original = ref({ title: '', content: '' })
// пока идёт запрос по заметке, кнопки и поля заблокированы
const busy = ref(false)

// сообщения над списком и ошибки под полями формы
const error = ref('')
const message = ref('')
const fieldErrors = ref({})

const isEditing = computed(() => mode.value === 'create' || mode.value === 'edit')
const hasChanges = computed(
  () => isEditing.value && (form.title !== original.value.title || form.content !== original.value.content),
)

function showError(e) {
  // TypeError от fetch = сервер недоступен
  error.value = e instanceof TypeError ? 'Нет связи с сервером.' : e.message
}

function clearMessages() {
  error.value = ''
  message.value = ''
  fieldErrors.value = {}
}

// спрашиваем, только если в форме правда что-то поменяли
function confirmLeave() {
  return !hasChanges.value || confirm('Несохранённые изменения пропадут. Продолжить?')
}

// загружает текущую страницу списка с учётом поиска
async function loadNotes() {
  loading.value = true
  try {
    const result = await api.fetchNotes({ limit: PAGE_SIZE, offset: offset.value, q: activeSearch.value })
    notes.value = result.data
    total.value = result.meta.total
  } catch (e) {
    showError(e)
  } finally {
    loading.value = false
  }
}

function refresh() {
  clearMessages()
  loadNotes()
}

// новый поиск всегда с первой страницы
function runSearch() {
  clearMessages()
  activeSearch.value = search.value.trim()
  offset.value = 0
  loadNotes()
}

// step: -1 назад, 1 вперёд
function changePage(step) {
  clearMessages()
  offset.value = Math.max(0, offset.value + step * PAGE_SIZE)
  loadNotes()
}

// заполняет форму и запоминает исходные значения
function fillForm(title, content) {
  Object.assign(form, { title, content })
  original.value = { title, content }
}

function newNote() {
  if (!confirmLeave()) return
  clearMessages()
  current.value = null
  fillForm('', '')
  mode.value = 'create'
}

async function openNote(note) {
  if (!confirmLeave()) return
  clearMessages()
  busy.value = true
  try {
    // берём заметку с сервера, а не из списка: в списке могла остаться старая версия
    current.value = await api.getNote(note.id)
    mode.value = 'view'
  } catch (e) {
    showError(e)
  } finally {
    busy.value = false
  }
}

function editNote() {
  clearMessages()
  fillForm(current.value.title, current.value.content)
  mode.value = 'edit'
}

function cancelEdit() {
  if (!confirmLeave()) return
  clearMessages()
  mode.value = current.value ? 'view' : 'empty'
}

// создание или обновление, смотря в каком режиме открыта форма
async function saveNote() {
  clearMessages()
  const isNew = mode.value === 'create'
  const data = { title: form.title, content: form.content }

  busy.value = true
  try {
    current.value = isNew ? await api.createNote(data) : await api.updateNote(current.value.id, data)
    mode.value = 'view'
    message.value = isNew ? 'Заметка создана.' : 'Изменения сохранены.'

    if (isNew) {
      // сбрасываем поиск и страницу, чтобы новая заметка была видна в списке
      search.value = ''
      activeSearch.value = ''
      offset.value = 0
    }
    await loadNotes()
  } catch (e) {
    // при 422 в details лежат ошибки по полям, их показываем под инпутами
    fieldErrors.value = e.details || {}
    showError(e)
  } finally {
    busy.value = false
  }
}

async function deleteNote() {
  if (!confirm(`Удалить заметку «${current.value.title}»?`)) return
  clearMessages()
  busy.value = true
  try {
    await api.deleteNote(current.value.id)
    current.value = null
    mode.value = 'empty'
    message.value = 'Заметка удалена.'
    // после удаления возвращаемся на первую страницу
    offset.value = 0
    await loadNotes()
  } catch (e) {
    showError(e)
  } finally {
    busy.value = false
  }
}

onMounted(loadNotes)
</script>

<template>
  <div class="app-shell">
    <header class="topbar">
      <a class="brand" href="/" aria-label="Тихо, главная">
        <span class="brand-mark" aria-hidden="true">т.</span>
        тихо<span class="brand-dot">.</span>
      </a>
      <a class="docs-link" href="/docs/" target="_blank">API</a>
    </header>

    <section class="intro">
      <h1>Мои заметки</h1>
      <button class="button primary" :disabled="busy" @click="newNote">
        <span aria-hidden="true">+</span> Новая заметка
      </button>
    </section>

    <!-- ошибки и сообщения об успехе, закрываются крестиком -->
    <div v-if="error" class="notice error" role="alert">
      <span>{{ error }}</span>
      <button aria-label="Закрыть" @click="error = ''">×</button>
    </div>
    <div v-if="message" class="notice success" role="status">
      <span>{{ message }}</span>
      <button aria-label="Закрыть" @click="message = ''">×</button>
    </div>

    <!-- слева список, справа форма, просмотр заметки или заглушка -->
    <main class="workspace">
      <NoteList
        v-model:search="search"
        :notes="notes"
        :total="total"
        :offset="offset"
        :limit="PAGE_SIZE"
        :loading="loading"
        :busy="busy"
        :selected-id="current?.id"
        :active-search="activeSearch"
        @select="openNote"
        @search="runSearch"
        @page="changePage"
        @refresh="refresh"
      />

      <section class="note-panel">
        <!-- :key пересоздаёт форму при смене режима, иначе фокус на заголовок не ставится -->
        <NoteEditor
          v-if="isEditing"
          :key="mode"
          v-model:title="form.title"
          v-model:content="form.content"
          :is-new="mode === 'create'"
          :changed="hasChanges"
          :busy="busy"
          :errors="fieldErrors"
          @submit="saveNote"
          @cancel="cancelEdit"
        />

        <NoteView
          v-else-if="mode === 'view' && current"
          :note="current"
          :busy="busy"
          @edit="editNote"
          @delete="deleteNote"
        />

        <div v-else class="empty-state">
          <h2>Заметка не выбрана</h2>
          <p>Выберите заметку в списке или создайте новую.</p>
          <button class="button secondary" :disabled="busy" @click="newNote">Создать заметку</button>
        </div>
      </section>
    </main>
  </div>
</template>
