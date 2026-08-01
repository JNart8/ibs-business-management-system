<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= APP_NAME ?></title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/ibs_logo_ntg.png">

    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
    <script defer src="<?= BASE_URL ?>/assets/js/alpine.min.js"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">

    <style>
        :root {
            --blue-deep: #1e3a5f;
            --blue-mid: #2563eb;
            --blue-light: #3b82f6;
            --accent: #f59e0b;
        }

        body {
            font-family: 'DM Sans', sans-serif;
        }

        brand-font {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .panel-bg {
            background-color: var(--blue-deep);
            background-image:
                radial-gradient(circle at 20% 20%, rgba(37, 99, 235, 0.45) 0%, transparent 55%),
                radial-gradient(circle at 80% 80%, rgba(59, 130, 246, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 60% 10%, rgba(245, 158, 11, 0.12) 0%, transparent 40%);
        }

        .grid-overlay {
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
        }

        .shape {
            position: absolute;
            border-radius: 50%;
            opacity: 0.08;
            background: white;
        }

        .field-input {
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .field-input:focus {
            border-color: var(--blue-mid);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
            outline: none;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(18px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-up {
            animation: fadeUp 0.5s ease both;
        }

        .fade-up-1 {
            animation-delay: 0.05s;
        }

        .fade-up-2 {
            animation-delay: 0.12s;
        }

        .fade-up-3 {
            animation-delay: 0.19s;
        }

        .fade-up-4 {
            animation-delay: 0.26s;
        }

        .fade-up-5 {
            animation-delay: 0.33s;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .spinner {
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255, 255, 255, 0.35);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }

        .btn-login {
            background: linear-gradient(135deg, var(--blue-mid) 0%, #1d4ed8 100%);
            transition: transform 0.15s, box-shadow 0.15s, filter 0.15s;
        }

        .btn-login:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
            filter: brightness(1.05);
        }

        .btn-login:active:not(:disabled) {
            transform: translateY(0);
        }

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(4px);
            border-radius: 12px;
            padding: 14px 18px;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen flex">

    <div class="flex w-full min-h-screen" x-data="loginForm()">

        <!-- ════════ LEFT PANEL — Branding ════════ -->
        <div class="hidden lg:flex lg:w-5/12 xl:w-1/2 panel-bg grid-overlay relative overflow-hidden flex-col justify-between p-10 xl:p-14">

            <div class="shape w-64 h-64 -top-16 -left-16"></div>
            <div class="shape w-96 h-96 -bottom-24 -right-24"></div>
            <div class="shape w-32 h-32 top-1/2 left-1/4" style="opacity:0.05"></div>

            <!-- Top: Logo -->
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <img src="<?= BASE_URL ?>/assets/images/ibs_logo_ntg.png"
                        alt="<?= APP_NAME ?>"
                        class="h-8 w-6 object-contain">
                    <span class="brand-font text-white text-xl font-bold tracking-tight"><?= APP_NAME ?></span>
                </div>
                <p class="text-blue-200 text-sm font-light ml-[60px]">Manage your business efficiently</p>
            </div>

            <!-- Middle: Headline -->
            <div class="relative z-10">
                <h1 class="brand-font text-white text-4xl xl:text-5xl font-extrabold leading-tight mb-4">
                    Everything<br>in its<br>
                    <span style="color:var(--accent);">right place.</span>
                </h1>
                <p class="text-blue-200 text-base font-light max-w-xs leading-relaxed">
                    Track stock, record sales, and manage customers — all from one fast, reliable system.
                </p>
                <div class="grid grid-cols-3 gap-3 mt-10">
                    <div class="stat-card">
                        <div class="text-white text-xs font-light mb-1 opacity-70">Products</div>
                        <div class="text-white brand-font text-xl font-bold">📦</div>
                    </div>
                    <div class="stat-card">
                        <div class="text-white text-xs font-light mb-1 opacity-70">Sales</div>
                        <div class="text-white brand-font text-xl font-bold">🛒</div>
                    </div>
                    <div class="stat-card">
                        <div class="text-white text-xs font-light mb-1 opacity-70">Customers</div>
                        <div class="text-white brand-font text-xl font-bold">👥</div>
                    </div>
                </div>
            </div>

            <!-- Bottom: Version -->
            <div class="text-blue-300 text-xs opacity-60">
                Version <?= APP_VERSION ?> &nbsp;·&nbsp; <?= date('Y') ?> <?= APP_NAME ?>
            </div>
        </div>

        <!-- ════════ RIGHT PANEL — Login Form ════════ -->
        <div class="flex-1 flex items-center justify-center p-6 sm:p-10">
            <div class="w-full max-w-md">

                <!-- Mobile logo -->
                <div class="lg:hidden flex items-center gap-3 mb-8 fade-up">
                    <img src="<?= BASE_URL ?>/assets/images/ibs_logo_ntg.png"
                        alt="<?= APP_NAME ?>"
                        class="h-10 w-auto">
                    <span class="brand-font text-gray-800 text-xl font-bold"><?= APP_NAME ?></span>
                </div>

                <!-- Header -->
                <div class="mb-8 fade-up fade-up-1">
                    <h2 class="brand-font text-gray-900 text-3xl font-extrabold">Welcome back</h2>
                    <p class="text-gray-500 text-sm mt-1.5 font-light">Sign in to your account to continue</p>
                </div>

                <?= flashMessage() ?>

                <form method="POST" action="<?= BASE_URL ?>/login" @submit.prevent="submit" novalidate>
                    <?= csrfField() ?>

                    <!-- Username -->
                    <div class="mb-5 fade-up fade-up-2">
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-1.5">Username</label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm select-none">👤</span>
                            <input type="text" id="username" name="username"
                                value="<?= e(old('username', '')) ?>"
                                placeholder="Enter your username"
                                required autocomplete="username" x-ref="username"
                                class="field-input w-full pl-10 pr-4 py-3 bg-white border border-gray-200
                                      rounded-xl text-gray-900 text-sm placeholder-gray-400">
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="mb-2 fade-up fade-up-3">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm select-none">🔒</span>
                            <input :type="showPassword ? 'text' : 'password'"
                                id="password" name="password"
                                placeholder="Enter your password"
                                required autocomplete="current-password"
                                class="field-input w-full pl-10 pr-12 py-3 bg-white border border-gray-200
                                      rounded-xl text-gray-900 text-sm placeholder-gray-400">
                            <button type="button" @click="showPassword = !showPassword" tabindex="-1"
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400
                                       hover:text-gray-600 transition-colors text-sm select-none">
                                <span x-text="showPassword ? '🙈' : '👁'"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Caps Lock warning -->
                    <p x-show="capsLock" x-cloak
                        class="text-amber-600 text-xs mt-1.5 mb-0 flex items-center gap-1">
                        ⚠️ Caps Lock is on
                    </p>

                    <!-- Submit -->
                    <div class="mt-7 fade-up fade-up-4">
                        <button type="submit" :disabled="loading"
                            class="btn-login w-full py-3.5 rounded-xl text-white font-semibold
                                   text-sm tracking-wide flex items-center justify-center gap-2.5">
                            <span x-show="loading" class="spinner"></span>
                            <span x-text="loading ? 'Signing in…' : 'Sign In'"></span>
                        </button>
                    </div>
                </form>

                <p class="text-center text-gray-400 text-xs mt-8 fade-up fade-up-5">
                    Having trouble? Contact your system administrator.
                </p>

            </div>
        </div>

    </div>

    <script>
        function loginForm() {
            return {
                loading: false,
                showPassword: false,
                capsLock: false,

                init() {
                    this.$nextTick(() => {
                        if (this.$refs.username) this.$refs.username.focus();
                    });
                    window.addEventListener('keydown', (e) => {
                        this.capsLock = e.getModifierState?.('CapsLock') ?? false;
                    });
                    window.addEventListener('keyup', (e) => {
                        this.capsLock = e.getModifierState?.('CapsLock') ?? false;
                    });
                },

                submit(e) {
                    this.loading = true;
                    e.target.submit();
                },
            };
        }
    </script>

</body>

</html>