// один форматтер на всё приложение, создавать новый на каждую дату незачем
const dateFormat = new Intl.DateTimeFormat('ru', {
  day: 'numeric',
  month: 'long',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
})

// дата приходит в UTC, показываем в часовом поясе браузера: "17 октября 2026 г. в 12:30"
export function formatDate(value) {
  return dateFormat.format(new Date(value))
}
