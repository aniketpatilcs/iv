<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

defineProps({
    banner: { type: String, required: true },
});

const user = computed(() => usePage().props.auth.user);
const year = new Date().getFullYear();

function signOut() {
    router.post(route('logout'));
}
</script>

<template>
    <div class="portal">
        <header class="portal-header">
            <component :is="user ? Link : 'span'" :href="user ? route('dashboard') : undefined" aria-label="IV Auto dashboard">
                <img class="portal-logo" src="/images/ivauto-logo.png" alt="IV Auto — Salvage Management Portal" width="234" height="56" />
            </component>
            <div v-if="user" class="portal-user">
                <span class="portal-welcome">
                    <small>Welcome,</small>
                    <strong>{{ user.name }}</strong>
                </span>
                <button type="button" class="portal-signout" @click="signOut">Sign Out</button>
            </div>
        </header>
        <div class="portal-banner">{{ banner }}</div>

        <main class="portal-main">
            <slot />
        </main>

        <footer class="portal-footer">
            <span>© {{ year }} IV Auto. All rights reserved.</span>
            <span>Authorized users only.</span>
        </footer>
    </div>
</template>

<style scoped>
.portal-user {
    display: flex;
    align-items: center;
    gap: 14px;
}

.portal-welcome {
    display: flex;
    flex-direction: column;
    line-height: 1.25;
    text-align: right;
}

.portal-welcome small {
    color: var(--muted);
    font-size: 11px;
}

.portal-welcome strong {
    color: #182b50;
    font:
        800 14px Manrope,
        Arial,
        sans-serif;
}

.portal-signout {
    height: 36px;
    padding: 0 14px;
    border: 1px solid #d8e1ed;
    border-radius: 7px;
    background: #fff;
    color: #456990;
    font-size: 12px;
    font-weight: 700;
}

.portal-signout:hover {
    background: var(--soft);
    color: var(--primary);
}

@media (max-width: 650px) {
    .portal-welcome {
        display: none;
    }
}
</style>
