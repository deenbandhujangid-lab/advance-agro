import 'package:flutter/material.dart';

import '../api.dart';
import '../main.dart';

String _ymd(DateTime d) => '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

/// Leave balance, own requests, and the Apply leave form.
class LeaveTab extends StatefulWidget {
  const LeaveTab({super.key});

  @override
  State<LeaveTab> createState() => _LeaveTabState();
}

class _LeaveTabState extends State<LeaveTab> {
  Map<String, dynamic>? data;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final res = Map<String, dynamic>.from(await Api.get('/leaves') as Map);
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

  Future<void> cancel(Map leave) async {
    final yes = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('Cancel this leave?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('No')),
          FilledButton(onPressed: () => Navigator.pop(c, true), child: const Text('Yes, cancel leave')),
        ],
      ),
    );
    if (yes != true) return;
    try {
      final res = await Api.post('/leaves/${leave['id']}/cancel');
      if (mounted) toast(context, res['message'] as String);
      load();
    } on ApiException catch (e) {
      if (mounted) toast(context, e.message);
    }
  }

  Future<void> apply() async {
    final types = Map<String, dynamic>.from(data!['types'] as Map);
    final sent = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => ApplyLeaveScreen(types: types)));
    if (sent == true) load();
  }

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
    final balances = Map<String, dynamic>.from(d['balances'] as Map);
    final leaves = (d['leaves'] as List).cast<Map>();
    const statusColor = {'pending': Colors.orange, 'approved': Colors.green, 'rejected': Colors.red, 'cancelled': Colors.grey};

    return RefreshIndicator(
      onRefresh: load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const Text('Leave balance', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          Row(children: [
            for (final e in {'Casual': 'CL', 'Sick': 'SL', 'CCL': 'CCL'}.entries)
              Expanded(
                child: Card(
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(children: [
                      Text('${balances[e.value]}', style: const TextStyle(fontSize: 26, fontWeight: FontWeight.bold, color: green)),
                      Text(e.key),
                    ]),
                  ),
                ),
              ),
          ]),
          const SizedBox(height: 8),
          FilledButton.icon(
            onPressed: apply,
            icon: const Icon(Icons.add),
            label: const Text('Apply leave', style: TextStyle(fontSize: 17)),
            style: FilledButton.styleFrom(padding: const EdgeInsets.all(14)),
          ),
          const SizedBox(height: 16),
          const Text('My requests', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
          if (leaves.isEmpty) const Padding(padding: EdgeInsets.all(16), child: Text('No leave requests yet.')),
          for (final l in leaves)
            Card(
              child: ListTile(
                title: Text('${l['type']} · ${l['from']}${l['to'] == l['from'] ? '' : ' to ${l['to']}'} (${l['days']} day)'),
                subtitle: Text('${l['reason'] ?? ''}${l['remark'] == null ? '' : '\nManager: ${l['remark']}'}'),
                trailing: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.end, children: [
                  Text('${l['status']}'.toUpperCase(),
                      style: TextStyle(color: statusColor['${l['status']}'] ?? Colors.grey, fontWeight: FontWeight.bold, fontSize: 12)),
                  if (l['can_cancel'] == true)
                    InkWell(onTap: () => cancel(l), child: const Padding(padding: EdgeInsets.only(top: 6), child: Text('Cancel', style: TextStyle(color: Colors.red)))),
                ]),
              ),
            ),
        ],
      ),
    );
  }
}

class ApplyLeaveScreen extends StatefulWidget {
  const ApplyLeaveScreen({super.key, required this.types});
  final Map<String, dynamic> types;

  @override
  State<ApplyLeaveScreen> createState() => _ApplyLeaveScreenState();
}

class _ApplyLeaveScreenState extends State<ApplyLeaveScreen> {
  String type = 'CL';
  DateTime from = DateTime.now();
  DateTime to = DateTime.now();
  bool halfDay = false;
  final reason = TextEditingController();
  bool busy = false;
  String? error;

  Future<void> pick(bool isFrom) async {
    final today = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: isFrom ? from : to,
      firstDate: DateTime(today.year, today.month, today.day),
      lastDate: today.add(const Duration(days: 365)),
    );
    if (picked == null) return;
    setState(() {
      if (isFrom) {
        from = picked;
        if (to.isBefore(from)) to = from;
      } else {
        to = picked.isBefore(from) ? from : picked;
      }
    });
  }

  Future<void> send() async {
    if (reason.text.trim().isEmpty) {
      setState(() => error = 'Write the reason for leave.');
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final res = await Api.post('/leaves', {
        'type': type,
        'from': _ymd(from),
        'to': _ymd(halfDay ? from : to),
        'half_day': halfDay,
        'reason': reason.text.trim(),
      });
      if (!mounted) return;
      toast(context, res['message'] as String);
      Navigator.pop(context, true);
    } on ApiException catch (e) {
      setState(() => error = e.message);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Apply leave')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          DropdownButtonFormField<String>(
            initialValue: type,
            decoration: const InputDecoration(labelText: 'Leave type', border: OutlineInputBorder()),
            items: [for (final e in widget.types.entries) DropdownMenuItem(value: e.key, child: Text('${e.value}'))],
            onChanged: (v) => setState(() => type = v ?? 'CL'),
          ),
          const SizedBox(height: 12),
          CheckboxListTile(
            value: halfDay,
            onChanged: (v) => setState(() => halfDay = v ?? false),
            title: const Text('Half day only'),
            contentPadding: EdgeInsets.zero,
            controlAffinity: ListTileControlAffinity.leading,
          ),
          ListTile(
            shape: RoundedRectangleBorder(side: const BorderSide(color: Colors.black38), borderRadius: BorderRadius.circular(4)),
            leading: const Icon(Icons.event),
            title: Text(halfDay ? 'Date' : 'From'),
            trailing: Text(_ymd(from), style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            onTap: () => pick(true),
          ),
          if (!halfDay) ...[
            const SizedBox(height: 12),
            ListTile(
              shape: RoundedRectangleBorder(side: const BorderSide(color: Colors.black38), borderRadius: BorderRadius.circular(4)),
              leading: const Icon(Icons.event),
              title: const Text('To'),
              trailing: Text(_ymd(to), style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
              onTap: () => pick(false),
            ),
          ],
          const SizedBox(height: 12),
          TextField(
            controller: reason,
            maxLength: 200,
            maxLines: 2,
            decoration: const InputDecoration(labelText: 'Reason', border: OutlineInputBorder()),
          ),
          if (error != null) Padding(padding: const EdgeInsets.only(bottom: 12), child: Text(error!, style: const TextStyle(color: Colors.red))),
          FilledButton(
            onPressed: busy ? null : send,
            style: FilledButton.styleFrom(padding: const EdgeInsets.all(14)),
            child: Text(busy ? 'Please wait...' : 'Send leave request', style: const TextStyle(fontSize: 17)),
          ),
        ],
      ),
    );
  }
}
