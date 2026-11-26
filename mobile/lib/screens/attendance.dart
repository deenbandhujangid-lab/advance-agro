import 'package:flutter/material.dart';

import '../api.dart';

/// Same colours as the web: green Present, amber Late, red Absent, blue Leave, grey off day.
Color codeColor(String code) {
  switch (code) {
    case 'P':
      return Colors.green;
    case 'L':
      return Colors.orange;
    case 'H':
      return Colors.teal;
    case 'A':
      return Colors.red;
    case 'LV':
    case 'LW':
      return Colors.blue;
    case 'WO':
    case 'HD':
      return Colors.grey;
    case 'PN':
      return Colors.black87;
    default:
      return Colors.transparent;
  }
}

const _months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

/// Month calendar with one coloured mark per day. Tap a day for its times.
class AttendanceTab extends StatefulWidget {
  const AttendanceTab({super.key});

  @override
  State<AttendanceTab> createState() => _AttendanceTabState();
}

class _AttendanceTabState extends State<AttendanceTab> {
  DateTime month = DateTime(DateTime.now().year, DateTime.now().month);
  Map<String, dynamic>? data;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      data = null;
      error = null;
    });
    try {
      final key = '${month.year}-${month.month.toString().padLeft(2, '0')}';
      final res = Map<String, dynamic>.from(await Api.get('/attendance?month=$key') as Map);
      if (mounted) setState(() => data = res);
    } on ApiException catch (e) {
      if (mounted) setState(() => error = e.message);
    }
  }

  void move(int by) {
    final next = DateTime(month.year, month.month + by);
    if (next.isAfter(DateTime.now())) return;
    month = next;
    load();
  }

  void showDay(Map day) {
    final moves = (day['movements'] as List).cast<Map>();
    showModalBottomSheet(
      context: context,
      showDragHandle: true,
      builder: (_) => SafeArea(
        child: ListView(
          shrinkWrap: true,
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
          children: [
            Text('${day['date']}', style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
            Text('${day['label']}', style: TextStyle(color: codeColor('${day['code']}'), fontWeight: FontWeight.bold)),
            const SizedBox(height: 12),
            _line('Login', '${day['login'] ?? '--'}'),
            _line('Logout', '${day['logout'] ?? '--'}'),
            _line('Overtime', '${day['ot_minutes']} min'),
            if (moves.isNotEmpty) ...[
              const Divider(),
              const Text('Went outside', style: TextStyle(fontWeight: FontWeight.bold)),
              for (final m in moves)
                _line('${m['out']} to ${m['in'] ?? 'still outside'}',
                    '${m['minutes'] == null ? '' : '${m['minutes']} min · '}${m['allowed'] == true ? 'Allowed' : 'No permission'}'),
            ],
          ],
        ),
      ),
    );
  }

  Widget _line(String a, String b) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Row(children: [Expanded(child: Text(a)), Text(b, style: const TextStyle(fontWeight: FontWeight.bold))]),
      );

  @override
  Widget build(BuildContext context) {
    final d = data;
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(8),
          child: Row(children: [
            IconButton(onPressed: () => move(-1), icon: const Icon(Icons.chevron_left, size: 32)),
            Expanded(
              child: Text('${_months[month.month - 1]} ${month.year}',
                  textAlign: TextAlign.center, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
            ),
            IconButton(onPressed: () => move(1), icon: const Icon(Icons.chevron_right, size: 32)),
          ]),
        ),
        if (d == null)
          Expanded(
            child: Center(
              child: error == null
                  ? const CircularProgressIndicator()
                  : Column(mainAxisSize: MainAxisSize.min, children: [
                      Text(error!),
                      const SizedBox(height: 12),
                      FilledButton(onPressed: load, child: const Text('Try again')),
                    ]),
            ),
          )
        else
          Expanded(child: _calendar(d)),
      ],
    );
  }

  Widget _calendar(Map<String, dynamic> d) {
    final days = (d['days'] as List).cast<Map>();
    final totals = Map<String, dynamic>.from(d['totals'] as Map);
    final blanks = DateTime(month.year, month.month, 1).weekday % 7; // Sunday first
    return ListView(
      padding: const EdgeInsets.symmetric(horizontal: 12),
      children: [
        Row(children: [
          for (final w in ['S', 'M', 'T', 'W', 'T', 'F', 'S'])
            Expanded(child: Center(child: Text(w, style: const TextStyle(color: Colors.black54)))),
        ]),
        const SizedBox(height: 6),
        GridView.count(
          crossAxisCount: 7,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          children: [
            for (var i = 0; i < blanks; i++) const SizedBox(),
            for (var i = 0; i < days.length; i++)
              InkWell(
                onTap: days[i]['code'] == '-' ? null : () => showDay(days[i]),
                child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                  Text('${i + 1}', style: const TextStyle(fontSize: 16)),
                  const SizedBox(height: 2),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                    decoration: BoxDecoration(color: codeColor('${days[i]['code']}'), borderRadius: BorderRadius.circular(4)),
                    child: Text(days[i]['code'] == '-' ? '' : '${days[i]['code']}',
                        style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold)),
                  ),
                ]),
              ),
          ],
        ),
        const SizedBox(height: 12),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(children: [
              _line('Present', '${totals['present']}'),
              _line('Late', '${totals['late']}'),
              _line('Absent', '${totals['absent']}'),
              _line('Leave', '${totals['leave']}'),
              _line('Overtime', '${((totals['ot_minutes'] as num) / 60).toStringAsFixed(1)} hours'),
            ]),
          ),
        ),
        const Padding(
          padding: EdgeInsets.all(8),
          child: Text('P Present · L Late · H Half day · A Absent · LV Leave · WO Weekly off · HD Holiday · PN Logout pending',
              style: TextStyle(color: Colors.black54, fontSize: 13)),
        ),
      ],
    );
  }
}
