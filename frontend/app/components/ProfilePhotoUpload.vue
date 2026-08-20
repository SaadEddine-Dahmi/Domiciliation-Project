<!-- app/components/ProfilePhotoUpload.vue -->
<!--
  Self-contained "change profile photo" control: preview + upload +
  remove. Drop it anywhere on the settings page — it reads/writes the
  auth store directly, so the sidebar/topbar avatars update instantly
  the moment a change succeeds.

  FIX: the preview <img> now has the same broken-image fallback as
  UserAvatar.vue. Previously, if photo_url pointed to a URL that
  failed to load (missing storage symlink, wrong APP_URL, etc.), the
  browser rendered the alt text unclipped inside the tiny 80px circle
  instead of the image — producing garbled wrapped text. Now a failed
  load falls back cleanly to the nom+prenom initials, exactly like the
  sidebar and topbar avatars do.
  -->
  <script setup lang="ts">
  import { computed, onBeforeUnmount, ref, watch } from 'vue'
  import { useAuthStore } from '~/stores/auth'
  import { uploadProfilePhoto, deleteProfilePhoto } from '~/services/profilePhotoService'
  
  const auth = useAuthStore()
  
  const fileInput   = ref<HTMLInputElement | null>(null)
  const uploading    = ref(false)
  const errorMessage = ref('')
  const localPreview = ref<string | null>(null) // object URL for instant feedback while uploading
  
  // FIX: tracks whether the current previewUrl failed to load, so a
  // broken image never shows raw/unclipped alt text — it shows initials
  // instead, same behavior as UserAvatar.vue.
  const imageFailed = ref(false)
  
  const MAX_SIZE_BYTES = 2 * 1024 * 1024
  const ALLOWED_TYPES  = ['image/jpeg', 'image/png', 'image/webp']
  
  // Shows the freshly picked file immediately; once the local preview is
  // cleared, falls back to whatever the server currently has.
  const previewUrl = computed(() => localPreview.value ?? auth.user?.photoUrl ?? null)
  
  // Reset the "failed" flag whenever the URL itself changes (new upload,
  // removal, or a fresh page load) so a stale failure doesn't stick
  // around and hide a URL that would actually load fine now.
  watch(previewUrl, () => { imageFailed.value = false })
  
  const previewStyle = computed(() => ({
    background: (previewUrl.value && !imageFailed.value) ? 'transparent' : `${auth.user?.color}22`,
    color: auth.user?.color,
  }))
  
  function triggerFilePicker(): void {
    errorMessage.value = ''
    fileInput.value?.click()
  }
  
  async function handleFileSelected(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement
    const file  = input.files?.[0]
    if (!file) return
  
    errorMessage.value = ''
  
    if (!ALLOWED_TYPES.includes(file.type)) {
      errorMessage.value = 'Format non supporté. Utilisez JPG, PNG ou WEBP.'
      input.value = ''
      return
    }
  
    if (file.size > MAX_SIZE_BYTES) {
      errorMessage.value = 'Le fichier dépasse 2 Mo.'
      input.value = ''
      return
    }
  
    revokeLocalPreview()
    localPreview.value = URL.createObjectURL(file)
  
    uploading.value = true
    try {
      const res = await uploadProfilePhoto(file)
      auth.setPhoto(res.data.photo_url)
    } catch (e: any) {
      errorMessage.value = e?.data?.message ?? "Échec de l'envoi de la photo."
    } finally {
      uploading.value = false
      revokeLocalPreview()
      input.value = ''
    }
  }
  
  async function handleRemove(): Promise<void> {
    errorMessage.value = ''
    uploading.value = true
    try {
      const res = await deleteProfilePhoto()
      auth.setPhoto(res.data.photo_url)
    } catch (e: any) {
      errorMessage.value = e?.data?.message ?? 'Échec de la suppression.'
    } finally {
      uploading.value = false
    }
  }
  
  // Object URLs must be released manually or they leak memory.
  function revokeLocalPreview(): void {
    if (localPreview.value) {
      URL.revokeObjectURL(localPreview.value)
      localPreview.value = null
    }
  }
  
  onBeforeUnmount(revokeLocalPreview)
  </script>
  
<template>
  <div class="flex items-center gap-4">

    <!-- Live preview: shows the local file the instant it's picked,
         then falls back to whatever is currently stored. Falls back to
         initials if the image URL fails to load. -->
    <div
      class="w-20 h-20 rounded-full overflow-hidden flex items-center justify-center shrink-0 font-bold text-xl select-none"
      :style="previewStyle"
    >
      <img
        v-if="previewUrl && !imageFailed"
        :src="previewUrl"
        alt=""
        class="w-full h-full object-cover"
        @error="imageFailed = true"
        @load="imageFailed = false"
      />
      <span v-else>{{ auth.user?.avatar ?? '?' }}</span>
    </div>

    <div class="flex flex-col gap-2">
      <div class="flex gap-2">
        <button
          type="button"
          class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors"
          style="background: var(--app-surface-2); border: 1px solid var(--app-border); color: var(--app-text);"
          :disabled="uploading"
          @click="triggerFilePicker"
        >
          {{ uploading ? 'Envoi…' : 'Changer la photo' }}
        </button>

        <button
          v-if="auth.user?.photoUrl"
          type="button"
          class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors"
          style="background: transparent; border: 1px solid var(--app-border); color: #ef4444;"
          :disabled="uploading"
          @click="handleRemove"
        >
          Supprimer
        </button>
      </div>

      <p v-if="imageFailed && previewUrl && !uploading" class="text-[11px]" style="color: #ef4444">
        La photo est enregistrée mais l'image ne se charge pas (vérifiez la configuration du serveur).
      </p>

      <p v-if="errorMessage" class="text-[11px]" style="color: #ef4444">
        {{ errorMessage }}
      </p>
    </div>

    <!-- Hidden native file input, triggered programmatically by the button above -->
    <input
      ref="fileInput"
      type="file"
      accept="image/jpeg,image/png,image/webp"
      class="hidden"
      @change="handleFileSelected"
    />
  </div>
</template>
