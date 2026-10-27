<template>
    <div class="niucloud-container">
        <el-card shadow="never">
            <!-- 搜索 -->
            <el-form :inline="true" :model="state.searchForm" @submit.prevent>
                {{SEARCH_FORM_ITEMS}}
                <el-form-item>
                    <el-button type="primary" @click="getList">搜索</el-button>
                    <el-button @click="resetSearch">重置</el-button>
                </el-form-item>
            </el-form>

            <div class="mb-3">
                <el-button type="primary" @click="router.push({ path: '/site/{{KEY}}/{{MODULE}}/edit' })">添加</el-button>
            </div>

            <el-table v-loading="state.loading" :data="state.list" border>
                {{TABLE_COLUMNS}}
                <el-table-column label="操作" width="160" fixed="right">
                    <template #default="{ row }">
                        <el-button link type="primary"
                            @click="router.push({ path: '/site/{{KEY}}/{{MODULE}}/edit', query: { id: row.id } })">编辑</el-button>
                        <el-popconfirm title="确定删除？" @confirm="onDelete(row.id)">
                            <template #reference>
                                <el-button link type="danger">删除</el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>

            <el-pagination
                class="mt-3 justify-end"
                layout="total, prev, pager, next"
                :total="state.total"
                :current-page="state.searchForm.page"
                :page-size="state.searchForm.limit"
                @current-change="onPageChange"
            />
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { get{{MODULE_PASCAL}}List, delete{{MODULE_PASCAL}} } from '@/addon/{{KEY}}/api/{{MODULE}}'

const router = useRouter()

const state = reactive({
    loading: false,
    list: [],
    total: 0,
    searchForm: {
        {{SEARCH_FORM_STATE}}
        page: 1,
        limit: 10
    }
})

const getList = async () => {
    state.loading = true
    try {
        const res = await get{{MODULE_PASCAL}}List(state.searchForm)
        state.list = res.data.data
        state.total = res.data.total
    } finally {
        state.loading = false
    }
}

const resetSearch = () => {
    {{SEARCH_RESET}}
    getList()
}

const onPageChange = (page: number) => {
    state.searchForm.page = page
    getList()
}

const onDelete = async (id: number) => {
    await delete{{MODULE_PASCAL}}(id)
    getList()
}

onMounted(getList)
</script>
