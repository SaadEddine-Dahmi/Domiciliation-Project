<!-- components/PasswordRevealModal.vue -->
<!--
  Shows a system-generated password exactly once, with a copy button.
  Used right after creating a client account, and after an admin-
  triggered password reset — both flows return the plaintext password
  only in that single API response.
-->
<script setup lang="ts">
const props = defineProps<{
  show: boolean
  password: string
  clientName?: string
}>()

const emit = defineEmits<{ close: [] }>()

const copied = ref(false)

async function copyPassword(): Promise<void> {
  try {
    await navigator.clipboard.writeText(props.password)
    copied.value = true
    setTimeout(() => (copied.value = false), 2000)
  } catch {
    // Clipboard API unavailable — the value stays selectable in the input.
  }
}
</script>

<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-[100] bg-black/70 flex items-center justify-center p-4"
         @click.self="emit('close')">
      <div class="card w-full max-w-sm p-6 space-y-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0"
               style="background:rgba(34,197,94,0.12)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.2">
              <path d="M20 6L9 17l-5-5"/>
            </svg>
          </div>
          <div>
            <h2 class="font-serif text-lg">Mot de passe généré</h2>
            <p v-if="clientName" class="text-xs text-app-text/50">{{ clientName }}</p>
          </div>
        </div>

        <p class="text-sm text-app-text/60">
          Communiquez ce mot de passe au client — il ne sera plus jamais affiché,
          notez-le maintenant. Le client sera invité à le changer à sa première connexion.
        </p>

        <div class="flex items-center gap-2">
          <input :value="password" readonly class="f-input font-mono text-center tracking-wider" />
          <button type="button" class="btn btn-outline btn-md shrink-0" @click="copyPassword">
            {{ copied ? '✓ Copié' : 'Copier' }}
          </button>
        </div>

        <button type="button" class="btn btn-gold btn-md w-full" @click="emit('close')">
          J'ai noté le mot de passe
        </button>
      </div>
    </div>
  </Teleport>
</template>