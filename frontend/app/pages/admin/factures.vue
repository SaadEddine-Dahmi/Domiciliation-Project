<template>
    <NuxtLayout>
  <div class="p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-white">Gestion des Factures</h1>
    </div>

    <div class="overflow-hidden rounded-xl border border-white/10 bg-white/5">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="bg-white/10 text-xs font-semibold text-app-text/60 uppercase tracking-wider">
            <th class="p-4">N° Facture</th>
            <th class="p-4">Entreprise</th>
            <th class="p-4">Date</th>
            <th class="p-4">Montant</th>
            <th class="p-4">Statut</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
          <tr v-for="f in factures" :key="f.id" class="hover:bg-white/5 transition">
            <td class="p-4 text-sm font-medium text-white">{{ f.numero_facture }}</td>
            <td class="p-4 text-sm text-app-text/80">{{ f.entreprise?.raison_sociale }}</td>
            <td class="p-4 text-sm text-app-text/60">{{ formatDate(f.date_facture) }}</td>
            <td class="p-4 text-sm font-semibold text-green-400">{{ f.montant_total }} DH</td>
            <td class="p-4">
              <span :class="statusClass(f.statut)">
                {{ f.statut }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
  </NuxtLayout>
</template>

<script setup>
const { $api } = useNuxtApp();
const factures = ref([]);

const fetchFactures = async () => {
  try {
    const response = await $api.get('/factures');
    factures.value = response.data.data;
  } catch (error) {
    console.error("Erreur lors du chargement des factures", error);
  }
};

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString('fr-FR');
};

const statusClass = (status) => {
  const base = "px-2 py-0.5 rounded-full text-[10px] uppercase font-bold ";
  return status === 'paid' 
    ? base + "bg-green-400/10 text-green-400" 
    : base + "bg-yellow-400/10 text-yellow-400";
};

onMounted(fetchFactures);
</script>