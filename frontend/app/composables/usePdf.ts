export function usePdf() {
    async function download(
        form: any,
        services: Array<{ id: string; label: string; price: number }>,
        dynNum?: (id: string) => number
    ) {
        // Placeholder implementation (replace later with html2pdf/jspdf if needed)
        const lines = [
            'AST-FISC - CONTRAT',
            `Société: ${form?.societe || '-'}`,
            `Gérant: ${form?.gerantNom || '-'}`,
            `CIN: ${form?.gerantCIN || '-'}`,
            `Téléphone: ${form?.tel || '-'}`,
            `Email: ${form?.email || '-'}`,
            `Date début: ${form?.dateDebut || '-'}`,
            `Durée: ${form?.months || 0} mois`,
            '',
            'Services:',
            ...services.map(s => `- ${s.label} (${s.price} DH) x ${dynNum ? dynNum(s.id) : 1}`),
        ].join('\n')

        const blob = new Blob([lines], { type: 'text/plain;charset=utf-8' })
        const url = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = `contrat-${(form?.societe || 'astfisc').toString().replace(/\s+/g, '-')}.txt`
        a.click()
        URL.revokeObjectURL(url)
    }

    return { download }
}