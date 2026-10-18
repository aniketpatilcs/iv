<script setup>
import { Head } from '@inertiajs/vue3';
import LegacyForm from '@/Components/LegacyForm.vue';
import markup from '@/legacy/bid/markup.html?raw';
import { mount } from '@/legacy/bid/script.js';
import '@/legacy/bid/style.css';

const fileFields = [
    { from: 'images', field: 'images', kind: 'vehicle_image' },
    { from: 'documents', field: 'documents', kind: 'bid_document' },
];
</script>

<template>
    <Head title="Bid Request" />
    <LegacyForm
        name="bid"
        :markup="markup"
        :mount="mount"
        :submit-url="route('bid-requests.store')"
        :file-fields="fileFields"
        status-id="submitResult"
        submit-button="#bidForm button[type=submit]"
        :globals="['openBidConfirmation']"
    />
</template>
