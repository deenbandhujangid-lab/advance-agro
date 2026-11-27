import 'package:flutter/material.dart';

import '../api.dart';
import '../main.dart';

class SignInScreen extends StatefulWidget {
  const SignInScreen({super.key, required this.onDone});
  final VoidCallback onDone;

  @override
  State<SignInScreen> createState() => _SignInScreenState();
}

class _SignInScreenState extends State<SignInScreen> {
  final code = TextEditingController();
  final password = TextEditingController();
  final server = TextEditingController(text: Api.server);
  bool busy = false;
  bool showServer = false;
  String? error;

  Future<void> signIn() async {
    if (code.text.trim().isEmpty || password.text.isEmpty) {
      setState(() => error = 'Enter your employee code and password.');
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await Api.setServer(server.text);
      final res = await Api.post('/login', {'emp_code': code.text.trim(), 'password': password.text, 'device_id': Api.deviceId});
      await Api.setToken(res['token'] as String);
      widget.onDone();
    } on ApiException catch (e) {
      setState(() => error = e.message);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: green,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Card(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Center(
                      child: Container(
                        width: 64,
                        height: 64,
                        decoration: BoxDecoration(color: const Color(0xFFE8F5EC), borderRadius: BorderRadius.circular(18)),
                        child: const Icon(Icons.eco, color: green, size: 36),
                      ),
                    ),
                    const SizedBox(height: 12),
                    const Text('GK Buildcon', textAlign: TextAlign.center, style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold)),
                    const Text('Employee attendance', textAlign: TextAlign.center, style: TextStyle(color: Colors.black54)),
                    const SizedBox(height: 20),
                    TextField(
                      controller: code,
                      textCapitalization: TextCapitalization.characters,
                      decoration: const InputDecoration(labelText: 'Employee code', border: OutlineInputBorder(), prefixIcon: Icon(Icons.badge_outlined)),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: password,
                      obscureText: true,
                      onSubmitted: (_) => signIn(),
                      decoration: const InputDecoration(labelText: 'Password', border: OutlineInputBorder(), prefixIcon: Icon(Icons.lock_outline)),
                    ),
                    if (error != null) ...[
                      const SizedBox(height: 12),
                      Text(error!, style: const TextStyle(color: Colors.red)),
                    ],
                    const SizedBox(height: 16),
                    FilledButton(
                      onPressed: busy ? null : signIn,
                      style: FilledButton.styleFrom(padding: const EdgeInsets.all(14)),
                      child: Text(busy ? 'Please wait...' : 'Sign in', style: const TextStyle(fontSize: 18)),
                    ),
                    TextButton(
                      onPressed: () => setState(() => showServer = !showServer),
                      child: const Text('Server address'),
                    ),
                    if (showServer)
                      TextField(
                        controller: server,
                        keyboardType: TextInputType.url,
                        decoration: const InputDecoration(labelText: 'Office server address', border: OutlineInputBorder(), helperText: 'Change only if admin tells you.'),
                      ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
