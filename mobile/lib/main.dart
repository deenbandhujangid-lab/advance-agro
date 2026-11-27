import 'package:flutter/material.dart';

import 'api.dart';
import 'screens/attendance.dart';
import 'screens/home.dart';
import 'screens/leave.dart';
import 'screens/profile.dart';
import 'screens/sign_in.dart';

const green = Color(0xFF14532D);

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Api.load();
  await Tracker.init();
  runApp(const GkBuildconApp());
}

class GkBuildconApp extends StatefulWidget {
  const GkBuildconApp({super.key});

  @override
  State<GkBuildconApp> createState() => _GkBuildconAppState();
}

class _GkBuildconAppState extends State<GkBuildconApp> {
  @override
  void initState() {
    super.initState();
    Api.onSignedOut = () {
      Tracker.stop();
      if (mounted) setState(() {});
    };
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'GK Buildcon',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: green, primary: green),
        scaffoldBackgroundColor: const Color(0xFFF5F7F9),
        appBarTheme: const AppBarTheme(backgroundColor: green, foregroundColor: Colors.white, elevation: 0),
        textTheme: const TextTheme(bodyMedium: TextStyle(fontSize: 16)),
        cardTheme: CardThemeData(
          color: Colors.white,
          elevation: 0,
          margin: const EdgeInsets.symmetric(vertical: 5),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16), side: const BorderSide(color: Color(0xFFE6EAEE))),
        ),
        filledButtonTheme: FilledButtonThemeData(
          style: FilledButton.styleFrom(shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
        ),
        outlinedButtonTheme: OutlinedButtonThemeData(
          style: OutlinedButton.styleFrom(
            padding: const EdgeInsets.all(14),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
        ),
        inputDecorationTheme: InputDecorationTheme(
          filled: true,
          fillColor: Colors.white,
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
        ),
        navigationBarTheme: const NavigationBarThemeData(backgroundColor: Colors.white, indicatorColor: Color(0xFFD9F2E1), elevation: 3),
      ),
      home: Api.token == null ? SignInScreen(onDone: () => setState(() {})) : Shell(onSignOut: () => setState(() {})),
    );
  }
}

/// The four tabs at the bottom: Home, Attendance, Leave, Profile.
class Shell extends StatefulWidget {
  const Shell({super.key, required this.onSignOut});
  final VoidCallback onSignOut;

  @override
  State<Shell> createState() => _ShellState();
}

class _ShellState extends State<Shell> {
  int tab = 0;

  @override
  Widget build(BuildContext context) {
    final pages = [
      const HomeTab(),
      const AttendanceTab(),
      const LeaveTab(),
      ProfileTab(onSignOut: widget.onSignOut),
    ];
    return Scaffold(
      body: SafeArea(child: pages[tab]),
      bottomNavigationBar: NavigationBar(
        selectedIndex: tab,
        onDestinationSelected: (i) => setState(() => tab = i),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.home_outlined), selectedIcon: Icon(Icons.home), label: 'Home'),
          NavigationDestination(icon: Icon(Icons.calendar_month_outlined), selectedIcon: Icon(Icons.calendar_month), label: 'Attendance'),
          NavigationDestination(icon: Icon(Icons.event_busy_outlined), selectedIcon: Icon(Icons.event_busy), label: 'Leave'),
          NavigationDestination(icon: Icon(Icons.person_outline), selectedIcon: Icon(Icons.person), label: 'Profile'),
        ],
      ),
    );
  }
}

/// Shows a short message at the bottom of the screen.
void toast(BuildContext context, String message) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(message)));
}
