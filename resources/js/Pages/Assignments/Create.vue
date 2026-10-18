<script setup>
import { Head } from '@inertiajs/vue3';
import LegacyForm from '@/Components/LegacyForm.vue';
import markup from '@/legacy/assignment/markup.html?raw';
import { mount } from '@/legacy/assignment/script.js';
import '@/legacy/assignment/style.css';

defineProps({
    initialType: { type: String, default: 'Standard' },
});

const fileFields = [
    { from: 'images', field: 'images', kind: 'vehicle_image' },
    { from: 'pickupDocuments', field: 'pickup_documents', kind: 'pickup_document' },
];
</script>

<template>
    <Head title="Create Assignment" />
    <LegacyForm
        name="assignment"
        :markup="markup"
        :mount="mount"
        :initial-type="initialType"
        :submit-url="route('assignments.store')"
        :file-fields="fileFields"
        status-id="saveStatus"
        submit-button="#reviewBtn"
        :globals="['getAssignmentPayload', 'openAssignmentConfirmation', 'IVAutoAssignmentDraft']"
    />
</template>
