<template>
    <div class="niucloud-container">
        <el-card shadow="never">
            <!-- 搜索 -->
            <el-form :inline="true" :model="state.searchForm" @submit.prevent>
                <el-form-item label="标题">
                    <el-input v-model="state.searchForm.title" clearable @keyup.enter="getList" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="getList">搜索</el-button>
                    <el-button @click="resetSearch">重置</el-button>
                </el-form-item>
            </el-form>

            <!-- 操作按钮 -->
            <div class="mb-3">
                <el-button type="primary" @click="router.push({ path: '/site/{{KEY}}/item/edit' })">添加</el-button>
            </div>

            <!-- 列表 -->
            <el-table v-loading="state.loading" :data="state.list" border>
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column prop="title" label="标题" min-width="160" show-overflow-tooltip />
                <el-table-column prop="sort" label="排序" width="100" />
                <el-table-column label="操作" width="160" fixed="right">
                    <template #default="{ row }">
                        <el-button link type="primary" @click="router.push({ path: '/site/{{KEY}}/item/edit', query: { id: row.id } })">编辑</el-button>
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
import { getItemList, deleteItem } from '@/addon/{{KEY}}/api/item'

const router = useRouter()

const state = reactive({
    loading: false,
    list: [],
    total: 0,
    searchForm: {
        title: '',
        page: 1,
        limit: 10
    }
})

const getList = async () => {
    state.loading = true
    try {
        const res = await getItemList(state.searchForm)
        state.list = res.data.data
        state.total = res.data.total
    } finally {
        state.loading = false
    }
}

const resetSearch = () => {
    state.searchForm.title = ''
    getList()
}

const onPageChange = (page: number) => {
    state.searchForm.page = page
    getList()
}

const onDelete = async (id: number) => {
    await deleteItem(id)
    getList()
}

onMounted(getList)
</script>
