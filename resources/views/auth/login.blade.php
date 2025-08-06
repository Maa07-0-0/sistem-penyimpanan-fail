<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>Log Masuk - {{ config('app.name') }}</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
        }
        
        .login-left {
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
            color: white;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
        }
        
        .login-right {
            padding: 3rem;
        }
        
        .form-control {
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
        }
        
        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3);
        }
        
        .logo {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .form-floating > label {
            color: #64748b;
        }
        
        .alert {
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="login-card">
                        <div class="row g-0">
                            <!-- Left Panel -->
                            <div class="col-lg-6 login-left d-none d-lg-flex">
                                <div>
                                    <div class="logo">
                                        <i class="fas fa-archive"></i>
                                    </div>
                                    <h2 class="fw-bold mb-3">Sistem Penyimpanan Fail</h2>
                                    <h4 class="fw-normal mb-4">Pejabat Daerah Tongod</h4>
                                    <p class="lead opacity-75">
                                        Sistem pengurusan fail digital yang komprehensif untuk memudahkan 
                                        penyimpanan, carian, dan pengurusan dokumen pejabat.
                                    </p>
                                    <div class="mt-4">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-check-circle me-3"></i>
                                            <span>Carian pantas dan tepat</span>
                                        </div>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-check-circle me-3"></i>
                                            <span>Penjejakan lokasi fail</span>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-check-circle me-3"></i>
                                            <span>Sistem peminjaman terintegrasi</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Right Panel -->
                            <div class="col-lg-6 login-right">
                                <div class="text-center mb-4 d-lg-none">
                                    <i class="fas fa-archive fa-3x text-primary mb-3"></i>
                                    <h3 class="fw-bold text-primary">SPF Tongod</h3>
                                    <p class="text-muted">Sistem Penyimpanan Fail</p>
                                </div>
                                
                                <div class="text-center mb-4 d-none d-lg-block">
                                    <h3 class="fw-bold text-dark">Selamat Kembali!</h3>
                                    <p class="text-muted">Sila log masuk ke akaun anda</p>
                                </div>
                                
                                @if ($errors->any())
                                    <div class="alert alert-danger">
                                        <i class="fas fa-exclamation-circle me-2"></i>
                                        {{ $errors->first() }}
                                    </div>
                                @endif
                                
                                <form method="POST" action="{{ route('login') }}">
                                    @csrf
                                    
                                    <div class="form-floating mb-3">
                                        <input type="email" 
                                               class="form-control @error('email') is-invalid @enderror" 
                                               id="email" 
                                               name="email" 
                                               placeholder="name@example.com"
                                               value="{{ old('email') }}" 
                                               required autocomplete="email" autofocus>
                                        <label for="email">
                                            <i class="fas fa-envelope me-2"></i>Alamat Email
                                        </label>
                                    </div>
                                    
                                    <div class="form-floating mb-3">
                                        <input type="password" 
                                               class="form-control @error('password') is-invalid @enderror" 
                                               id="password" 
                                               name="password" 
                                               placeholder="Password"
                                               required autocomplete="current-password">
                                        <label for="password">
                                            <i class="fas fa-lock me-2"></i>Kata Laluan
                                        </label>
                                    </div>
                                    
                                    <div class="form-check mb-4">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               name="remember" 
                                               id="remember" 
                                               {{ old('remember') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="remember">
                                            Ingat saya
                                        </label>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-sign-in-alt me-2"></i>Log Masuk
                                        </button>
                                    </div>
                                </form>
                                
                                <div class="text-center mt-4">
                                    <small class="text-muted">
                                        © {{ date('Y') }} Pejabat Daerah Tongod. Semua hak terpelihara.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>