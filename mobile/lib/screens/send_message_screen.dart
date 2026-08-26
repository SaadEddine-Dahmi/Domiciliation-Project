import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../widgets/error_banner.dart';

class SendMessageScreen extends StatefulWidget {
  const SendMessageScreen({
    super.key,
    required this.api,
    required this.clientUserId,
    required this.clientName,
  });

  final ApiClient api;
  final Object clientUserId;
  final String clientName;

  @override
  State<SendMessageScreen> createState() => _SendMessageScreenState();
}

class _SendMessageScreenState extends State<SendMessageScreen> {
  final subject = TextEditingController();
  final message = TextEditingController();
  bool sending = false;
  String? error;

  @override
  void dispose() {
    subject.dispose();
    message.dispose();
    super.dispose();
  }

  Future<void> send() async {
    if (message.text.trim().isEmpty) {
      setState(() => error = 'Le message est obligatoire.');
      return;
    }

    setState(() {
      sending = true;
      error = null;
    });

    try {
      await widget.api.sendMessage(
        clientUserId: widget.clientUserId,
        subject: subject.text,
        message: message.text,
      );
      if (mounted) Navigator.pop(context);
    } catch (e) {
      setState(() => error = e is ApiException ? e.message : e.toString());
    } finally {
      if (mounted) setState(() => sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Envoyer un message')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: ListTile(
              leading: const Icon(Icons.business_outlined),
              title: Text(widget.clientName),
              subtitle: const Text('Destinataire client'),
            ),
          ),
          const SizedBox(height: 16),
          if (error != null) ErrorBanner(message: error!),
          const SizedBox(height: 12),
          TextField(
            controller: subject,
            decoration: const InputDecoration(labelText: 'Objet'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: message,
            minLines: 5,
            maxLines: 8,
            decoration: const InputDecoration(labelText: 'Message *'),
          ),
          const SizedBox(height: 20),
          FilledButton.icon(
            onPressed: sending ? null : send,
            icon: const Icon(Icons.send_outlined),
            label: Text(sending ? 'Envoi...' : 'Envoyer'),
          ),
        ],
      ),
    );
  }
}
