<template>
    <div class="max-w-5xl mx-auto py-6 px-4">
        <h1 class="text-xl font-medium mb-4">{{ title }}</h1>
        <div v-for="item in state.list" :key="item.id" class="bg-white rounded border p-4 mb-2">
            {{ item.title }}
        </div>
    </div>
</template>

<script setup lang="ts">
import { reactive, onMounted } from 'vue'
import { getItemList } from '@/addon/{{KEY}}/api/item'

useHead({ title: '示例列表' })

const state = reactive({
    list: [] as any[]
})

onMounted(async () => {
    const res = await getItemList({ page: 1, limit: 20 })
    state.list = res.data.data
})
</script>
