<script setup lang="ts">
/**
 * pages/admin/contrat.vue
 * 4-step contract creation wizard using shared ContratPreviewModal component.
 *
 * RESUME / RENEWAL SUPPORT (this revision):
 *   ?id=<id> (or legacy ?edit=<id>) loads an existing draft contract —
 *   created either through this wizard normally, or via
 *   ContratController::renew() — and resumes editing it.
 *
 *   &renewal=1&step=3 (set by contrats.vue when the user confirms a
 *   renewal) forces the wizard to open directly on Step 3, skipping
 *   Step 1/2 entirely, since a renewal draft already has its client and
 *   domiciliataire fixed server-side. Articles (with their saved order)
 *   and financial fields are populated from the loaded draft.
 *
 *   saveDraft() switches from POST (create) to PUT (update) whenever a
 *   draft was resumed this way, via the isResumedDraft flag — otherwise
 *   saving a resumed/renewal draft would create a duplicate contract
 *   instead of updating the existing one.
 */

import { useContractStore } from "~/stores/contrat";
import { useClientsStore } from "~/stores/clients";
import { useArticlesStore } from "~/stores/articles";
import { contratService } from "~/services/contrat.service";
import {
  templateService,
  type TemplateEntity,
} from "~/services/template.service";
import ContratPreviewModal from "~/components/ContratPreviewModal.vue";
import { countryDialCodes } from "~/utils/countryDialCodes";

definePageMeta({ layout: "dashboard", middleware: ["auth"] });

const route = useRoute();
const contract = useContractStore();
const clientsStore = useClientsStore();
const articlesStore = useArticlesStore();
const { success, error: toastError } = useToast();

const dialCodes = countryDialCodes;

function joinPhone(dialCode: string, number: string): string {
  const local = number.trim().replace(/^0+/, "");
  return local ? `${dialCode} ${local}` : "";
}

const pdfPreview = ref();

function getApiBase(): string {
  const config = useRuntimeConfig();
  return (config.public.apiBase as string) ?? "";
}

function authHeaders(): Record<string, string> {
  if (!import.meta.client) return {};
  try {
    const raw = localStorage.getItem("app_auth");
    if (!raw) return {};
    const parsed = JSON.parse(raw);
    return parsed?.token ? { Authorization: `Bearer ${parsed.token}` } : {};
  } catch {
    return {};
  }
}

// ── Wizard state ─────────────────────────────────────────────────────────

const step = ref(1);
const totalSteps = 4;
const saving = ref(false);
const loadingDraft = ref(false);
const contratId = ref<number | null>(null);

/**
 * True once an existing draft (normal resume or renewal) has been loaded
 * via ?id=/?edit=. Controls whether saveDraft() creates (POST) or
 * updates (PUT) — resuming a draft must never create a duplicate.
 */
const isResumedDraft = ref(false);

/** True when this resume was specifically triggered by a renewal (routed
 *  here from contrats.vue's "Renouveler" confirm). Used only to tailor
 *  a couple of UI strings (Step 4 confirmation message, page title). */
const isRenewalResume = ref(false);

// ── Step 1 ──────────────────────────────────────────────────────────────

const profile = ref<any>({});
const profileLoaded = ref(false);
const addresses = ref<{ label: string; value: string }[]>([]);
const selectedAddress = ref("");

function normaliseAddresses(raw: any): { label: string; value: string }[] {
  if (!raw) return [];
  if (Array.isArray(raw)) {
    return raw
      .map((item, i) => {
        if (typeof item === "string")
          return { label: `Adresse ${i + 1}`, value: item };
        if (item && typeof item === "object") {
          const value =
            item.value ?? item.adresse ?? item.address ?? String(item);
          const label =
            item.label ?? item.nom ?? item.name ?? `Adresse ${i + 1}`;
          return { label, value };
        }
        return { label: `Adresse ${i + 1}`, value: String(item) };
      })
      .filter((a) => a.value.trim());
  }
  if (typeof raw === "string" && raw.trim()) {
    return [{ label: "Adresse principale", value: raw.trim() }];
  }
  return [];
}

async function loadProfile(): Promise<void> {
  try {
    const res = await $fetch<{ success: boolean; data: any }>(
      `${getApiBase()}/api/profile`,
      { headers: authHeaders() },
    );
    profile.value = res.data ?? {};
    contract.fillFromProfile(res.data ?? {});

    const raw =
      res.data?.adresses ??
      res.data?.addresses ??
      res.data?.adresse ??
      res.data?.address ??
      null;
    addresses.value = normaliseAddresses(raw);

    profileLoaded.value = !!(
       res.data?.nom_societe && res.data?.representant?.nom && res.data?.representant?.cin
    );
  } catch {
    profileLoaded.value = false;
    addresses.value = [];
  }
}

function pickAddress(addr: { label: string; value: string }): void {
  selectedAddress.value = addr.value;
  contract.form.companyAdresse = addr.value;
}

function pickBestAddress(value?: string | null): void {
  const trimmed = (value ?? "").trim();
  const match = trimmed
    ? addresses.value.find((addr) => addr.value.trim() === trimmed)
    : null;
  const selected = match ?? addresses.value[0] ?? null;

  if (!selected) {
    selectedAddress.value = trimmed;
    contract.form.companyAdresse = trimmed;
    return;
  }

  pickAddress(selected);
}

// ── Step 2 ──────────────────────────────────────────────────────────────

const clientMode = ref<"select" | "create">("select");
const selectedClientId = ref<number | null>(null);
const selectedClient = ref<any>(null);
const clientSearchQuery = ref("");

const newClientForm = reactive({
  raison_sociale: "",
  forme_juridique: "",
  gerantNom: "",
  gerantCIN: "",
  dateNaissance: "",
  adressePerso: "",
  telDialCode: "+212",
  telNumber: "",
  email: "",
  password: "",
});

const filteredClients = computed(() => {
  const q = clientSearchQuery.value.toLowerCase().trim();
  const items = clientsStore.items ?? [];
  if (!q) return items;
  return items.filter(
    (c) =>
      c.raison_sociale?.toLowerCase().includes(q) ||
      c.client_user?.email?.toLowerCase().includes(q),
  );
});

function selectClient(client: any): void {
  selectedClientId.value = client.id;
  selectedClient.value = client;

  contract.fillFromClient(client);

  clientSearchQuery.value = "";
}

function switchToCreate(): void {
  selectedClientId.value = null;
  selectedClient.value = null;
  clientMode.value = "create";
  Object.assign(newClientForm, {
    raison_sociale: "",
    forme_juridique: "",
    gerantNom: "",
    gerantCIN: "",
    dateNaissance: "",
    adressePerso: "",
    telDialCode: "+212",
    telNumber: "",
    email: "",
    password: "",
  });
}

watch(
  () => newClientForm.raison_sociale,
  (v) => {
    contract.form.societe = v;
  },
);
watch(
  () => newClientForm.gerantNom,
  (v) => {
    contract.form.gerantNom = v;
  },
);
watch(
  () => newClientForm.gerantCIN,
  (v) => {
    contract.form.gerantCIN = v;
  },
);
watch(
  () => [newClientForm.telDialCode, newClientForm.telNumber],
  () => {
    contract.form.tel = joinPhone(newClientForm.telDialCode, newClientForm.telNumber);
  },
);
watch(
  () => newClientForm.email,
  (v) => {
    contract.form.email = v;
  },
);
watch(
  () => newClientForm.adressePerso,
  (v) => {
    contract.form.adressePerso = v;
  },
);

// ── Templates ──────────────────────────────────────────────────────────

const templates = ref<TemplateEntity[]>([]);
const selectedTemplateId = ref<number | null>(null);
const showTemplatePicker = ref(false);

async function loadTemplates(): Promise<void> {
  try {
    const res = await templateService.list();
    templates.value = res.data ?? [];
  } catch {
    templates.value = [];
  }
}

function loadTemplate(template: TemplateEntity): void {
  selectedTemplateId.value = template.id;
  const sorted = [...template.articles].sort(
    (a, b) => (a.pivot?.ordre ?? 0) - (b.pivot?.ordre ?? 0),
  );
  selectedArticleIds.value = sorted.map((a) => String(a.id));
  orderedArticles.value = sorted.map((a, i) => ({
    ...a,
    ordre: a.pivot?.ordre ?? i + 1,
    _expanded: false,
  }));
}

function onTemplatePicked(template: TemplateEntity): void {
  loadTemplate(template);
  success(`Modèle "${template.name}" chargé`);
}

function clearSelection(): void {
  selectedTemplateId.value = null;
  selectedArticleIds.value = [];
  orderedArticles.value = [];
}

// ── Step 3 ──────────────────────────────────────────────────────────────

const selectedArticleIds = ref<string[]>([]);
const orderedArticles = ref<any[]>([]);

function toggleArticle(article: any): void {
  const id = String(article.id);
  selectedTemplateId.value = null;

  if (selectedArticleIds.value.includes(id)) {
    selectedArticleIds.value = selectedArticleIds.value.filter((x) => x !== id);
    orderedArticles.value = orderedArticles.value
      .filter((a) => String(a.id) !== id)
      .map((a, i) => ({ ...a, ordre: i + 1 }));
  } else {
    const newOrdre = orderedArticles.value.length + 1;
    selectedArticleIds.value.push(id);
    orderedArticles.value.push({
      ...article,
      ordre: newOrdre,
      _expanded: false,
    });
  }
}

function resetArticleBody(article: any): void {
  const original = articlesStore.items.find(
    (a) => String(a.id) === String(article.id),
  );
  if (original) article.body = original.body;
}

// Drag & drop
const dragIndex = ref<number | null>(null);
function onDragStart(index: number, event: DragEvent): void {
  dragIndex.value = index;
  if (event.dataTransfer) event.dataTransfer.effectAllowed = "move";
}
function onDragOver(index: number, event: DragEvent): void {
  event.preventDefault();
  if (event.dataTransfer) event.dataTransfer.dropEffect = "move";
}
function onDrop(targetIndex: number): void {
  if (dragIndex.value === null || dragIndex.value === targetIndex) return;
  const arr = [...orderedArticles.value];
  const [moved] = arr.splice(dragIndex.value, 1);
  arr.splice(targetIndex, 0, moved);
  orderedArticles.value = arr.map((a, i) => ({ ...a, ordre: i + 1 }));
  dragIndex.value = null;
  selectedTemplateId.value = null;
}
function onDragEnd(): void {
  dragIndex.value = null;
}

// Save template
const showSaveTemplateModal = ref(false);
const newTemplateName = ref("");
const newTemplateDesc = ref("");
const savingTemplate = ref(false);

function openSaveTemplateModal(): void {
  if (orderedArticles.value.length === 0) {
    toastError?.("Sélectionnez au moins un article avant de créer un modèle");
    return;
  }
  newTemplateName.value = "";
  newTemplateDesc.value = "";
  showSaveTemplateModal.value = true;
}

async function submitSaveTemplate(): Promise<void> {
  if (!newTemplateName.value.trim()) {
    toastError?.("Le nom du modèle est obligatoire");
    return;
  }
  savingTemplate.value = true;
  try {
    const payload = orderedArticles.value.map((a) => ({
      id: String(a.id),
      ordre: a.ordre,
    }));
    const res = await templateService.create(
      newTemplateName.value.trim(),
      newTemplateDesc.value.trim(),
      payload,
    );
    templates.value.unshift(res.data);
    selectedTemplateId.value = res.data.id;
    showSaveTemplateModal.value = false;
    success("Modèle créé avec succès");
  } catch (e: any) {
    toastError?.(e?.data?.message ?? "Erreur lors de la création du modèle");
  } finally {
    savingTemplate.value = false;
  }
}

// ── Resume an existing draft (?id= / legacy ?edit=) ────────────────────

/**
 * Loads an existing draft contract — created either through the normal
 * wizard, or via ContratController::renew() — and populates every
 * wizard field from it: client, address, contract title, articles (with
 * their saved order), and financial terms.
 *
 * When called with forceStep3=true (renewal flow), Step 1/2 are skipped
 * entirely — client and domiciliataire are already fixed on a renewal
 * draft — and the wizard opens directly on Step 3.
 */
async function loadExistingDraft(
  id: string,
  forceStep3: boolean,
): Promise<void> {
  loadingDraft.value = true;
  try {
    const res = await contratService.getById(id);
    const c = res.data as any;

    contratId.value = c.id;
    isResumedDraft.value = true;
    contract.fillFromProfile(profile.value ?? {});

    // ── Client / entreprise — already fixed on the draft ──────────────
    selectedClientId.value = c.entreprise_id ?? c.entreprise?.id ?? null;
    selectedClient.value = c.entreprise ?? null;
    clientMode.value = "select";

    contract.fillFromClient(c.entreprise ?? {});

    // ── Contract title / metadata ──────────────────────────────────────
    contract.form.titreContrat = c.titre_contrat ?? "";
    contract.form.instruction_no = c.instruction_no ?? "";
    contract.form.ville_signature = c.ville_signature ?? "";
    contract.form.date_signature = c.date_signature ?? "";

    // ── Financial / duration fields ────────────────────────────────────
    contract.form.dateDebut = c.date_debut ?? contract.form.dateDebut;
    contract.form.dateFin = c.date_fin ?? contract.form.dateFin;
    contract.form.months = c.duree_mois ?? contract.form.months;
    contract.form.mode_paiement = c.mode_paiement ?? "";
    contract.form.caution = c.caution ?? "";
    if (c.prix_mensuel !== null && c.prix_mensuel !== undefined) {
      contract.setMonthly(Number(c.prix_mensuel));
    }
    if (c.prix_total !== null && c.prix_total !== undefined) {
      contract.form.redevanceAnnuelle = Number(c.prix_total);
    }

    // ── Articles, in their saved order ─────────────────────────────────
    const sorted = [...(c.articles ?? [])].sort(
      (a: any, b: any) => (a.pivot?.ordre ?? 0) - (b.pivot?.ordre ?? 0),
    );
    selectedArticleIds.value = sorted.map((a: any) => String(a.id));
    orderedArticles.value = sorted.map((a: any, i: number) => ({
      ...a,
      ordre: a.pivot?.ordre ?? i + 1,
      _expanded: false,
    }));

    // ── Address — needed for Step 1 validation even when skipped,
    //    since saveDraft() doesn't re-check it once resumed, but
    //    keeping it populated avoids a blank field if the user goes
    //    back to Step 1 manually.
    pickBestAddress(contract.form.companyAdresse);

    if (forceStep3) {
      isRenewalResume.value = true;
      step.value = 3;
      success(
        "Brouillon de renouvellement chargé — vérifiez les conditions avant activation.",
      );
    } else {
      success("Brouillon chargé.");
    }
  } catch (e: any) {
    toastError?.(e?.data?.message ?? "Impossible de charger ce brouillon");
  } finally {
    loadingDraft.value = false;
  }
}

// Navigation
function canProceed(): boolean {
  if (step.value === 1) return selectedAddress.value.trim().length > 0;
  if (step.value === 2) {
    if (clientMode.value === "select") return selectedClientId.value !== null;
    return !!(
      newClientForm.raison_sociale &&
      newClientForm.email &&
      newClientForm.password
    );
  }
  if (step.value === 3) {
    return !!(
      contract.form.ville_signature.trim() &&
      contract.form.date_signature
    );
  }
  return true;
}

async function nextStep(): Promise<void> {
  if (!canProceed()) {
    toastError?.(
      step.value === 1
        ? "Veuillez sélectionner une adresse de domiciliation"
        : "Veuillez remplir les champs obligatoires",
    );
    return;
  }

  if (step.value === 2 && clientMode.value === "create") {
    saving.value = true;
    try {
      const newClient = await clientsStore.create({
        raison_sociale: newClientForm.raison_sociale,
        forme_juridique: newClientForm.forme_juridique || undefined,
        client_nom: newClientForm.gerantNom,
        client_email: newClientForm.email,
        client_password: newClientForm.password,
        client_telephone: joinPhone(newClientForm.telDialCode, newClientForm.telNumber) || undefined,
        statut: "actif",
        pays: "Maroc",
      });
      selectedClientId.value = newClient.id;
      selectedClient.value = newClient;
      clientMode.value = "select";
      success("Client créé avec succès");
    } catch (e: any) {
      const msg = e?.data?.errors
        ? Object.values(e.data.errors).flat().join(" · ")
        : (e?.data?.message ?? "Erreur lors de la création du client");
      toastError?.(msg);
      saving.value = false;
      return;
    } finally {
      saving.value = false;
    }
  }

  step.value++;
}

function prevStep(): void {
  if (step.value > 1) step.value--;
}

/**
 * Save Draft — POST for a brand-new contract, PUT when resuming an
 * existing one (normal draft resume OR renewal draft). isResumedDraft
 * is set the moment a draft is loaded via ?id=, and stays true for the
 * rest of the session so re-saving never creates a duplicate row.
 */
async function saveDraft(): Promise<void> {
  if (!selectedClientId.value) {
    toastError?.("Aucun client sélectionné");
    return;
  }

  if (!contract.form.ville_signature.trim() || !contract.form.date_signature) {
    toastError?.("Ville et date de signature sont obligatoires");
    return;
  }

  saving.value = true;
  try {
    const body = {
      entreprise_id: selectedClientId.value,
      titre_contrat: contract.form.titreContrat || null,
      date_debut: contract.form.dateDebut || null,
      date_fin: contract.form.dateFin || null,
      duree_mois: contract.form.months || null,
      prix_mensuel: contract.monthlyTotal || null,
      prix_total: contract.grandTotal || null,
      caution: contract.form.caution || null,
      mode_paiement: contract.form.mode_paiement || null,
      ville_signature: contract.form.ville_signature.trim(),
      date_signature: contract.form.date_signature,
      instruction_no: contract.form.instruction_no || null,
      statut: "draft",
      articles: orderedArticles.value.map((a) => ({
        id: String(a.id),
        ordre: a.ordre,
      })),
    };

    const url =
      isResumedDraft.value && contratId.value
        ? `${getApiBase()}/api/contrats/${contratId.value}`
        : `${getApiBase()}/api/contrats`;
    const method = isResumedDraft.value && contratId.value ? "PUT" : "POST";

    const res = await $fetch<{ success: boolean; data: any }>(url, {
      method,
      headers: authHeaders(),
      body,
    });

    contratId.value = res.data.id;
    isResumedDraft.value = true;
    success("Contrat enregistré avec succès");
    step.value = 4;
  } catch (e: any) {
    const msg = e?.data?.errors
      ? Object.values(e.data.errors).flat().join(" · ")
      : (e?.data?.message ?? "Erreur lors de la sauvegarde");
    toastError?.(msg);
  } finally {
    saving.value = false;
  }
}

// Live Preview Handler using the universal component
function openLivePreview(): void {
  if (!pdfPreview.value || typeof pdfPreview.value.openLive !== "function") {
    console.warn(
      "L'aperçu n'est pas prêt. Veuillez rafraîchir la page ou vérifier l'import du composant.",
    );
    return;
  }

  pdfPreview.value.openLive(
    {
      titreContrat: contract.form.titreContrat,
      instruction_no: contract.form.instruction_no,
      duree_mois: contract.form.months,
      date_debut: contract.form.dateDebut,
      date_fin: contract.form.dateFin,
      date_signature: contract.form.date_signature,
      redevanceMensuelle: contract.monthlyTotal,
      redevanceAnnuelle: contract.grandTotal,
      mode_paiement: contract.form.mode_paiement,
      caution: contract.form.caution,
      companyName: contract.form.companyName,
      companyRC: contract.form.companyRC,
      companyIF: contract.form.companyIF,
      companyTP: contract.form.companyTP,
      companyAdresse: contract.form.companyAdresse,
      companyEmail: contract.form.companyEmail,
      companyTelephone: contract.form.companyTelephone,
      companyRepresentant: contract.form.companyRepresentant,
      companyCIN: contract.form.companyCIN,
      societe: contract.form.societe,
      forme_juridique: selectedClient.value?.forme_juridique,
      adresse_domiciliation: contract.form.companyAdresse,
      ville_client: selectedClient.value?.ville,
      gerantNom: contract.form.gerantNom,
      gerantPrenom: contract.form.gerantPrenom,
      gerantCIN: contract.form.gerantCIN,
      nationalite: contract.form.nationalite,
      dateNaissance: contract.form.dateNaissance,
      tel: contract.form.tel,
      email: contract.form.email,
      adressePerso: contract.form.adressePerso,
      ville_signature: contract.form.ville_signature,
      articles: orderedArticles.value,
    },
    contract.form.titreContrat || "Aperçu du contrat",
  );
}

/**
 * Opens the ContratPreviewModal pointed at the SAVED contract's live
 * document stream (id known — contract already exists in the database).
 */
function openSavedPreview(): void {
  if (!contratId.value) return;
  const url = contratService.streamPdfUrl(String(contratId.value), "preview");
  if (pdfPreview.value && typeof pdfPreview.value.openUrl === "function") {
    pdfPreview.value.openUrl(
      url,
      contract.form.titreContrat || `Contrat #${contratId.value}`,
    );
  }
}

watch(() => contract.form.dateDebut, recalcMonths);
watch(() => contract.form.dateFin, recalcMonths);

function recalcMonths(): void {
  if (!contract.form.dateDebut || !contract.form.dateFin) return;
  const start = new Date(contract.form.dateDebut);
  const end = new Date(contract.form.dateFin);
  const months =
    (end.getFullYear() - start.getFullYear()) * 12 +
    (end.getMonth() - start.getMonth());
  if (!isNaN(months) && months > 0) {
    contract.form.months = months;
    contract.syncFromMonths();
  }
}

onMounted(async () => {
  contract.resetForm();

  await Promise.all([
    loadProfile(),
    clientsStore.fetchAll(),
    articlesStore.fetchAll(),
    loadTemplates(),
  ]);

  // ── Resume an existing draft, normal OR renewal ────────────────────
  const editId = route.query.id ?? route.query.edit;
  const isRenewalResumeFlag = route.query.renewal === "1";
  const forcedStep = route.query.step ? Number(route.query.step) : null;

  if (typeof editId === "string" && editId) {
    await loadExistingDraft(editId, isRenewalResumeFlag);
    if (isRenewalResumeFlag && forcedStep) {
      step.value = forcedStep; // force Step 3, skip 1/2 even if loadExistingDraft defaults elsewhere
    }
  } else if (templates.value.length > 0) {
    // Fresh contract, no draft to resume — offer the template picker.
    showTemplatePicker.value = true;
  }
});
</script>

<template>
  <div class="space-y-5 animate-fade-up max-w-3xl mx-auto">
    <!-- Header + live preview button -->
    <div class="flex items-center justify-between gap-3 flex-wrap">
      <div>
        <h1 class="font-serif text-2xl" style="color: var(--app-text)">
          <template v-if="isRenewalResume">
            Renouvellement
            <em class="italic" style="color: #c8a96e">du contrat</em>
          </template>
          <template v-else-if="isResumedDraft">
            Modifier <em class="italic" style="color: #c8a96e">le contrat</em>
          </template>
          <template v-else>
            Nouveau <em class="italic" style="color: #c8a96e">Contrat</em>
          </template>
        </h1>
        <p class="text-sm mt-1" style="color: var(--app-text-muted)">
          Étape {{ step }} sur {{ totalSteps }}
        </p>
      </div>
      <button
        type="button"
        class="btn btn-outline btn-md shrink-0"
        @click="openLivePreview">
        <svg
          width="14"
          height="14"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2.2"
          stroke-linecap="round">
          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
          <circle cx="12" cy="12" r="3" />
        </svg>
        Aperçu du contrat
      </button>
    </div>

    <!-- Loading resumed draft -->
    <div v-if="loadingDraft" class="card p-6 text-center text-app-text/40">
      Chargement du brouillon...
    </div>

    <template v-else>
      <!-- Progress bar -->
      <div class="flex items-center gap-2">
        <div
          v-for="s in totalSteps"
          :key="s"
          class="h-1.5 flex-1 rounded-full transition-all duration-300"
          :style="`background: ${s <= step ? '#c8a96e' : 'var(--app-border)'}`" />
      </div>

      <!-- Renewal banner — reminds the user Steps 1/2 were skipped on purpose -->
      <div
        v-if="isRenewalResume"
        class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm"
        style="
          background: rgba(200, 169, 110, 0.08);
          border: 1px solid rgba(200, 169, 110, 0.2);
          color: #c8a96e;
        ">
        <svg
          width="15"
          height="15"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2.2"
          stroke-linecap="round">
          <path
            d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
          <line x1="12" y1="9" x2="12" y2="13" />
          <line x1="12" y1="17" x2="12.01" y2="17" />
        </svg>
        <span>
          Client et domiciliataire déjà repris de l'ancien contrat. Vérifiez les
          articles et les montants ci-dessous, puis enregistrez.
        </span>
      </div>
      <TemplatePickerModal
        v-model="showTemplatePicker"
        :templates="templates"
        @select="onTemplatePicked"
        @skip="showTemplatePicker = false" />
      <!-- ══════════════════════════════════════════════════════════════════════
         STEP 1 — Domiciliataire info + address selector + contract title
    ══════════════════════════════════════════════════════════════════════ -->
      <div v-if="step === 1" class="space-y-4">
        <!-- Profile status banner -->
        <div
          class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm"
          :style="
            profileLoaded
              ? 'background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.2);color:#22c55e'
              : 'background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);color:#f59e0b'
          ">
          <svg
            width="15"
            height="15"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.2"
            stroke-linecap="round">
            <template v-if="profileLoaded">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
              <polyline points="22 4 12 14.01 9 11.01" />
            </template>
            <template v-else>
              <path
                d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
              <line x1="12" y1="9" x2="12" y2="13" />
              <line x1="12" y1="17" x2="12.01" y2="17" />
            </template>
          </svg>
          <span>
            {{
              profileLoaded
                ? "Profil chargé automatiquement"
                : "Profil incomplet —"
            }}
            <NuxtLink
              to="/admin/profile"
              class="underline ml-1"
              style="opacity: 0.8">
              {{ profileLoaded ? "Modifier →" : "Compléter votre profil →" }}
            </NuxtLink>
          </span>
        </div>

        <!-- Domiciliataire read-only summary -->
        <div class="card p-5">
          <p
            class="text-xs uppercase tracking-widest font-bold mb-4"
            style="color: #c8a96e">
            Domiciliataire (depuis votre profil)
          </p>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
            <div>
              <p class="text-xs mb-0.5" style="color: var(--app-text-faint)">
                Société
              </p>
              <p class="font-medium" style="color: var(--app-text)">
                {{ contract.form.companyName || "—" }}
              </p>
            </div>
            <div>
              <p class="text-xs mb-0.5" style="color: var(--app-text-faint)">
                Représentant
              </p>
              <p class="font-medium" style="color: var(--app-text)">
                {{ contract.form.companyRepresentant || "—" }}
              </p>
            </div>
            <div>
              <p class="text-xs mb-0.5" style="color: var(--app-text-faint)">
                CIN
              </p>
              <p style="color: var(--app-text)">
                {{ contract.form.companyCIN || "—" }}
              </p>
            </div>
            <div>
              <p class="text-xs mb-0.5" style="color: var(--app-text-faint)">
                RC
              </p>
              <p style="color: var(--app-text)">
                {{ contract.form.companyRC || "—" }}
              </p>
            </div>
            <div>
              <p class="text-xs mb-0.5" style="color: var(--app-text-faint)">
                IF
              </p>
              <p style="color: var(--app-text)">
                {{ contract.form.companyIF || "—" }}
              </p>
            </div>
            <div>
              <p class="text-xs mb-0.5" style="color: var(--app-text-faint)">
                TP
              </p>
              <p style="color: var(--app-text)">
                {{ contract.form.companyTP || "—" }}
              </p>
            </div>
          </div>
        </div>

        <!-- ── Dynamic contract title ─────────────────────────────────────────── -->
        <div class="card p-5 space-y-4">
          <p
            class="text-xs uppercase tracking-widest font-bold"
            style="color: #c8a96e">
            Titre du contrat
          </p>
          <div>
            <label class="f-label">Intitulé affiché sur le document</label>
            <input
              v-model="contract.form.titreContrat"
              class="f-input"
              placeholder="Contrat de Domiciliation"
              maxlength="255" />
            <p class="text-[10px] mt-1" style="color: var(--app-text-faint)">
              Ce texte apparaît centré en tête du document. Laissez vide pour
              utiliser le titre par défaut « Contrat de Domiciliation ».
            </p>
          </div>
          <div
            v-if="contract.form.titreContrat.trim()"
            class="rounded-xl px-4 py-3 text-sm text-center font-semibold tracking-wide"
            style="
              background: rgba(200, 169, 110, 0.08);
              border: 1px solid rgba(200, 169, 110, 0.2);
              color: #c8a96e;
            ">
            Aperçu : {{ contract.form.titreContrat }}
          </div>
          <div>
            <label class="f-label">
              Numéro d'instruction
              <span
                class="text-[10px] ml-1"
                style="color: var(--app-text-faint)"
                >(optionnel)</span
              >
            </label>
            <input
              v-model="contract.form.instruction_no"
              class="f-input"
              placeholder="INS-2026-001" />
          </div>
        </div>

        <!-- ── Address selector ──────────────────────────────────────────────── -->
        <div class="card p-5 space-y-4">
          <div class="flex items-center justify-between flex-wrap gap-2">
            <p
              class="text-xs uppercase tracking-widest font-bold"
              style="color: #c8a96e">
              Adresse de domiciliation *
            </p>
            <NuxtLink
              to="/admin/profile"
              class="text-[10px] underline"
              style="color: var(--app-text-faint)"
              target="_blank">
              Gérer les adresses →
            </NuxtLink>
          </div>

          <div v-if="addresses.length > 0" class="space-y-3">
            <p class="text-xs" style="color: var(--app-text-faint)">
              Sélectionnez l'adresse qui apparaîtra sur ce contrat :
            </p>
            <div class="flex flex-col gap-2">
              <button
                v-for="addr in addresses"
                :key="addr.value"
                type="button"
                class="w-full text-left rounded-xl px-4 py-3 transition-all text-sm"
                :style="
                  selectedAddress === addr.value
                    ? 'background:rgba(200,169,110,0.12);border:2px solid #c8a96e;color:var(--app-text)'
                    : 'background:var(--app-surface-2);border:2px solid var(--app-border);color:var(--app-text-muted)'
                "
                @click="pickAddress(addr)">
                <div class="flex items-center justify-between gap-3">
                  <div class="min-w-0">
                    <p
                      class="font-semibold text-xs uppercase tracking-wide mb-0.5"
                      :style="
                        selectedAddress === addr.value
                          ? 'color:#c8a96e'
                          : 'color:var(--app-text-faint)'
                      ">
                      {{ addr.label }}
                    </p>
                    <p class="truncate" style="color: var(--app-text)">
                      {{ addr.value }}
                    </p>
                  </div>
                  <div
                    class="shrink-0 w-6 h-6 rounded-full flex items-center justify-center transition-all"
                    :style="
                      selectedAddress === addr.value
                        ? 'background:#c8a96e'
                        : 'background:var(--app-border)'
                    ">
                    <svg
                      width="11"
                      height="11"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="white"
                      stroke-width="3"
                      stroke-linecap="round">
                      <path d="M20 6L9 17l-5-5" />
                    </svg>
                  </div>
                </div>
              </button>
            </div>
            <div
              v-if="selectedAddress"
              class="flex items-center gap-2 rounded-lg px-3 py-2 text-xs"
              style="
                background: rgba(34, 197, 94, 0.08);
                border: 1px solid rgba(34, 197, 94, 0.15);
                color: #22c55e;
              ">
              <svg
                width="12"
                height="12"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                stroke-linecap="round">
                <path d="M20 6L9 17l-5-5" />
              </svg>
              {{ selectedAddress }}
            </div>
          </div>

          <div v-else class="space-y-3">
            <div
              class="flex items-start gap-3 rounded-xl px-4 py-3 text-sm"
              style="
                background: rgba(245, 158, 11, 0.08);
                border: 1px solid rgba(245, 158, 11, 0.2);
                color: #f59e0b;
              ">
              <svg
                class="shrink-0 mt-0.5"
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.2"
                stroke-linecap="round">
                <path
                  d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                <line x1="12" y1="9" x2="12" y2="13" />
                <line x1="12" y1="17" x2="12.01" y2="17" />
              </svg>
              <span>
                Aucune adresse enregistrée dans votre profil.
                <NuxtLink
                  to="/admin/profile"
                  class="underline ml-1"
                  target="_blank">
                  Ajouter des adresses →
                </NuxtLink>
              </span>
            </div>
            <div>
              <label class="f-label">Saisir l'adresse manuellement *</label>
              <input
                v-model="selectedAddress"
                class="f-input"
                placeholder="Ex : Rue Mohammed V, Résidence Atlas, Agadir 80000"
                @input="contract.form.companyAdresse = selectedAddress" />
            </div>
          </div>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════════════════════════
         STEP 2 — Client selection / creation
    ══════════════════════════════════════════════════════════════════════ -->
      <div v-else-if="step === 2" class="space-y-4">
        <div class="flex gap-2">
          <button
            type="button"
            class="flex-1 py-2.5 rounded-xl text-sm font-medium transition-all"
            :style="
              clientMode === 'select'
                ? 'background:rgba(200,169,110,0.15);border:2px solid #c8a96e;color:#c8a96e'
                : 'background:var(--app-surface-2);border:2px solid var(--app-border);color:var(--app-text-muted)'
            "
            @click="
              clientMode = 'select';
              selectedClient = null;
              selectedClientId = null;
            ">
            Choisir un client existant
          </button>
          <button
            type="button"
            class="flex-1 py-2.5 rounded-xl text-sm font-medium transition-all"
            :style="
              clientMode === 'create'
                ? 'background:rgba(200,169,110,0.15);border:2px solid #c8a96e;color:#c8a96e'
                : 'background:var(--app-surface-2);border:2px solid var(--app-border);color:var(--app-text-muted)'
            "
            @click="switchToCreate">
            + Nouveau client
          </button>
        </div>

        <div v-if="clientMode === 'select'" class="space-y-4">
          <div class="relative">
            <svg
              class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              style="color: var(--app-text-faint)">
              <circle cx="11" cy="11" r="8" />
              <path d="M21 21l-4.35-4.35" />
            </svg>
            <input
              v-model="clientSearchQuery"
              class="f-input pl-9"
              placeholder="Rechercher par raison sociale ou email..." />
          </div>

          <div class="space-y-2 max-h-64 overflow-y-auto">
            <div
              v-for="client in filteredClients"
              :key="client.id"
              class="flex items-center gap-3 p-3 rounded-xl cursor-pointer transition-all"
              :style="
                selectedClientId === client.id
                  ? 'background:rgba(200,169,110,0.12);border:2px solid #c8a96e'
                  : 'background:var(--app-surface-2);border:2px solid var(--app-border)'
              "
              @click="selectClient(client)">
              <div
                class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm shrink-0"
                style="background: rgba(200, 169, 110, 0.15); color: #c8a96e">
                {{ (client.raison_sociale ?? "?").slice(0, 2).toUpperCase() }}
              </div>
              <div class="min-w-0 flex-1">
                <p
                  class="font-semibold text-sm truncate"
                  style="color: var(--app-text)">
                  {{ client.raison_sociale }}
                </p>
                <p
                  class="text-xs truncate"
                  style="color: var(--app-text-muted)">
                  {{ client.client_user?.email ?? "" }}
                  <span v-if="client.ville"> · {{ client.ville }}</span>
                </p>
              </div>
              <div v-if="selectedClientId === client.id" class="shrink-0">
                <svg
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="#c8a96e"
                  stroke-width="2.5"
                  stroke-linecap="round">
                  <path d="M20 6L9 17l-5-5" />
                </svg>
              </div>
            </div>
            <div
              v-if="filteredClients.length === 0 && clientSearchQuery"
              class="text-center py-6 rounded-xl"
              style="
                background: var(--app-surface-2);
                border: 2px dashed var(--app-border);
                color: var(--app-text-faint);
              ">
              <p class="text-sm mb-2">
                Aucun client trouvé pour "{{ clientSearchQuery }}"
              </p>
              <button
                type="button"
                class="btn btn-gold btn-sm"
                @click="switchToCreate">
                + Créer ce client maintenant
              </button>
            </div>
          </div>

          <div v-if="selectedClient" class="card p-5 space-y-3">
            <p
              class="text-xs uppercase tracking-widest font-bold"
              style="color: #22c55e">
              ✓ Client sélectionné
            </p>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
              <div>
                <p class="text-xs mb-0.5" style="color: var(--app-text-faint)">
                  Société
                </p>
                <p class="font-medium" style="color: var(--app-text)">
                  {{ selectedClient.raison_sociale }}
                </p>
              </div>
              <div v-if="selectedClient.forme_juridique">
                <p class="text-xs mb-0.5" style="color: var(--app-text-faint)">
                  Forme juridique
                </p>
                <p style="color: var(--app-text)">
                  {{ selectedClient.forme_juridique }}
                </p>
              </div>
              <div v-if="selectedClient.ville">
                <p class="text-xs mb-0.5" style="color: var(--app-text-faint)">
                  Ville
                </p>
                <p style="color: var(--app-text)">{{ selectedClient.ville }}</p>
              </div>
              <div v-if="contract.form.gerantNom">
                <p class="text-xs mb-0.5" style="color: var(--app-text-faint)">
                  Gérant
                </p>
                <p style="color: var(--app-text)">
                  {{ contract.form.gerantNom }}
                </p>
              </div>
              <div v-if="contract.form.tel">
                <p class="text-xs mb-0.5" style="color: var(--app-text-faint)">
                  Téléphone
                </p>
                <p style="color: var(--app-text)">{{ contract.form.tel }}</p>
              </div>
              <div v-if="contract.form.email">
                <p class="text-xs mb-0.5" style="color: var(--app-text-faint)">
                  Email
                </p>
                <p class="truncate" style="color: var(--app-text)">
                  {{ contract.form.email }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <div v-else-if="clientMode === 'create'" class="card p-5 space-y-4">
          <p
            class="text-xs uppercase tracking-widest font-bold"
            style="color: #c8a96e">
            Informations du nouveau client
          </p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
              <label class="f-label">Raison sociale *</label>
              <input
                v-model="newClientForm.raison_sociale"
                class="f-input"
                required
                placeholder="ATLAS IMPORT EXPORT SARL" />
            </div>
            <div>
              <label class="f-label">Forme juridique</label>
              <input
                v-model="newClientForm.forme_juridique"
                class="f-input"
                placeholder="SARL, SA, SAS..." />
            </div>
            <div>
              <label class="f-label">Nom du gérant *</label>
              <input
                v-model="newClientForm.gerantNom"
                class="f-input"
                required
                placeholder="Nom complet" />
            </div>
            <div>
              <label class="f-label">CIN / Passeport</label>
              <input
                v-model="newClientForm.gerantCIN"
                class="f-input"
                placeholder="BJ422176" />
            </div>
            <div>
              <label class="f-label">Date de naissance</label>
              <input
                v-model="newClientForm.dateNaissance"
                type="date"
                class="f-input" />
            </div>
            <div>
              <label class="f-label">Téléphone</label>
              <div class="grid grid-cols-[minmax(130px,0.42fr)_1fr] gap-2">
                <select
                  v-model="newClientForm.telDialCode"
                  class="f-input min-w-0">
                  <option v-for="code in dialCodes" :key="code.iso" :value="code.dialCode">
                    {{ code.flag }} {{ code.dialCode }} {{ code.country }}
                  </option>
                </select>
                <input
                  v-model="newClientForm.telNumber"
                  class="f-input min-w-0"
                  type="tel"
                  placeholder="6XX XXX XXX" />
              </div>
            </div>
            <div>
              <label class="f-label">Email (accès portail) *</label>
              <input
                v-model="newClientForm.email"
                type="email"
                class="f-input"
                required
                placeholder="client@exemple.ma" />
            </div>
            <div>
              <label class="f-label">Mot de passe portail *</label>
              <input
                v-model="newClientForm.password"
                type="password"
                class="f-input"
                required
                placeholder="Min. 8 caractères" />
            </div>
            <div class="sm:col-span-2">
              <label class="f-label">Adresse personnelle</label>
              <input
                v-model="newClientForm.adressePerso"
                class="f-input"
                placeholder="Adresse personnelle du gérant" />
            </div>
          </div>
          <p class="text-xs" style="color: var(--app-text-faint)">
            Un compte client sera créé avec cet email et ce mot de passe.
          </p>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════════════════════════
         STEP 3 — Articles + financial fields
    ══════════════════════════════════════════════════════════════════════ -->
      <div v-else-if="step === 3" class="space-y-5">
        <div class="card p-5">
          <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <div>
              <p
                class="text-xs uppercase tracking-widest font-bold"
                style="color: #c8a96e">
                Bibliothèque d'articles
              </p>
              <p class="text-xs mt-0.5" style="color: var(--app-text-faint)">
                Cliquez pour ajouter au contrat
              </p>
            </div>
            <NuxtLink
              to="/admin/articles"
              class="text-xs underline"
              style="color: var(--app-text-faint)"
              target="_blank">
              Gérer les articles →
            </NuxtLink>
          </div>

          <div class="flex flex-wrap gap-2">
            <button
              v-for="article in articlesStore.items"
              :key="String(article.id)"
              type="button"
              class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all border"
              :style="
                selectedArticleIds.includes(String(article.id))
                  ? 'background:#c8a96e;color:#111;border-color:#c8a96e'
                  : 'background:var(--app-surface-2);border-color:var(--app-border);color:var(--app-text-muted)'
              "
              @click="toggleArticle(article)">
              {{ selectedArticleIds.includes(String(article.id)) ? "✓ " : "+ "
              }}{{ article.title }}
            </button>

            <p
              v-if="articlesStore.items.length === 0"
              class="text-sm"
              style="color: var(--app-text-faint)">
              Aucun article dans la bibliothèque.
              <NuxtLink to="/admin/articles" class="underline"
                >Créer des articles →</NuxtLink
              >
            </p>
          </div>
        </div>

        <div v-if="orderedArticles.length > 0" class="card p-5">
          <div class="flex items-center justify-between mb-4">
            <div>
              <p
                class="text-xs uppercase tracking-widest font-bold"
                style="color: #c8a96e">
                Articles sélectionnés — {{ orderedArticles.length }} article(s)
              </p>
              <p class="text-xs mt-0.5" style="color: var(--app-text-faint)">
                Glissez pour réordonner · ∨ pour modifier le texte
              </p>
            </div>
          </div>

          <div class="space-y-3">
            <div
              v-for="(article, index) in orderedArticles"
              :key="String(article.id)"
              draggable="true"
              class="rounded-xl transition-all"
              :style="`
              border: 2px solid ${dragIndex === index ? '#c8a96e' : 'var(--app-border)'};
              opacity: ${dragIndex === index ? 0.45 : 1};
              background: var(--app-surface-2);
            `"
              @dragstart="onDragStart(index, $event)"
              @dragover="onDragOver(index, $event)"
              @drop="onDrop(index)"
              @dragend="onDragEnd">
              <div class="flex items-center gap-3 px-4 py-3">
                <svg
                  class="shrink-0 cursor-grab active:cursor-grabbing"
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
                  stroke-linecap="round"
                  style="color: var(--app-text-faint)">
                  <circle cx="9" cy="5" r="1" />
                  <circle cx="15" cy="5" r="1" />
                  <circle cx="9" cy="12" r="1" />
                  <circle cx="15" cy="12" r="1" />
                  <circle cx="9" cy="19" r="1" />
                  <circle cx="15" cy="19" r="1" />
                </svg>
                <span
                  class="w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-bold shrink-0"
                  style="background: rgba(200, 169, 110, 0.2); color: #c8a96e">
                  {{ article.ordre }}
                </span>
                <input
                  v-model="article.title"
                  class="flex-1 min-w-0 bg-transparent border-none outline-none font-semibold text-sm"
                  style="color: var(--app-text)"
                  :placeholder="`Titre de l'article ${article.ordre}`"
                  @click.stop />
                <button
                  type="button"
                  class="shrink-0 w-8 h-8 rounded-lg flex items-center justify-center transition-colors nav-inactive"
                  @click.stop="article._expanded = !article._expanded">
                  <svg
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.2"
                    stroke-linecap="round"
                    :style="`transform:rotate(${article._expanded ? 180 : 0}deg);transition:transform 0.2s`">
                    <path d="M6 9l6 6 6-6" />
                  </svg>
                </button>
                <button
                  type="button"
                  class="shrink-0 w-8 h-8 rounded-lg flex items-center justify-center"
                  style="color: #ef4444"
                  @click.stop="toggleArticle(article)">
                  <svg
                    width="13"
                    height="13"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.2"
                    stroke-linecap="round">
                    <path d="M18 6L6 18M6 6l12 12" />
                  </svg>
                </button>
              </div>

              <Transition name="expand-body">
                <div
                  v-if="article._expanded"
                  class="px-4 pb-4 pt-1"
                  style="border-top: 1px solid var(--app-border-2)"
                  @dragstart.stop
                  @dragover.stop>
                  <label class="f-label mb-2">Corps de l'article</label>
                  <textarea
                    v-model="article.body"
                    class="f-input resize-none"
                    rows="6"
                    :placeholder="`Texte de l'article ${article.ordre}...`"
                    @click.stop
                    @mousedown.stop />
                  <div class="flex items-center justify-between mt-2">
                    <p class="text-[10px]" style="color: var(--app-text-faint)">
                      {{ article.body?.length ?? 0 }} caractères
                    </p>
                    <button
                      type="button"
                      class="text-[11px] underline"
                      style="color: var(--app-text-faint)"
                      @click="resetArticleBody(article)">
                      Réinitialiser depuis la bibliothèque
                    </button>
                  </div>
                </div>
              </Transition>
            </div>
          </div>

          <div
            class="mt-4 rounded-xl p-4"
            style="
              background: var(--app-surface);
              border: 1px solid var(--app-border);
            ">
            <p
              class="text-[10px] uppercase tracking-widest font-bold mb-2"
              style="color: var(--app-text-faint)">
              Ordre dans le document
            </p>
            <div class="flex flex-wrap gap-2">
              <span
                v-for="a in orderedArticles"
                :key="String(a.id)"
                class="text-xs px-2 py-1 rounded-lg font-medium"
                style="
                  background: rgba(200, 169, 110, 0.1);
                  color: #c8a96e;
                  border: 1px solid rgba(200, 169, 110, 0.2);
                ">
                Art. {{ a.ordre }} — {{ a.title }}
              </span>
            </div>
          </div>
        </div>

        <div
          v-else
          class="rounded-xl p-8 text-center"
          style="
            background: var(--app-surface-2);
            border: 2px dashed var(--app-border);
            color: var(--app-text-faint);
          ">
          <svg
            class="mx-auto mb-3 opacity-40"
            width="32"
            height="32"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round">
            <path
              d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <polyline points="14 2 14 8 20 8" />
          </svg>
          <p class="font-medium mb-1">Aucun article sélectionné</p>
          <p class="text-sm">
            Le document sera généré sans articles de contrat.
          </p>
        </div>

        <div class="card p-5 space-y-4">
          <p
            class="text-xs uppercase tracking-widest font-bold"
            style="color: #c8a96e">
            Durée et montants
          </p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="f-label">Date de début</label>
              <input
                :value="contract.form.dateDebut"
                type="date"
                class="f-input"
                @change="
                  contract.setDateDebut(
                    ($event.target as HTMLInputElement).value,
                  )
                " />
            </div>
            <div>
              <label class="f-label">
                Date de fin
                <span
                  class="text-[10px] ml-1"
                  style="color: var(--app-text-faint)"
                  >(calculée automatiquement)</span
                >
              </label>
              <input
                :value="contract.form.dateFin"
                type="date"
                class="f-input"
                @change="
                  contract.form.dateFin = (
                    $event.target as HTMLInputElement
                  ).value;
                  recalcMonths();
                " />
            </div>
            <div>
              <label class="f-label">Durée (mois)</label>
              <input
                :value="contract.form.months"
                type="number"
                min="1"
                class="f-input"
                @input="
                  contract.setMonths(
                    Number(($event.target as HTMLInputElement).value),
                  )
                " />
            </div>
            <div>
              <label class="f-label">Redevance mensuelle (DH)</label>
              <input
                :value="contract.form.redevanceMensuelle"
                type="number"
                min="0"
                step="0.01"
                class="f-input"
                @input="
                  contract.setMonthly(
                    Number(($event.target as HTMLInputElement).value),
                  )
                " />
            </div>
            <div>
              <label class="f-label">
                Redevance annuelle (DH)
                <span
                  class="text-[10px] ml-1"
                  style="color: var(--app-text-faint)"
                  >(auto)</span
                >
              </label>
              <input
                :value="contract.form.redevanceAnnuelle"
                type="number"
                min="0"
                step="0.01"
                class="f-input"
                @input="
                  contract.setAnnual(
                    Number(($event.target as HTMLInputElement).value),
                  )
                " />
            </div>
            <div>
              <label class="f-label">Mode de paiement</label>
              <select v-model="contract.form.mode_paiement" class="f-input">
                <option value="">--</option>
                <option>Virement</option>
                <option>Espèces</option>
                <option>Chèque</option>
                <option>Carte bancaire</option>
              </select>
            </div>
            <div>
              <label class="f-label">Caution (DH)</label>
              <input
                v-model="contract.form.caution"
                type="number"
                min="0"
                class="f-input" />
            </div>
            <div>
              <label class="f-label">Ville de signature *</label>
              <input
                v-model="contract.form.ville_signature"
                class="f-input"
                required
                placeholder="Agadir" />
            </div>
            <div>
              <label class="f-label">Date de signature *</label>
              <input
                v-model="contract.form.date_signature"
                type="date"
                required
                class="f-input" />
            </div>
          </div>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════════════════════════
         STEP 4 — Confirmation + preview
    ══════════════════════════════════════════════════════════════════════ -->
      <div v-else-if="step === 4" class="space-y-4">
        <div class="card p-6 text-center space-y-4">
          <div
            class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto"
            style="background: rgba(34, 197, 94, 0.1)">
            <svg
              width="28"
              height="28"
              viewBox="0 0 24 24"
              fill="none"
              stroke="#22c55e"
              stroke-width="2"
              stroke-linecap="round">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
              <polyline points="22 4 12 14.01 9 11.01" />
            </svg>
          </div>
          <div>
            <h2 class="font-serif text-xl" style="color: var(--app-text)">
              {{
                isRenewalResume
                  ? "Renouvellement enregistré"
                  : "Contrat enregistré"
              }}
            </h2>
            <p class="text-sm mt-1" style="color: var(--app-text-muted)">
              Contrat #{{ contratId }} —
              <em style="color: #c8a96e">
                {{ contract.form.titreContrat || "Contrat de Domiciliation" }}
              </em>
              — statut : brouillon
            </p>
          </div>
          <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <button class="btn btn-outline btn-md" @click="openSavedPreview">
              <svg
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.2"
                stroke-linecap="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                <circle cx="12" cy="12" r="3" />
              </svg>
              Aperçu
            </button>
            <a
              v-if="contratId"
              :href="contratService.streamPdfUrl(String(contratId), 'download')"
              target="_blank"
              class="btn btn-gold btn-md">
              <svg
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.2"
                stroke-linecap="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="7 10 12 15 17 10" />
                <line x1="12" y1="15" x2="12" y2="3" />
              </svg>
              Télécharger
            </a>
          </div>
          <NuxtLink
            to="/admin/contrats"
            class="btn btn-outline btn-md w-full sm:w-auto">
            Voir tous les contrats →
          </NuxtLink>
        </div>
      </div>

      <!-- ── Navigation buttons (steps 1–3) ────────────────────────────────────── -->
      <div v-if="step < 4" class="flex justify-between gap-3 pt-2">
        <button
          v-if="step > 1"
          type="button"
          class="btn btn-outline btn-md"
          :disabled="saving"
          @click="prevStep">
          ← Retour
        </button>
        <div v-else />
        <button
          v-if="step === 3"
          type="button"
          class="btn btn-gold btn-md ml-auto"
          :disabled="saving"
          @click="saveDraft">
          {{ saving ? "Enregistrement..." : "Enregistrer le contrat →" }}
        </button>
        <button
          v-else
          type="button"
          class="btn btn-gold btn-md ml-auto"
          :disabled="saving"
          @click="nextStep">
          {{ saving ? "Patientez..." : "Suivant →" }}
        </button>
      </div>
    </template>

    <!-- Modals -->
    <TemplatePickerModal
      v-if="showTemplatePicker"
      :templates="templates"
      @close="showTemplatePicker = false"
      @select="onTemplatePicked" />
    <ContratPreviewModal ref="pdfPreview" />
  </div>
</template>
