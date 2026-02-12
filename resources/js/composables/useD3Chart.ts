import { useResizeObserver } from '@vueuse/core';
import * as d3 from 'd3';
import { ref, onMounted, onBeforeUnmount, type Ref } from 'vue';

export interface ChartMargin {
    top: number;
    right: number;
    bottom: number;
    left: number;
}

const defaultMargin: ChartMargin = { top: 20, right: 20, bottom: 30, left: 40 };

export function useD3Chart(
    containerRef: Ref<HTMLElement | null>,
    margin: ChartMargin = defaultMargin,
) {
    const svgRef = ref<SVGSVGElement | null>(null);
    const width = ref(0);
    const height = ref(0);
    const innerWidth = ref(0);
    const innerHeight = ref(0);
    const isDark = ref(false);
    const drawCount = ref(0);

    let resizeCallback: (() => void) | null = null;
    let mutationObserver: MutationObserver | null = null;

    function updateDimensions() {
        if (!containerRef.value) return;
        const rect = containerRef.value.getBoundingClientRect();
        width.value = rect.width;
        height.value = rect.height;
        innerWidth.value = Math.max(0, rect.width - margin.left - margin.right);
        innerHeight.value = Math.max(0, rect.height - margin.top - margin.bottom);
    }

    function createSvg(): d3.Selection<SVGSVGElement, unknown, null, undefined> | null {
        if (!containerRef.value) return null;

        drawCount.value++;

        // Reuse existing SVG element to avoid flash on data updates
        const existing = d3.select(containerRef.value).select<SVGSVGElement>('svg');
        if (!existing.empty()) {
            existing.attr('viewBox', `0 0 ${width.value} ${height.value}`);
            existing.selectAll('*').remove();
            svgRef.value = existing.node();
            return existing as d3.Selection<SVGSVGElement, unknown, null, undefined>;
        }

        const svg = d3
            .select(containerRef.value)
            .append('svg')
            .attr('width', '100%')
            .attr('height', '100%')
            .attr('viewBox', `0 0 ${width.value} ${height.value}`)
            .attr('preserveAspectRatio', 'xMidYMid meet');

        svgRef.value = svg.node();
        return svg as d3.Selection<SVGSVGElement, unknown, null, undefined>;
    }

    /**
     * Wrap a draw function for use in watch callbacks.
     * Redraws immediately — entrance animations are skipped via drawCount.
     */
    function wrapUpdate(drawFn: () => void): () => void {
        return () => {
            drawFn();
        };
    }

    /** Convert any CSS color value to hex using a canvas context. */
    function toHex(cssColor: string): string {
        const ctx = document.createElement('canvas').getContext('2d')!;
        ctx.fillStyle = cssColor;
        return ctx.fillStyle; // always returns #rrggbb
    }

    function getChartColors(count: number): string[] {
        const root = getComputedStyle(document.documentElement);
        const baseColors: string[] = [];
        for (let i = 1; i <= 5; i++) {
            const val = root.getPropertyValue(`--chart-${i}`).trim();
            if (val) baseColors.push(toHex(val));
        }

        if (count <= baseColors.length) {
            return baseColors.slice(0, count);
        }

        // Interpolate additional colors from the 5 base colors
        return d3.quantize(d3.interpolateRgbBasis(baseColors), count);
    }

    function getColor(cssVar: string): string {
        const val = getComputedStyle(document.documentElement).getPropertyValue(cssVar).trim();
        return val ? toHex(val) : val;
    }

    function onResize(callback: () => void) {
        resizeCallback = callback;
    }

    useResizeObserver(containerRef, () => {
        updateDimensions();
        resizeCallback?.();
    });

    onMounted(() => {
        isDark.value = document.documentElement.classList.contains('dark');

        mutationObserver = new MutationObserver(() => {
            const dark = document.documentElement.classList.contains('dark');
            if (dark !== isDark.value) {
                isDark.value = dark;
                resizeCallback?.();
            }
        });
        mutationObserver.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class'],
        });

        updateDimensions();
    });

    onBeforeUnmount(() => {
        mutationObserver?.disconnect();
    });

    /**
     * Resolve a color that may be a CSS var reference like "var(--chart-2)"
     * or a raw CSS color value like "hsl(220 70% 50%)" to a hex string.
     */
    function resolveColor(value: string): string {
        const varMatch = value.match(/^var\(--(.+?)\)$/);
        if (varMatch) {
            return getColor(`--${varMatch[1]}`);
        }
        return toHex(value);
    }

    return {
        svgRef,
        width,
        height,
        innerWidth,
        innerHeight,
        isDark,
        drawCount,
        margin,
        updateDimensions,
        createSvg,
        getChartColors,
        getColor,
        resolveColor,
        onResize,
        wrapUpdate,
    };
}
