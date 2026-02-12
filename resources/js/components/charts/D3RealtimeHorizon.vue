<script setup lang="ts">
import { useResizeObserver } from '@vueuse/core';
import { ref, onMounted, onUnmounted, watch } from 'vue';
import ChartTooltip from './ChartTooltip.vue';

export interface HorizonDatum {
    timestamp: string;
    duration_ms: number;
    has_error?: boolean;
}

const props = withDefaults(
    defineProps<{
        initialData: HorizonDatum[];
        label?: string;
    }>(),
    { label: 'Crawl Duration' },
);

const CAPACITY = 600;

const containerRef = ref<HTMLElement | null>(null);
const canvasRef = ref<HTMLCanvasElement | null>(null);
const tooltip = ref({ visible: false, x: 0, y: 0, timestamp: '', duration: '', hasError: false });

// Ring buffer
const values = new Float64Array(CAPACITY);
const timestamps: (Date | null)[] = new Array(CAPACITY).fill(null);
const errors: boolean[] = new Array(CAPACITY).fill(false);
let head = 0; // next write position
let count = 0;

let isDark = false;
let mutationObs: MutationObserver | null = null;
let echoChannel: ReturnType<typeof window.Echo.channel> | null = null;

function pushDatum(ts: Date, durationMs: number, hasError = false) {
    values[head] = durationMs;
    timestamps[head] = ts;
    errors[head] = hasError;
    head = (head + 1) % CAPACITY;
    if (count < CAPACITY) count++;
}

function getDatum(index: number): { ts: Date | null; value: number; hasError: boolean } {
    // index 0 = oldest, index count-1 = newest
    const pos = (head - count + index + CAPACITY) % CAPACITY;
    return { ts: timestamps[pos], value: values[pos], hasError: errors[pos] };
}

function resolveColor(cssVar: string): string {
    const val = getComputedStyle(document.documentElement).getPropertyValue(cssVar).trim();
    if (!val) return '#6366f1';
    const ctx = document.createElement('canvas').getContext('2d')!;
    ctx.fillStyle = val;
    return ctx.fillStyle;
}

function hexToRgb(hex: string): [number, number, number] {
    const n = parseInt(hex.slice(1), 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}

function draw() {
    const canvas = canvasRef.value;
    if (!canvas || count === 0) return;

    const dpr = window.devicePixelRatio || 1;
    const rect = canvas.parentElement!.getBoundingClientRect();
    const w = rect.width;
    const h = rect.height;

    canvas.width = w * dpr;
    canvas.height = h * dpr;
    canvas.style.width = `${w}px`;
    canvas.style.height = `${h}px`;

    const ctx = canvas.getContext('2d')!;
    ctx.scale(dpr, dpr);
    ctx.clearRect(0, 0, w, h);

    const baseHex = resolveColor('--chart-1');
    const [br, bg, bb] = hexToRgb(baseHex);
    const errorHex = resolveColor('--destructive');
    const [er, eg, eb] = hexToRgb(errorHex);

    // Compute max value for scaling
    let maxVal = 0;
    for (let i = 0; i < count; i++) {
        const v = getDatum(i).value;
        if (v > maxVal) maxVal = v;
    }
    if (maxVal === 0) maxVal = 1;

    const barWidth = Math.max(1, w / Math.max(count, 1));
    const margin = { bottom: 20, top: 4 };
    const chartH = h - margin.top - margin.bottom;

    // Draw bars — red for errors, normal color otherwise
    for (let i = 0; i < count; i++) {
        const d = getDatum(i);
        const barH = (d.value / maxVal) * chartH;
        if (barH <= 0) continue;

        ctx.fillStyle = d.hasError
            ? `rgba(${er}, ${eg}, ${eb}, 0.85)`
            : `rgba(${br}, ${bg}, ${bb}, 0.75)`;
        const x = i * barWidth;
        const y = margin.top + chartH - barH;
        ctx.fillRect(x, y, Math.max(barWidth - 0.5, 1), barH);
    }

    // Time labels along bottom — measure text width to avoid overlap
    const textColor = isDark ? 'rgba(255,255,255,0.5)' : 'rgba(0,0,0,0.4)';
    ctx.fillStyle = textColor;
    ctx.font = '10px system-ui, sans-serif';
    ctx.textAlign = 'center';

    const sampleWidth = ctx.measureText('00:00 AM').width + 16;
    const maxLabels = Math.max(1, Math.floor(w / sampleWidth));
    const labelInterval = Math.max(1, Math.ceil(count / maxLabels));
    for (let i = 0; i < count; i += labelInterval) {
        const d = getDatum(i);
        if (!d.ts) continue;
        const x = i * barWidth + barWidth / 2;
        const label = d.ts.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        ctx.fillText(label, x, h - 4);
    }

    // Top-left label
    if (props.label) {
        const labelColor = isDark ? 'rgba(255,255,255,0.6)' : 'rgba(0,0,0,0.5)';
        ctx.fillStyle = labelColor;
        ctx.font = '600 11px system-ui, sans-serif';
        ctx.textAlign = 'left';
        ctx.fillText(props.label, 6, margin.top + 14);
    }
}

function handleMouseMove(event: MouseEvent) {
    const canvas = canvasRef.value;
    if (!canvas || count === 0) return;

    const rect = canvas.getBoundingClientRect();
    const mx = event.clientX - rect.left;
    const barWidth = rect.width / Math.max(count, 1);
    const idx = Math.floor(mx / barWidth);

    if (idx < 0 || idx >= count) {
        tooltip.value.visible = false;
        return;
    }

    const d = getDatum(idx);
    if (!d.ts) {
        tooltip.value.visible = false;
        return;
    }

    tooltip.value = {
        visible: true,
        x: event.clientX + 12,
        y: event.clientY - 10,
        timestamp: d.ts.toLocaleString([], {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        }),
        duration: (d.value / 1000).toFixed(1) + 's',
        hasError: d.hasError,
    };
}

function handleMouseLeave() {
    tooltip.value.visible = false;
}

// Initialize from props
function initFromProps() {
    head = 0;
    count = 0;
    for (const d of props.initialData) {
        pushDatum(new Date(d.timestamp), d.duration_ms, d.has_error ?? false);
    }
    draw();
}

useResizeObserver(containerRef, draw);

onMounted(() => {
    isDark = document.documentElement.classList.contains('dark');

    mutationObs = new MutationObserver(() => {
        const dark = document.documentElement.classList.contains('dark');
        if (dark !== isDark) {
            isDark = dark;
            draw();
        }
    });
    mutationObs.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

    initFromProps();

    // Subscribe to real-time crawl events
    echoChannel = window.Echo.channel('crawl-activity');
    echoChannel.listen('.CrawlCompleted', (e: { crawl_duration_ms?: number; has_error?: boolean }) => {
        if (e.crawl_duration_ms != null) {
            pushDatum(new Date(), e.crawl_duration_ms, e.has_error ?? false);
            requestAnimationFrame(draw);
        }
    });
});

onUnmounted(() => {
    mutationObs?.disconnect();
    // Don't call Echo.leave() — the parent page manages the channel lifecycle
});

watch(() => props.initialData, initFromProps, { deep: true });
</script>

<template>
    <div ref="containerRef" class="relative h-full w-full">
        <canvas
            ref="canvasRef"
            class="h-full w-full"
            style="cursor: crosshair"
            @mousemove="handleMouseMove"
            @mouseleave="handleMouseLeave"
        />
        <Teleport to="body">
            <ChartTooltip :visible="tooltip.visible" :x="tooltip.x" :y="tooltip.y">
                <div class="flex flex-col gap-0.5">
                    <span class="text-xs text-muted-foreground">{{ tooltip.timestamp }}</span>
                    <span class="font-medium tabular-nums">{{ tooltip.duration }}</span>
                    <span v-if="tooltip.hasError" class="text-xs font-medium text-destructive">Error</span>
                </div>
            </ChartTooltip>
        </Teleport>
    </div>
</template>
