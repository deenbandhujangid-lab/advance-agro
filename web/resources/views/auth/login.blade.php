<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in - GK Buildcon</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('app.css') }}?v=2" rel="stylesheet">
</head>
<body>
<div class="auth">
    <div class="art">
        <div class="brand p-0 mb-4"><span class="logo"><i class="bi bi-flower1"></i></span> GK Buildcon</div>
        <h2>Attendance, leave and salary in one place.</h2>
        <ul class="list-unstyled mt-4 fs-6">
            <li><i class="bi bi-check2-circle me-2"></i> See who is present, late or outside right now</li>
            <li><i class="bi bi-check2-circle me-2"></i> Approve leave and outside permission</li>
            <li><i class="bi bi-check2-circle me-2"></i> Salary with overtime and advance, calculated for you</li>
        </ul>
    </div>
    <div class="form">
        <div style="width:100%;max-width:360px">
            <h3 class="fw-bold mb-1">Sign in</h3>
            <p class="text-muted mb-4">For Super Admin, Manager and Account Manager</p>
            @if ($errors->any())
                <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
            @endif
            <form method="post" action="{{ route('login') }}">
                @csrf
                <label class="form-label">Email or employee code</label>
                <div class="input-group mb-3">
                    <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                    <input class="form-control" name="login" value="{{ old('login') }}" required autofocus>
                </div>
                <label class="form-label">Password</label>
                <div class="input-group mb-4">
                    <span class="input-group-text bg-white"><i class="bi bi-lock"></i></span>
                    <input class="form-control" type="password" name="password" required>
                </div>
                <button class="btn btn-success w-100 py-2">Sign in</button>
            </form>
            <p class="text-muted small mt-4 mb-0">Employees use the mobile app for attendance.</p>
        </div>
    </div>
</div>
</body>
</html>
