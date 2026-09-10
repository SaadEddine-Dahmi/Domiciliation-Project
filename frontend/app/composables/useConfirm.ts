import { readonly, ref } from 'vue'

export type ConfirmVariant = 'default' | 'danger'

export interface ConfirmOptions {
  title?: string
  message: string
  confirmLabel?: string
  cancelLabel?: string
  variant?: ConfirmVariant
}

const defaultState = {
  open: false,
  title: 'Confirmer cette action',
  message: '',
  confirmLabel: 'Confirmer',
  cancelLabel: 'Annuler',
  variant: 'default' as ConfirmVariant,
}

const state = ref({ ...defaultState })
let resolver: ((confirmed: boolean) => void) | null = null

export function useConfirm() {
  function confirm(options: ConfirmOptions | string): Promise<boolean> {
    if (resolver) resolver(false)

    const next = typeof options === 'string' ? { message: options } : options
    state.value = {
      ...defaultState,
      ...next,
      open: true,
    }

    return new Promise((resolve) => {
      resolver = resolve
    })
  }

  function alert(options: Omit<ConfirmOptions, 'confirmLabel' | 'cancelLabel'> | string): Promise<boolean> {
    return confirm({
      ...(typeof options === 'string' ? { message: options } : options),
      confirmLabel: 'OK',
      cancelLabel: '',
    })
  }

  function close(confirmed: boolean): void {
    if (!state.value.open) return
    state.value.open = false
    resolver?.(confirmed)
    resolver = null
  }

  return {
    state: readonly(state),
    confirm,
    alert,
    close,
  }
}
