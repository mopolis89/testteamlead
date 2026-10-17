// все запросы к API заметок в одном месте
const BASE_URL = '/api/notes'

// обёртка над fetch: заголовки, разбор JSON и ошибки
async function request(path = '', options = {}) {
  const headers = { Accept: 'application/json' }
  if (options.body) {
    headers['Content-Type'] = 'application/json'
  }

  const response = await fetch(BASE_URL + path, { ...options, headers })
  // у DELETE ответ 204 без тела, разбирать нечего
  if (response.status === 204) {
    return null
  }

  // API всегда отвечает JSON-ом, если пришло что-то другое, значит сломалось на сервере
  let data
  try {
    data = await response.json()
  } catch {
    throw new Error('Некорректный ответ сервера.')
  }

  // ошибку API превращаем в Error, status и details потом пригодятся в App.vue
  if (!response.ok) {
    const error = new Error(data.error?.message || 'Ошибка запроса.')
    error.status = response.status
    error.details = data.error?.details || {}
    throw error
  }

  return data
}

// список: { data: [...], meta: { total, limit, offset } }
export function fetchNotes({ limit, offset, q }) {
  const params = new URLSearchParams({ limit, offset })
  if (q) {
    params.set('q', q)
  }
  return request(`?${params}`)
}

// сервер отдаёт заметку в виде { data: {...} }, наружу возвращаем сразу саму заметку
export async function getNote(id) {
  const { data } = await request(`/${id}`)
  return data
}

export async function createNote(note) {
  const { data } = await request('', { method: 'POST', body: JSON.stringify(note) })
  return data
}

export async function updateNote(id, note) {
  const { data } = await request(`/${id}`, { method: 'PUT', body: JSON.stringify(note) })
  return data
}

export function deleteNote(id) {
  return request(`/${id}`, { method: 'DELETE' })
}
