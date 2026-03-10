<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { use } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { SunburstChart } from 'echarts/charts';
import {
    TitleComponent,
    TooltipComponent,
    LegendComponent,
} from 'echarts/components';
import VChart from 'vue-echarts';
import { ref, computed } from 'vue';

use([
    CanvasRenderer,
    SunburstChart,
    TitleComponent,
    TooltipComponent,
    LegendComponent,
]);

interface TreeNode {
    name: string;
    value?: number;
    children?: TreeNode[];
}
let data = [
    {
        name: 'Grand Father 1',
        children: [
            {
                name: 'Father 1',
                children: [
                    {name: 'Child 1', value: 1},
                    {name: 'Child 2', value: 1}
                ]
            },
            {
                name: 'Father 2',
                children: [
                    {name: 'Child A', value: 1},
                    {name: 'Child B', value: 1},
                    {
                        name: 'Child C',
                        children: [
                            {name: 'Baby 1', value: 1},
                            {name: 'Baby 2', value: 1}
                        ]
                    }
                ]
            }
        ]
    },
    {
        name: 'Grand Father 2',
        children: [
            {
                name: 'Father 1',
                children: [
                    {name: 'Child 1', value: 1,
                        children: [
                            {name: 'Child 1', value: 1,
                                children: [
                                    {name: 'Child 1', value: 1,
                                        children: [
                                            {name: 'Child 1', value: 1},
                                            {name: 'Child 2', value: 1}
                                        ]},
                                    {name: 'Child 2', value: 1}
                                ]},
                            {name: 'Child 2', value: 1}
                        ]},
                    {name: 'Child 2', value: 1}
                ]
            },
            {
                name: 'Father 2',
                children: [
                    {name: 'Child A', value: 1},
                    {name: 'Child B', value: 1},
                    {
                        name: 'Child C',
                        children: [
                            {name: 'Baby 1', value: 1},
                            {name: 'Baby 2', value: 1}
                        ]
                    }
                ]
            }
        ]
    },
    {
        name: 'Grand Father 3',
        children: [
            {
                name: 'Father 1',
                children: [
                    {name: 'Child 1'},
                    {name: 'Child 2'}
                ]
            },
            {
                name: 'Father 2',
                children: [
                    {name: 'Child A'},
                    {name: 'Child B'},
                    {
                        name: 'Child C',
                        children: [
                            {name: 'Baby 1'},
                            {name: 'Baby 2'}
                        ]
                    }
                ]
            }
        ]
    },
];
const familyData: TreeNode = {
    name: 'Grandfather',
    children: [
        {
            name: 'Uncle',
            children: [
                { name: 'Cousin 1', value: 1 },
                { name: 'Cousin 2', value: 1 },
            ],
        },
        {
            name: 'Father',
            children: [
                {
                    name: 'Me',
                    value: 1,
                    children: [
                        { name: 'Someone', value: 1 },
                    ],
                },
                { name: 'Sister', value: 1 },
            ],
        },
        {
            name: 'Aunt',
            children: [{ name: 'Cousin 3', value: 1 }],
        },
    ],
};

const option = computed(() => ({
    backgroundColor: '#F8F9FA',
    color: [
        '#FF6B6B', '#FFD93D', '#6BCB77', '#4D96FF', '#FF6EC7',
        '#845EC2', '#FFA07A', '#20C997', '#FFB6C1', '#00CED1'
    ],
    tooltip: {
        trigger: 'item',
        formatter: '{b}',
    },
    series: {
        type: 'sunburst',
        data: data,
        radius: [0, '90%'],
        label: {
            rotate: 'radial',
        },
        emphasis: {
            focus: 'ancestor',
        },
    },
}));
</script>

<template>
    <Head title="Family Tree">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>
    <div
        class="flex min-h-screen flex-col items-center bg-[#FDFDFC] p-6 text-[#1b1b18] lg:justify-center lg:p-8 dark:bg-[#0a0a0a]"
    >
        <main class="w-full max-w-8xl">
            <h1 class="mb-6 text-2xl font-bold text-white">شجرة العائلة</h1>
            <div
                class="overflow-hidden rounded-lg bg-white p-4 shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:bg-[#161615] dark:text-[#EDEDEC] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]"
            >
                <v-chart style="width: 100%; height: 700px" :option="option" autoresize />
            </div>
        </main>
    </div>
</template>
