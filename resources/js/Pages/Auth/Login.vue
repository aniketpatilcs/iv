<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import PortalLayout from '@/Layouts/PortalLayout.vue';

const REMEMBERED_USERNAME = 'ivauto.username';

const form = useForm({
    username: '',
    password: '',
    remember: false,
});

const touched = ref({ username: false, password: false });
const showPassword = ref(false);
const notice = ref('');
const usernameInput = ref(null);
const passwordInput = ref(null);

const missing = computed(() => ({
    username: touched.value.username && !form.username.trim(),
    password: touched.value.password && !form.password,
}));

// Server errors (bad credentials, lockout) are reported against `username`.
const alertText = computed(() => form.errors.username || notice.value);

onMounted(() => {
    // Per-browser convenience only; the password is never stored.
    try {
        const saved = localStorage.getItem(REMEMBERED_USERNAME);
        if (saved) {
            form.username = saved;
            form.remember = true;
        }
    } catch {}
    (form.username ? passwordInput : usernameInput).value?.focus();
});

function onInput(field) {
    touched.value[field] = true;
    notice.value = '';
    form.clearErrors();
}

function rememberUsername() {
    try {
        if (form.remember) localStorage.setItem(REMEMBERED_USERNAME, form.username.trim());
        else localStorage.removeItem(REMEMBERED_USERNAME);
    } catch {}
}

function submit() {
    notice.value = '';
    touched.value = { username: true, password: true };
    if (missing.value.username || missing.value.password) {
        (missing.value.username ? usernameInput : passwordInput).value?.focus();
        return;
    }
    rememberUsername();
    form.transform((data) => ({ ...data, username: data.username.trim() })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
}

function forgotPassword() {
    notice.value = 'Please contact your IV Auto account representative to reset your password.';
}
</script>

<template>
    <Head title="Sign In" />
    <PortalLayout banner="Sign In">
        <div class="login-wrap">
            <section class="card" aria-labelledby="loginTitle">
                <div class="eyebrow">Salvage Management Portal</div>
                <h1 id="loginTitle">Welcome back</h1>
                <p class="lede">Sign in with your IV Auto account to manage assignments, transports, and bids.</p>

                <div v-if="alertText" class="alert" role="alert">
                    <span aria-hidden="true">⚠</span>
                    <span>{{ alertText }}</span>
                </div>

                <form novalidate @submit.prevent="submit">
                    <div class="field">
                        <label for="username">
                            Username
                            <span class="req">*</span>
                        </label>
                        <input
                            id="username"
                            ref="usernameInput"
                            v-model="form.username"
                            name="username"
                            type="text"
                            autocomplete="username"
                            autocapitalize="none"
                            spellcheck="false"
                            maxlength="60"
                            placeholder="Enter your username"
                            required
                            :class="{ invalid: missing.username }"
                            :aria-invalid="missing.username"
                            aria-describedby="usernameError"
                            @input="onInput('username')"
                        />
                        <span v-if="missing.username" id="usernameError" class="error">Enter your username.</span>
                    </div>

                    <div class="field">
                        <label for="password">
                            Password
                            <span class="req">*</span>
                        </label>
                        <div class="control has-toggle">
                            <input
                                id="password"
                                ref="passwordInput"
                                v-model="form.password"
                                name="password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="current-password"
                                placeholder="Enter your password"
                                required
                                :class="{ invalid: missing.password }"
                                :aria-invalid="missing.password"
                                aria-describedby="passwordError"
                                @input="onInput('password')"
                            />
                            <button
                                type="button"
                                class="toggle"
                                aria-controls="password"
                                :aria-pressed="showPassword"
                                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                @click="
                                    showPassword = !showPassword;
                                    passwordInput?.focus();
                                "
                            >
                                {{ showPassword ? 'Hide' : 'Show' }}
                            </button>
                        </div>
                        <span v-if="missing.password" id="passwordError" class="error">Enter your password.</span>
                    </div>

                    <div class="row">
                        <label class="remember">
                            <input v-model="form.remember" type="checkbox" name="remember" />
                            Remember me
                        </label>
                        <a class="link" href="#" @click.prevent="forgotPassword">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn-primary submit" :class="{ loading: form.processing }" :disabled="form.processing">
                        <span class="spinner" aria-hidden="true"></span>
                        <span>{{ form.processing ? 'Signing in…' : 'Sign In' }}</span>
                    </button>
                </form>

                <div class="helpbox">
                    <strong>Need access?</strong>
                    Contact your IV Auto account representative to request portal credentials.
                </div>
            </section>
        </div>
    </PortalLayout>
</template>

<style scoped>
.login-wrap {
    display: grid;
    place-items: center;
    padding: 48px 16px 64px;
}

.card {
    width: 100%;
    max-width: 440px;
    background: #fff;
    border: 1px solid #e0e7f0;
    border-radius: 16px;
    box-shadow: 0 7px 28px #1c35550f;
    padding: 34px 36px 32px;
}

.eyebrow {
    text-transform: uppercase;
    letter-spacing: 0.15em;
    color: var(--orange);
    font:
        800 11px Manrope,
        Arial,
        sans-serif;
}

.card h1 {
    font:
        800 26px Manrope,
        Arial,
        sans-serif;
    letter-spacing: -0.03em;
    color: #182b50;
    margin: 8px 0 6px;
}

.lede {
    margin: 0 0 26px;
    color: var(--muted);
    font-size: 14px;
    line-height: 1.55;
}

.alert {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin: 0 0 20px;
    padding: 12px 14px;
    border: 1px solid #f0c9cd;
    background: #fff6f7;
    color: var(--danger);
    border-radius: 8px;
    font-size: 13px;
    line-height: 1.45;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 20px;
}

.field label {
    color: #2b405e;
    font-size: 13px;
    line-height: 1.45;
    font-weight: 600;
}

.req {
    color: #d97924;
}

.control {
    position: relative;
}

.field input {
    height: 46px;
    width: 100%;
    border: 1px solid #d8e1ed;
    background: #fff;
    border-radius: 7px;
    padding: 0 14px;
    color: #233652;
    font-size: 14px;
    outline: 0;
    transition:
        border-color 0.16s,
        box-shadow 0.16s;
}

.field input::placeholder {
    color: #8996a8;
    opacity: 1;
}

.field input:focus {
    border-color: var(--focus);
    box-shadow: 0 0 0 3px #608bbd20;
}

.field input.invalid {
    border-color: #d4707a;
    box-shadow: 0 0 0 3px #d4707a1a;
}

.field .error {
    font-size: 12px;
    color: var(--danger);
}

.has-toggle input {
    padding-right: 70px;
}

.toggle {
    position: absolute;
    right: 6px;
    top: 50%;
    transform: translateY(-50%);
    height: 34px;
    padding: 0 11px;
    border: 0;
    border-radius: 6px;
    background: none;
    color: #456990;
    font-size: 12px;
    font-weight: 700;
}

.toggle:hover {
    background: var(--soft);
    color: var(--primary);
}

.row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin: -4px 0 24px;
    font-size: 13px;
}

.remember {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #50617b;
    font-weight: 500;
}

input[type='checkbox'] {
    accent-color: #1b4ca4;
    width: 16px;
    height: 16px;
    margin: 0;
}

.link {
    color: #456990;
    font-weight: 600;
    text-decoration: none;
}

.link:hover {
    color: var(--primary);
    text-decoration: underline;
    text-underline-offset: 3px;
}

.submit {
    width: 100%;
}

.spinner {
    width: 15px;
    height: 15px;
    border: 2px solid #ffffff55;
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
    display: none;
}

.loading .spinner {
    display: block;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.helpbox {
    margin-top: 24px;
    background: #eaf0f8;
    border: 1px solid #dbe5f2;
    border-radius: 12px;
    padding: 14px 16px;
    font-size: 12px;
    line-height: 1.55;
    color: #6b7b96;
}

.helpbox strong {
    display: block;
    font:
        800 12px Manrope,
        Arial,
        sans-serif;
    color: #2e4266;
    margin-bottom: 3px;
}

@media (max-width: 650px) {
    .login-wrap {
        place-items: start center;
        padding: 28px 16px 40px;
    }

    .card {
        padding: 26px 22px 24px;
        border-radius: 14px;
    }

    .card h1 {
        font-size: 23px;
    }

    .field input {
        font-size: 16px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .field input {
        transition: none;
    }

    .spinner {
        animation: none;
    }
}
</style>
