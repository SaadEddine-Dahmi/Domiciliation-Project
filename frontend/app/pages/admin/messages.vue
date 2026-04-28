<!-- ============================================================
  pages/admin/messages.vue
  Messagerie domiciliataire → client
  - Composer un message pour n'importe quel client
  - Voir si le message a été lu (read receipt avec timestamp)
  - Historique des messages envoyés
============================================================ -->
<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { useClientsStore } from '~/stores/clients'

definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const clientsStore = useClientsStore()
const { items: clientItems } = storeToRefs(clientsStore)
const { success, error: toastError } = useToast()

function getApiBase() {
  const config = useRuntimeConfig()
  return (config.public.apiBase as string) ?? ''
}
function authHeaders(): Record<string, string> {
  if (!import.meta.client) return {}
  try {
    const raw = localStorage.getItem('astfisc_auth')
    if (!raw) return {}
    const parsed = JSON.parse(raw)
    return parsed?.token ? { Authorization: `Bearer ${parsed.token}` } : {}
  } catch { return {} }
}

// ── State ─────────────────────────────────────────────────
const messages   = ref<any[]>([])
const loading    = ref(true)
const sending    = ref(false)
const showCompose = ref(false)

const form = reactive({
  client_user_id: null as number | null,
  subject:        '',
  message:        '',
})

// ── Chargement messages envoyés ───────────────────────────
async function load(): Promise<void> {
  loading.value = true
  try {
    const res = await $fetch<{ success: boolean; data: any[] }>(
      `${getApiBase()}/api/messages`,
      { headers: authHeaders() }
    )
    messages.value = res.data ?? []
  } catch {} finally {
    loading.value = false
  }
}

// ── Envoyer un message ────────────────────────────────────
async function send(): Promise<void> {
  if (!form.client_user_id) return toastError?.('Sélectionnez un client')
  if (!form.message.trim()) return toastError?.('Rédigez un message')

  sending.value = true
  try {
    const res = await $fetch<{ success: boolean; data: any }>(
      `${getApiBase()}/api/messages`,
      {
        method:  'POST',
        headers: authHeaders(),
        body: {
          client_user_id: form.client_user_id,
          subject:        form.subject.trim() || null,
          message:        form.message.trim(),
        },
      }
    )
    messages.value.unshift(res.data)
    success('Message envoyé ✓')
    showCompose.value = false
    Object.assign(form, { client_user_id: null, subject: '', message: '' })
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur envoi')
  } finally {
    sending.value = false
  }
}

// ── Rafraîchir le read receipt d'un message ───────────────
async function refreshReceipt(msg: any): Promise<void> {
  try {
    const res = await $fetch<{ success: boolean; data: { is_read: boolean; read_at: string | null } }>(
      `${getApiBase()}/api/messages/${msg.id}/receipt`,
      { headers: authHeaders() }
    )
    msg.is_read = res.data.is_read
    msg.read_at = res.data.read_at
  } catch {}
}

// ── Helpers ───────────────────────────────────────────────
// Trouve le nom du client destinataire
function clientName(msg: any): string {
  return msg.toUser
    ? `${msg.toUser.nom} ${msg.toUser.prenom}`
    : clientItems.value.find(c => c.client_user?.id === msg.user_id)?.raison_sociale ?? `Client #${msg.user_id}`
}

function formatDate(d: string | null): string {
  if (!d) return '-'
  return new Date(d).toLocaleString('fr-FR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

onMounted(async () => {
  await clientsStore.fetchAll()
  await load()
})
</script>

<template>
  <div class="space-y-5 animate-fade-up">

    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="font-serif text-2xl">Messages <em class="text-gold italic">clients</em></h1>
        <p class="text-app-text/50 text-sm mt-1">{{ messages.length }} message(s) envoyé(s)</p>
      </div>
      <button class="btn btn-gold btn-md" @click="showCompose = true">
        ✉ Composer un message
      </button>
    </div>

    <!-- Liste messages envoyés -->
    <div v-if="loading" class="space-y-2">
      <div v-for="i in 3" :key="i" class="card p-4 animate-pulse">
        <div class="h-3 w-48 bg-white/10 rounded mb-2" />
        <div class="h-3 w-24 bg-white/10 rounded" />
      </div>
    </div>

    <div v-else-if="messages.length" class="space-y-3">
      <div
        v-for="msg in messages" :key="msg.id"
        class="card p-4 space-y-2"
      >
        <div class="flex items-start justify-between gap-3 flex-wrap">
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 flex-wrap">
              <p class="font-semibold text-sm">{{ clientName(msg) }}</p>
              <!-- Read receipt -->
              <span
                class="text-xs px-2 py-0.5 rounded-full font-medium"
                :class="msg.is_read
                  ? 'text-green-400 bg-green-400/10'
                  : 'text-app-text/40 bg-white/5'"
              >
                {{ msg.is_read ? `✓✓ Lu ${formatDate(msg.read_at)}` : '✓ Envoyé' }}
              </span>
            </div>
            <p v-if="msg.subject" class="text-xs text-gold mt-0.5 font-medium">
              {{ msg.subject }}
            </p>
            <p class="text-sm text-app-text/70 mt-1 line-clamp-2">{{ msg.message }}</p>
            <p class="text-xs text-app-text/40 mt-1">{{ formatDate(msg.created_at) }}</p>
          </div>

          <!-- Bouton refresh receipt -->
          <button
            v-if="!msg.is_read"
            class="btn btn-outline btn-sm flex-shrink-0"
            @click="refreshReceipt(msg)"
            title="Vérifier si lu"
          >
            🔄
          </button>
        </div>
      </div>
    </div>

    <div v-else class="card p-10 text-center text-app-text/40">
      <p class="text-4xl mb-3">✉</p>
      <p>Aucun message envoyé.</p>
      <button class="btn btn-gold btn-md mt-4" @click="showCompose = true">
        Envoyer un premier message
      </button>
    </div>

    <!-- Modal composer -->
    <div
      v-if="showCompose"
      class="fixed inset-0 z-[100] bg-black/70 flex items-center justify-center p-4"
      @click.self="showCompose = false"
    >
      <div class="card w-full max-w-lg p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="font-serif text-xl">Nouveau message</h2>
          <button class="text-app-text/40 hover:text-white text-xl" @click="showCompose = false">✕</button>
        </div>

        <!-- Destinataire -->
        <div>
          <label class="f-label">Destinataire *</label>
          <select v-model="form.client_user_id" class="f-input">
            <option :value="null" disabled>-- Sélectionner un client --</option>
            <option
              v-for="c in clientItems.filter(x => x.client_user)"
              :key="c.client_user.id"
              :value="c.client_user.id"
            >
              {{ c.raison_sociale }} — {{ c.client_user.nom }} {{ c.client_user.prenom }}
            </option>
          </select>
        </div>

        <!-- Sujet -->
        <div>
          <label class="f-label">Sujet (optionnel)</label>
          <input
            v-model="form.subject"
            class="f-input"
            placeholder="Ex: Renouvellement de votre contrat..."
          />
        </div>

        <!-- Message -->
        <div>
          <label class="f-label">Message *</label>
          <textarea
            v-model="form.message"
            class="f-input min-h-[120px] resize-y"
            placeholder="Rédigez votre message ici..."
          />
          <p class="text-xs text-app-text/40 mt-1 text-right">
            {{ form.message.length }}/2000
          </p>
        </div>

        <div class="flex gap-3 justify-end">
          <button class="btn btn-outline btn-md" @click="showCompose = false">Annuler</button>
          <button
            class="btn btn-gold btn-md"
            :disabled="sending || !form.client_user_id || !form.message.trim()"
            @click="send"
          >
            {{ sending ? 'Envoi...' : '✉ Envoyer' }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>
