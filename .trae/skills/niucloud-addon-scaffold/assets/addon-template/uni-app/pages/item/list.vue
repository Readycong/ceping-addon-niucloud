<template>
    <view class="p-3">
        <view v-for="item in state.list" :key="item.id" class="bg-white rounded-lg p-3 mb-2">
            <text class="text-base">{{ item.title }}</text>
        </view>
        <u-empty v-if="!state.loading && state.list.length === 0" text="暂无数据" />
    </view>
</template>

<script setup lang="ts">
import { reactive } from 'vue'
import { onLoad, onReachBottom } from '@dcloudio/uni-app'
import { getItemList } from '@/addon/{{KEY}}/api/item'

const state = reactive({
    loading: false,
    list: [] as any[],
    page: 1,
    lastPage: 1
})

const loadList = async () => {
    state.loading = true
    try {
        const res = await getItemList({ page: state.page, limit: 10 })
        state.list = state.page === 1 ? res.data.data : [...state.list, ...res.data.data]
        state.lastPage = res.data.lastPage
    } finally {
        state.loading = false
    }
}

onLoad(() => loadList())

onReachBottom(() => {
    if (state.page < state.lastPage) {
        state.page++
        loadList()
    }
})
</script>
