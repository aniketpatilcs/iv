<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';

defineProps({
    stats: { type: Object, required: true },
    recentAssignments: { type: Array, required: true },
    recentBidRequests: { type: Array, required: true },
});

const dateFormat = new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
const formatDate = (value) => dateFormat.format(new Date(value));
const label = (value) => value.charAt(0).toUpperCase() + value.slice(1);
</script>

<template>
    <Head title="Dashboard" />
    <PortalLayout banner="Dashboard">
        <div class="dashboard">
            <div class="heading">
                <div class="eyebrow">Salvage Management Portal</div>
                <h1>What would you like to do?</h1>
            </div>

            <div class="options">
                <Link :href="route('assignments.create')" class="option">
                    <span class="option-icon" aria-hidden="true">
                        <svg
                            width="26"
                            height="26"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M5 17h14l-1.5-6.5A2 2 0 0 0 15.6 9H8.4a2 2 0 0 0-1.9 1.5Z" />
                            <circle cx="7.5" cy="17.5" r="1.5" />
                            <circle cx="16.5" cy="17.5" r="1.5" />
                            <path d="M3 13h2m14 0h2" />
                        </svg>
                    </span>
                    <span class="option-copy">
                        <strong>Assignment</strong>
                        <small>Assign a vehicle for salvage pickup, or request Transport Only service between two locations.</small>
                    </span>
                    <span class="option-count">{{ stats.assignments }} submitted</span>
                    <span class="option-go" aria-hidden="true">→</span>
                </Link>

                <Link :href="route('bid-requests.create')" class="option">
                    <span class="option-icon" aria-hidden="true">
                        <svg
                            width="26"
                            height="26"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z" />
                            <path d="M14 3v5h5M9 13h6M9 17h4" />
                        </svg>
                    </span>
                    <span class="option-copy">
                        <strong>Bid Request</strong>
                        <small>Request a salvage bid with vehicle details, valuation, images, and supporting documents.</small>
                    </span>
                    <span class="option-count">{{ stats.bidRequests }} submitted</span>
                    <span class="option-go" aria-hidden="true">→</span>
                </Link>
            </div>

            <div class="recent">
                <section class="panel" aria-labelledby="recentAssignmentsTitle">
                    <h2 id="recentAssignmentsTitle">Recent Assignments</h2>
                    <table v-if="recentAssignments.length">
                        <thead>
                            <tr>
                                <th>Stock #</th>
                                <th>Claim #</th>
                                <th>Vehicle</th>
                                <th>Type</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in recentAssignments" :key="item.id">
                                <td class="ref">{{ item.stock_number }}</td>
                                <td>{{ item.claim_number }}</td>
                                <td>{{ item.vehicle_description || '—' }}</td>
                                <td>{{ item.type === 'transport' ? 'Transport Only' : 'Standard' }}</td>
                                <td>{{ formatDate(item.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="empty">No assignments yet.</p>
                </section>

                <section class="panel" aria-labelledby="recentBidsTitle">
                    <h2 id="recentBidsTitle">Recent Bid Requests</h2>
                    <table v-if="recentBidRequests.length">
                        <thead>
                            <tr>
                                <th>Bid #</th>
                                <th>Claim #</th>
                                <th>Vehicle</th>
                                <th>Status</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in recentBidRequests" :key="item.id">
                                <td class="ref">{{ item.bid_number }}</td>
                                <td>{{ item.claim_number }}</td>
                                <td>{{ item.vehicle_description || '—' }}</td>
                                <td>{{ label(item.status) }}</td>
                                <td>{{ formatDate(item.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="empty">No bid requests yet.</p>
                </section>
            </div>
        </div>
    </PortalLayout>
</template>

<style scoped>
.dashboard {
    max-width: 1120px;
    margin: 0 auto;
    padding: 40px 27px 64px;
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

h1 {
    font:
        800 26px Manrope,
        Arial,
        sans-serif;
    letter-spacing: -0.03em;
    color: #182b50;
    margin: 8px 0 24px;
}

.options {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
}

.option {
    display: grid;
    grid-template-columns: auto 1fr auto;
    grid-template-areas:
        'icon copy go'
        'icon count go';
    align-items: center;
    gap: 6px 18px;
    padding: 26px 26px 24px;
    background: #fff;
    border: 1px solid #e0e7f0;
    border-radius: 16px;
    box-shadow: 0 7px 28px #1c35550f;
    color: inherit;
    text-decoration: none;
    transition:
        border-color 0.16s,
        box-shadow 0.16s,
        transform 0.16s;
}

.option:hover {
    border-color: var(--focus);
    box-shadow: 0 10px 32px #1c35551c;
    transform: translateY(-1px);
}

.option-icon {
    grid-area: icon;
    display: grid;
    place-items: center;
    width: 54px;
    height: 54px;
    border-radius: 14px;
    background: var(--soft);
    color: var(--primary);
}

.option-copy {
    grid-area: copy;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.option-copy strong {
    font:
        800 19px Manrope,
        Arial,
        sans-serif;
    color: #182b50;
}

.option-copy small {
    color: var(--muted);
    font-size: 13px;
    line-height: 1.5;
}

.option-count {
    grid-area: count;
    color: #456990;
    font-size: 12px;
    font-weight: 700;
}

.option-go {
    grid-area: go;
    color: var(--primary);
    font-size: 22px;
    font-weight: 700;
}

.recent {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
    margin-top: 28px;
}

.panel {
    background: #fff;
    border: 1px solid #e0e7f0;
    border-radius: 16px;
    padding: 20px 22px;
    overflow-x: auto;
}

.panel h2 {
    margin: 0 0 12px;
    font:
        800 15px Manrope,
        Arial,
        sans-serif;
    color: #182b50;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

th {
    text-align: left;
    color: var(--muted);
    font-weight: 600;
    padding: 6px 8px;
    border-bottom: 1px solid var(--line);
    white-space: nowrap;
}

td {
    padding: 9px 8px;
    border-bottom: 1px solid #eef2f6;
    color: #2b405e;
}

.ref {
    font-weight: 700;
    color: var(--primary);
    white-space: nowrap;
}

.empty {
    margin: 0;
    color: var(--muted);
    font-size: 13px;
}

@media (max-width: 860px) {
    .options,
    .recent {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 650px) {
    .dashboard {
        padding: 28px 16px 40px;
    }

    h1 {
        font-size: 23px;
    }

    .option {
        padding: 20px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .option {
        transition: none;
    }
}
</style>
