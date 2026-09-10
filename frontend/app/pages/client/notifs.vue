<!-- pages/client/notifs.vue -->
<!--
  Client-facing notifications page.

  This is the client-side counterpart to the admin/domiciliataire
  notifications page — that page additionally surfaces a reminder-schedule
  info card for contract renewals, which isn't relevant to a client account
  and is intentionally left out here.

  GET /api/notifications is already scoped server-side to the authenticated
  user, so this page consumes it directly with no role branching.

  Two things beyond a generic notification list:
    1. A small type-based icon tile instead of a plain colored dot, matching
       the design mock (document / calendar / invoice / account glyphs).
       These are STYLED PLACEHOLDERS keyed off notification `type`, not real
       thumbnails of an uploaded file — real file preview thumbnails would
       need server-side image rendering that isn't in place yet. Opening the
       actual file is handled by the "Voir le document" link below instead.
    2. A "Voir le document" link on `document_uploaded` notifications, using
       `data.document_id` to open the existing public preview route,
       GET /api/documents/{id}/preview — same token-in-query pattern already
       used elsewhere in the client dashboard.
-->
<script setup lang="ts">
import { useNotificationsStore } from '~/stores/notifs'

definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const { success, error: toastError } = useToast()
const notifsStore = useNotificationsStore()
const router = useRouter()
const documentViewer = useDocumentViewer()

/** Base URL for API calls, from runtime config. */
function getApiBase(): string {
  const config = useRuntimeConfig()
  return (config.public.apiBase as string) ?? ''
}

/** Bearer auth header built from the locally stored session. */
function authHeaders(): Record<string, string> {
  if (!import.meta.client) return {}
  try {
    const raw = localStorage.getItem('app_auth')
    if (!raw) return {}
    const parsed = JSON.parse(raw)
    return parsed?.token ? { Authorization: `Bearer ${parsed.token}` } : {}
  } catch {
    return {}
  }
}

/** Raw token string, used for links that must carry auth in the query
 *  string (e.g. opening a document preview in a new tab, where a
 *  standard Authorization header can't be attached). */
function getRawToken(): string {
  if (!import.meta.client) return ''
  try {
    const raw = localStorage.getItem('app_auth')
    if (!raw) return ''
    const parsed = JSON.parse(raw)
    return parsed?.token ?? ''
  } catch {
    return ''
  }
}

/** Appends `?token=...` (or `&token=...`) to a URL for links opened
 *  outside of $fetch, where headers can't be set. */
function withToken(url: string): string {
  const token = getRawToken()
  if (!token) throw new Error('Missing token')
  const sep = url.includes('?') ? '&' : '?'
  return `${url}${sep}token=${encodeURIComponent(token)}`
}

// ---------------------------------------------------------------------
// State
// ---------------------------------------------------------------------

const notifications = ref<any[]>([])
const loading        = ref(true)
const search         = ref('')
const filter         = ref<'all' | 'unread' | 'read'>('all')
const expandedId     = ref<number | null>(null)

function toggleExpand(id: number): void {
  const n = notifications.value.find(x => x.id === id)
  if (n && !n.is_read) {
    markRead(id)
  }
  expandedId.value = expandedId.value === id ? null : id
}

/** Loads the current user's notifications. */
async function loadAll(): Promise<void> {
  loading.value = true
  try {
    const res = await $fetch<{ success: boolean; data: any[] }>(
      `${getApiBase()}/api/notifications`,
      { headers: authHeaders() }
    )
    notifications.value = res.data ?? []
    await notifsStore.refreshUnreadCount()
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur lors du chargement des notifications')
  } finally {
    loading.value = false
  }
}

const unreadCount = computed(() => notifications.value.filter(n => !n.is_read).length)

/** Applies the active tab filter (all / unread / read) and the search box,
 *  matching against the notification title and message text. */
const filtered = computed(() => {
  let list = notifications.value

  if (filter.value === 'unread') list = list.filter(n => !n.is_read)
  if (filter.value === 'read')   list = list.filter(n => n.is_read)

  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter(n =>
      (n.subject ?? '').toLowerCase().includes(q) ||
      (n.message ?? '').toLowerCase().includes(q)
    )
  }

  return list
})

/** Marks a single notification as read, optimistically updating local state. */
async function markRead(id: number): Promise<void> {
  const n = notifications.value.find(x => x.id === id)
  const wasUnread = n && !n.is_read
  try {
    await $fetch(`${getApiBase()}/api/notifications/${id}/read`, {
      method: 'POST',
      headers: authHeaders(),
    })
    if (n) n.is_read = true
    if (wasUnread) notifsStore.markOneRead()
  } catch {
    toastError?.('Impossible de marquer cette notification comme lue')
  }
}

/** Marks every notification as read in one call. */
async function markAllRead(): Promise<void> {
  try {
    await $fetch(`${getApiBase()}/api/notifications/read-all`, {
      method: 'POST',
      headers: authHeaders(),
    })
    notifications.value.forEach(n => { n.is_read = true })
    notifsStore.markAllReadLocally()
    success('Toutes les notifications ont été marquées comme lues')
  } catch {
    toastError?.('Impossible de marquer toutes les notifications comme lues')
  }
}

/** Two-line date/time, right-aligned, matching the design ("27/03/2023" / "20:00"). */
function dateParts(d: string): { date: string; time: string } {
  const dt = new Date(d)
  return {
    date: dt.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' }),
    time: dt.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }),
  }
}

/** Falls back to the first few words of the message when `subject` is empty
 *  (covers older notifications created before the `subject` column existed). */
function titleFor(n: any): string {
  if (n.subject) return n.subject
  const words = (n.message ?? '').replace(/^[^\wÀ-ÿ]+/, '').split(' ')
  return words.slice(0, 4).join(' ') || 'Notification'
}

/** Type-based icon tile. See the header comment for why this is a styled
 *  placeholder rather than a real file thumbnail. */
function iconFor(n: any): { bg: string; kind: 'document' | 'calendar' | 'invoice' | 'account' } {
  switch (n.type) {
    case 'document_uploaded':
    case 'contract_legalized':
      return { bg: '#ffffff', kind: 'document' }

    case 'pre_expiry_30':
    case 'pre_expiry_15':
    case 'pre_expiry_3':
    case 'post_expiry':
      return { bg: '#1f2937', kind: 'calendar' }

    default: {
      const msg = (n.message ?? '').toLowerCase()
      if (msg.includes('paiement') || msg.includes('facture')) {
        return { bg: '#ffffff', kind: 'invoice' }
      }
      if (msg.includes('compte')) {
        return { bg: '#ffffff', kind: 'account' }
      }
      return { bg: '#ffffff', kind: 'document' }
    }
  }
}

/** Opens the exact uploaded document via the existing public preview route.
 *  Only rendered when `data.document_id` is present on the notification. */
function notificationTarget(n: any): { label: string; to?: string; external?: string } | null {
  const type = n.type ?? ''
  const data = n.data ?? {}
  const message = String(n.message ?? '').toLowerCase()
  const contratId = data.contrat_id ?? n.contrat_id

  if (type === 'document_uploaded' && data.document_id) {
    return { label: 'Voir le document' }
  }

  if (
    contratId &&
    (type === 'contract_legalized' || type.startsWith('pre_expiry_') || type === 'post_expiry' || type === 'contract_expired' || message.includes('contrat'))
  ) {
    return {
      label: type === 'contract_legalized' ? 'Voir le contrat légalisé' : 'Voir le contrat',
      external: withToken(`${getApiBase()}/api/contrats/${contratId}/pdf/stream?mode=preview`),
    }
  }

  return null
}

async function openNotificationTarget(n: any): Promise<void> {
  if (!n.is_read) await markRead(n.id)
  if (n.type === 'document_uploaded' && n.data?.document_id) {
    await documentViewer.openPreview({
      id: Number(n.data.document_id),
      name: n.data?.document,
      redirectTo: '/client/documents',
    })
    return
  }
  const target = notificationTarget(n)
  if (!target) return
  if (target.external) {
    window.open(target.external, '_blank', 'noopener')
    return
  }
  if (target.to) await router.push(target.to)
}

onMounted(loadAll)
</script>

<template>
  <div class="space-y-5 animate-fade-up max-w-3xl">

    <!-- Header: title + unread badge + "mark all as read" -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div class="flex items-center gap-3">
        <h1 class="font-serif text-2xl font-bold" style="color:var(--app-text)">
          Notifications
        </h1>
        <span
          v-if="unreadCount > 0"
          class="text-xs font-bold px-3 py-1 rounded-full"
          style="background:rgba(200,169,110,0.15);color:#c8a96e"
        >
          {{ unreadCount }} non lue{{ unreadCount > 1 ? 's' : '' }}
        </span>
      </div>

      <button
        v-if="unreadCount > 0"
        class="btn btn-outline btn-sm"
        @click="markAllRead"
      >
        Tout marquer comme lu ✓
      </button>
    </div>

    <!-- Search box + status tabs -->
    <div class="flex items-center gap-3 flex-wrap">
      <div class="relative flex-1 min-w-[220px]">
        <svg
          class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
          width="15" height="15" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2" stroke-linecap="round"
          style="color: var(--app-text-faint)"
        >
          <circle cx="11" cy="11" r="8"/>
          <path d="M21 21l-4.35-4.35"/>
        </svg>
        <input
          v-model="search"
          class="f-input pl-9"
          placeholder="Rechercher une notification..."
        />
      </div>

      <div class="flex gap-2 shrink-0">
        <button
          class="px-4 py-2 rounded-xl text-sm font-semibold transition-all"
          :style="filter === 'all'
            ? 'background:var(--app-surface-2);color:var(--app-text)'
            : 'background:transparent;color:var(--app-text-faint)'"
          @click="filter = 'all'"
        >
          Toutes
        </button>
        <button
          class="px-4 py-2 rounded-xl text-sm font-semibold transition-all"
          :style="filter === 'unread'
            ? 'background:var(--app-surface-2);color:var(--app-text)'
            : 'background:transparent;color:var(--app-text-faint)'"
          @click="filter = 'unread'"
        >
          Non lues
        </button>
        <button
          class="px-4 py-2 rounded-xl text-sm font-semibold transition-all"
          :style="filter === 'read'
            ? 'background:var(--app-surface-2);color:var(--app-text)'
            : 'background:transparent;color:var(--app-text-faint)'"
          @click="filter = 'read'"
        >
          Lues
        </button>
      </div>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="space-y-2">
      <div
        v-for="i in 4" :key="i"
        class="card p-4 animate-pulse flex items-center gap-4"
      >
        <div class="w-11 h-11 rounded-xl shrink-0" style="background: var(--app-border)"/>
        <div class="flex-1 space-y-2">
          <div class="h-3 w-1/2 rounded" style="background: var(--app-border)"/>
          <div class="h-3 w-1/3 rounded" style="background: var(--app-border)"/>
        </div>
      </div>
    </div>

    <!-- Notification list -->
    <div
      v-else-if="filtered.length"
      class="rounded-2xl overflow-hidden"
      style="background:var(--app-surface);border:1px solid var(--app-border-2)"
    >
      <div
        v-for="(n, i) in filtered" :key="n.id"
        class="relative px-4 py-4 pl-5 flex items-center gap-4 flex-wrap sm:flex-nowrap transition-colors"
        :class="i < filtered.length - 1 ? 'border-b' : ''"
        :style="i < filtered.length - 1 ? 'border-color:var(--app-border-2)' : ''"
        role="button"
        tabindex="0"
        @click="toggleExpand(n.id)"
        @keydown.enter.prevent="toggleExpand(n.id)"
      >
        <!-- Unread accent bar, full row height, gold -->
        <span
          v-if="!n.is_read"
          class="absolute left-0 top-0 bottom-0 w-1"
          style="background:#c8a96e"
        />

        <!-- Icon tile: small square "preview" tile instead of a plain dot -->
        <div
          class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0"
          :style="`background:${iconFor(n).bg};border:1px solid var(--app-border)`"
        >
          <!-- Document / contract / uploaded-file style -->
          <svg
            v-if="iconFor(n).kind === 'document'"
            width="20" height="20" viewBox="0 0 24 24" fill="none"
            stroke="#1f2937" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"
          >
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="16" y1="13" x2="8" y2="13"/>
            <line x1="16" y1="17" x2="8" y2="17"/>
          </svg>

          <!-- Reminder / expiry style -->
          <svg
            v-else-if="iconFor(n).kind === 'calendar'"
            width="18" height="18" viewBox="0 0 24 24" fill="none"
            stroke="#ffffff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
          >
            <rect x="3" y="4" width="18" height="18" rx="2"/>
            <line x1="16" y1="2" x2="16" y2="6"/>
            <line x1="8" y1="2" x2="8" y2="6"/>
            <line x1="3" y1="10" x2="21" y2="10"/>
          </svg>

          <!-- Payment / invoice style -->
          <svg
            v-else-if="iconFor(n).kind === 'invoice'"
            width="20" height="20" viewBox="0 0 24 24" fill="none"
            stroke="#22c55e" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"
          >
            <rect x="2" y="5" width="20" height="14" rx="2"/>
            <line x1="2" y1="10" x2="22" y2="10"/>
          </svg>

          <!-- Account style -->
          <svg
            v-else
            width="20" height="20" viewBox="0 0 24 24" fill="none"
            stroke="#60a5fa" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"
          >
            <circle cx="12" cy="8" r="4"/>
            <path d="M4 21v-1a8 8 0 0 1 16 0v1"/>
          </svg>
        </div>

        <!-- Title / message / optional document link -->
        <div class="flex-1 min-w-0">
          <p class="text-sm font-bold truncate" style="color:var(--app-text)">
            {{ titleFor(n) }}
          </p>
          <p class="text-xs mt-0.5 truncate" style="color:var(--app-text-muted)">
            {{ n.message }}
          </p>
          <button
            v-if="notificationTarget(n)"
            class="text-xs font-semibold mt-1 underline underline-offset-2"
            style="color:#c8a96e"
            @click.stop="openNotificationTarget(n)"
          >
            {{ notificationTarget(n)?.label }} →
          </button>
        </div>

        <!-- Date, right-aligned, two lines -->
        <div class="text-right shrink-0 text-xs" style="color:var(--app-text-faint)">
          <p>{{ dateParts(n.created_at).date }}</p>
          <p>{{ dateParts(n.created_at).time }}</p>
        </div>

        <!-- Read/unread action -->
        <div class="shrink-0">
          <button
            v-if="!n.is_read"
            class="text-xs font-bold px-3 py-1.5 rounded-lg transition-colors"
            style="color:#1f2937;background:#c8a96e"
            @click.stop="markRead(n.id)"
          >
            Marquer comme lu
          </button>
          <span
            v-else
            class="text-xs font-medium px-3 py-1.5 rounded-lg"
            style="color:var(--app-text-faint);background:var(--app-surface-2)"
          >
            Lue
          </span>
        </div>

        <div
          v-if="expandedId === n.id"
          class="w-full rounded-xl p-4 mt-2 space-y-2 text-sm"
          style="background:var(--app-surface-2);border:1px solid var(--app-border)"
          @click.stop
        >
          <div class="flex items-center justify-between gap-3 flex-wrap">
            <p class="font-semibold" style="color:var(--app-text)">
              {{ titleFor(n) }}
            </p>
            <span class="text-xs" style="color:var(--app-text-faint)">
              {{ dateParts(n.created_at).date }} {{ dateParts(n.created_at).time }}
            </span>
          </div>
          <p style="color:var(--app-text-muted)">{{ n.message }}</p>
          <div class="pt-1 space-y-1 text-xs" style="color:var(--app-text-muted)">
            <p v-if="n.data?.contrat_id"><span class="font-medium" style="color:var(--app-text)">Contrat ID :</span> #{{ n.data.contrat_id }}</p>
            <p v-if="n.data?.entreprise"><span class="font-medium" style="color:var(--app-text)">Entreprise :</span> {{ n.data.entreprise }}</p>
            <p v-if="n.data?.document"><span class="font-medium" style="color:var(--app-text)">Document :</span> {{ n.data.document }}</p>
            <p v-if="n.data?.date_expiration"><span class="font-medium" style="color:var(--app-text)">Expiration :</span> {{ n.data.date_expiration }}</p>
            <p><span class="font-medium" style="color:var(--app-text)">Statut :</span>
              <span :style="n.is_read ? 'color:#22c55e' : 'color:#f59e0b'">{{ n.is_read ? 'Lue' : 'Non lue' }}</span>
            </p>
          </div>
          <div class="flex gap-2 pt-2 flex-wrap">
            <button
              v-if="notificationTarget(n)"
              class="btn btn-gold btn-sm"
              @click="openNotificationTarget(n)"
            >
              {{ notificationTarget(n)?.label }}
            </button>
            <button class="btn btn-outline btn-sm" @click="expandedId = null">Fermer</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="card p-10 text-center" style="color:var(--app-text-faint)">
      <p class="text-3xl mb-3">🔔</p>
      <p>{{ search || filter !== 'all' ? 'Aucun résultat.' : 'Aucune notification.' }}</p>
    </div>

  </div>
</template>
