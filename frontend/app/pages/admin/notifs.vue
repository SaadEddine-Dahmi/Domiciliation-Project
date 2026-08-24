<!-- pages/admin/notifs.vue -->
<script setup lang="ts">
// pages/admin/notifs.vue
//
// Shared notifications page for the two "internal" roles that use the
// /admin/* section: Domiciliataire and Super Admin (both nav trees link
// here — see app.vue → adminNav / domiciNav). The notification feed
// itself (GET /api/notifications) is already scoped server-side to
// whatever the authenticated user is entitled to see, so the list
// doesn't need role branching.
//
// CHANGE: the old "Alertes d'expiration" card let a Domiciliataire pick
// custom reminder delays (1 / 3 / 6 months) via PUT
// /api/notifications/preferences. That's been replaced by a fixed,
// automatic schedule (see NotificationService::notifyContractReminder()
// + routes/console.php): 3 reminders during the contract's last month
// (30 / 15 / 3 days before date_fin) plus 1 extra reminder 1-2 days
// after date_fin. Nothing is user-configurable anymore, so the
// preferences UI, its localStorage cache and its API calls are gone —
// replaced below with a small read-only card explaining the schedule.

import { useAuthStore } from '~/stores/auth'
import { useNotificationsStore } from '~/stores/notifs'

definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const auth = useAuthStore()
const isAdmin = computed(() => auth.isAdmin)
const notifsStore = useNotificationsStore()
const router = useRouter()

const { success, error: toastError } = useToast()

function getApiBase() {
  const config = useRuntimeConfig()
  return (config.public.apiBase as string) ?? ''
}

function authHeaders(): Record<string, string> {
  if (!import.meta.client) return {}
  try {
    const raw = localStorage.getItem('app_auth')
    if (!raw) return {}
    const parsed = JSON.parse(raw)
    return parsed?.token ? { Authorization: `Bearer ${parsed.token}` } : {}
  } catch { return {} }
}

const notifications = ref<any[]>([])
const loading        = ref(true)
const expandedId     = ref<number | null>(null)

function toggleExpand(id: number): void {
  const n = notifications.value.find(x => x.id === id)
  if (n && !n.is_read) {
    markRead(id)
  }
  expandedId.value = expandedId.value === id ? null : id
}

function getRawToken(): string {
  if (!import.meta.client) return ''
  try {
    const raw = localStorage.getItem('app_auth')
    if (!raw) return ''
    const parsed = JSON.parse(raw)
    return parsed?.token ?? ''
  } catch { return '' }
}

function withToken(url: string): string {
  const token = getRawToken()
  if (!token) return url
  const sep = url.includes('?') ? '&' : '?'
  return `${url}${sep}token=${encodeURIComponent(token)}`
}

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
    toastError?.(e?.data?.message ?? 'Erreur chargement')
  } finally {
    loading.value = false
  }
}

async function markRead(id: number): Promise<void> {
  const n = notifications.value.find(x => x.id === id)
  const wasUnread = n && !n.is_read
  try {
    await $fetch(`${getApiBase()}/api/notifications/${id}/read`, { method: 'POST', headers: authHeaders() })
    if (n) n.is_read = true
    if (wasUnread) notifsStore.markOneRead()
  } catch {}
}

async function markAllRead(): Promise<void> {
  try {
    await $fetch(`${getApiBase()}/api/notifications/read-all`, { method: 'POST', headers: authHeaders() })
    notifications.value.forEach(n => { n.is_read = true })
    notifsStore.markAllReadLocally()
    success('Toutes les notifications lues')
  } catch {}
}

const unreadCount = computed(() => notifications.value.filter(n => !n.is_read).length)

function formatDate(d: string): string {
  return new Date(d).toLocaleString('fr-FR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

function notifColor(n: any): string {
  const msg = (n.message ?? '').toLowerCase()
  if (msg.includes('paiement')) return '#22c55e'
  if (msg.includes('expir'))    return '#f59e0b'
  if (msg.includes('contrat'))  return '#c8a96e'
  if (msg.includes('document')) return '#60a5fa'
  // Account/signup notifications (Super Admin: new domiciliataire
  // requests) — same yellow used for "pending" everywhere else in the
  // admin UI (dashboard.vue, domiciliataires.vue).
  if (msg.includes('compte') || msg.includes('inscription') || msg.includes('domiciliataire')) return '#facc15'
  return '#94a3b8'
}

/** True when a notification is about an account/signup event rather
 *  than a contract/document/payment one — used to offer a "manage
 *  domiciliataires" shortcut in the expanded view instead of the
 *  contract-specific fields, which won't be present on this kind of
 *  notification anyway. */
function isAccountNotif(n: any): boolean {
  const msg = (n.message ?? '').toLowerCase()
  return msg.includes('compte') || msg.includes('inscription') || msg.includes('domiciliataire')
}

function notificationTarget(n: any): { label: string; to?: string; external?: string } | null {
  const type = n.type ?? ''
  const data = n.data ?? {}
  const message = String(n.message ?? '').toLowerCase()
  const contratId = data.contrat_id ?? n.contrat_id

  if (type === 'document_uploaded' && data.document_id) {
    return {
      label: 'Voir le document',
      external: withToken(`${getApiBase()}/api/documents/${data.document_id}/preview`),
    }
  }

  if (data.facture_id || type === 'payment_received' || message.includes('paiement') || message.includes('facture')) {
    return data.facture_id
      ? { label: 'Voir la facture', to: `/admin/factures?facture_id=${data.facture_id}` }
      : { label: 'Voir les paiements', to: '/admin/paiements' }
  }

  if (
    contratId &&
    (type === 'contract_legalized' || type.startsWith('pre_expiry_') || type === 'post_expiry' || type === 'contract_expired' || type === 'renewal_nudge' || message.includes('contrat'))
  ) {
    return {
      label: type === 'contract_legalized' ? 'Voir le contrat légalisé' : 'Voir le contrat',
      external: withToken(`${getApiBase()}/api/contrats/${contratId}/pdf/stream?mode=preview`),
    }
  }

  if (isAdmin.value && isAccountNotif(n)) {
    return { label: 'Gérer les domiciliataires', to: '/admin/domiciliataires' }
  }

  return null
}

async function openNotificationTarget(n: any): Promise<void> {
  if (!n.is_read) await markRead(n.id)
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

    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="font-serif text-2xl"><em class="text-gold italic">Notifications</em></h1>
        <p class="text-sm mt-1" style="color:var(--app-text-muted)">{{ unreadCount }} non lue(s)</p>
      </div>
      <button v-if="unreadCount > 0" class="btn btn-outline btn-sm" @click="markAllRead">
        ✓ Tout marquer comme lu
      </button>
    </div>

    <!-- Fixed expiry-reminder schedule — read-only, Domiciliataire only.
         Replaces the old configurable 1/3/6-month preference card: the
         schedule is now automatic and identical for every contract. -->
    <div v-if="!isAdmin" class="card p-5 space-y-3">
      <div>
        <p class="text-xs uppercase tracking-widest font-bold" style="color:#c8a96e">Alertes d'expiration</p>
        <p class="text-xs mt-1" style="color:var(--app-text-muted)">
          Le rappel est désormais automatique et s'applique à tous vos contrats — rien à configurer.
        </p>
      </div>
      <ul class="space-y-2 text-sm">
        <li class="flex items-center gap-2">
          <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background:#c8a96e" />
          <span style="color:var(--app-text)">Rappel 1 mois avant l'expiration</span>
        </li>
        <li class="flex items-center gap-2">
          <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background:#c8a96e" />
          <span style="color:var(--app-text)">Rappel 15 jours avant l'expiration</span>
        </li>
        <li class="flex items-center gap-2">
          <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background:#c8a96e" />
          <span style="color:var(--app-text)">Rappel 3 jours avant l'expiration</span>
        </li>
        <li class="flex items-center gap-2">
          <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background:#f59e0b" />
          <span style="color:var(--app-text)">Rappel supplémentaire 1 à 2 jours après la fin du contrat</span>
        </li>
      </ul>
      <p class="text-xs" style="color:var(--app-text-faint)">
        Vous et votre client recevez chacun ces rappels par notification et par e-mail.
      </p>
    </div>

    <div v-if="loading" class="space-y-2">
      <div v-for="i in 4" :key="i" class="card p-4 animate-pulse">
        <div class="h-3 w-3/4 rounded mb-2" style="background:var(--app-border)"/>
        <div class="h-3 w-1/3 rounded" style="background:var(--app-border)"/>
      </div>
    </div>

    <div v-else-if="notifications.length" class="rounded-2xl overflow-hidden"
         style="background:var(--app-surface);border:1px solid var(--app-border-2)">
      <div v-for="(n, i) in notifications" :key="n.id"
           :class="[i < notifications.length - 1 ? 'border-b' : '']"
           :style="i < notifications.length - 1 ? 'border-color:var(--app-border-2)' : ''">
        <div class="px-4 py-3.5 flex items-start gap-3 cursor-pointer transition-colors"
             :style="!n.is_read ? 'background:rgba(200,169,110,0.05)'
                     : expandedId === n.id ? 'background:var(--app-surface-2)' : ''"
             @click="toggleExpand(n.id)">
          <div class="w-2 h-2 rounded-full shrink-0 mt-1.5"
               :style="`background:${notifColor(n)};opacity:${n.is_read ? 0.3 : 1}`"/>
          <div class="flex-1 min-w-0">
            <p class="text-sm leading-snug" :class="!n.is_read ? 'font-semibold' : ''"
               :style="n.is_read ? 'color:var(--app-text-muted)' : 'color:var(--app-text)'">
              {{ n.message }}
            </p>
            <p class="text-xs mt-0.5" style="color:var(--app-text-faint)">{{ formatDate(n.created_at) }}</p>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <button v-if="!n.is_read" class="text-xs font-medium px-2 py-0.5 rounded-lg transition-colors"
                    style="color:#c8a96e;background:rgba(200,169,110,0.1)"
                    @click.stop="markRead(n.id)">Lu</button>
            <span v-else class="text-xs" style="color:var(--app-text-faint)">✓</span>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                 class="transition-transform duration-200"
                 :style="`color:var(--app-text-faint);transform:rotate(${expandedId === n.id ? 180 : 0}deg)`">
              <path d="M6 9l6 6 6-6"/>
            </svg>
          </div>
        </div>

        <Transition name="expand">
          <div v-if="expandedId === n.id" class="px-5 pb-4 pt-1"
               style="background:var(--app-surface-2);border-top:1px solid var(--app-border-2)">
            <div class="rounded-xl p-4 space-y-2 text-sm"
                 style="background:var(--app-surface);border:1px solid var(--app-border)">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-2 h-2 rounded-full shrink-0" :style="`background:${notifColor(n)}`"/>
                <p class="font-semibold text-xs uppercase tracking-wide" :style="`color:${notifColor(n)}`">
                  {{ n.type ?? 'Notification système' }}
                </p>
              </div>
              <p style="color:var(--app-text)">{{ n.message }}</p>
              <div class="pt-2 space-y-1 text-xs" style="color:var(--app-text-muted)">
                <!-- Contract/document/payment context — present on
                     Domiciliataire-facing notifications. -->
                <p v-if="n.data?.contrat_id"><span class="font-medium" style="color:var(--app-text)">Contrat ID :</span> #{{ n.data.contrat_id }}</p>
                <p v-if="n.data?.entreprise"><span class="font-medium" style="color:var(--app-text)">Entreprise :</span> {{ n.data.entreprise }}</p>
                <p v-if="n.data?.montant"><span class="font-medium" style="color:var(--app-text)">Montant :</span> {{ n.data.montant }} DH</p>
                <p v-if="n.data?.date_expiration"><span class="font-medium" style="color:var(--app-text)">Expiration :</span> {{ n.data.date_expiration }}</p>
                <!-- Account/signup context — present on Super-Admin-facing
                     notifications (new domiciliataire requests). -->
                <p v-if="n.data?.domiciliataire"><span class="font-medium" style="color:var(--app-text)">Domiciliataire :</span> {{ n.data.domiciliataire }}</p>
                <p v-if="n.data?.email"><span class="font-medium" style="color:var(--app-text)">Email :</span> {{ n.data.email }}</p>
                <p><span class="font-medium" style="color:var(--app-text)">Reçu le :</span> {{ formatDate(n.created_at) }}</p>
                <p><span class="font-medium" style="color:var(--app-text)">Statut :</span>
                  <span :style="n.is_read ? 'color:#22c55e' : 'color:#f59e0b'">{{ n.is_read ? 'Lu' : 'Non lu' }}</span>
                </p>
              </div>
              <div class="flex gap-2 pt-2 flex-wrap">
                <button v-if="!n.is_read" class="btn btn-outline btn-sm" @click="markRead(n.id)">Marquer comme lu</button>
                <button v-if="notificationTarget(n)" class="btn btn-gold btn-sm" @click="openNotificationTarget(n)">
                  {{ notificationTarget(n)?.label }}
                </button>
                <!-- Shortcut to act on the request — only for Super
                     Admin, and only on account/signup notifications. -->
                <NuxtLink v-if="isAdmin && isAccountNotif(n)" to="/admin/domiciliataires" class="btn btn-gold btn-sm">
                  Gérer les domiciliataires
                </NuxtLink>
                <button class="btn btn-outline btn-sm" @click="expandedId = null">Fermer</button>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </div>

    <div v-else class="card p-10 text-center" style="color:var(--app-text-faint)">
      <p class="text-3xl mb-3">🔔</p>
      <p>Aucune notification.</p>
    </div>

  </div>
</template>

<style scoped>
.expand-enter-active, .expand-leave-active {
  transition: max-height 0.25s ease, opacity 0.2s ease;
  overflow: hidden;
  max-height: 400px;
}
.expand-enter-from, .expand-leave-to { max-height: 0; opacity: 0; }
</style>
