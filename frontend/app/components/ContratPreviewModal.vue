<script setup lang="ts">
/**
 * components/ContratPreviewModal.vue
 *
 * Universal HTML preview modal for contracts.
 * Supports:
 *   1. Direct HTML preview from a live token map / state object (openLive)
 *   2. Server-side HTML stream preview via contract URL (openUrl)
 */

interface LiveDataPayload {
  titreContrat?: string
  instruction_no?: string
  duree_mois?: string | number
  date_debut?: string
  date_fin?: string
  date_signature?: string
  redevanceMensuelle?: string | number
  redevanceAnnuelle?: string | number
  mode_paiement?: string
  caution?: string | number
  companyName?: string
  companyRC?: string
  companyIF?: string
  companyTP?: string
  companyAdresse?: string
  companyRepresentant?: string
  companyCIN?: string
  societe?: string
  forme_juridique?: string
  ville_client?: string
  gerantNom?: string
  gerantCIN?: string
  tel?: string
  email?: string
  adressePerso?: string
  ville_signature?: string
  articles?: Array<{ title: string; body: string }>
}

const isOpen = ref(false)
const title = ref('Aperçu du contrat')
const iframeSrcDoc = ref<string | null>(null)
const iframeUrl = ref<string | null>(null)

function getRawToken(): string {
  if (!import.meta.client) return ''
  try {
    const raw = localStorage.getItem('app_auth')
    if (!raw) return ''
    const parsed = JSON.parse(raw)
    return parsed?.token ?? ''
  } catch {
    return ''
  }
}

function withToken(url: string): string {
  const token = getRawToken()
  if (!token) return url
  const sep = url.includes('?') ? '&' : '?'
  return `${url}${sep}token=${encodeURIComponent(token)}`
}

function fmtMoney(v: number | string | null | undefined): string {
  const n = Number(v)
  if (!v || isNaN(n)) return ''
  return n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' DH'
}

function fmtDate(v: string | null | undefined): string {
  if (!v) return ''
  const d = new Date(v)
  return isNaN(d.getTime()) ? '' : d.toLocaleDateString('fr-FR')
}

function dash(v: string | null | undefined): string {
  return v && v.trim() ? v : '—'
}

function buildTokenMap(payload: LiveDataPayload): Record<string, string> {
  return {
    domiciliataire_nom: payload.companyName ?? '',
    domiciliataire_rc: payload.companyRC ?? '',
    domiciliataire_if: payload.companyIF ?? '',
    domiciliataire_tp: payload.companyTP ?? '',
    domiciliataire_adresse: payload.companyAdresse ?? '',
    domiciliataire_representant: payload.companyRepresentant ?? '',
    domiciliataire_identite_representant: payload.companyCIN ?? '',

    raison_sociale: payload.societe ?? '',
    societe: payload.societe ?? '',
    forme_juridique: payload.forme_juridique ?? '',
    ville_client: payload.ville_client ?? '',

    gerant_nom: payload.gerantNom ?? '',
    gerant_identite: payload.gerantCIN ?? '',
    gerant_telephone: payload.tel ?? '',
    telephone: payload.tel ?? '',
    gerant_email: payload.email ?? '',
    email: payload.email ?? '',
    gerant_adresse: payload.adressePerso ?? '',

    date_debut: fmtDate(payload.date_debut),
    date_fin: fmtDate(payload.date_fin),
    date_signature: fmtDate(payload.date_signature),
    duree_mois: String(payload.duree_mois ?? ''),
    instruction_no: payload.instruction_no ?? '',
    ville_signature: payload.ville_signature ?? '',

    redevance_mensuelle: fmtMoney(payload.redevanceMensuelle),
    redevance_annuelle: fmtMoney(payload.redevanceAnnuelle),
    mode_paiement: payload.mode_paiement ?? '',
    caution: fmtMoney(payload.caution),
  }
}

function resolveTokens(text: string, tokenMap: Record<string, string>): string {
  const esc = (s: string) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  const escaped = esc(text ?? '')
  return escaped.replace(/\{\{\s*([a-z_]+)\s*\}\}/gi, (match, rawKey) => {
    const key = rawKey.toLowerCase()
    const found = Object.entries(tokenMap).find(([k]) => k.toLowerCase() === key)
    return found ? `<strong>${esc(found[1] || '—')}</strong>` : `<em style="color:#c8a96e">${esc(match)}</em>`
  })
}

function generateContractHtml(payload: LiveDataPayload): string {
  const t = buildTokenMap(payload)
  const articles = payload.articles ?? []

  const articlesHtml = articles.length ? `
    <div class="section-heading">Clauses Contractuelles</div>
    ${articles.map((a, i) => `
        <div class="article-block">
            <div class="article-title">Article ${i + 1} — ${a.title ?? ''}</div>
            <div class="article-body">${resolveTokens(a.body ?? '', t).replace(/\n/g, '<br>')}</div>
        </div>
    `).join('')}
  ` : ''

  return `
    <!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color:#000; line-height:1.4; font-size:13px; margin:0; padding:20px 24px; background-color:#fff; }
        .contract-title { text-align:center; font-size:18px; font-weight:bold; text-transform:uppercase; margin:10px 0 5px; }
        .ref-line { text-align:center; font-size:12px; font-weight:bold; margin-bottom:25px; }
        .party-block { margin-bottom:15px; text-align:justify; }
        .party-title { font-weight:bold; text-transform:uppercase; text-decoration:underline; margin-bottom:5px; }
        .client-details-list { list-style:none; padding-left:30px; margin:0 0 10px; }
        .client-details-list li { margin-bottom:3px; }
        .section-heading { font-size:15px; font-weight:bold; text-transform:uppercase; text-decoration:underline; margin:30px 0 15px; }
        .article-block { margin-bottom:15px; }
        .article-title { font-weight:bold; font-size:14px; text-transform:uppercase; margin-bottom:4px; }
        .article-body { text-align:justify; }
        .signature-section { margin-top:40px; }
        .date-location { text-align:right; font-weight:bold; margin-bottom:15px; font-size:13px; }
        .signature-mention { font-style:italic; text-align:center; margin-bottom:15px; font-size:13px; }
        .signature-table { width:100%; border-collapse:collapse; }
        .signature-table td { width:50%; vertical-align:top; padding:10px; height:110px; }
        .signature-label { font-weight:bold; text-decoration:underline; margin-bottom:5px; }
        .footer { margin-top:20px; border-top:1px solid #000; padding-top:5px; font-size:10px; text-align:center; line-height:1.4; }
    </style></head><body>
        <div class="contract-title">${dash(t.domiciliataire_nom)}</div>
        <div class="contract-title">${payload.titreContrat || 'CONTRAT DE DOMICILIATION'}</div>
        ${t.instruction_no ? `<div class="ref-line">Réf. N° ${t.instruction_no}</div>` : ''}

        <p style="font-weight:bold;text-decoration:underline;margin-bottom:10px">Entre les soussignés :</p>

        <div class="party-block">
            <div class="party-title">D'une part</div>
            <p>
                Le Centre De domiciliation <strong>${dash(t.domiciliataire_nom)}</strong>,
                RC <strong>${dash(t.domiciliataire_rc)}</strong>,
                I.F : <strong>${dash(t.domiciliataire_if)}</strong>
                sise à <strong>${dash(t.domiciliataire_adresse)}</strong>.<br>
                Représentée par <strong>${dash(t.domiciliataire_representant)}</strong>
                ${t.domiciliataire_identite_representant ? `titulaire de la CIN N° <strong>${t.domiciliataire_identite_representant}</strong>` : ''}.<br>
                ${t.instruction_no ? `Déclare par la présente suivant l'instruction No. : <strong>${t.instruction_no}</strong>,` : 'Déclare par la présente'}
                Donner domiciliation à :
            </p>
        </div>

        <div class="party-block">
            <div class="party-title">D'autre part</div>
            <p>
                <strong>${dash(t.raison_sociale)}</strong> à l'adresse suivante
                C/O <strong>${dash(t.domiciliataire_nom)}</strong>
                <strong>${dash(t.ville_client || t.ville_signature)}</strong>.<br>
                Nous déclarons en outre avoir pris connaissance des dispositions applicables en matière de domiciliation fiscale.
            </p>
            <p style="margin-bottom:5px">LA SOCIETE « <strong>${dash(t.raison_sociale)}</strong> » — Représentée par :</p>
            <ul class="client-details-list">
                <li>➤ <strong>${dash(t.gerant_nom)}</strong>, porteur de CIN/Passeport : <strong>${dash(t.gerant_identite)}</strong>,</li>
                <li>➤ Demeurant à <strong>${dash(t.gerant_adresse)}</strong></li>
            </ul>
        </div>

        <div class="party-block">
            <p>
                Le présent contrat est prévu pour une durée de <strong>${dash(t.duree_mois)} mois</strong>
                qui commencera le <strong>${dash(t.date_debut)}</strong>
                et se terminera le <strong>${dash(t.date_fin)}</strong>.
                Le présent contrat est consenti moyennant une redevance mensuelle de
                <strong>${dash(t.redevance_mensuelle)}</strong>,
                soit <strong>${dash(t.redevance_annuelle)}</strong> annuelle
                ${t.mode_paiement ? `, payable par <strong>${t.mode_paiement}</strong>` : ''}.
                ${t.caution ? `Une caution de <strong>${t.caution}</strong> est exigée.` : ''}
            </p>
        </div>

        ${articlesHtml}

        <div class="party-block">
            <p>
                Je certifie, <strong>${dash(t.gerant_nom)}</strong>, l'exactitude des informations ci-dessous :<br>
                N° Tel : <strong>${dash(t.gerant_telephone)}</strong><br>
                Email : <strong>${dash(t.gerant_email)}</strong><br>
                Adresse personnelle : <strong>${dash(t.gerant_adresse)}</strong>
            </p>
        </div>

        <div class="signature-section">
            ${(t.ville_signature || t.date_signature) ? `
            <div class="date-location">
                Fait à <strong>${t.ville_signature || '___________'}</strong>,
                le <strong>${t.date_signature || '___________'}</strong>
            </div>` : ''}
            <div class="signature-mention">« Signature précédée des mentions Lu et approuvé, bon pour accord »</div>
            <table class="signature-table">
                <tr>
                    <td>
                        <div class="signature-label">La société ${t.domiciliataire_nom}</div>
                        <div>Représentée par Mr. <strong>${t.domiciliataire_representant}</strong></div>
                    </td>
                    <td>
                        <div class="signature-label">La société ${t.raison_sociale}</div>
                        <div>Représentée par <strong>${t.gerant_nom}</strong></div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="footer">
            <strong>${dash(t.domiciliataire_nom)}</strong> —
            RC : <strong>${dash(t.domiciliataire_rc)}</strong> |
            IF : <strong>${dash(t.domiciliataire_if)}</strong> |
            TP : <strong>${dash(t.domiciliataire_tp)}</strong><br>
            ${t.domiciliataire_adresse || ''}
        </div>
    </body></html>
  `
}

function openLive(payload: LiveDataPayload, customTitle?: string) {
  iframeUrl.value = null
  iframeSrcDoc.value = generateContractHtml(payload)
  title.value = customTitle || payload.titreContrat || 'Aperçu du contrat'
  isOpen.value = true
}

function openUrl(url: string, customTitle?: string) {
  iframeSrcDoc.value = null
  iframeUrl.value = withToken(url)
  title.value = customTitle || 'Aperçu du contrat'
  isOpen.value = true
}

function close() {
  isOpen.value = false
  iframeSrcDoc.value = null
  iframeUrl.value = null
}

defineExpose({
  openLive,
  openUrl,
  close,
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="isOpen"
      class="fixed inset-0 z-[300] flex flex-col p-4 md:p-8"
      style="background: rgba(0, 0, 0, 0.9)"
    >
      <div class="flex justify-between items-center mb-4 text-white">
        <h3 class="text-lg font-serif">{{ title }}</h3>
        <button
          @click="close"
          class="w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition-colors"
        >
          ✕
        </button>
      </div>
      <div class="flex-1 bg-white rounded-xl overflow-hidden relative shadow-2xl">
        <iframe
          v-if="iframeSrcDoc"
          :srcdoc="iframeSrcDoc"
          class="w-full h-full border-0"
        />
        <iframe
          v-else-if="iframeUrl"
          :src="iframeUrl"
          class="w-full h-full border-0"
        />
      </div>
    </div>
  </Teleport>
</template>