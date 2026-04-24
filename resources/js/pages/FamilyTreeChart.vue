<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';

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

let echarts: any = null;
let chart: any = null;

const buildTree = (flat: FamilyMember[]): FamilyMember[] => {
    const map = new Map<number, any>();
    const roots: any[] = [];

    flat.forEach((m) => {
        if (m.id) {
            map.set(m.id, { ...m, children: [] });
        }
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

const decorateNodes = (node: any): any => ({
    ...node,
    itemStyle: {
        color: node.gender === 'male' ? '#4D96FF' : '#FF6EC7',
        borderColor: node.gender === 'male' ? '#2563eb' : '#db2777',
        borderWidth: 1,
    },
    children: node.children?.map(decorateNodes) ?? [],
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

    setTimeout(() => chart?.resize(), 100);
};

const renderChart = () => {
    if (!chart || !echarts) return;

    const roots = treeData.value.map(decorateNodes);
    const root =
        roots.length === 1
            ? roots[0]
            : {
                  name: 'الجذر',
                  children: roots,
                  itemStyle: {
                      color: '#845EC2',
                      borderColor: '#6d28d9',
                      borderWidth: 1,
                  },
              };

    chart.setOption(
        {
            backgroundColor: '#1a1a2e',
            tooltip: {
                trigger: 'item',
                formatter: (params: any) => {
                    const m = findMember(params.data.id, treeData.value);
                    if (!m) return params.name;
                    let html = `<strong>${m.name}</strong><br/>الجنس: ${m.gender === 'male' ? 'ذكر' : 'أنثى'}`;
                    if (m.birth_date) html += `<br/>الميلاد: ${m.birth_date}`;
                    if (m.death_date) html += `<br/>الوفاة: ${m.death_date}`;
                    if (m.wife_name) html += `<br/>الزوجة: ${m.wife_name}`;
                    return html;
                },
            },
            series: [
                {
                    type: 'tree',
                    data: [root],
                    top: '5%',
                    left: '5%',
                    bottom: '5%',
                    right: '20%',
                    orient: 'LR',
                    symbol: 'roundRect',
                    symbolSize: [110, 34],
                    roam: true,
                    initialTreeDepth: 3,
                    label: {
                        show: true,
                        position: 'inside',
                        color: '#fff',
                        fontSize: 13,
                        fontFamily: 'Tajawal, sans-serif',
                        formatter: '{b}',
                    },
                    leaves: {
                        label: { position: 'inside', color: '#fff' },
                    },
                    lineStyle: {
                        color: '#7c7c9a',
                        width: 1.5,
                        curveness: 0.5,
                    },
                    emphasis: { focus: 'descendant' },
                    expandAndCollapse: true,
                    animationDuration: 550,
                    animationDurationUpdate: 750,
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
    chart?.dispose();
    chart = null;
});
</script>

<template>
    <Head title="مخطط شجرة العائلة">
        <link
            href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div
        class="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 p-4 md:p-8"
        style="font-family: 'Tajawal', sans-serif"
    >
        <div class="mb-6 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <h1 class="text-3xl font-bold text-white">
                    مخطط شجرة العائلة
                </h1>
                <div class="flex items-center gap-3 text-sm text-slate-400">
                    <span class="flex items-center gap-1">
                        <span
                            class="inline-block h-3 w-3 rounded-sm bg-blue-400"
                        ></span>
                        ذكر
                    </span>
                    <span class="flex items-center gap-1">
                        <span
                            class="inline-block h-3 w-3 rounded-sm bg-pink-400"
                        ></span>
                        أنثى
                    </span>
                </div>
            </div>
            <Link
                href="/"
                class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-600"
            >
                ← العودة إلى المخطط الدائري
            </Link>
        </div>

        <div
            v-if="selectedMember"
            class="mb-4 rounded-lg bg-amber-500/20 p-3 text-amber-300"
        >
            <strong>{{ selectedMember.name }}</strong>
            <span class="mx-2 text-slate-500">|</span>
            {{ selectedMember.gender === 'male' ? 'ذكر' : 'أنثى' }}
            <template v-if="selectedMember.birth_date">
                <span class="mx-2 text-slate-500">|</span>
                الميلاد: {{ selectedMember.birth_date }}
            </template>
            <template v-if="selectedMember.wife_name">
                <span class="mx-2 text-slate-500">|</span>
                الزوجة: {{ selectedMember.wife_name }}
            </template>
            <template v-if="selectedMember.description">
                <span class="mx-2 text-slate-500">|</span>
                {{ selectedMember.description }}
            </template>
        </div>

        <div class="flex justify-center px-2">
            <div
                class="relative rounded-2xl shadow-2xl"
                style="height: 95vh; width: 100%; background: #1a1a2e"
            >
                <div
                    ref="chartContainer"
                    style="width: 100%; height: 100%"
                ></div>

                <div
                    v-if="loading"
                    class="absolute inset-0 flex items-center justify-center rounded-2xl"
                    style="background: #1a1a2e"
                >
                    <div class="text-center text-slate-400">
                        <div
                            class="mx-auto mb-3 h-10 w-10 animate-spin rounded-full border-4 border-slate-600 border-t-blue-400"
                        ></div>
                        <span>جارٍ تحميل البيانات...</span>
                    </div>
                </div>

                <span class="absolute bottom-3 left-3 text-xs text-slate-500">
                    انقر على العقدة للتوسيع/الطي · اسحب للتنقل · عجلة الماوس
                    للتكبير
                </span>
            </div>
        </div>
    </div>
</template>
