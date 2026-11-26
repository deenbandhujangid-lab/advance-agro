import 'dart:async';
import 'dart:ui';

import 'package:flutter_background_service/flutter_background_service.dart';
import 'package:geolocator/geolocator.dart';

import 'api.dart';

/// Runs inside an Android foreground service, separate from the app screens.
/// It keeps sending location after the app is minimized or closed, and stops only
/// when the server says the employee is no longer logged in for the day.
@pragma('vm:entry-point')
Future<void> trackingMain(ServiceInstance service) async {
  DartPluginRegistrant.ensureInitialized();
  await Api.load();
  if (Api.token == null) {
    service.stopSelf();
    return;
  }

  StreamSubscription<Position>? sub;
  Timer? heartbeat;
  DateTime? lastSent;
  var stopped = false;

  Future<void> stop() async {
    if (stopped) return;
    stopped = true;
    await sub?.cancel();
    heartbeat?.cancel();
    service.stopSelf();
  }

  Future<void> send(Position p) async {
    final now = DateTime.now();
    if (stopped || (lastSent != null && now.difference(lastSent!) < const Duration(seconds: 20))) return;
    lastSent = now;
    try {
      final res = await Api.post('/track', {'lat': p.latitude, 'lng': p.longitude, 'accuracy': p.accuracy});
      if (res is Map && res['tracking'] == false) {
        await stop(); // logged out, or the day was closed
      }
    } on ApiException {
      if (Api.token == null) await stop(); // signed out on the server
      // Otherwise: no internet right now, the next reading tries again.
    } catch (_) {}
  }

  service.on('stop').listen((_) => stop());

  // A reading whenever the phone moves 10 metres...
  sub = Geolocator.getPositionStream(
    locationSettings: AndroidSettings(
      accuracy: LocationAccuracy.high,
      distanceFilter: 10,
      intervalDuration: const Duration(seconds: 30),
    ),
  ).listen(send, onError: (_) {});

  // ...and one every 2 minutes even when standing still, so the server always knows the latest state.
  heartbeat = Timer.periodic(const Duration(minutes: 2), (_) async {
    try {
      final p = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.high, timeLimit: Duration(seconds: 30)),
      );
      await send(p);
    } catch (_) {}
  });
}
