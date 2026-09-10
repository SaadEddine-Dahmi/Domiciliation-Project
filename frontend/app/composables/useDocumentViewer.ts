import { readonly, ref } from 'vue'
import { apiBase } from '~/services/http'

interface DocumentViewPayload {
  id: number
  name?: string
  extension?: string
  redirectTo?: string
}

interface ViewerState {
  open: boolean
  loading: boolean
  url: string | null
  title: string
  contentType: string
  isPdf: boolean
  isImage: boolean
}

const state = ref<ViewerState>({
  open: false,
  loading: false,
  url: null,
  title: 'Document',
  contentType: '',
  isPdf: false,
  isImage: false,
})

function token(): string {
  if (!import.meta.client) return ''

  try {
    return JSON.parse(localStorage.getItem('app_auth') ?? '{}')?.token ?? ''
  } catch {
    return ''
  }
}

function streamUrl(id: number, action: 'preview' | 'download'): string {
  const sep = '?'
  return `${apiBase()}/api/documents/${id}/${action}${sep}token=${encodeURIComponent(token())}`
}

function filenameFromContentDisposition(cd: string | null): string {
  if (!cd) return ''
  const utf = cd.match(/filename\*\s*=\s*UTF-8''([^;]+)/i)
  if (utf?.[1]) return decodeURIComponent(utf[1])
  const ascii = cd.match(/filename\s*=\s*"([^"]+)"|filename\s*=\s*([^;]+)/i)
  return (ascii?.[1] || ascii?.[2] || '').trim().replace(/^"|"$/g, '')
}

function extensionFromMime(mime: string): string {
  if (mime.includes('pdf')) return 'pdf'
  if (mime.includes('png')) return 'png'
  if (mime.includes('jpeg') || mime.includes('jpg')) return 'jpg'
  if (mime.includes('msword')) return 'doc'
  if (mime.includes('officedocument.wordprocessingml.document')) return 'docx'
  return 'bin'
}

function safeFilename(payload: DocumentViewPayload, contentType: string, disposition: string | null): string {
  const fromHeader = filenameFromContentDisposition(disposition)
  if (fromHeader) return fromHeader

  const ext = String(payload.extension || '').toLowerCase() || extensionFromMime(contentType)
  const base = String(payload.name || `document-${payload.id}`).replace(/[\\/:*?"<>|]/g, '-')
  return base.includes('.') ? base : `${base}.${ext}`
}

function clearUrl(): void {
  if (state.value.url) URL.revokeObjectURL(state.value.url)
  state.value.url = null
}

export function useDocumentViewer() {
  const router = useRouter()
  const { error: toastError } = useToast()

  async function handleError(response: Response, redirectTo?: string): Promise<void> {
    let message = 'Impossible d ouvrir le document.'
    try {
      const data = await response.clone().json()
      message = data?.message || message
    } catch {}

    if (response.status === 404) {
      message = 'Ce document est introuvable ou a ete supprime.'
    } else if (response.status === 401 || response.status === 403) {
      message = 'Vous n avez pas acces a ce document.'
    }

    toastError?.(message)
    if (redirectTo) await router.push(redirectTo)
  }

  async function openPreview(payload: DocumentViewPayload): Promise<void> {
    state.value.open = true
    state.value.loading = true
    state.value.title = payload.name || 'Document'
    clearUrl()

    try {
      const response = await fetch(streamUrl(payload.id, 'preview'), { method: 'GET' })
      if (!response.ok) {
        state.value.open = false
        await handleError(response, payload.redirectTo)
        return
      }

      const contentType = (response.headers.get('content-type') || '').toLowerCase()
      if (contentType.includes('application/json') || contentType.includes('text/html') || contentType.includes('text/plain')) {
        state.value.open = false
        await handleError(response, payload.redirectTo)
        return
      }

      const blob = await response.blob()
      state.value.url = URL.createObjectURL(blob)
      state.value.contentType = contentType
      state.value.isPdf = contentType.includes('application/pdf') || payload.extension === 'pdf'
      state.value.isImage = contentType.startsWith('image/')
    } catch {
      state.value.open = false
      toastError?.('Impossible d ouvrir le document.')
    } finally {
      state.value.loading = false
    }
  }

  async function downloadDocument(payload: DocumentViewPayload): Promise<void> {
    try {
      const response = await fetch(streamUrl(payload.id, 'download'), { method: 'GET' })
      if (!response.ok) {
        await handleError(response, payload.redirectTo)
        return
      }

      const contentType = (response.headers.get('content-type') || '').toLowerCase()
      if (contentType.includes('application/json') || contentType.includes('text/html') || contentType.includes('text/plain')) {
        await handleError(response, payload.redirectTo)
        return
      }

      const blob = await response.blob()
      const blobUrl = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = blobUrl
      a.download = safeFilename(payload, contentType, response.headers.get('content-disposition'))
      document.body.appendChild(a)
      a.click()
      a.remove()
      URL.revokeObjectURL(blobUrl)
    } catch {
      toastError?.('Erreur de telechargement.')
    }
  }

  function close(): void {
    state.value.open = false
    clearUrl()
  }

  return {
    state: readonly(state),
    openPreview,
    downloadDocument,
    close,
  }
}
