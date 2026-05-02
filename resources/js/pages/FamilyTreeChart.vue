<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';

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

interface FlatMember extends FamilyMember {
    id: number;
    depth: number;
    ancestors: string[]; // root → immediate parent
}

const treeData = ref<FamilyMember[]>([]);
const flatMembersList = ref<FlatMember[]>([]);
const selectedMember = ref<FamilyMember | null>(null);
const chartContainer = ref<HTMLDivElement | null>(null);
const loading = ref(true);
const searchQuery = ref('');
const showDropdown = ref(false);

const searchResults = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return [];
    return flatMembersList.value.filter((m) => m.name.toLowerCase().includes(q));
});

const ancestorPath = (ancestors: string[]): string => {
    if (!ancestors.length) return '';
    const tail = ancestors.length > 4 ? ancestors.slice(-3) : [...ancestors];
    const suffix = ancestors.length > 4 ? ' ابن ...' : '';
    return [...tail].reverse().join(' ابن ') + suffix;
};

const popup = ref<{ show: boolean; member: FlatMember | null }>({ show: false, member: null });

const popupChain = computed(() =>
    popup.value.member
        ? [popup.value.member.name, ...[...popup.value.member.ancestors].reverse()]
        : [],
);

const closeDropdown = () => {
    setTimeout(() => {
        showDropdown.value = false;
    }, 150);
};
const chartH = ref(window.innerHeight - 130);
const chartW = ref(window.innerWidth);
// tracks the deepest generation the user has expanded into
const visibleDepth = ref(window.innerWidth < 768 ? 1 : 2);

const calcDimensions = () => {
    const mobile = window.innerWidth < 768;
    const nodeW = mobile ? 68 : 88;
    // connector gap: minimal on mobile, comfortable on desktop
    const gap = mobile ? 10 : 44;
    const containerW = chartContainer.value?.parentElement?.clientWidth ?? window.innerWidth;
    chartH.value = window.innerHeight - 130;
    // canvas grows only as deep as the user has opened — no extra width on first load
    const needed = (visibleDepth.value + 1) * nodeW + visibleDepth.value * gap + 32;
    chartW.value = Math.max(containerW, needed);
};

let echarts: any = null;
let chart: any = null;
let resizeObserver: ResizeObserver | null = null;
let resizeTimer: ReturnType<typeof setTimeout> | null = null;
let lastMobile = window.innerWidth < 768;

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

const buildFlatList = (nodes: FamilyMember[], ancestors: string[] = [], depth = 0) => {
    for (const node of nodes) {
        if (!node.id) continue;
        flatMembersList.value.push({ ...node, id: node.id, depth, ancestors });
        if (node.children?.length) {
            buildFlatList(node.children, [...ancestors, node.name], depth + 1);
        }
    }
};

const selectResult = (member: FlatMember) => {
    popup.value = { show: true, member };
    selectedMember.value = member;
    searchQuery.value = '';
    showDropdown.value = false;

    const d = member.depth;
    if (d + 1 > visibleDepth.value) {
        visibleDepth.value = d + 1;
        calcDimensions();
        nextTick(() => {
            chart?.resize();
            // renderChart();
        });
    }
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
const resetView = () => {
    visibleDepth.value = window.innerWidth < 768 ? 1 : 2;
    calcDimensions();
    nextTick(() => {
        chart?.resize();
        chart?.dispatchAction({ type: 'restore' });
    });
};

const initChart = async () => {
    echarts = await import('echarts');
    if (!chartContainer.value) return;
    chart = echarts.init(chartContainer.value, undefined, { renderer: 'canvas' });

    chart.off('click');
    chart.on('click', (params: any) => {
        if (params.data?.id) {
            selectedMember.value = findMember(params.data.id, treeData.value);
            // expand: if user opens a node deeper than current canvas allows, grow it
            const d: number = params.data.depth ?? 0;
            if (d + 1 > visibleDepth.value) {
                visibleDepth.value = d + 1;
                calcDimensions();
                nextTick(() => chart?.resize());
            }
        } else {
            selectedMember.value = null;
        }
    });

    resizeObserver = new ResizeObserver(() => {
        chart?.resize();
        calcDimensions();
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
                    initialTreeDepth: visibleDepth.value,
                    nodeGap: mobile ? 5 : 8,
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
        flatMembersList.value = [];
        buildFlatList(treeData.value);
        visibleDepth.value = window.innerWidth < 768 ? 1 : 2;
        calcDimensions();
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
    <Head title="مخطط شجرة العائلة شبلاق">
        <link
            href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div
        class="min-h-screen bg-linear-to-br from-slate-900 via-purple-950 to-slate-900"
        style="font-family: 'Tajawal', sans-serif"
    >
        <!-- Sticky header bar -->
        <div class="sticky top-0 z-20 flex flex-wrap items-center justify-between gap-2 bg-slate-900/95 px-4 py-3 backdrop-blur md:px-6">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-lg font-bold text-white sm:text-2xl">مخطط شجرة العائلة شبلاق</h1>

                <!-- Search box -->
                <div class="relative" dir="rtl">
                    <div
                        class="flex items-center gap-2 rounded-lg bg-slate-700 px-3 py-1.5 ring-1 ring-transparent focus-within:ring-blue-500"
                    >
                        <span class="text-slate-400 text-sm select-none">🔍</span>
                        <input
                            v-model="searchQuery"
                            @focus="showDropdown = true"
                            @input="showDropdown = true"
                            @blur="closeDropdown"
                            type="text"
                            placeholder="ابحث بالاسم أو الأب أو الجد..."
                            class="w-44 bg-transparent text-sm text-white placeholder-slate-400 outline-none sm:w-60"
                            dir="rtl"
                        />
                        <button
                            v-if="searchQuery"
                            @click="searchQuery = '';
                             showDropdown = false"
                            class="text-slate-400 hover:text-white leading-none"
                        >✕</button>
                    </div>

                    <!-- Results dropdown -->
                    <div
                        v-if="showDropdown && searchResults.length"
                        class="absolute right-0 top-full z-50 mt-1 w-72 overflow-y-auto rounded-xl border border-slate-600/60 bg-slate-900/95 shadow-2xl backdrop-blur sm:w-80" style="max-height: min(60vh, 320px)"
                        dir="rtl"
                    >
                        <button
                            v-for="result in searchResults"
                            :key="result.id"
                            @mousedown.prevent="selectResult(result)"
                            class="group flex w-full items-start gap-2.5 px-3 py-2.5 text-right transition-colors hover:bg-slate-700/60 first:rounded-t-xl last:rounded-b-xl"
                        >
                            <span
                                class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                               
                            ></span>
                            <span class="flex min-w-0 flex-col">
                                <span class="truncate text-sm font-semibold text-white">{{ result.name }}</span>
                                <span v-if="result.ancestors.length" class="mt-0.5 truncate text-xs text-slate-400">
                                    {{ ancestorPath(result.ancestors) }}
                                </span>
                                <span v-else class="mt-0.5 text-xs text-slate-500">جذر الشجرة</span>
                            </span>
                        </button>
                    </div>

                    <!-- No results -->
                    <div
                        v-if="showDropdown && searchQuery.trim() && !searchResults.length"
                        class="absolute right-0 top-full z-50 mt-1 w-60 rounded-lg border border-slate-600 bg-slate-800 px-4 py-3 text-sm text-slate-400 shadow-2xl"
                        dir="rtl"
                    >
                        لا توجد نتائج
                    </div>
                </div>
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
                    title="تصغير"
                >−</button>
                <button
                    @click="resetView"
                    class="rounded-lg bg-slate-700 px-3 py-1.5 text-sm text-white transition hover:bg-slate-600 active:scale-95"
                    title="إعادة ضبط العرض"
                >⟳ إعادة ضبط</button>
                <Link
                    href="/tree-chart-full"
                    class="rounded-lg bg-slate-700 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-slate-600 active:scale-95"
                >← مشاهدة الشجرة كاملة</Link>
                <Link
                    href="/"
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

        <!-- Scrollable chart area — horizontal scroll when tree is wider than viewport -->
        <div class="overflow-x-auto px-4 pb-6 md:px-6">
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
                    انقر للتوسيع/الطي · اسحب للتنقل · عجلة الماوس للتكبير
                </span>
            </div>
        </div>

        <!-- Genealogy popup -->
        <Transition name="popup">
            <div
                v-if="popup.show"
                class="fixed inset-0 z-[200] flex items-center justify-center p-4"
                style="font-family: 'Tajawal', sans-serif"
                @click.self="popup.show = false"
            >
                <!-- backdrop -->
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>

                <!-- card -->
                <div
                    class="popup-card relative w-full max-w-xs rounded-2xl border border-slate-600/40 bg-slate-900 shadow-2xl"
                    dir="rtl"
                >
                    <!-- header -->
                    <div class="flex items-center justify-between border-b border-slate-700/50 px-5 py-4">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">🌳</span>
                            <span class="font-bold text-white">النسب الكامل</span>
                        </div>
                        <button
                            @click="popup.show = false"
                            class="flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-700 hover:text-white"
                        >✕</button>
                    </div>

                    <!-- chain -->
                    <div class="max-h-80 overflow-y-auto px-5 py-4">
                        <div v-for="(name, i) in popupChain" :key="i">
                            <!-- name row -->
                            <div class="flex items-center gap-3">
                                <span
                                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                                    :class="i === 0
                                        ? (popup.member?.gender === 'male' ? 'bg-blue-600' : 'bg-pink-600')
                                        : 'bg-slate-700'"
                                >{{ i + 1 }}</span>
                                <span
                                    class="text-sm"
                                    :class="i === 0 ? 'font-bold text-white' : 'text-slate-300'"
                                >{{ name }}</span>
                            </div>
                            <!-- connector -->
                            <div v-if="i < popupChain.length - 1" class="mr-3 flex items-center gap-3 py-0.5">
                                <div class="w-1 self-stretch border-r border-dashed border-slate-600"></div>
                                <span class="text-xs text-slate-500">ابن</span>
                            </div>
                        </div>
                    </div>

                    <!-- footer -->
                    <div class="border-t border-slate-700/50 px-5 py-3">
                        <button
                            @click="popup.show = false"
                            class="w-full rounded-xl bg-slate-700 py-2 text-sm font-medium text-white transition hover:bg-slate-600 active:scale-95"
                        >إغلاق</button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.popup-enter-active,
.popup-leave-active {
    transition: opacity 0.2s ease;
}
.popup-enter-from,
.popup-leave-to {
    opacity: 0;
}
.popup-enter-active .popup-card,
.popup-leave-active .popup-card {
    transition: transform 0.2s ease, opacity 0.2s ease;
}
.popup-enter-from .popup-card,
.popup-leave-to .popup-card {
    transform: scale(0.92) translateY(10px);
    opacity: 0;
}
</style>
