<script setup lang="ts">
import { computed } from 'vue';
import { t } from '@/lib/i18n';
import type { LoginSeriesPoint } from '@/types';

/**
 * Dependency-free SVG chart (fast first paint, tiny bundle — spec section 11).
 * Two series: successful logins (area + line) and failed attempts (line).
 * RTL chart: time flows right → left so the newest day is on the right side
 * of the Persian reading direction... actually Persian charts commonly read
 * left → right for time; we keep the newest day at the LEFT edge.
 */
const props = defineProps<{
    series: LoginSeriesPoint[];
}>();

const width = 560;
const height = 180;
const padding = { top: 14, right: 10, bottom: 24, left: 10 };

const points = computed(() => props.series ?? []);

const maxValue = computed(() =>
    Math.max(1, ...points.value.flatMap((p) => [p.logins, p.failed])),
);

// 14 days → up to 14 x-slots; fewer days still spread across full width.
const xFor = (index: number, total: number): number => {
    const inner = width - padding.left - padding.right;
    const step = total > 1 ? inner / (total - 1) : inner / 2;
    // newest (last item of the series) maps to x = padding.left (left edge)
    return padding.left + (total - 1 - index) * step;
};

const yFor = (value: number): number => {
    const inner = height - padding.top - padding.bottom;
    return padding.top + inner - (value / maxValue.value) * inner;
};

const pathFor = (key: 'logins' | 'failed'): string =>
    points.value
        .map(
            (p, i) =>
                `${i === 0 ? 'M' : 'L'}${xFor(i, points.value.length).toFixed(1)},${yFor(p[key]).toFixed(1)}`,
        )
        .join(' ');

const areaPath = computed(() => {
    const pts = points.value;
    if (pts.length === 0) {
        return '';
    }
    const baseline = height - padding.bottom;
    const line = pathFor('logins');
    const firstX = xFor(0, pts.length).toFixed(1);
    const lastX = xFor(pts.length - 1, pts.length).toFixed(1);

    return `${line} L${lastX},${baseline} L${firstX},${baseline} Z`;
});

const xLabels = computed(() => {
    const pts = points.value;
    const step = Math.max(1, Math.ceil(pts.length / 6));

    return pts
        .filter((_, i) => i % step === 0)
        .map((p, i) => ({
            x: xFor(i * step, pts.length),
            label: p.label,
        }));
});
</script>

<template>
    <div class="w-full overflow-hidden">
        <svg
            :viewBox="`0 0 ${width} ${height}`"
            class="h-44 w-full"
            role="img"
            :aria-label="t('dashboard.loginChart')"
            preserveAspectRatio="none"
        >
            <defs>
                <linearGradient id="loginArea" x1="0" y1="0" x2="0" y2="1">
                    <stop
                        offset="0%"
                        stop-color="currentColor"
                        stop-opacity="0.18"
                    />
                    <stop
                        offset="100%"
                        stop-color="currentColor"
                        stop-opacity="0"
                    />
                </linearGradient>
            </defs>

            <!-- horizontal guide lines -->
            <line
                v-for="i in 3"
                :key="`g${i}`"
                :x1="padding.left"
                :x2="width - padding.right"
                :y1="padding.top + ((height - padding.top - padding.bottom) / 3) * i"
                :y2="padding.top + ((height - padding.top - padding.bottom) / 3) * i"
                class="stroke-border"
                stroke-dasharray="2 4"
            />

            <path v-if="areaPath" :d="areaPath" fill="url(#loginArea)" />
            <path
                :d="pathFor('logins')"
                fill="none"
                class="stroke-foreground"
                stroke-width="2"
                stroke-linejoin="round"
                stroke-linecap="round"
            />
            <path
                :d="pathFor('failed')"
                fill="none"
                class="stroke-destructive"
                stroke-width="1.5"
                stroke-dasharray="4 3"
                stroke-linejoin="round"
                stroke-linecap="round"
            />

            <circle
                v-for="(p, i) in points"
                :key="`p${i}`"
                :cx="xFor(i, points.length)"
                :cy="yFor(p.logins)"
                r="2.5"
                class="fill-foreground"
            />

            <text
                v-for="(l, i) in xLabels"
                :key="`l${i}`"
                :x="l.x"
                :y="height - 6"
                text-anchor="middle"
                class="fill-muted-foreground"
                style="font-size: 10px"
            >
                {{ l.label }}
            </text>
        </svg>

        <div
            class="mt-2 flex items-center justify-center gap-5 text-xs text-muted-foreground"
        >
            <span class="flex items-center gap-1.5">
                <span class="h-0.5 w-4 rounded-full bg-foreground" />
                {{ t('dashboard.chart.logins') }}
            </span>
            <span class="flex items-center gap-1.5">
                <span
                    class="h-0.5 w-4 rounded-full bg-destructive"
                    style="
                        background-image: linear-gradient(
                            to right,
                            currentColor 50%,
                            transparent 50%
                        );
                        background-size: 6px 100%;
                    "
                />
                {{ t('dashboard.chart.failed') }}
            </span>
        </div>
    </div>
</template>
