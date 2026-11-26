import 'package:flutter/material.dart';

import '../api.dart';
import '../main.dart';

String _rs(dynamic amount) => 'Rs ${(amount as num).toStringAsFixed(2)}';

/// Own details, salary, advance due amount, payslips, change password, sign out.
class ProfileTab extends StatefulWidget {
  const ProfileTab({super.key, required this.onSignOut});
  final VoidCallback onSignOut;

  @override
  State<ProfileTab> createState() => _ProfileTabState();
}

class _ProfileTabState extends State<ProfileTab> {
  Map<String, dynamic>? data;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final res = Map<String, dynamic>.from(await Api.get('/profile') as Map);
      if (mounted) {
        setState(() {
          data = res;
          error = null;
        });
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => error = e.message);
    }
  }

  Future<void> signOut() async {
    try {
      await Api.post('/sign-out');
      await Tracker.stop();
      await Api.setToken(null);
      widget.onSignOut();
    } on ApiException catch (e) {
      // The server refuses while logged in for the day, so tracking cannot be escaped by signing out.
      if (mounted) toast(context, e.message);
    }
  }

  Future<void> changePassword() async {
    final current = TextEditingController();
    final next = TextEditingController();
    String? problem;
    await showDialog(
      context: context,
      builder: (c) => StatefulBuilder(
        builder: (c, setBox) => AlertDialog(
          title: const Text('Change password'),
          content: Column(mainAxisSize: MainAxisSize.min, children: [
            TextField(controller: current, obscureText: true, decoration: const InputDecoration(labelText: 'Current password')),
            TextField(controller: next, obscureText: true, decoration: const InputDecoration(labelText: 'New password (6 or more letters)')),
            if (problem != null) Padding(padding: const EdgeInsets.only(top: 10), child: Text(problem!, style: const TextStyle(color: Colors.red))),
          ]),
          actions: [
            TextButton(onPressed: () => Navigator.pop(c), child: const Text('Cancel')),
            FilledButton(
              onPressed: () async {
                try {
                  final res = await Api.post('/change-password', {'current_password': current.text, 'new_password': next.text});
                  if (!c.mounted) return;
                  Navigator.pop(c);
                  toast(context, res['message'] as String);
                } on ApiException catch (e) {
                  setBox(() => problem = e.message);
                }
              },
              child: const Text('Change password'),
            ),
          ],
        ),
      ),
    );
  }

  void showPayslip(Map p) {
    showModalBottomSheet(
      context: context,
      showDragHandle: true,
      builder: (_) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Text('Salary ${p['month']}', style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
            const SizedBox(height: 10),
            _line('Basic salary', _rs(p['basic'])),
            _line('Unpaid days (${p['unpaid_days']})', '- ${_rs(p['unpaid_amount'])}'),
            _line('Overtime', '+ ${_rs(p['ot_amount'])}'),
            _line('Advance cut', '- ${_rs(p['advance_deduction'])}'),
            const Divider(),
            _line('Salary to pay', _rs(p['net']), bold: true),
          ]),
        ),
      ),
    );
  }

  Widget _line(String a, String b, {bool bold = false}) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Row(children: [
          Expanded(child: Text(a, style: TextStyle(fontWeight: bold ? FontWeight.bold : FontWeight.normal))),
          Text(b, style: TextStyle(fontWeight: FontWeight.bold, fontSize: bold ? 19 : 16)),
        ]),
      );

  @override
  Widget build(BuildContext context) {
    final d = data;
    if (d == null) {
      return Center(
        child: error == null
            ? const CircularProgressIndicator()
            : Column(mainAxisSize: MainAxisSize.min, children: [
                Text(error!),
                const SizedBox(height: 12),
                FilledButton(onPressed: load, child: const Text('Try again')),
              ]),
      );
    }
    final user = Map<String, dynamic>.from(d['user'] as Map);
    final salary = Map<String, dynamic>.from(d['salary'] as Map);
    final payslips = (d['payslips'] as List).cast<Map>();

    return RefreshIndicator(
      onRefresh: load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text('${user['name']}', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold)),
          Text('${user['emp_code']} · ${user['role']} · ${user['type']}', style: const TextStyle(color: Colors.black54)),
          const SizedBox(height: 10),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(children: [
                _line('Contact', '${user['phone'] ?? '-'}'),
                _line('Email', '${user['email'] ?? '-'}'),
                _line('Manager', '${user['manager'] ?? '-'}'),
                _line('Morning time', '${user['morning_time']}'),
                _line('Evening time', '${user['evening_time']}'),
              ]),
            ),
          ),
          const SizedBox(height: 8),
          const Text('Salary', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(children: [
                _line('Basic salary', _rs(salary['basic'])),
                _line('Advance due amount', _rs(salary['due_amount'])),
                _line('Cut per month', _rs(salary['deduction_per_month'])),
              ]),
            ),
          ),
          if (payslips.isNotEmpty) ...[
            const SizedBox(height: 8),
            const Text('Payslips', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            for (final p in payslips)
              Card(
                child: ListTile(
                  title: Text('${p['month']}'),
                  trailing: Text(_rs(p['net']), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                  onTap: () => showPayslip(p),
                ),
              ),
          ],
          const SizedBox(height: 16),
          OutlinedButton.icon(onPressed: changePassword, icon: const Icon(Icons.lock_outline), label: const Text('Change password')),
          const SizedBox(height: 8),
          OutlinedButton.icon(
            onPressed: signOut,
            icon: const Icon(Icons.logout, color: Colors.red),
            label: const Text('Sign out of app', style: TextStyle(color: Colors.red)),
          ),
        ],
      ),
    );
  }
}
