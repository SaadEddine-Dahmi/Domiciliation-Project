<script setup lang="ts">
definePageMeta({ layout: "dashboard", middleware: ["auth"] });

const { success, error: toastError } = useToast();

function getApiBase() {
  const config = useRuntimeConfig();
  return (config.public.apiBase as string) ?? "";
}

// ── Auth & Tokens ─────────────────────────────────────────
function getToken(): string {
  if (!import.meta.client) return '';
  try {
    const raw = localStorage.getItem('astfisc_auth');
    return JSON.parse(raw ?? '{}')?.token ?? '';
  } catch { 
    return ''; 
  }
}

function authHeaders(): Record<string, string> {
  const token = getToken();
  return token ? { Authorization: `Bearer ${token}` } : {};
}

// ── State ─────────────────────────────────────────────────
const contrats = ref<any[]>([]);
const paiements = ref<any[]>([]);
const summary = ref<any>(null);
const selectedId = ref<number | null>(null);
const loading = ref(true);
const loadingPayments = ref(false);
const showModal = ref(false);
const saving = ref(false);

// PDF Preview State
const previewFacture  = ref<any>(null);
const showPdfPreview  = ref(false);
const pdfPreviewLoading = ref(false);

const form = reactive({
  montant: "",
  date_paiement: new Date().toISOString().split("T")[0],
  mode_paiement: "virement",
  note: "",
});

const modeOptions = [
  "virement",
  "espèces",
  "chèque",
  "carte bancaire",
  "autre",
];

// ── Charger les contrats ──────────────────────────────────
async function loadContrats(): Promise<void> {
  loading.value = true;
  try {
    const res = await $fetch<{ success: boolean; data: any[] }>(
      `${getApiBase()}/api/contrats`,
      { headers: authHeaders() },
    );
    // Uniquement les contrats actifs ou draft (payables)
    contrats.value = (res.data ?? []).filter((c) =>
      ["active", "draft"].includes(c.statut),
    );
  } catch {
  } finally {
    loading.value = false;
  }
}

// ── Charger paiements + résumé d'un contrat ───────────────
async function selectContrat(id: number): Promise<void> {
  selectedId.value = id;
  loadingPayments.value = true;
  paiements.value = [];
  summary.value = null;
  try {
    const [pRes, sRes] = await Promise.all([
      $fetch<{ success: boolean; data: any[] }>(
        `${getApiBase()}/api/contrats/${id}/paiements`,
        { headers: authHeaders() },
      ),
      $fetch<{ success: boolean; data: any }>(
        `${getApiBase()}/api/contrats/${id}/paiements/summary`,
        { headers: authHeaders() },
      ),
    ]);
    paiements.value = pRes.data ?? [];
    summary.value = sRes.data;
  } catch (e: any) {
    toastError?.(e?.data?.message ?? "Erreur chargement paiements");
  } finally {
    loadingPayments.value = false;
  }
}

// ── Enregistrer un paiement ───────────────────────────────
async function submitPaiement(): Promise<void> {
  if (!selectedId.value) return;
  if (!form.montant || Number(form.montant) <= 0)
    return toastError?.("Montant invalide");
  if (!form.date_paiement) return toastError?.("Date requise");

  saving.value = true;
  try {
    const res = await $fetch<{ success: boolean; data: any }>(
      `${getApiBase()}/api/contrats/${selectedId.value}/paiements`,
      {
        method: "POST",
        headers: authHeaders(),
        body: {
          montant: Number(form.montant),
          date_paiement: form.date_paiement,
          mode_paiement: form.mode_paiement,
          note: form.note || null,
        },
      },
    );
    paiements.value.unshift(res.data);
    showModal.value = false;
    success("Paiement enregistré ✓");
    // Recharger le résumé
    await selectContrat(selectedId.value);
    Object.assign(form, { montant: "", mode_paiement: "virement", note: "" });
  } catch (e: any) {
    toastError?.(e?.data?.message ?? "Erreur enregistrement");
  } finally {
    saving.value = false;
  }
}

// ── Helpers ───────────────────────────────────────────────
const selectedContrat = computed(() =>
  contrats.value.find((c) => c.id === selectedId.value),
);

function formatDate(d: string | null): string {
  if (!d) return "-";
  return new Date(d).toLocaleDateString("fr-FR");
}

function openFacturePreview(facture: any) {
  previewFacture.value    = facture;
  showPdfPreview.value    = true;
  pdfPreviewLoading.value = true;
}

const statutColor: Record<string, string> = {
  draft: "text-yellow-400 bg-yellow-400/10",
  active: "text-green-400 bg-green-400/10",
};

onMounted(loadContrats);
</script>

<template>
  <div class="space-y-5 animate-fade-up">
    <div>
      <h1 class="font-serif text-2xl">
        Paiements <em class="text-gold italic">& Facturation</em>
      </h1>
      <p class="text-app-text/50 text-sm mt-1">
        Suivi des paiements par contrat
      </p>
    </div>

    <div>
      <button class="btn btn-gold btn-md" @click="$router.push('/admin/factures')">
        &larr; All Factures
      </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
      <!-- Liste contrats (colonne gauche) -->
      <div class="card p-4 space-y-2">
        <p class="text-xs uppercase text-gold tracking-widest font-bold mb-3">
          Contrats
        </p>
        <div v-if="loading" class="space-y-2">
          <div
            v-for="i in 3"
            :key="i"
            class="h-12 bg-white/5 rounded animate-pulse" />
        </div>
        <div v-else-if="contrats.length" class="space-y-1">
          <button
            v-for="c in contrats"
            :key="c.id"
            class="w-full text-left px-3 py-2.5 rounded-xl text-sm transition"
            :class="
              selectedId === c.id
                ? 'bg-gold/15 border border-gold/30'
                : 'hover:bg-white/5 border border-transparent'
            "
            @click="selectContrat(c.id)">
            <p class="font-medium truncate">
              {{ c.entreprise?.raison_sociale ?? `#${c.id}` }}
            </p>
            <div class="flex items-center gap-2 mt-0.5">
              <span
                class="text-xs px-1.5 py-0.5 rounded-full"
                :class="statutColor[c.statut] ?? 'text-app-text/40 bg-white/5'"
                >{{ c.statut }}</span
              >
              <span class="text-xs text-app-text/40"
                >{{ c.prix_total ?? 0 }} DH</span
              >
            </div>
          </button>
        </div>
        <p v-else class="text-sm text-app-text/40 text-center py-4">
          Aucun contrat actif
        </p>
      </div>

      <!-- Détail paiements (colonne droite) -->
      <div class="lg:col-span-2 space-y-4">
        <div v-if="!selectedId" class="card p-10 text-center text-app-text/40">
          <p class="text-3xl mb-2">👈</p>
          <p>Sélectionnez un contrat</p>
        </div>

        <template v-else>
          <!-- Résumé financier -->
          <div v-if="summary" class="card p-4 space-y-3">
            <div class="flex items-center justify-between">
              <p class="font-semibold">
                {{ selectedContrat?.entreprise?.raison_sociale }}
              </p>
              <button class="btn btn-gold btn-sm" @click="showModal = true">
                + Enregistrer un paiement
              </button>
            </div>

            <div class="grid grid-cols-3 gap-3 text-center">
              <div class="rounded-xl bg-white/5 p-3">
                <p class="text-xs text-app-text/40 mb-1">Total contrat</p>
                <p class="font-serif text-xl text-gold">
                  {{ summary.prix_total }} DH
                </p>
              </div>
              <div class="rounded-xl bg-green-500/10 p-3">
                <p class="text-xs text-app-text/40 mb-1">Payé</p>
                <p class="font-serif text-xl text-green-400">
                  {{ summary.total_paye }} DH
                </p>
              </div>
              <div class="rounded-xl bg-red-500/10 p-3">
                <p class="text-xs text-app-text/40 mb-1">Restant</p>
                <p class="font-serif text-xl text-red-400">
                  {{ summary.restant }} DH
                </p>
              </div>
            </div>

            <!-- Barre de progression -->
            <div class="space-y-1">
              <div class="flex justify-between text-xs text-app-text/40">
                <span>Progression</span>
                <span>{{ summary.pourcentage }}%</span>
              </div>
              <div class="h-2 rounded-full bg-white/10 overflow-hidden">
                <div
                  class="h-full rounded-full bg-gold transition-all duration-500"
                  :style="`width:${summary.pourcentage}%`" />
              </div>
            </div>
          </div>

          <!-- Historique paiements -->
          <div class="card p-4 space-y-3">
            <p class="text-xs uppercase text-gold tracking-widest font-bold">
              Historique
            </p>

            <div v-if="loadingPayments" class="space-y-2">
              <div
                v-for="i in 2"
                :key="i"
                class="h-12 bg-white/5 rounded animate-pulse" />
            </div>

            <div v-else-if="paiements.length" class="space-y-2">
              <div
                v-for="p in paiements"
                :key="p.id"
                class="flex items-center justify-between p-3 rounded-xl gap-3 flex-wrap"
                style="background:var(--app-surface-2);border:1px solid var(--app-border)"
              >
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-bold text-green-400">
                    +{{ Number(p.montant).toLocaleString('fr-MA') }} DH
                  </p>
                  <p class="text-xs mt-0.5" style="color:var(--app-text-muted)">
                    {{ p.mode_paiement }} &middot; {{ formatDate(p.date_paiement) }}
                  </p>
                  <p v-if="p.facture?.numero_facture" class="text-xs mt-0.5 font-mono" style="color:#c8a96e">
                    {{ p.facture.numero_facture }}
                  </p>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                  <span class="text-xs text-green-400 bg-green-400/10 px-2 py-0.5 rounded-full font-semibold">
                    Payé
                  </span>

                  <!-- Preview PDF of the linked facture -->
                  <button
                    v-if="p.facture?.id"
                    class="btn btn-outline btn-sm"
                    title="Aperçu facture"
                    @click="openFacturePreview(p.facture)"
                  >
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                      <circle cx="12" cy="12" r="3"/>
                    </svg>
                  </button>

                  <!-- Download PDF -->
                  <a
                    v-if="p.facture?.id"
                    :href="`${getApiBase()}/api/factures/${p.facture.id}/pdf?token=${encodeURIComponent(getToken())}&mode=download`"
                    target="_blank"
                    class="btn btn-gold btn-sm"
                    title="Télécharger facture"
                  >
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                      <polyline points="7 10 12 15 17 10"/>
                      <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                  </a>
                </div>
              </div>
            </div>

            <p v-else class="text-sm text-app-text/40 text-center py-4">
              Aucun paiement enregistré
            </p>
          </div>
        </template>
      </div>
    </div>

    <!-- Modal nouveau paiement -->
    <div
      v-if="showModal"
      class="fixed inset-0 z-100 bg-black/70 flex items-center justify-center p-4"
      @click.self="showModal = false">
      <div class="card w-full max-w-md p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="font-serif text-xl">Enregistrer un paiement</h2>
          <button
            class="text-app-text/40 hover:text-white text-xl"
            @click="showModal = false">
            ✕
          </button>
        </div>

        <p class="text-sm text-app-text/50">
          Contrat : <b>{{ selectedContrat?.entreprise?.raison_sociale }}</b>
        </p>

        <div class="space-y-3">
          <div>
            <label class="f-label">Montant (DH) *</label>
            <input
              v-model="form.montant"
              class="f-input"
              type="number"
              min="0"
              step="0.01"
              placeholder="0.00" />
          </div>
          <div>
            <label class="f-label">Date du paiement *</label>
            <input v-model="form.date_paiement" class="f-input" type="date" />
          </div>
          <div>
            <label class="f-label">Mode de paiement *</label>
            <select v-model="form.mode_paiement" class="f-input">
              <option v-for="m in modeOptions" :key="m" :value="m">
                {{ m }}
              </option>
            </select>
          </div>
          <div>
            <label class="f-label">Note (optionnel)</label>
            <input
              v-model="form.note"
              class="f-input"
              placeholder="Ex: Acompte premier trimestre..." />
          </div>
        </div>

        <div class="flex gap-3 justify-end">
          <button class="btn btn-outline btn-md" @click="showModal = false">
            Annuler
          </button>
          <button
            class="btn btn-gold btn-md"
            :disabled="saving"
            @click="submitPaiement">
            {{ saving ? "Enregistrement..." : "💳 Enregistrer" }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>