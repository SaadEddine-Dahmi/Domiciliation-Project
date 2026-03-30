import { ref } from 'vue'


type ToastType = 'success' | 'error' | 'info'
interface ToastItem {
    id: number
    type: ToastType
    message: string
    timeout?: number
}

const _toasts = ref<ToastItem[]>([])
let _id = 1

export function useToast() {
    function push(type: ToastType, message: string, timeout = 2600) {
        const id = _id++
        _toasts.value.push({ id, type, message, timeout })
        if (timeout > 0) {
            setTimeout(() => {
                _toasts.value = _toasts.value.filter(t => t.id !== id)
            }, timeout)
        }
    }

    return {
        toasts: _toasts,
        success: (m: string, t?: number) => push('success', m, t),
        error: (m: string, t?: number) => push('error', m, t),
        info: (m: string, t?: number) => push('info', m, t),
        remove: (id: number) => { _toasts.value = _toasts.value.filter(t => t.id !== id) },
        clear: () => { _toasts.value = [] },
    }
}