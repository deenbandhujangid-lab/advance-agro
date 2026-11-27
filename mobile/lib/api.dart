import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math';

import 'package:flutter_background_service/flutter_background_service.dart';
import 'package:geolocator/geolocator.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import 'tracking_service.dart';

/// An error whose message is a plain sentence that can be shown to the employee.
class ApiException implements Exception {
  ApiException(this.message);
  final String message;
  @override
  String toString() => message;
}

/// Talks to the office server. Keeps the server address, the sign-in token and this phone's ID.
class Api {
  /// Address of the office server. Can be changed on the sign-in screen.
  static const defaultServer = String.fromEnvironment('SERVER',
      defaultValue: 'https://advance-agro.giftkoo.com');

  static late SharedPreferences _prefs;
  static String server = defaultServer;
  static String? token;
  static String deviceId = '';

  /// Called when the server says the sign-in is no longer valid.
  static void Function()? onSignedOut;

  static Future<void> load() async {
    _prefs = await SharedPreferences.getInstance();
    server = _prefs.getString('server') ?? defaultServer;
    token = _prefs.getString('token');
    deviceId = _prefs.getString('device_id') ?? '';
    if (deviceId.isEmpty) {
      // One ID per installation. The server ties the employee to it on first sign-in.
      final r = Random.secure();
      deviceId = List.generate(16, (_) => r.nextInt(256).toRadixString(16).padLeft(2, '0')).join();
      await _prefs.setString('device_id', deviceId);
    }
  }

  static Future<void> setServer(String value) async {
    server = value.trim().replaceAll(RegExp(r'/+$'), '');
    await _prefs.setString('server', server);
  }

  static Future<void> setToken(String? value) async {
    token = value;
    if (value == null) {
      await _prefs.remove('token');
    } else {
      await _prefs.setString('token', value);
    }
  }

  static Future<dynamic> get(String path) => _send('GET', path);

  static Future<dynamic> post(String path, [Map<String, dynamic>? body]) => _send('POST', path, body);

  static Future<dynamic> _send(String method, String path, [Map<String, dynamic>? body]) async {
    final uri = Uri.parse('$server/api$path');
    final headers = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };
    http.Response res;
    try {
      res = method == 'GET'
          ? await http.get(uri, headers: headers).timeout(const Duration(seconds: 20))
          : await http.post(uri, headers: headers, body: jsonEncode(body ?? {})).timeout(const Duration(seconds: 20));
    } on SocketException {
      throw ApiException('No internet, or the office server cannot be reached.');
    } on TimeoutException {
      throw ApiException('The server is taking too long. Try again.');
    } catch (_) {
      throw ApiException('Could not connect to the server. Check the server address.');
    }

    dynamic data;
    try {
      data = jsonDecode(res.body);
    } catch (_) {
      data = null;
    }
    if (res.statusCode == 401) {
      await setToken(null);
      onSignedOut?.call();
      throw ApiException('Please sign in again.');
    }
    if (res.statusCode < 200 || res.statusCode >= 300) {
      final message = data is Map && data['message'] is String ? data['message'] as String : 'Something went wrong (${res.statusCode}).';
      throw ApiException(message);
    }
    return data;
  }
}

/// Location helpers. Every failure is explained in one plain line.
class Loc {
  /// Makes sure GPS is on and the app may read location. Asks the employee if needed.
  static Future<void> ensureReady() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      throw ApiException('Location (GPS) is off. Turn it on and try again.');
    }
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied) {
      throw ApiException('Location permission is needed for attendance. Please allow it.');
    }
    if (permission == LocationPermission.deniedForever) {
      throw ApiException('Location permission is blocked. Open phone Settings > Apps > GK Buildcon > Permissions and allow Location.');
    }
  }

  static Future<Position> current() async {
    await ensureReady();
    try {
      return await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.high, timeLimit: Duration(seconds: 25)),
      );
    } on TimeoutException {
      throw ApiException('Could not get your location. Move near a window or outside and try again.');
    } catch (_) {
      throw ApiException('Could not get your location. Check that GPS is on.');
    }
  }
}

/// Starts and stops the tracking service. The service itself (tracking_service.dart) keeps
/// running after the app is minimized or closed, until the employee logs out for the day.
class Tracker {
  static final _service = FlutterBackgroundService();

  /// Call once when the app starts.
  static Future<void> init() async {
    await _service.configure(
      androidConfiguration: AndroidConfiguration(
        onStart: trackingMain,
        autoStart: false,
        autoStartOnBoot: false,
        isForegroundMode: true,
        initialNotificationTitle: 'Attendance tracking is on',
        initialNotificationContent: 'It stops when you logout.',
        foregroundServiceNotificationId: 4401,
        foregroundServiceTypes: [AndroidForegroundType.location],
      ),
      iosConfiguration: IosConfiguration(autoStart: false),
    );
  }

  static Future<void> start() async {
    try {
      await Loc.ensureReady();
      if (!await _service.isRunning()) {
        await _service.startService();
      }
    } catch (_) {
      // Home screen shows the reason when the employee presses a button.
    }
  }

  static Future<void> stop() async {
    if (await _service.isRunning()) {
      _service.invoke('stop');
    }
  }
}
