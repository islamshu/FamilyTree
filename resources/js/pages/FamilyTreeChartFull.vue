<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, nextTick, onMounted, onUnmounted } from 'vue';

interface FamilyMember {
    id?: number;
    name: string;
    gender: 'male' | 'female';
    birth_date: string | null;
    death_date: string | null;
    wife_name: string | null;
    description: string | null;
    parent_id: number | null;
    children: FamilyMember[];
}

const treeData = ref<FamilyMember[]>([]);
const selectedMember = ref<FamilyMember | null>(null);
const chartContainer = ref<HTMLDivElement | null>(null);
const loading = ref(true);
const chartH = ref(window.innerHeight - 130);
const chartW = ref(window.innerWidth);

const getMaxDepth = (node: any, d = 0): number => {
    if (!node.children?.length) return d;
    return Math.max(...node.children.map((c: any) => getMaxDepth(c, d + 1)));
};

const countLeaves = (node: any): number => {
    if (!node.children?.length) return 1;
    return node.children.reduce((sum: number, c: any) => sum + countLeaves(c), 0);
};

const calcDimensions = (maxDepth: number, leafCount: number) => {
    const mobile = window.innerWidth < 768;
    const nodeW = mobile ? 68 : 88;
    const nodeH = mobile ? 22 : 26;
    const hGap = mobile ? 10 : 44;
    const vGap = mobile ? 20 : 40;
    const containerW = chartContainer.value?.parentElement?.clientWidth ?? window.innerWidth;
    chartH.value = Math.max(600, leafCount * (nodeH + vGap) + 80);
    const needed = (maxDepth + 1) * nodeW + maxDepth * hGap + 32;
    chartW.value = Math.max(containerW, needed);
};

let echarts: any = null;
let chart: any = null;
let resizeObserver: ResizeObserver | null = null;
let resizeTimer: ReturnType<typeof setTimeout> | null = null;
let lastMobile = window.innerWidth < 768;
let currentMaxDepth = 0;
let currentLeafCount = 1;

const buildTree = (flat: FamilyMember[]): FamilyMember[] => {
    const map = new Map<number, any>();
    const roots: any[] = [];

    flat.forEach((m) => {
        if (m.id) map.set(m.id, { ...m, children: [] });
    });

    flat.forEach((m) => {
        if (!m.id) return;
        const node = map.get(m.id);
        if (!node) return;
        if (m.parent_id && map.has(m.parent_id)) {
            map.get(m.parent_id).children.push(node);
        } else {
            roots.push(node);
        }
    });

    return roots;
};

const decorateNodes = (node: any, d = 0): any => ({
    ...node,
    depth: d,
    itemStyle: {
        color: node.gender === 'male' ? '#2563eb' : '#db2777',
        borderColor: node.gender === 'male' ? '#1e40af' : '#9d174d',
        borderWidth: 1.5,
    },
    children: node.children?.map((c: any) => decorateNodes(c, d + 1)) ?? [],
});

const findMember = (id: number, nodes: FamilyMember[]): FamilyMember | null => {
    for (const n of nodes) {
        if (n.id === id) return n;
        if (n.children?.length) {
            const found = findMember(id, n.children);
            if (found) return found;
        }
    }
    return null;
};

const triggerZoom = (delta: number) => {
    if (!chart) return;
    const cx = chart.getWidth() / 2;
    const cy = chart.getHeight() / 2;
    chart.getZr().trigger('mousewheel', {
        zrX: cx,
        zrY: cy,
        wheelDelta: delta,
        event: { preventDefault: () => {}, stopPropagation: () => {} },
    });
};

const zoomIn = () => triggerZoom(1);
const zoomOut = () => triggerZoom(-1);



const initChart = async () => {
    echarts = await import('echarts');
    if (!chartContainer.value) return;
    chart = echarts.init(chartContainer.value, undefined, { renderer: 'canvas' });

    chart.off('click');
    chart.on('click', (params: any) => {
        if (params.data?.id) {
            selectedMember.value = findMember(params.data.id, treeData.value);
        } else {
            selectedMember.value = null;
        }
    });

    resizeObserver = new ResizeObserver(() => {
        chart?.resize();
        calcDimensions(currentMaxDepth, currentLeafCount);
        if (resizeTimer) clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            const nowMobile = window.innerWidth < 768;
            if (nowMobile !== lastMobile) {
                lastMobile = nowMobile;
                renderChart();
            }
        }, 250);
    });
    resizeObserver.observe(chartContainer.value);
};

const renderChart = () => {
    if (!chart || !echarts) return;

    const mobile = window.innerWidth < 768;
    const nodeW = mobile ? 68 : 88;
    const nodeH = mobile ? 22 : 26;
    const fontSize = mobile ? 10 : 12;
    const labelW = nodeW - 12;

    const roots = treeData.value.map(decorateNodes);
    const root =
        roots.length === 1
            ? roots[0]
            : {
                  name: 'الجذر',
                  children: roots,
                  itemStyle: {
                      color: '#7c3aed',
                      borderColor: '#5b21b6',
                      borderWidth: 1.5,
                  },
              };

    chart.setOption(
        {
            backgroundColor: '#0f172a',
            tooltip: {
                trigger: 'item',
                backgroundColor: '#1e293b',
                borderColor: '#334155',
                textStyle: { color: '#e2e8f0', fontFamily: 'Tajawal, sans-serif', fontSize: 12 },
                formatter: (params: any) => {
                    const m = findMember(params.data.id, treeData.value);
                    if (!m) return params.name;
                    let html = `<strong>${m.name}</strong><br/>الجنس: ${m.gender === 'male' ? 'ذكر' : 'أنثى'}`;
                    if (m.birth_date) html += `<br/>الميلاد: ${m.birth_date}`;
                    if (m.death_date) html += `<br/>الوفاة: ${m.death_date}`;
                    if (m.wife_name) html += `<br/>الزوجة: ${m.wife_name}`;
                    if (m.description) html += `<br/>${m.description}`;
                    return html;
                },
            },
            series: [
                {
                    type: 'tree',
                    data: [root],
                    top: '2%',
                    left: mobile ? '4%' : '6%',
                    bottom: '2%',
                    right: mobile ? '4%' : '6%',
                    orient: 'LR',
                    symbol: 'roundRect',
                    symbolSize: [nodeW, nodeH],
                    roam: true,
                    initialTreeDepth: -1,
                    nodeGap: mobile ? 20 : 40,
                    label: {
                        show: true,
                        position: 'inside',
                        color: '#fff',
                        fontSize,
                        fontFamily: 'Tajawal, sans-serif',
                        overflow: 'truncate',
                        width: labelW,
                        formatter: '{b}',
                    },
                    leaves: {
                        label: {
                            position: 'inside',
                            color: '#fff',
                            overflow: 'truncate',
                            width: labelW,
                        },
                    },
                    lineStyle: {
                        color: '#64748b',
                        width: 1.5,
                        curveness: 0.4,
                    },
                    emphasis: {
                        focus: 'descendant',
                        blurScope: 'series',
                        itemStyle: {
                            borderWidth: 3,
                            borderColor: '#facc15',
                        },
                        label: { color: '#fff', fontWeight: 'bold' },
                    },
                    blur: {
                        itemStyle: { opacity: 0.3 },
                        lineStyle: { opacity: 0.2 },
                    },
                    expandAndCollapse: true,
                    animationDuration: 300,
                    animationDurationUpdate: 500,
                    animationEasingUpdate: 'cubicInOut',
                },
            ],
        },
        true,
    );

    setTimeout(() => chart?.resize(), 50);
};

const fetchData = async () => {
    loading.value = true;
    try {
        const res = await fetch('/api/family-members');
        treeData.value = buildTree(await res.json());

        const roots = treeData.value.map((r) => decorateNodes(r));
        const virtualRoot =
            roots.length === 1 ? roots[0] : { children: roots };
        currentMaxDepth = getMaxDepth(virtualRoot);
        currentLeafCount = countLeaves(virtualRoot);

        calcDimensions(currentMaxDepth, currentLeafCount);
        await nextTick();
        chart?.resize();
        renderChart();
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    setTimeout(async () => {
        await initChart();
        await fetchData();
    }, 200);
});

onUnmounted(() => {
    resizeObserver?.disconnect();
    chart?.dispose();
    chart = null;
});
</script>

<template>
    <Head title="مخطط شجرة العائلة الكامل">
        <link
            href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div
        class="flex h-screen flex-col bg-linear-to-br from-slate-900 via-purple-950 to-slate-900"
        style="font-family: 'Tajawal', sans-serif"
    >
        <!-- Header bar -->
        <div class="z-20 flex shrink-0 flex-wrap items-center justify-between gap-2 bg-slate-900/95 px-4 py-3 backdrop-blur md:px-6">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-lg font-bold text-white sm:text-2xl">مخطط شجرة العائلة الكامل</h1>
               
            </div>

            <div class="flex items-center gap-1.5">
                <button
                    @click="zoomIn"
                    class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-700 text-lg font-bold text-white transition hover:bg-slate-600 active:scale-95"
                    title="تكبير"
                >+</button>
                <button
                    @click="zoomOut"
                    class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-700 text-lg font-bold text-white transition hover:bg-slate-600 active:scale-95"
                >−</button>
                
                
                <Link
                    href="/tree-chart"
                    class="rounded-lg bg-slate-700 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-slate-600 active:scale-95"
                >← رجوع</Link>
            </div>
        </div>

        <!-- Selected member info bar -->
        <div
            v-if="selectedMember"
            class="mx-4 mb-1 shrink-0 rounded-lg bg-amber-500/20 px-4 py-2 text-sm text-amber-300 md:mx-6"
        >
            <strong class="text-amber-200">{{ selectedMember.name }}</strong>
            <span class="mx-2 text-slate-600">|</span>
            {{ selectedMember.gender === 'male' ? 'ذكر' : 'أنثى' }}
            <template v-if="selectedMember.birth_date">
                <span class="mx-2 text-slate-600">|</span>الميلاد: {{ selectedMember.birth_date }}
            </template>
            <template v-if="selectedMember.wife_name">
                <span class="mx-2 text-slate-600">|</span>الزوجة: {{ selectedMember.wife_name }}
            </template>
            <template v-if="selectedMember.description">
                <span class="mx-2 text-slate-600">|</span>{{ selectedMember.description }}
            </template>
        </div>

        <!-- Scrollable chart area — both axes -->
        <div class="flex-1 overflow-auto px-4 pb-6 md:px-6">
            <div
                class="relative rounded-2xl shadow-2xl"
                :style="{ background: '#0f172a', height: chartH + 'px', width: chartW + 'px', minWidth: '100%' }"
            >
                <div ref="chartContainer" style="width: 100%; height: 100%"></div>

                <!-- Loading overlay -->
                <div
                    v-if="loading"
                    class="absolute inset-0 flex items-center justify-center rounded-2xl"
                    style="background: #0f172a"
                >
                    <div class="text-center text-slate-400">
                        <div
                            class="mx-auto mb-3 h-10 w-10 animate-spin rounded-full border-4 border-slate-600 border-t-blue-400"
                        ></div>
                        <span>جارٍ تحميل البيانات...</span>
                    </div>
                </div>

                <!-- Hint -->
                <span class="absolute bottom-2 left-3 text-xs text-slate-600">
                    انقر للطي/التوسيع · اسحب للتنقل · عجلة الماوس للتكبير
                </span>
            </div>
        </div>
    </div>
</template>
