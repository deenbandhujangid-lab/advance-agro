import 'dart:async';

import 'package:flutter/material.dart';

import '../api.dart';
import '../main.dart';

const _line = Color(0xFFE6EAEE);
const _muted = Color(0xFF6B7785);

/// The daily screen: welcome, the Login / Logout button, who you report to, and leave balance.
class HomeTab extends StatefulWidget {
  const HomeTab({super.key});

  @override
  State<HomeTab> createState() => _HomeTabState();
}

class _HomeTabState extends State<HomeTab> with WidgetsBindingObserver {
  Map<String, dynamic>? home;
  String? error;
  bool busy = false;
  Timer? timer;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    load();
    timer = Timer.periodic(const Duration(minutes: 1), (_) => load());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    timer?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) load();
  }

  Future<void> load() async {
    try {
      final data = Map<String, dynamic>.from(await Api.get('/home') as Map);
      // Tracking runs from Login until Logout, also when the app is minimized or closed.
      if (data['tracking'] == true) {
        Tracker.start();
      } else {
        Tracker.stop();
      }
      if (mounted) {
        setState(() {
          home = data;
          error = null;
        });
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => error = e.message);
    }
  }

  Future<void> punch(bool login) async {
    setState(() => busy = true);
    try {
      final p = await Loc.current();
      final res = await Api.post(login ? '/attendance/login' : '/attendance/logout',
          {'lat': p.latitude, 'lng': p.longitude, 'accuracy': p.accuracy, 'mock': p.isMocked});
      if (login) {
        await Tracker.start();
      } else {
        await Tracker.stop();
      }
      await load();
      if (mounted) await showDone(res['message'] as String, login);
    } on ApiException catch (e) {
      if (mounted) await showProblem(e.message);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> showDone(String message, bool login) {
    return showDialog(
      context: context,
      builder: (c) => AlertDialog(
        icon: Icon(Icons.check_circle, color: login ? Colors.green : Colors.red, size: 64),
        title: Text(message, textAlign: TextAlign.center),
        content: Text(login ? 'Attendance tracking is on until you logout.' : 'Tracking is off now. See you tomorrow.', textAlign: TextAlign.center),
        actions: [TextButton(onPressed: () => Navigator.pop(c), child: const Text('OK'))],
      ),
    );
  }

  Future<void> showProblem(String message) {
    return showDialog(
      context: context,
      builder: (c) => AlertDialog(
        icon: const Icon(Icons.info_outline, color: Colors.orange, size: 48),
        content: Text(message, textAlign: TextAlign.center, style: const TextStyle(fontSize: 17)),
        actions: [TextButton(onPressed: () => Navigator.pop(c), child: const Text('OK'))],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final h = home;
    if (h == null) {
      return Center(
        child: error == null
            ? const CircularProgressIndicator()
            : Padding(
                padding: const EdgeInsets.all(24),
                child: Column(mainAxisSize: MainAxisSize.min, children: [
                  Text(error!, textAlign: TextAlign.center),
                  const SizedBox(height: 12),
                  FilledButton(onPressed: load, child: const Text('Try again')),
                ]),
              ),
      );
    }

    final user = Map<String, dynamic>.from(h['user'] as Map);
    final loggedIn = h['state'] == 'logged_in';
    final lock = h['lock_reason'] as String?;
    final worked = (h['worked_minutes'] as num).toInt();
    final permission = h['permission'] as Map?;
    final manager = h['manager'] as Map?;
    final balances = Map<String, dynamic>.from(h['balances'] as Map? ?? {});
    final unread = (h['unread_notices'] as num).toInt();
    final canPress = !busy && (loggedIn || lock == null);
    final name = '${user['name']}';

    return RefreshIndicator(
      onRefresh: load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
        children: [
          // ---- top bar: app name, bell ----
          Row(children: [
            Container(
              width: 34,
              height: 34,
              decoration: BoxDecoration(color: green, borderRadius: BorderRadius.circular(10)),
              child: const Icon(Icons.eco, color: Colors.white, size: 20),
            ),
            const SizedBox(width: 10),
            const Expanded(child: Text('GK Buildcon', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold))),
            IconButton(
              onPressed: () async {
                await Navigator.push(context, MaterialPageRoute(builder: (_) => const NoticesScreen()));
                load();
              },
              icon: Badge(isLabelVisible: unread > 0, label: Text('$unread'), child: const Icon(Icons.notifications_outlined, size: 28)),
            ),
          ]),
          const SizedBox(height: 8),

          // ---- welcome ----
          Card(
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 16),
              child: Column(children: [
                const Text('👋', style: TextStyle(fontSize: 34)),
                const SizedBox(height: 6),
                Text('Welcome, $name', textAlign: TextAlign.center, style: const TextStyle(fontSize: 21, fontWeight: FontWeight.bold)),
              ]),
            ),
          ),

          // ---- login / logout ----
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                Row(children: [
                  Icon(Icons.circle, size: 10, color: loggedIn ? Colors.green : Colors.grey),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      loggedIn
                          ? 'In ${h['login_time']} · ${worked ~/ 60}h ${worked % 60}m'
                          : (h['logout_time'] != null ? 'Out ${h['logout_time']} · ${worked ~/ 60}h ${worked % 60}m worked' : 'Not logged in today'),
                      style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
                    ),
                  ),
                  if (h['is_late'] == true) _chip('Late', Colors.orange),
                ]),
                const SizedBox(height: 14),
                SizedBox(
                  height: 62,
                  child: ElevatedButton.icon(
                    onPressed: canPress ? () => punch(!loggedIn) : null,
                    icon: Icon(busy ? Icons.hourglass_top : (loggedIn ? Icons.logout : Icons.login), size: 26),
                    label: Text(busy ? 'Please wait...' : (loggedIn ? 'Logout' : 'Login'), style: const TextStyle(fontSize: 21, fontWeight: FontWeight.bold)),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: loggedIn ? const Color(0xFFDC2626) : const Color(0xFF16A34A),
                      foregroundColor: Colors.white,
                      disabledBackgroundColor: Colors.grey.shade400,
                      disabledForegroundColor: Colors.white,
                      elevation: 2,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                if (lock != null && !loggedIn)
                  _note(Icons.lock_outline, lock, Colors.deepOrange, const Color(0xFFFFF4E0))
                else if (loggedIn)
                  _note(Icons.location_on, '${h['status_line'] ?? ''}  ·  Tracking is on until you logout', green, const Color(0xFFE8F5EC))
                else
                  const Text('Press Login to start your day', textAlign: TextAlign.center, style: TextStyle(color: _muted)),
                if (permission != null) ...[
                  const SizedBox(height: 8),
                  _note(Icons.directions_walk, 'You may go outside: ${permission['from']} to ${permission['to']} (${permission['reason'] ?? ''})', green,
                      const Color(0xFFE8F5EC)),
                ],
              ]),
            ),
          ),

          // ---- you and reporting manager ----
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                _label('YOU'),
                Text(name, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                Text('${user['emp_code']} · ${user['role']} · ${user['type']}', style: const TextStyle(color: _muted)),
                if (manager != null) ...[
                  const Divider(height: 26, color: _line),
                  _label('REPORTING MANAGER'),
                  Text('${manager['name']}', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  Text('${manager['role']}', style: const TextStyle(color: _muted)),
                  if (manager['phone'] != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Row(children: [const Icon(Icons.phone, size: 16, color: green), const SizedBox(width: 6), Text('${manager['phone']}')]),
                    ),
                ],
              ]),
            ),
          ),

          // ---- leave balance ----
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Text('My Leave Balance', style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold)),
                const SizedBox(height: 12),
                Row(children: [
                  _balance('${balances['CL'] ?? 0}', 'Casual'),
                  const SizedBox(width: 10),
                  _balance('${balances['SL'] ?? 0}', 'Sick'),
                  const SizedBox(width: 10),
                  _balance('${balances['CCL'] ?? 0}', 'CCL'),
                ]),
                const SizedBox(height: 10),
                Text('Grace left this month: ${h['grace_left']}', style: const TextStyle(color: _muted)),
              ]),
            ),
          ),
        ],
      ),
    );
  }

  Widget _label(String text) => Padding(
        padding: const EdgeInsets.only(bottom: 4),
        child: Text(text, style: const TextStyle(fontSize: 12, color: _muted, letterSpacing: 0.8, fontWeight: FontWeight.w600)),
      );

  Widget _chip(String text, Color color) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        decoration: BoxDecoration(color: color.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(20)),
        child: Text(text, style: TextStyle(color: color, fontWeight: FontWeight.bold, fontSize: 13)),
      );

  Widget _note(IconData icon, String text, Color color, Color background) => Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(12)),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(icon, size: 20, color: color),
          const SizedBox(width: 8),
          Expanded(child: Text(text, style: const TextStyle(fontSize: 15))),
        ]),
      );

  Widget _balance(String number, String label) => Expanded(
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 14),
          decoration: BoxDecoration(border: Border.all(color: _line), borderRadius: BorderRadius.circular(14)),
          child: Column(children: [
            Text(number, style: const TextStyle(fontSize: 26, fontWeight: FontWeight.bold, color: green)),
            const SizedBox(height: 2),
            Text(label, style: const TextStyle(color: _muted)),
          ]),
        ),
      );
}

class NoticesScreen extends StatefulWidget {
  const NoticesScreen({super.key});

  @override
  State<NoticesScreen> createState() => _NoticesScreenState();
}

class _NoticesScreenState extends State<NoticesScreen> {
  List<dynamic>? notices;
  String? error;

  @override
  void initState() {
    super.initState();
    Api.get('/notices').then((r) {
      if (mounted) setState(() => notices = r['notices'] as List);
    }).catchError((Object e) {
      if (mounted) setState(() => error = '$e');
    });
  }

  @override
  Widget build(BuildContext context) {
    final list = notices;
    return Scaffold(
      appBar: AppBar(title: const Text('Notifications')),
      body: list == null
          ? Center(child: error == null ? const CircularProgressIndicator() : Text(error!))
          : list.isEmpty
              ? const Center(child: Text('No notifications.'))
              : ListView.separated(
                  itemCount: list.length,
                  separatorBuilder: (_, _) => const Divider(height: 1),
                  itemBuilder: (_, i) {
                    final n = list[i] as Map;
                    return ListTile(
                      leading: Icon(Icons.circle, size: 12, color: n['unread'] == true ? Colors.green : Colors.transparent),
                      title: Text('${n['title']}', style: const TextStyle(fontWeight: FontWeight.bold)),
                      subtitle: Text('${n['body'] ?? ''}\n${n['time']}'),
                      isThreeLine: true,
                    );
                  },
                ),
    );
  }
}
