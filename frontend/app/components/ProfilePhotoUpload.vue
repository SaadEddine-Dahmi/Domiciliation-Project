<!-- app/components/ProfilePhotoUpload.vue -->
<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { deleteProfilePhoto, uploadProfilePhoto } from '~/services/profilePhotoService'
import { useAuthStore } from '~/stores/auth'

const auth = useAuthStore()
const { confirm: confirmAction } = useConfirm()

const fileInput = ref<HTMLInputElement | null>(null)
const cropFrame = ref<HTMLElement | null>(null)
const uploading = ref(false)
const errorMessage = ref('')
const localPreview = ref<string | null>(null)
const cropImageUrl = ref<string | null>(null)
const imageFailed = ref(false)
const isCropperOpen = ref(false)
const isLightboxOpen = ref(false)
const cropScale = ref(1)
const cropOffset = ref({ x: 0, y: 0 })
const cropNaturalSize = ref({ width: 0, height: 0 })
const cropFrameSize = ref(300)
const dragStart = ref({ x: 0, y: 0 })
const dragOrigin = ref({ x: 0, y: 0 })
const isDragging = ref(false)

const MAX_SIZE_BYTES = 2 * 1024 * 1024
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp']
const OUTPUT_SIZE = 512

const previewUrl = computed(() => {
  if (localPreview.value) return localPreview.value
  const photoUrl = auth.user?.photoUrl ?? auth.user?.photo_url
  if (!photoUrl) return null

  const separator = photoUrl.includes('?') ? '&' : '?'
  return `${photoUrl}${separator}_pv=${auth.photoVersion}`
})

watch([previewUrl, () => auth.photoVersion], () => {
  imageFailed.value = false
})

const canPreviewImage = computed(() => Boolean(previewUrl.value && !imageFailed.value))

const previewStyle = computed(() => ({
  background: canPreviewImage.value ? 'transparent' : `${auth.user?.color}22`,
  color: auth.user?.color,
}))

const cropImageStyle = computed(() => ({
  width: `${cropDisplaySize.value.width}px`,
  height: `${cropDisplaySize.value.height}px`,
  transform: `translate(calc(-50% + ${cropOffset.value.x}px), calc(-50% + ${cropOffset.value.y}px)) scale(${cropScale.value})`,
}))

const cropBaseScale = computed(() => {
  if (!cropNaturalSize.value.width || !cropNaturalSize.value.height) return 1
  return Math.min(
    cropFrameSize.value / cropNaturalSize.value.width,
    cropFrameSize.value / cropNaturalSize.value.height,
  )
})

const cropDisplaySize = computed(() => ({
  width: cropNaturalSize.value.width * cropBaseScale.value,
  height: cropNaturalSize.value.height * cropBaseScale.value,
}))

watch(cropScale, () => clampCropOffset())

function triggerFilePicker(): void {
  errorMessage.value = ''
  fileInput.value?.click()
}

async function handleFileSelected(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return

  errorMessage.value = ''

  if (!ALLOWED_TYPES.includes(file.type)) {
    errorMessage.value = 'Format non supporte. Utilisez JPG, PNG ou WEBP.'
    return
  }

  if (file.size > MAX_SIZE_BYTES) {
    errorMessage.value = 'Le fichier depasse 2 Mo.'
    return
  }

  revokeCropImage()
  cropImageUrl.value = URL.createObjectURL(file)
  cropScale.value = 1
  cropOffset.value = { x: 0, y: 0 }
  cropNaturalSize.value = { width: 0, height: 0 }
  isCropperOpen.value = true
  await nextTick()
  try {
    await initializeCropper()
    cropFrame.value?.focus()
  } catch {
    errorMessage.value = "Impossible de charger l'image selectionnee."
    cancelCrop()
  }
}

async function initializeCropper(): Promise<void> {
  if (!cropImageUrl.value || !cropFrame.value) return

  cropFrameSize.value = cropFrame.value.clientWidth || cropFrameSize.value
  const image = await loadImage(cropImageUrl.value)
  cropNaturalSize.value = {
    width: image.naturalWidth,
    height: image.naturalHeight,
  }
  cropScale.value = 1
  cropOffset.value = { x: 0, y: 0 }
}

function startDrag(event: PointerEvent): void {
  if (!cropImageUrl.value) return

  isDragging.value = true
  dragStart.value = { x: event.clientX, y: event.clientY }
  dragOrigin.value = { ...cropOffset.value }
  ;(event.currentTarget as HTMLElement).setPointerCapture(event.pointerId)
}

function dragCrop(event: PointerEvent): void {
  if (!isDragging.value) return

  cropOffset.value = clampOffset({
    x: dragOrigin.value.x + event.clientX - dragStart.value.x,
    y: dragOrigin.value.y + event.clientY - dragStart.value.y,
  })
}

function stopDrag(event: PointerEvent): void {
  if (!isDragging.value) return

  isDragging.value = false
  const target = event.currentTarget as HTMLElement
  if (target.hasPointerCapture(event.pointerId)) target.releasePointerCapture(event.pointerId)
}

function adjustZoom(delta: number): void {
  cropScale.value = Math.min(3, Math.max(1, Number((cropScale.value + delta).toFixed(2))))
}

function clampCropOffset(): void {
  cropOffset.value = clampOffset(cropOffset.value)
}

function clampOffset(offset: { x: number; y: number }): { x: number; y: number } {
  const width = cropDisplaySize.value.width * cropScale.value
  const height = cropDisplaySize.value.height * cropScale.value
  const maxX = Math.max(0, (width - cropFrameSize.value) / 2)
  const maxY = Math.max(0, (height - cropFrameSize.value) / 2)

  return {
    x: Math.min(maxX, Math.max(-maxX, offset.x)),
    y: Math.min(maxY, Math.max(-maxY, offset.y)),
  }
}

function cancelCrop(): void {
  isCropperOpen.value = false
  revokeCropImage()
}

async function confirmCrop(): Promise<void> {
  if (!cropImageUrl.value || !cropFrame.value) return

  errorMessage.value = ''
  uploading.value = true

  try {
    const croppedFile = await createCroppedAvatarFile(cropImageUrl.value, cropFrame.value.clientWidth)
    revokeLocalPreview()
    localPreview.value = URL.createObjectURL(croppedFile)

    const res = await uploadProfilePhoto(croppedFile)
    auth.setPhoto(res.data.photo_url)
    isCropperOpen.value = false
    revokeCropImage()
  } catch (e: any) {
    errorMessage.value = e?.data?.message ?? "Echec de l'envoi de la photo."
  } finally {
    uploading.value = false
    revokeLocalPreview()
  }
}

async function createCroppedAvatarFile(sourceUrl: string, frameSize: number): Promise<File> {
  const image = await loadImage(sourceUrl)
  const canvas = document.createElement('canvas')
  canvas.width = OUTPUT_SIZE
  canvas.height = OUTPUT_SIZE

  const ctx = canvas.getContext('2d')
  if (!ctx) throw new Error('Canvas indisponible.')

  ctx.fillStyle = '#ffffff'
  ctx.fillRect(0, 0, OUTPUT_SIZE, OUTPUT_SIZE)

  const baseScale = Math.min(frameSize / image.naturalWidth, frameSize / image.naturalHeight)
  const outputRatio = OUTPUT_SIZE / frameSize
  const drawWidth = image.naturalWidth * baseScale * cropScale.value * outputRatio
  const drawHeight = image.naturalHeight * baseScale * cropScale.value * outputRatio
  const drawX = (OUTPUT_SIZE - drawWidth) / 2 + cropOffset.value.x * outputRatio
  const drawY = (OUTPUT_SIZE - drawHeight) / 2 + cropOffset.value.y * outputRatio

  ctx.drawImage(image, drawX, drawY, drawWidth, drawHeight)

  const blob = await new Promise<Blob>((resolve, reject) => {
    canvas.toBlob((result) => {
      if (result) resolve(result)
      else reject(new Error('Impossible de preparer la photo.'))
    }, 'image/jpeg', 0.9)
  })

  return new File([blob], 'profile-photo.jpg', { type: 'image/jpeg' })
}

function loadImage(sourceUrl: string): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const image = new Image()
    image.onload = () => resolve(image)
    image.onerror = () => reject(new Error("Impossible de charger l'image."))
    image.src = sourceUrl
  })
}

async function handleRemove(): Promise<void> {
  const confirmed = await confirmAction({
    title: 'Supprimer la photo',
    message: 'Supprimer la photo de profil actuelle ?',
    confirmLabel: 'Supprimer',
    variant: 'danger',
  })
  if (!confirmed) return

  errorMessage.value = ''
  uploading.value = true

  try {
    const res = await deleteProfilePhoto()
    auth.setPhoto(res.data.photo_url)
    isLightboxOpen.value = false
  } catch (e: any) {
    errorMessage.value = e?.data?.message ?? 'Echec de la suppression.'
  } finally {
    uploading.value = false
  }
}

function openLightbox(): void {
  if (canPreviewImage.value) isLightboxOpen.value = true
}

function closeLightbox(): void {
  isLightboxOpen.value = false
}

function revokeLocalPreview(): void {
  if (localPreview.value) {
    URL.revokeObjectURL(localPreview.value)
    localPreview.value = null
  }
}

function revokeCropImage(): void {
  if (cropImageUrl.value) {
    URL.revokeObjectURL(cropImageUrl.value)
    cropImageUrl.value = null
  }
}

onBeforeUnmount(() => {
  revokeLocalPreview()
  revokeCropImage()
})
</script>

<template>
  <div class="flex items-center gap-4">
    <button
      type="button"
      class="w-20 h-20 rounded-full overflow-hidden flex items-center justify-center shrink-0 font-bold text-xl select-none transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
      :class="canPreviewImage ? 'cursor-zoom-in hover:opacity-90' : 'cursor-default'"
      :style="previewStyle"
      :disabled="!canPreviewImage"
      aria-label="Afficher la photo de profil"
      @click="openLightbox"
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
    </button>

    <div class="flex flex-col gap-2">
      <div class="flex gap-2">
        <button
          type="button"
          class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors"
          style="background: var(--app-surface-2); border: 1px solid var(--app-border); color: var(--app-text);"
          :disabled="uploading"
          @click="triggerFilePicker"
        >
          {{ uploading ? 'Envoi...' : 'Changer la photo' }}
        </button>

        <button
          v-if="auth.user?.photoUrl || auth.user?.photo_url"
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
        La photo est enregistree mais l'image ne se charge pas.
      </p>

      <p v-if="errorMessage" class="text-[11px]" style="color: #ef4444">
        {{ errorMessage }}
      </p>
    </div>

    <input
      ref="fileInput"
      type="file"
      accept="image/jpeg,image/png,image/webp"
      class="hidden"
      @change="handleFileSelected"
    />

    <Teleport to="body">
      <div
        v-if="isCropperOpen"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 p-3 sm:p-4"
        role="dialog"
        aria-modal="true"
        aria-label="Recadrer la photo de profil"
      >
        <div
          class="w-full max-w-lg overflow-hidden rounded-2xl shadow-2xl"
          style="background: var(--app-surface); color: var(--app-text); border: 1px solid var(--app-border);"
        >
          <div class="flex items-center justify-between gap-3 px-5 py-4" style="border-bottom: 1px solid var(--app-border);">
            <h2 class="text-lg font-semibold">Ajuster la photo de profil</h2>
            <button
              type="button"
              class="flex h-9 w-9 items-center justify-center rounded-full transition hover:opacity-80"
              style="background: var(--app-surface-2); color: var(--app-text);"
              aria-label="Fermer"
              :disabled="uploading"
              @click="cancelCrop"
            >
              <UiIcon name="x-circle" :size="18" />
            </button>
          </div>

          <div class="px-5 py-5 sm:px-6">
            <div
              class="relative mx-auto flex aspect-square w-full max-w-[360px] items-center justify-center overflow-hidden rounded-xl"
              style="background: var(--app-surface-2);"
            >
              <div
                ref="cropFrame"
                tabindex="0"
                class="relative aspect-square w-[78%] max-w-[300px] overflow-hidden rounded-full bg-white touch-none outline-none ring-2 ring-white shadow-[0_0_0_9999px_rgba(0,0,0,0.46)]"
                @pointerdown="startDrag"
                @pointermove="dragCrop"
                @pointerup="stopDrag"
                @pointercancel="stopDrag"
              >
                <img
                  v-if="cropImageUrl && cropNaturalSize.width"
                  :src="cropImageUrl"
                  alt=""
                  class="absolute left-1/2 top-1/2 max-w-none will-change-transform select-none"
                  :class="isDragging ? 'cursor-grabbing' : 'cursor-grab'"
                  :style="cropImageStyle"
                  draggable="false"
                />
              </div>
            </div>

            <div class="mt-5 flex items-center gap-3">
              <button
                type="button"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-lg font-semibold transition disabled:opacity-40"
                style="background: var(--app-surface-2); border: 1px solid var(--app-border); color: var(--app-text);"
                :disabled="cropScale <= 1 || uploading"
                aria-label="Reduire le zoom"
                @click="adjustZoom(-0.1)"
              >
                -
              </button>
              <input
                v-model.number="cropScale"
                type="range"
                min="1"
                max="3"
                step="0.01"
                class="min-w-0 flex-1 accent-[#1877f2]"
                aria-label="Zoom"
              />
              <button
                type="button"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-lg font-semibold transition disabled:opacity-40"
                style="background: var(--app-surface-2); border: 1px solid var(--app-border); color: var(--app-text);"
                :disabled="cropScale >= 3 || uploading"
                aria-label="Augmenter le zoom"
                @click="adjustZoom(0.1)"
              >
                +
              </button>
            </div>
          </div>

          <div class="flex justify-end gap-2 px-5 py-4" style="border-top: 1px solid var(--app-border);">
            <button
              type="button"
              class="px-4 py-2 rounded-lg text-sm font-semibold transition hover:opacity-80"
              style="background: transparent; border: 1px solid var(--app-border); color: var(--app-text);"
              :disabled="uploading"
              @click="cancelCrop"
            >
              Annuler
            </button>
            <button
              type="button"
              class="px-4 py-2 rounded-lg text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-60"
              style="background: #1877f2;"
              :disabled="uploading"
              @click="confirmCrop"
            >
              {{ uploading ? 'Envoi...' : 'Enregistrer' }}
            </button>
          </div>
        </div>
      </div>

      <div
        v-if="isLightboxOpen && previewUrl"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/85 p-4"
        role="dialog"
        aria-modal="true"
        aria-label="Apercu de la photo de profil"
        @click.self="closeLightbox"
      >
        <button
          type="button"
          class="absolute right-5 top-5 h-10 w-10 rounded-full bg-white/10 text-2xl leading-none text-white transition hover:bg-white/20"
          aria-label="Fermer"
          @click="closeLightbox"
        >
          x
        </button>
        <img
          :src="previewUrl"
          alt=""
          class="max-h-[88vh] max-w-[92vw] rounded-xl object-contain shadow-2xl"
        />
      </div>
    </Teleport>
  </div>
</template>
