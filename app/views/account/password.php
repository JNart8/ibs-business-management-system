<?php include APP_PATH . '/views/layout/header.php'; ?>

<div class="max-w-md mx-auto">
    <div class="mb-5 text-sm">
        <a href="<?= BASE_URL ?>/account" class="text-blue-600 hover:underline">← Back to My Account</a>
    </div>

    <h1 class="text-2xl font-bold text-gray-800 mb-6">Change Password</h1>

    <?= flashMessage() ?>

    <form method="POST" action="<?= BASE_URL ?>/account/password"
        class="bg-white rounded-lg shadow p-6 space-y-5"
        x-data="{ showCurrent: false, showPw: false, showPw2: false, pw: '', pw2: '' }">
        <?= csrfField() ?>

        <!-- Current password -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Current Password <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <input :type="showCurrent ? 'text' : 'password'"
                    name="current_password"
                    placeholder="Your current password"
                    required autocomplete="current-password"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm pr-10
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <button type="button" @click="showCurrent = !showCurrent"
                    class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
                    <span x-text="showCurrent ? '🙈' : '👁'"></span>
                </button>
            </div>
        </div>

        <div class="border-t border-gray-100"></div>

        <!-- New password -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                New Password <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <input :type="showPw ? 'text' : 'password'"
                    name="password" x-model="pw"
                    placeholder="Min <?= PASSWORD_MIN_LENGTH ?> characters"
                    required autocomplete="new-password"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm pr-10
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <button type="button" @click="showPw = !showPw"
                    class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
                    <span x-text="showPw ? '🙈' : '👁'"></span>
                </button>
            </div>
            <!-- Strength bar -->
            <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                <div class="h-full rounded-full transition-all duration-300"
                    :class="{
                         'w-0':                 pw.length === 0,
                         'w-1/4 bg-red-400':    pw.length >= 1 && pw.length < 6,
                         'w-2/4 bg-yellow-400': pw.length >= 6 && pw.length < 10,
                         'w-3/4 bg-blue-400':   pw.length >= 10 && pw.length < 14,
                         'w-full bg-green-500': pw.length >= 14
                     }">
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-1"
                x-text="pw.length === 0 ? '' :
                        pw.length < 6  ? 'Too short' :
                        pw.length < 10 ? 'Weak' :
                        pw.length < 14 ? 'Good' : 'Strong'">
            </p>
        </div>

        <!-- Confirm new password -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Confirm New Password <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <input :type="showPw2 ? 'text' : 'password'"
                    name="password_confirm" x-model="pw2"
                    placeholder="Repeat new password"
                    required autocomplete="new-password"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm pr-10
                              focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    :class="pw2.length > 0 && pw !== pw2 ? 'border-red-400' : ''">
                <button type="button" @click="showPw2 = !showPw2"
                    class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
                    <span x-text="showPw2 ? '🙈' : '👁'"></span>
                </button>
            </div>
            <p x-show="pw2.length > 0 && pw !== pw2"
                class="text-xs text-red-500 mt-1">
                Passwords do not match
            </p>
        </div>

        <div class="bg-blue-50 border border-blue-100 rounded-lg px-4 py-3 text-xs text-blue-700">
            ℹ️ You will be logged out and asked to sign in with your new password.
        </div>

        <!-- Actions -->
        <div class="flex justify-between pt-2">
            <a href="<?= BASE_URL ?>/account"
                class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition text-sm">
                Cancel
            </a>
            <button type="submit"
                :disabled="pw !== pw2 || pw.length < <?= PASSWORD_MIN_LENGTH ?>"
                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300
                           disabled:cursor-not-allowed text-white rounded-lg shadow transition text-sm font-semibold">
                Update Password
            </button>
        </div>
    </form>
</div>

<?php include APP_PATH . '/views/layout/footer.php'; ?>