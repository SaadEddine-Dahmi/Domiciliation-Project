import 'package:flutter/material.dart';

import '../core/api_exception.dart';
import 'error_banner.dart';

class ApiFuture<T> extends StatefulWidget {
  const ApiFuture({super.key, required this.load, required this.builder});

  final Future<T> Function() load;
  final Widget Function(
    BuildContext context,
    T data,
    Future<void> Function() refresh,
  ) builder;

  @override
  State<ApiFuture<T>> createState() => _ApiFutureState<T>();
}

class _ApiFutureState<T> extends State<ApiFuture<T>> {
  late Future<T> future;

  @override
  void initState() {
    super.initState();
    future = widget.load();
  }

  Future<void> refresh() async {
    setState(() => future = widget.load());
    await future;
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<T>(
      future: future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }

        if (snapshot.hasError) {
          return Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: ErrorBanner(
                message: snapshot.error is ApiException
                    ? (snapshot.error as ApiException).message
                    : snapshot.error.toString(),
              ),
            ),
          );
        }

        return widget.builder(context, snapshot.data as T, refresh);
      },
    );
  }
}
