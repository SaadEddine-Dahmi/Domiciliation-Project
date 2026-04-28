<!-- ============================================================
  pages/admin/contrat.vue
============================================================ -->
<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from "vue";
import { storeToRefs } from "pinia";
import { useRoute, navigateTo } from "#app";
import { useContractStore } from "~/stores/contrat";
import { useArticlesStore } from "~/stores/articles";
import { useClientsStore } from "~/stores/clients";
import type { ContratForm } from "~/types/contrat";
import { useContratValidation } from "~/composables/useContratValidation";
import { useContratDraft } from "~/composables/useContratDraft";

definePageMeta({ layout: "dashboard", middleware: ["auth"] });

// ── Stores ────────────────────────────────────────────────
const route         = useRoute();
const contract      = useContractStore();
const articlesStore = useArticlesStore();
const clientsStore  = useClientsStore();
const { success, error: toastError } = useToast();

const { items: articleItems, loading: articlesLoading } = storeToRefs(articlesStore);
const { items: clientItems }                             = storeToRefs(clientsStore);

// ── Stepper ───────────────────────────────────────────────
const step  = ref(0);
const steps = ["AST-FISC", "Client", "Durée", "Articles", "Récap"];

// ── IDs courants ──────────────────────────────────────────
const currentContratId     = ref<string>("");
const selectedEntrepriseId = ref<number | null>(null);

// ── Autosave ──────────────────────────────────────────────
const autosaveState = ref<"idle" | "saving" | "saved" | "error">("idle");
const autosaveAt    = ref<string>("");
const autosaveError = ref<string>("");
let autosaveTimer: ReturnType<typeof setTimeout> | null = null;
const AUTOSAVE_DELAY = 1200;

// ── Articles ──────────────────────────────────────────────
const selectedIds   = ref<string[]>([]);
const editingId     = ref<string | null>(null);
const editTitle     = ref<string>("");
const editBody      = ref<string>("");
const newArtTitle   = ref<string>("");
const newArtBody    = ref<string>("");
const savingArticle = ref(false);

// ── VariablePanel refs ────────────────────────────────────
const inlineEditTextareaRef = ref<HTMLTextAreaElement | null>(null);
const inlineEditPanelRef    = ref<any>(null);
const newArtTextareaRef     = ref<HTMLTextAreaElement | null>(null);
const newArtPanelRef        = ref<any>(null);

// ── PDF ───────────────────────────────────────────────────
const showPreview = ref(false);
const pdfUrl      = ref<string>("");
const pdfLoading  = ref(false);

// ── Notification delay — multi-select (can pick 1, 3, 6 or any combo)
const notificationDelays = ref<number[]>([1]);

// ── Composables ───────────────────────────────────────────
const formTyped = contract.form as ContratForm;
const { validateStep } = useContratValidation(formTyped);
const { saveDraft, loadDraft, clearDraft, lastSavedAt } = useContratDraft(
  formTyped,
  selectedIds,
  ref([]),
);

// ── Computed ──────────────────────────────────────────────
const selectedSorted = computed(
  () =>
    selectedIds.value
      .map((id) => articleItems.value.find((a) => a.id === id))
      .filter(Boolean) as typeof articleItems.value,
);

// ── Helpers API ───────────────────────────────────────────
function getApiBase(): string {
  const config = useRuntimeConfig();
  return (config.public.apiBase as string) ?? "";
}

function authHeaders(): Record<string, string> {
  if (!import.meta.client) return {};
  try {
    const raw = localStorage.getItem("astfisc_auth");
    if (!raw) return {};
    const parsed = JSON.parse(raw);
    return parsed?.token ? { Authorization: `Bearer ${parsed.token}` } : {};
  } catch {
    return {};
  }
}

// ── Date helpers ──────────────────────────────────────────

// Returns today as YYYY-MM-DD (for date input value)
function todayIso(): string {
  return new Date().toISOString().split("T")[0];
}

// Returns today + N months as YYYY-MM-DD
function addMonths(base: string, n: number): string {
  const d = new Date(base);
  d.setMonth(d.getMonth() + n);
  // Subtract 1 day so 12-04-2026 + 12 months = 11-04-2027
  d.setDate(d.getDate() - 1);
  return d.toISOString().split("T")[0];
}

// Format any date string → dd/mm/yyyy for display
function formatDatePreview(d: string | null | undefined): string {
  if (!d) return "—";
  const date = new Date(d);
  if (isNaN(date.getTime())) return d;
  return date.toLocaleDateString("fr-MA", {
    day:   "2-digit",
    month: "2-digit",
    year:  "numeric",
  });
}

// ── Notification delay toggle (multi-select) ──────────────
function toggleDelay(m: number): void {
  if (notificationDelays.value.includes(m)) {
    // Don't allow deselecting all — keep at least one
    if (notificationDelays.value.length > 1) {
      notificationDelays.value = notificationDelays.value.filter((x) => x !== m);
    }
  } else {
    notificationDelays.value = [...notificationDelays.value, m].sort((a, b) => a - b);
  }
}

// ── Calcul durée depuis les dates ─────────────────────────
function monthsBetween(start: string, end: string): number {
  if (!start || !end) return 0;
  const diff = new Date(end).getTime() - new Date(start).getTime();
  if (diff < 0) return 0;
  return Math.max(1, Math.round(diff / (1000 * 60 * 60 * 24 * 30.44)));
}

function syncMonthsFromDates(): void {
  const m = monthsBetween(contract.form.dateDebut, contract.form.dateFin);
  if (m > 0) {
    contract.form.months = m;
    contract.syncFromMonths();
  }
}

// ── Client-side variable renderer ─────────────────────────
// Mirrors TemplateService.render() so preview matches PDF
function renderVariables(body: string, vars: Record<string, string>): string {
  let result = body;
  for (const [key, value] of Object.entries(vars)) {
    result = result.replaceAll(`{{${key}}}`, `<strong>${value}</strong>`);
  }
  // Show unreplaced vars in gold so missing data is visible
  result = result.replace(
    /\{\{([a-z_]+)\}\}/g,
    '<span style="color:#c8a96e;font-style:italic">[$1]</span>',
  );
  return result;
}

// ── Articles CRUD ─────────────────────────────────────────
function toggleArticle(id: string): void {
  selectedIds.value = selectedIds.value.includes(id)
    ? selectedIds.value.filter((x) => x !== id)
    : [...selectedIds.value, id];
}

function startEdit(a: { id: string; title: string; body: string }): void {
  editingId.value = a.id;
  editTitle.value = a.title;
  editBody.value  = a.body;
}

function cancelEdit(): void {
  editingId.value = null;
  editTitle.value = "";
  editBody.value  = "";
}

async function saveEdit(): Promise<void> {
  if (!editingId.value || !editTitle.value.trim()) return;
  savingArticle.value = true;
  try {
    await articlesStore.update(editingId.value, editTitle.value.trim(), editBody.value.trim(), true);
    success("Article mis à jour");
    cancelEdit();
  } catch (e: any) {
    toastError?.(e?.data?.message ?? "Erreur mise à jour article");
  } finally {
    savingArticle.value = false;
  }
}

async function deleteArticle(id: string): Promise<void> {
  if (!confirm("Supprimer cet article définitivement ?")) return;
  try {
    await articlesStore.remove(id);
    selectedIds.value = selectedIds.value.filter((x) => x !== id);
    success("Article supprimé");
  } catch (e: any) {
    toastError?.(e?.data?.message ?? "Erreur suppression article");
  }
}

async function addArticle(): Promise<void> {
  if (!newArtTitle.value.trim()) return;
  savingArticle.value = true;
  try {
    const created = await articlesStore.create(newArtTitle.value.trim(), newArtBody.value.trim());
    selectedIds.value.push(created.id);
    newArtTitle.value = "";
    newArtBody.value  = "";
    success("Article ajouté à la bibliothèque");
  } catch (e: any) {
    toastError?.(e?.data?.message ?? "Erreur création article");
  } finally {
    savingArticle.value = false;
  }
}

// ── Sauvegarde contrat ────────────────────────────────────
function buildContratBody() {
  return {
    entreprise_id:             selectedEntrepriseId.value,
    date_debut:                contract.form.dateDebut       || null,
    date_fin:                  contract.form.dateFin         || null,
    duree_mois:                contract.form.months          || null,
    prix_mensuel:              contract.monthlyTotal         || null,
    prix_total:                contract.grandTotal           || null,
    statut:                    "draft",
    // Send the smallest delay to backend for DB storage
    notification_delay_months: Math.min(...notificationDelays.value),
    article_ids:               selectedIds.value,
    instruction_no:            contract.form.instruction_no  || null,
    ville_signature:           contract.form.ville_signature || null,
    date_signature:            contract.form.date_signature  || null,
    caution:                   contract.form.caution         || null,
    mode_paiement:             contract.form.mode_paiement   || null,
  };
}

async function saveAsDraft(silent = false): Promise<void> {
  if (!selectedEntrepriseId.value) {
    if (!silent) toastError?.("Sélectionnez une entreprise cliente (étape 2)");
    return;
  }
  try {
    if (!silent) autosaveState.value = "saving";
    const body = buildContratBody();

    if (!currentContratId.value) {
      const res = await $fetch<{ success: boolean; data: any }>(
        `${getApiBase()}/api/contrats`,
        { method: "POST", headers: authHeaders(), body },
      );
      currentContratId.value = String(res.data.id);
      await navigateTo(
        { path: "/admin/contrat", query: { id: currentContratId.value } },
        { replace: true },
      );
      if (!silent) success("Brouillon créé");
    } else {
      await $fetch(`${getApiBase()}/api/contrats/${currentContratId.value}`, {
        method: "PUT",
        headers: authHeaders(),
        body,
      });
      if (!silent) success("Brouillon mis à jour");
    }

    autosaveState.value = "saved";
    autosaveAt.value    = new Date().toISOString();
    autosaveError.value = "";
  } catch (e: any) {
    autosaveState.value = "error";
    autosaveError.value = e?.data?.errors
      ? Object.values(e.data.errors).flat().join(" · ")
      : (e?.data?.message ?? "Erreur de sauvegarde");
    if (!silent) toastError?.(autosaveError.value);
  }
}

function scheduleAutosave(): void {
  if (!selectedEntrepriseId.value) return;
  if (autosaveTimer) clearTimeout(autosaveTimer);
  autosaveState.value = "saving";
  autosaveTimer = setTimeout(() => saveAsDraft(true), AUTOSAVE_DELAY);
}

// ── PDF ───────────────────────────────────────────────────
async function generateAndDownloadPdf(): Promise<void> {
  if (!currentContratId.value) {
    toastError?.("Sauvegardez d'abord le contrat avant de générer le PDF");
    return;
  }
  pdfLoading.value = true;
  try {
    const res = await $fetch<{ success: boolean; data: { url: string; pdf_path: string } }>(
      `${getApiBase()}/api/contrats/${currentContratId.value}/pdf`,
      { method: "POST", headers: authHeaders() },
    );
    pdfUrl.value = res.data.url;

    const response = await fetch(res.data.url);
    const blob     = await response.blob();
    const blobUrl  = URL.createObjectURL(new Blob([blob], { type: "application/pdf" }));
    const a        = document.createElement("a");
    a.href         = blobUrl;
    a.download     = `contrat_${currentContratId.value}.pdf`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(blobUrl), 3000);
    success("PDF généré et téléchargé ✓");
  } catch (e: any) {
    toastError?.(e?.data?.message ?? "Erreur génération PDF");
  } finally {
    pdfLoading.value = false;
  }
}

// ── Stepper navigation ────────────────────────────────────
function next(): void {
  if (!validateStep(step.value)) return toastError?.("Veuillez corriger les champs requis");
  if (step.value < steps.length - 1) step.value++;
}
function prev(): void {
  if (step.value > 0) step.value--;
}

// ── Reset ─────────────────────────────────────────────────
function resetForNewContract(): void {
  contract.resetForm?.();
  contract.selectedServices  = [];
  // Default dates: today → today + 12 months - 1 day
  const today = todayIso();
  contract.form.dateDebut    = today;
  contract.form.dateFin      = addMonths(today, 12);
  contract.form.months       = 12;
  selectedIds.value          = articleItems.value.filter((a) => a.is_active).map((a) => a.id);
  currentContratId.value     = "";
  selectedEntrepriseId.value = null;
  pdfUrl.value               = "";
  step.value                 = 0;
  autosaveState.value        = "idle";
  autosaveAt.value           = "";
  autosaveError.value        = "";
  notificationDelays.value   = [1];
}

async function preloadFromRouteId(): Promise<void> {
  const id = String(route.query.id || "");
  if (!id) return;
  try {
    const res = await $fetch<{ success: boolean; data: any }>(
      `${getApiBase()}/api/contrats/${id}`,
      { headers: authHeaders() },
    );
    const c = res.data;
    currentContratId.value     = String(c.id);
    selectedEntrepriseId.value = c.entreprise_id    ?? null;
    contract.form.dateDebut    = c.date_debut        ?? "";
    contract.form.dateFin      = c.date_fin          ?? "";
    contract.form.months       = c.duree_mois        ?? 12;
    contract.form.instruction_no  = c.instruction_no  ?? "";
    contract.form.ville_signature = c.ville_signature ?? "";
    contract.form.date_signature  = c.date_signature  ?? "";
    contract.form.caution         = c.caution         ?? "";
    contract.form.mode_paiement   = c.mode_paiement   ?? "";
    contract.setMonthly(Number(c.prix_mensuel ?? 0));
    if (Array.isArray(c.articles)) {
      selectedIds.value = c.articles.map((a: any) => a.id);
    }
    if (c.notification_delay_months) {
      notificationDelays.value = [c.notification_delay_months];
    }
    if (c.pdf_path) pdfUrl.value = `${getApiBase()}/storage/${c.pdf_path}`;
  } catch (e) {
    console.error("Erreur chargement contrat:", e);
  }
}

// ── Watches ───────────────────────────────────────────────
watch(() => [contract.form.dateDebut, contract.form.dateFin], syncMonthsFromDates);
watch(() => contract.form.months, () => contract.syncFromMonths());
watch(
  () => [contract.form, selectedIds.value, contract.selectedServices],
  () => { saveDraft(); scheduleAutosave(); },
  { deep: true },
);

// ── Lifecycle ─────────────────────────────────────────────
onMounted(async () => {
  await Promise.all([articlesStore.fetchAll(), clientsStore.fetchAll()]);

  const isNew = String(route.query.new || "") === "1";
  if (isNew) {
    resetForNewContract();
    clearDraft();
    await navigateTo({ path: "/admin/contrat", query: {} }, { replace: true });
    return;
  }

  await preloadFromRouteId();

  if (!currentContratId.value) {
    loadDraft();
    if (!selectedIds.value.length) {
      selectedIds.value = articleItems.value.filter((a) => a.is_active).map((a) => a.id);
    }
    // Set default dates only if not restored from draft
    if (!contract.form.dateDebut) {
      const today = todayIso();
      contract.form.dateDebut = today;
      contract.form.dateFin   = addMonths(today, 12);
      contract.form.months    = 12;
    }
  }

  syncMonthsFromDates();
});

onBeforeUnmount(() => {
  if (autosaveTimer) clearTimeout(autosaveTimer);
});

// ── HTML Preview computed ─────────────────────────────────
// SECURITY: esc() is defined OUTSIDE computed — fixes compiler error
// All user values must go through esc() before v-html interpolation
function esc(value: string | null | undefined): string {
  if (!value) return "";
  return String(value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#x27;")
    .replace(/\//g, "&#x2F;");
}

const contractPreviewHtml = computed((): string => {
  const f          = contract.form as any;
  const entreprise = clientItems.value.find((c) => c.id === selectedEntrepriseId.value);
  const rep        = (entreprise as any)?.representant;

  // Variable map — mirrors TemplateService.dataFromContrat()
  const vars: Record<string, string> = {
    domiciliataire_nom:     f.astNom      || "",
    domiciliataire_rc:      f.astRC       || "",
    domiciliataire_if:      f.astIF       || "",
    domiciliataire_adresse: f.astAdresse  || "",
    raison_sociale:      entreprise?.raison_sociale  || f.societe || "",
    forme_juridique:     entreprise?.forme_juridique || "",
    adresse_entreprise:  entreprise?.adresse         || "",
    ville:               entreprise?.ville           || "",
    pays:                entreprise?.pays            || "",
    capital:             entreprise?.capital
      ? Number(entreprise.capital).toLocaleString("fr-MA") + " DH"
      : "",
    gerant_nom:         f.gerantNom       || rep?.nom_complet || "",
    gerant_cin:         f.gerantCIN       || rep?.cin         || "",
    gerant_naissance:   rep?.date_naissance
      ? formatDatePreview(rep.date_naissance)
      : "",
    gerant_adresse:     rep?.adresse      || f.adressePerso   || "",
    gerant_telephone:   f.tel             || rep?.telephone   || "",
    gerant_email:       f.email           || rep?.email       || "",
    gerant_nationalite: rep?.nationalite  || "",
    numero_contrat:  currentContratId.value       || "",
    instruction_no:  f.instruction_no             || "",
    date_debut:      formatDatePreview(f.dateDebut),
    date_fin:        formatDatePreview(f.dateFin),
    duree_mois:      String(f.months              || ""),
    date_signature:  formatDatePreview(f.date_signature),
    ville_signature: f.ville_signature            || "",
    redevance_mensuelle: contract.monthlyTotal
      ? contract.monthlyTotal.toLocaleString("fr-MA") + " DH/mois"
      : "",
    redevance_annuelle: contract.grandTotal
      ? contract.grandTotal.toLocaleString("fr-MA") + " DH"
      : "",
    caution: f.caution
      ? Number(f.caution).toLocaleString("fr-MA") + " DH"
      : "",
    mode_paiement: f.mode_paiement || "",
  };

  const articleLines = selectedSorted.value
    .map((a, i) =>
      `<div style="margin-bottom:16px">
        <p style="margin:0 0 6px;font-weight:700;font-size:13px">
          ARTICLE ${i + 1} — ${esc(a.title)}
        </p>
        <p style="margin:0;line-height:1.7;text-align:justify">
          ${renderVariables(esc(a.body ?? ""), vars)}
        </p>
      </div>`
    )
    .join("");

  // SECURITY: every user value goes through esc() before HTML interpolation
  const domiciliataire  = esc(vars.domiciliataire_nom) || "-";
  const domicilie       = esc(vars.raison_sociale)      || "-";
  const astNom          = esc(f.astNom)                 || "-";
  const astRC           = esc(f.astRC)                  || "-";
  const astIF           = esc(f.astIF)                  || "-";
  const astAdresse      = esc(f.astAdresse)             || "-";
  const astRepresentant = esc(f.astRepresentant)        || "-";
  const astCIN          = esc(f.astCIN)                 || "-";
  const gerantNom       = esc(vars.gerant_nom)          || "-";
  const gerantCin       = esc(vars.gerant_cin)          || "-";
  const gerantTel       = esc(vars.gerant_telephone)    || "-";
  const gerantEmail     = esc(vars.gerant_email)        || "-";
  const dateDebut       = esc(vars.date_debut)          || "-";
  const dateFin         = esc(vars.date_fin)            || "-";
  const dureeMois       = esc(vars.duree_mois)          || "-";
  const mensuel         = esc(vars.redevance_mensuelle) || "-";
  const annuel          = esc(vars.redevance_annuelle)  || "-";
  const villeSig        = esc(vars.ville_signature)     || "__________________";
  const dateSig         = esc(vars.date_signature)      || "__________________";

  return `
    <div style="font-family:'Times New Roman',serif;color:#111;background:#fff;
                padding:40px 48px;max-width:860px;margin:0 auto;font-size:12px;line-height:1.65">
      <h1 style="text-align:center;font-size:20px;margin:0 0 6px;letter-spacing:1px;text-transform:uppercase">
        Contrat de Domiciliation
      </h1>
      <p style="text-align:center;font-size:11px;color:#555;margin:0 0 14px">
        ${astNom} — RC : ${astRC} — IF : ${astIF}
      </p>
      <hr style="border:none;border-top:1.5px solid #c8a96e;margin:12px 0 16px"/>

      <p style="margin:0 0 5px;font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#888">Parties</p>
      <p style="margin:0 0 5px"><b>Domiciliataire :</b> ${domiciliataire}</p>
      <p style="margin:0 0 5px"><b>Représenté par :</b> ${astRepresentant}, CIN ${astCIN}</p>
      <p style="margin:0 0 5px"><b>Adresse siège :</b> ${astAdresse}</p>
      <p style="margin:0 0 5px"><b>Domicilié :</b> ${domicilie}</p>
      <p style="margin:0 0 5px"><b>Gérant :</b> ${gerantNom}, CIN ${gerantCin}</p>
      <p style="margin:0 0 14px"><b>Contact :</b> ${gerantTel} · ${gerantEmail}</p>

      <hr style="border:none;border-top:1px solid #ddd;margin:10px 0 14px"/>

      <p style="margin:0 0 5px;font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#888">Durée &amp; Redevance</p>
      <p style="margin:0 0 5px"><b>Période :</b> ${dateDebut} → ${dateFin} (${dureeMois} mois)</p>
      <p style="margin:0 0 16px"><b>Redevance mensuelle :</b> ${mensuel} &nbsp;|&nbsp; <b>Annuelle :</b> ${annuel}</p>

      <hr style="border:none;border-top:1px solid #ddd;margin:10px 0 14px"/>

      <p style="margin:0 0 12px;font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#888">Articles du contrat</p>
      ${articleLines || '<p style="color:#999;font-style:italic">Aucun article sélectionné.</p>'}

      <hr style="border:none;border-top:1.5px solid #c8a96e;margin:20px 0 14px"/>

      <div style="display:table;width:100%;margin-top:40px">
        <div style="display:table-cell;width:50%">
          <p style="margin:0 0 50px">Fait à ${villeSig}</p>
          <p style="margin:0;font-weight:bold">Signature du domiciliataire</p>
          <p style="margin:4px 0 0;font-size:11px;color:#555">${domiciliataire}</p>
        </div>
        <div style="display:table-cell;width:50%;text-align:right">
          <p style="margin:0 0 50px">Le ${dateSig}</p>
          <p style="margin:0;font-weight:bold">Lu et approuvé, bon pour accord</p>
          <p style="margin:4px 0 0;font-size:11px;color:#555">${domicilie}</p>
        </div>
      </div>
    </div>`;
});
</script>

<template>
  <div class="space-y-4 animate-fade-up">

    <!-- ── Header ─────────────────────────────────────── -->
    <div class="card p-4 flex items-center justify-between flex-wrap gap-2">
      <div>
        <h1 class="font-serif text-2xl">
          {{ currentContratId ? `Contrat #${currentContratId}` : "Nouveau contrat" }}
        </h1>
        <p class="text-xs mt-1 text-app-text/40">
          <span v-if="autosaveState === 'saved'">
            ✓ Sauvegardé {{ new Date(autosaveAt).toLocaleTimeString() }}
          </span>
          <span v-else-if="autosaveState === 'saving'">⏳ Sauvegarde en cours...</span>
          <span v-else-if="autosaveState === 'error'" class="text-red-400">
            ✗ {{ autosaveError }}
          </span>
          <span v-else-if="lastSavedAt">
            Brouillon local : {{ new Date(lastSavedAt).toLocaleString() }}
          </span>
        </p>
      </div>
      <div class="flex gap-2">
        <button class="btn btn-outline btn-sm" @click="showPreview = true">👁 Aperçu</button>
        <button class="btn btn-outline btn-sm" @click="saveAsDraft(false)">💾 Sauvegarder</button>
      </div>
    </div>

    <!-- ── Stepper ────────────────────────────────────── -->
    <div class="card p-3">
      <div class="flex items-center gap-2 flex-wrap">
        <button
          v-for="(s, i) in steps"
          :key="s"
          class="px-3 py-1.5 rounded-lg text-xs border font-bold transition"
          :class="step === i
            ? 'border-gold text-gold bg-gold/10'
            : 'border-white/10 text-app-text/50 hover:border-white/20'"
          @click="step = i"
        >
          {{ i + 1 }}. {{ s }}
        </button>
      </div>
    </div>

    <!-- ── Étape 0 : AST-FISC ────────────────────────── -->
    <div v-if="step === 0" class="card p-4 grid grid-cols-1 md:grid-cols-2 gap-3">
      <div>
        <label class="f-label">Raison sociale</label>
        <input v-model="contract.form.astNom" class="f-input" />
      </div>
      <div>
        <label class="f-label">RC</label>
        <input v-model="contract.form.astRC" class="f-input" />
      </div>
      <div>
        <label class="f-label">IF</label>
        <input v-model="contract.form.astIF" class="f-input" />
      </div>
      <div>
        <label class="f-label">Représentant</label>
        <input v-model="contract.form.astRepresentant" class="f-input" />
      </div>
      <div>
        <label class="f-label">CIN Représentant</label>
        <input v-model="contract.form.astCIN" class="f-input" />
      </div>
      <div>
        <label class="f-label">Adresse siège</label>
        <input v-model="contract.form.astAdresse" class="f-input" />
      </div>
    </div>

    <!-- ── Étape 1 : Client ───────────────────────────── -->
    <div v-else-if="step === 1" class="card p-4 space-y-4">
      <div>
        <label class="f-label">Entreprise cliente *</label>
        <select v-model="selectedEntrepriseId" class="f-input">
          <option :value="null" disabled>-- Sélectionner une entreprise --</option>
          <option v-for="c in clientItems" :key="c.id" :value="c.id">
            {{ c.raison_sociale }}{{ c.ville ? ` — ${c.ville}` : "" }}
          </option>
        </select>
        <p
          v-if="!clientItems.length && !clientsStore.loading"
          class="text-xs text-app-text/40 mt-1"
        >
          Aucun client —
          <NuxtLink to="/admin/clients" class="text-gold underline">
            créer un client d'abord
          </NuxtLink>
        </p>
      </div>

      <div
        v-if="selectedEntrepriseId"
        class="rounded-xl border border-white/10 p-3 space-y-1 text-sm bg-white/3"
      >
        <p class="text-app-text/40 text-xs uppercase tracking-widest mb-2">
          Informations depuis la base de données
        </p>
        <template
          v-for="c in clientItems.filter((x) => x.id === selectedEntrepriseId)"
          :key="c.id"
        >
          <p><b>Raison sociale :</b> {{ c.raison_sociale }}</p>
          <p><b>Forme juridique :</b> {{ c.forme_juridique ?? "-" }}</p>
          <p><b>Adresse :</b> {{ c.adresse ?? "-" }}, {{ c.ville ?? "-" }}</p>
          <p v-if="c.client_user">
            <b>Gérant :</b> {{ c.client_user.nom }} {{ c.client_user.prenom }}
          </p>
          <p v-if="c.client_user"><b>Email :</b> {{ c.client_user.email }}</p>
        </template>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="f-label">Nom gérant (contrat)</label>
          <input v-model="contract.form.gerantNom" class="f-input" />
        </div>
        <div>
          <label class="f-label">CIN gérant</label>
          <input v-model="contract.form.gerantCIN" class="f-input" />
        </div>
        <div>
          <label class="f-label">Téléphone</label>
          <input v-model="contract.form.tel" class="f-input" />
        </div>
        <div>
          <label class="f-label">Email</label>
          <input v-model="contract.form.email" class="f-input" type="email" />
        </div>
        <div class="md:col-span-2">
          <label class="f-label">Adresse personnelle</label>
          <input v-model="contract.form.adressePerso" class="f-input" />
        </div>
      </div>
    </div>

    <!-- ── Étape 2 : Durée & Montants ────────────────── -->
    <div v-else-if="step === 2" class="card p-4 space-y-4">

      <!-- Dates + redevance -->
      <p class="text-xs uppercase text-gold tracking-widest font-bold">
        Durée &amp; Redevance
      </p>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="f-label">Date début *</label>
          <input v-model="contract.form.dateDebut" class="f-input" type="date" />
        </div>
        <div>
          <label class="f-label">Date fin</label>
          <input v-model="contract.form.dateFin" class="f-input" type="date" />
        </div>
        <div>
          <label class="f-label">Redevance mensuelle (DH)</label>
          <input
            :value="contract.form.redevanceMensuelle"
            class="f-input"
            type="number"
            @input="(e: any) => contract.setMonthly(Number(e.target.value) || 0)"
          />
        </div>
        <div>
          <label class="f-label">Redevance annuelle (DH)</label>
          <input
            :value="contract.form.redevanceAnnuelle"
            class="f-input"
            type="number"
            @input="(e: any) => contract.setAnnual(Number(e.target.value) || 0)"
          />
        </div>
      </div>

      <!-- Recap -->
      <div class="rounded-xl border border-white/10 p-3 space-y-1 text-sm bg-white/3">
        <p>Durée calculée : <b>{{ contract.form.months }} mois</b></p>
        <p>Total mensuel : <b>{{ contract.monthlyTotal }} DH</b></p>
        <p>Total global : <b class="text-gold text-base">{{ contract.grandTotal }} DH</b></p>
      </div>

      <!-- Notification delay — multi-select -->
      <div class="rounded-xl border border-gold/20 p-3 space-y-2 bg-gold/5">
        <p class="text-xs uppercase text-gold tracking-widest font-bold">
          🔔 Rappels avant expiration
        </p>
        <p class="text-xs text-app-text/50">
          Sélectionnez un ou plusieurs délais — vous recevrez une notification à chaque échéance.
        </p>
        <div class="flex gap-2 flex-wrap">
          <button
            v-for="m in [1, 3, 6]"
            :key="m"
            class="px-4 py-2 rounded-lg text-sm font-bold border transition"
            :class="notificationDelays.includes(m)
              ? 'border-gold bg-gold/20 text-gold'
              : 'border-white/10 text-app-text/50 hover:border-white/30'"
            @click="toggleDelay(m)"
          >
            {{ m }} mois
          </button>
        </div>
        <p class="text-xs text-app-text/30">
          Sélectionnés : {{ notificationDelays.sort((a,b) => a-b).join(", ") }} mois avant expiration
        </p>
      </div>

      <!-- Contract detail fields — feed {{variables}} -->
      <p class="text-xs uppercase text-gold tracking-widest font-bold pt-1">
        Détails du contrat
      </p>
      <p class="text-xs text-app-text/40 -mt-2">
        Ces valeurs remplaceront les variables dans les articles.
      </p>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="f-label">
            N° Instruction
            <code class="text-gold text-[10px] ml-1 font-mono">&#123;&#123;instruction_no&#125;&#125;</code>
          </label>
          <input
            v-model="contract.form.instruction_no"
            class="f-input"
            placeholder="Ex: 1923"
          />
        </div>
        <div>
          <label class="f-label">
            Ville de signature
            <code class="text-gold text-[10px] ml-1 font-mono">&#123;&#123;ville_signature&#125;&#125;</code>
          </label>
          <input
            v-model="contract.form.ville_signature"
            class="f-input"
            placeholder="Ex: AGADIR"
          />
        </div>
        <div>
          <label class="f-label">
            Date de signature
            <code class="text-gold text-[10px] ml-1 font-mono">&#123;&#123;date_signature&#125;&#125;</code>
          </label>
          <input
            v-model="contract.form.date_signature"
            class="f-input"
            type="date"
          />
        </div>
        <div>
          <label class="f-label">
            Mode de paiement
            <code class="text-gold text-[10px] ml-1 font-mono">&#123;&#123;mode_paiement&#125;&#125;</code>
          </label>
          <select v-model="contract.form.mode_paiement" class="f-input">
            <option value="">-- Choisir --</option>
            <option value="Virement bancaire">Virement bancaire</option>
            <option value="Espèces">Espèces</option>
            <option value="Chèque">Chèque</option>
          </select>
        </div>
        <div>
          <label class="f-label">
            Caution (DH)
            <code class="text-gold text-[10px] ml-1 font-mono">&#123;&#123;caution&#125;&#125;</code>
          </label>
          <input
            v-model="contract.form.caution"
            class="f-input"
            type="number"
            placeholder="Ex: 500"
          />
        </div>
      </div>
    </div>

    <!-- ── Étape 3 : Articles depuis DB ──────────────── -->
    <div v-else-if="step === 3" class="space-y-3">
      <div class="card p-4 space-y-3">

        <div class="flex items-center justify-between">
          <p class="font-semibold text-sm">
            Bibliothèque d'articles
            <span class="text-app-text/40 font-normal ml-1">
              ({{ articleItems.length }} disponibles)
            </span>
          </p>
          <p class="text-xs text-gold">{{ selectedIds.length }} sélectionné(s)</p>
        </div>

        <div v-if="articlesLoading" class="text-center py-8 text-app-text/40">
          Chargement des articles...
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="a in articleItems"
            :key="a.id"
            class="rounded-xl border p-3 transition"
            :class="selectedIds.includes(a.id)
              ? 'border-gold/40 bg-gold/5'
              : 'border-white/10 hover:border-white/20'"
          >
            <div v-if="editingId !== a.id">
              <div class="flex items-start justify-between gap-2">
                <button class="text-left flex-1 min-w-0" @click="toggleArticle(a.id)">
                  <p class="font-semibold text-sm">{{ a.title }}</p>
                  <p class="text-xs text-app-text/50 mt-1 line-clamp-2">{{ a.body }}</p>
                </button>
                <div class="flex gap-1 flex-shrink-0 ml-2 items-center">
                  <span
                    class="text-xs px-2 py-0.5 rounded-full"
                    :class="selectedIds.includes(a.id)
                      ? 'text-gold bg-gold/10'
                      : 'text-app-text/40 bg-white/5'"
                  >
                    {{ selectedIds.includes(a.id) ? "✓ Sélectionné" : "○" }}
                  </span>
                  <button class="btn btn-outline btn-sm" @click.stop="startEdit(a)">Éditer</button>
                  <button class="btn btn-danger btn-sm" @click.stop="deleteArticle(a.id)">✕</button>
                </div>
              </div>
            </div>

            <div v-else class="space-y-3">
              <input
                v-model="editTitle"
                class="f-input text-sm"
                placeholder="Titre de l'article"
              />
              <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                <div>
                  <p class="f-label mb-1">
                    Contenu
                    <span class="text-app-text/30 font-normal text-xs ml-1">
                      — glissez les variables →
                    </span>
                  </p>
                  <textarea
                    ref="inlineEditTextareaRef"
                    v-model="editBody"
                    class="f-input text-sm min-h-[140px] resize-y font-mono"
                    placeholder="Contenu de l'article avec {{variables}}..."
                    @dragover="inlineEditPanelRef?.onDragOver($event)"
                    @drop="inlineEditPanelRef?.onDrop($event)"
                  />
                </div>
                <div
                  class="border border-white/10 rounded-xl p-3 overflow-y-auto"
                  style="max-height:220px"
                >
                  <VariablePanel
                    ref="inlineEditPanelRef"
                    v-model="editBody"
                    :textarea-ref="inlineEditTextareaRef"
                  />
                </div>
              </div>
              <div class="flex gap-2">
                <button
                  class="btn btn-gold btn-sm"
                  :disabled="savingArticle"
                  @click="saveEdit"
                >
                  {{ savingArticle ? "Enregistrement..." : "✓ Enregistrer" }}
                </button>
                <button class="btn btn-outline btn-sm" @click="cancelEdit">Annuler</button>
              </div>
            </div>
          </div>
        </div>

        <div class="border-t border-white/10 pt-4 space-y-3">
          <p class="text-xs uppercase text-gold tracking-widest font-bold">
            Ajouter un article à la bibliothèque
          </p>
          <input
            v-model="newArtTitle"
            class="f-input"
            placeholder="Titre du nouvel article"
          />
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            <div>
              <textarea
                ref="newArtTextareaRef"
                v-model="newArtBody"
                class="f-input min-h-[140px] resize-y font-mono text-sm"
                placeholder="Contenu du nouvel article avec {{variables}}..."
                @dragover="newArtPanelRef?.onDragOver($event)"
                @drop="newArtPanelRef?.onDrop($event)"
              />
            </div>
            <div
              class="border border-white/10 rounded-xl p-3 overflow-y-auto"
              style="max-height:220px"
            >
              <VariablePanel
                ref="newArtPanelRef"
                v-model="newArtBody"
                :textarea-ref="newArtTextareaRef"
              />
            </div>
          </div>
          <button
            class="btn btn-gold btn-md"
            :disabled="savingArticle || !newArtTitle.trim()"
            @click="addArticle"
          >
            {{ savingArticle ? "Ajout..." : "+ Ajouter à la bibliothèque" }}
          </button>
        </div>

      </div>
    </div>

    <!-- ── Étape 4 : Récapitulatif ────────────────────── -->
    <div v-else class="card p-4 space-y-3">
      <p class="font-serif text-lg">Récapitulatif du contrat</p>
      <div class="space-y-1 text-sm">
        <p>
          Entreprise :
          <b>{{ clientItems.find((c) => c.id === selectedEntrepriseId)?.raison_sociale ?? "—" }}</b>
        </p>
        <p>
          Période :
          <b>{{ formatDatePreview(contract.form.dateDebut) }} → {{ formatDatePreview(contract.form.dateFin) }}</b>
        </p>
        <p>Durée : <b>{{ contract.form.months }} mois</b></p>
        <p>Redevance mensuelle : <b>{{ contract.monthlyTotal }} DH</b></p>
        <p>Total global : <b class="text-gold text-base">{{ contract.grandTotal }} DH</b></p>
        <p>Articles inclus : <b>{{ selectedIds.length }}</b></p>
        <p v-if="contract.form.ville_signature">
          Ville signature : <b>{{ contract.form.ville_signature }}</b>
        </p>
        <p v-if="contract.form.mode_paiement">
          Mode de paiement : <b>{{ contract.form.mode_paiement }}</b>
        </p>
        <p>
          Rappels configurés :
          <b>{{ notificationDelays.sort((a,b) => a-b).join(", ") }} mois avant expiration</b>
        </p>
      </div>

      <div v-if="pdfUrl" class="p-3 rounded-xl border border-green-500/30 bg-green-500/10">
        <p class="text-sm text-green-400">
          ✓ PDF disponible —
          <a :href="pdfUrl" target="_blank" class="underline">Ouvrir dans un nouvel onglet</a>
        </p>
      </div>
    </div>

    <!-- ── Navigation ─────────────────────────────────── -->
    <div class="flex items-center justify-between gap-2">
      <button class="btn btn-outline btn-md" :disabled="step === 0" @click="prev">
        ← Précédent
      </button>
      <div class="flex gap-2 flex-wrap justify-end">
        <button class="btn btn-outline btn-sm" @click="clearDraft">🗑 Vider brouillon</button>
        <button
          v-if="step === 4"
          class="btn btn-gold btn-md"
          :disabled="pdfLoading"
          @click="generateAndDownloadPdf"
        >
          {{ pdfLoading ? "⏳ Génération..." : "⬇ Générer PDF" }}
        </button>
        <button v-if="step < 4" class="btn btn-gold btn-md" @click="next">Suivant →</button>
      </div>
    </div>

  </div>

  <!-- ── Modal aperçu — teleported to body ─────────────── -->
  <Teleport to="body">
    <div
      v-if="showPreview"
      class="fixed inset-0 z-[200] bg-black/80 overflow-y-auto"
    >
      <div class="min-h-full px-4 py-8">
        <div class="max-w-4xl mx-auto">

          <div class="flex justify-end mb-4 gap-2">
            <button class="btn btn-outline btn-sm" @click="showPreview = false">
              ✕ Fermer
            </button>
            <button
              class="btn btn-gold btn-sm"
              :disabled="pdfLoading"
              @click="generateAndDownloadPdf"
            >
              {{ pdfLoading ? "Génération..." : "⬇ Télécharger PDF" }}
            </button>
          </div>

          <div class="rounded-xl overflow-hidden shadow-2xl">
            <!-- eslint-disable-next-line vue/no-v-html -->
            <div v-html="contractPreviewHtml" />
          </div>

          <div class="flex justify-center mt-6 pb-4">
            <button class="btn btn-outline btn-md" @click="showPreview = false">
              ✕ Fermer l'aperçu
            </button>
          </div>

        </div>
      </div>
    </div>
  </Teleport>

</template>