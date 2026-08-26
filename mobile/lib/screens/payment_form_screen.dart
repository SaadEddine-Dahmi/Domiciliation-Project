import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../widgets/error_banner.dart';

class PaymentFormScreen extends StatefulWidget {
  const PaymentFormScreen({
    super.key,
    required this.api,
    required this.contractId,
    required this.contractTitle,
  });

  final ApiClient api;
  final Object contractId;
  final String contractTitle;

  @override
  State<PaymentFormScreen> createState() => _PaymentFormScreenState();
}

class _PaymentFormScreenState extends State<PaymentFormScreen> {
  final date = TextEditingController(text: DateTime.now().toIso8601String().substring(0, 10));
  final note = TextEditingController();
  final lines = <InvoiceLine>[InvoiceLine(description: 'Domiciliation ', quantity: 1, unitPrice: 0)];
  String mode = 'Virement';
  String status = 'paid';
  double vatRate = 20;
  bool saving = false;
  String? error;

  double get subtotal => lines.fold(0, (sum, line) => sum + line.total);
  double get vatAmount => subtotal * vatRate / 100;
  double get total => subtotal + vatAmount;
  double get paidAmount => status == 'paid' ? total : 0;

  @override
  void dispose() {
    date.dispose();
    note.dispose();
    for (final line in lines) {
      line.dispose();
    }
    super.dispose();
  }

  Future<void> save() async {
    if (total <= 0 || date.text.trim().isEmpty) {
      setState(() => error = 'Ajoutez au moins une ligne avec un montant valide.');
      return;
    }

    setState(() {
      saving = true;
      error = null;
    });

    try {
      await widget.api.createPayment(widget.contractId, {
        'montant': paidAmount > 0 ? paidAmount : total,
        'date_paiement': date.text.trim(),
        'mode_paiement': mode,
        'note': [
          if (note.text.trim().isNotEmpty) note.text.trim(),
          'Statut mobile: $status',
          'Sous-total: ${subtotal.toStringAsFixed(2)} DH',
          'TVA ${vatRate.toStringAsFixed(0)}%: ${vatAmount.toStringAsFixed(2)} DH',
          'Total TTC: ${total.toStringAsFixed(2)} DH',
          for (final line in lines) '${line.description.text} x ${line.quantity.text}: ${line.total.toStringAsFixed(2)} DH',
        ].join('\n'),
      });
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      setState(() => error = e is ApiException ? e.message : e.toString());
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  void addLine() {
    setState(() => lines.add(InvoiceLine(description: '', quantity: 1, unitPrice: 0)));
  }

  void removeLine(int index) {
    if (lines.length == 1) return;
    setState(() => lines.removeAt(index).dispose());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Creer facture')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: ListTile(
              leading: const Icon(Icons.description_outlined),
              title: Text(widget.contractTitle),
              subtitle: const Text('Les lignes et la TVA sont calculees sur mobile puis envoyees au backend facture/paiement.'),
            ),
          ),
          if (error != null) ...[
            const SizedBox(height: 12),
            ErrorBanner(message: error!),
          ],
          const SizedBox(height: 16),
          Text('Lignes', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
          const SizedBox(height: 8),
          for (var i = 0; i < lines.length; i++)
            InvoiceLineCard(
              line: lines[i],
              onChanged: () => setState(() {}),
              onRemove: lines.length == 1 ? null : () => removeLine(i),
            ),
          OutlinedButton.icon(
            onPressed: addLine,
            icon: const Icon(Icons.add),
            label: const Text('Ajouter une ligne'),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: DropdownButtonFormField<double>(
                  value: vatRate,
                  decoration: const InputDecoration(labelText: 'TVA'),
                  items: const [0.0, 10.0, 20.0]
                      .map((value) => DropdownMenuItem(value: value, child: Text('${value.toStringAsFixed(0)}%')))
                      .toList(),
                  onChanged: (value) => setState(() => vatRate = value ?? 20),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: DropdownButtonFormField<String>(
                  value: status,
                  decoration: const InputDecoration(labelText: 'Statut'),
                  items: const [
                    DropdownMenuItem(value: 'paid', child: Text('Payee')),
                    DropdownMenuItem(value: 'pending', child: Text('En attente')),
                  ],
                  onChanged: (value) => setState(() => status = value ?? 'paid'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            value: mode,
            decoration: const InputDecoration(labelText: 'Mode paiement'),
            items: const ['Virement', 'Especes', 'Cheque', 'Carte bancaire']
                .map((value) => DropdownMenuItem(value: value, child: Text(value)))
                .toList(),
            onChanged: (value) => setState(() => mode = value ?? 'Virement'),
          ),
          const SizedBox(height: 12),
          TextField(controller: date, decoration: const InputDecoration(labelText: 'Date paiement YYYY-MM-DD *')),
          const SizedBox(height: 12),
          TextField(controller: note, minLines: 2, maxLines: 4, decoration: const InputDecoration(labelText: 'Note')),
          const SizedBox(height: 16),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  TotalRow(label: 'Sous-total', value: subtotal),
                  TotalRow(label: 'TVA', value: vatAmount),
                  const Divider(),
                  TotalRow(label: 'Total TTC', value: total, strong: true),
                ],
              ),
            ),
          ),
          const SizedBox(height: 20),
          FilledButton.icon(
            onPressed: saving ? null : save,
            icon: const Icon(Icons.receipt_long_outlined),
            label: Text(saving ? 'Creation...' : 'Creer facture'),
          ),
        ],
      ),
    );
  }
}

class InvoiceLine {
  InvoiceLine({required String description, required double quantity, required double unitPrice})
      : description = TextEditingController(text: description),
        quantity = TextEditingController(text: quantity.toStringAsFixed(quantity.truncateToDouble() == quantity ? 0 : 2)),
        unitPrice = TextEditingController(text: unitPrice == 0 ? '' : unitPrice.toStringAsFixed(2));

  final TextEditingController description;
  final TextEditingController quantity;
  final TextEditingController unitPrice;

  double get total {
    final q = double.tryParse(quantity.text.replaceAll(',', '.')) ?? 0;
    final p = double.tryParse(unitPrice.text.replaceAll(',', '.')) ?? 0;
    return q * p;
  }

  void dispose() {
    description.dispose();
    quantity.dispose();
    unitPrice.dispose();
  }
}

class InvoiceLineCard extends StatelessWidget {
  const InvoiceLineCard({super.key, required this.line, required this.onChanged, this.onRemove});

  final InvoiceLine line;
  final VoidCallback onChanged;
  final VoidCallback? onRemove;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          children: [
            TextField(
              controller: line.description,
              onChanged: (_) => onChanged(),
              decoration: const InputDecoration(labelText: 'Designation'),
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: line.quantity,
                    onChanged: (_) => onChanged(),
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(labelText: 'Quantite'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: TextField(
                    controller: line.unitPrice,
                    onChanged: (_) => onChanged(),
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(labelText: 'Prix HT'),
                  ),
                ),
                IconButton(onPressed: onRemove, icon: const Icon(Icons.delete_outline), tooltip: 'Supprimer'),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class TotalRow extends StatelessWidget {
  const TotalRow({super.key, required this.label, required this.value, this.strong = false});

  final String label;
  final double value;
  final bool strong;

  @override
  Widget build(BuildContext context) {
    final style = strong ? Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900) : null;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Expanded(child: Text(label, style: style)),
          Text('${value.toStringAsFixed(2)} DH', style: style),
        ],
      ),
    );
  }
}
