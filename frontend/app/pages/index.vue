<script setup lang="ts">
import { useAuthStore } from '~/stores/auth';

definePageMeta({ middleware: ['auth'] })

const auth = useAuthStore()
if (auth.isAuthenticated) {
  await navigateTo(auth.isAdmin ? '/admin/dashboard' : '/client/dashboard')
} else {
  await navigateTo('/login')
}
</script>

<template><div /></template>