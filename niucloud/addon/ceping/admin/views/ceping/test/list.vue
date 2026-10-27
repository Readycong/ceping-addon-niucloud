<template>
    <div ref="pageRef" class="main-container table-fill-page" :style="pageStyle">
        <div class="table-fill-page__card">
            <div class="table-search-wrap table-search-wrap--panel flex justify-between items-center">
                <el-form :inline="true" :model="tableData.searchParam" ref="searchFormRef">
                    <el-form-item label="关键词" prop="keyword">
                        <el-input v-model.trim="tableData.searchParam.keyword" class="input-width" placeholder="量表名称 / 编号" clearable @keyup.enter="loadList(1)" />
                    </el-form-item>
                    <el-form-item label="分类">
                        <el-select v-model="tableData.searchParam.category" placeholder="全部分类" clearable class="input-width">
                            <el-option v-for="c in categoryOptions" :key="c.key" :label="c.name" :value="c.key" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="计分模型">
                        <el-select v-model="tableData.searchParam.scoring_model" placeholder="全部模型" clearable class="input-width">
                            <el-option v-for="(label, key) in modelMap" :key="key" :label="label" :value="key" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="状态">
                        <el-select v-model="tableData.searchParam.online_status" placeholder="全部状态" clearable class="input-width">
                            <el-option label="已上线" :value="1" />
                            <el-option label="已下线" :value="0" />
                        </el-select>
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="loadList(1)">搜索</el-button>
                        <el-button @click="resetSearch">重置</el-button>
                    </el-form-item>
                </el-form>
            </div>

            <div class="table-panel table-fill-page__panel">
                <table-toolbar title="题库管理" :selectable="false">
                    <template #actions>
                        <span class="text-[13px] text-[var(--el-text-color-secondary)] mr-[8px]">共 {{ tableData.total }} 份量表</span>
                    </template>
                </table-toolbar>
                <div class="table-fill-page__table">
                    <el-table height="100%" :data="tableData.data" size="large" v-loading="tableData.loading" row-key="id">
                        <template #empty>
                            <span>{{ !tableData.loading ? '暂无数据' : '' }}</span>
                        </template>
                        <el-table-column label="量表" min-width="300" show-overflow-tooltip>
                            <template #default="{ row }">
                                <div class="flex items-center">
                                    <el-image :src="img(row.cover)" class="w-[72px] h-[40px] rounded-[4px] mr-[10px] flex-shrink-0" fit="cover" lazy>
                                        <template #error>
                                            <div class="w-[72px] h-[40px] bg-[var(--el-fill-color-light)] rounded-[4px]" />
                                        </template>
                                    </el-image>
                                    <div class="min-w-0">
                                        <div class="font-medium truncate">#{{ row.test_id }} {{ row.display_name || row.title }}</div>
                                        <div class="text-[12px] text-[var(--el-text-color-secondary)] mt-[2px]">{{ categoryName(row.category) }} · {{ modelMap[row.scoring_model] || row.scoring_model }}</div>
                                    </div>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column label="题量" min-width="90" align="center">
                            <template #default="{ row }">{{ row.question_count }} 题 / {{ row.result_count }} 结果</template>
                        </el-table-column>
                        <el-table-column label="预计时长" width="90" align="center">
                            <template #default="{ row }">{{ row.est_minutes }} 分钟</template>
                        </el-table-column>
                        <el-table-column label="版权来源" width="130" align="center">
                            <template #default="{ row }">
                                <el-tag :type="row.copyright_status === 'commercial' ? 'warning' : 'info'" effect="plain">
                                    {{ copyrightMap[row.copyright_status] || row.copyright_status }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="排序" width="100" align="center">
                            <template #default="{ row }">
                                <el-input-number v-model="row.sort" :controls="false" size="small" class="w-[70px]" @change="(v: number) => changeSort(row, v)" />
                            </template>
                        </el-table-column>
                        <el-table-column label="热门" width="80" align="center">
                            <template #default="{ row }">
                                <el-switch :model-value="row.is_hot === 1" :loading="row._hotLoading" @change="(v: boolean) => toggleHot(row, v)" />
                            </template>
                        </el-table-column>
                        <el-table-column label="上线" width="80" align="center">
                            <template #default="{ row }">
                                <el-switch :model-value="row.online_status === 1" :loading="row._onlineLoading" @change="(v: boolean) => toggleOnline(row, v)" />
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="90" align="right" fixed="right">
                            <template #default="{ row }">
                                <el-button link type="primary" @click="openDetail(row)">查看</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>

                <table-pagination
                    v-model:current-page="tableData.page"
                    v-model:page-size="tableData.limit"
                    :total="tableData.total"
                    @size-change="loadList(1)"
                    @current-change="loadList"
                />
            </div>
        </div>

        <!-- 题库只读详情 -->
        <el-dialog v-model="detail.visible" :title="detail.data ? `#${detail.data.test_id} ${detail.data.display_name || detail.data.title}` : '量表详情'" width="820px" top="6vh" class="ceping-detail-dialog">
            <div v-loading="detail.loading" style="max-height: 66vh; overflow-y: auto;">
                <template v-if="detail.data">
                    <el-descriptions :column="2" border size="small" class="mb-[16px]">
                        <el-descriptions-item label="分类">{{ categoryName(detail.data.category) }}</el-descriptions-item>
                        <el-descriptions-item label="计分模型">{{ modelMap[detail.data.scoring_model] || detail.data.scoring_model }}</el-descriptions-item>
                        <el-descriptions-item label="题量">{{ detail.data.question_count }}</el-descriptions-item>
                        <el-descriptions-item label="结果数">{{ detail.data.result_count }}</el-descriptions-item>
                        <el-descriptions-item label="版权来源" :span="2">{{ copyrightMap[detail.data.copyright_status] || detail.data.copyright_status }}</el-descriptions-item>
                        <el-descriptions-item label="简介" :span="2">{{ detail.data.description || '—' }}</el-descriptions-item>
                        <el-descriptions-item label="答题说明" :span="2">{{ detail.data.instructions || '—' }}</el-descriptions-item>
                    </el-descriptions>

                    <div class="text-[14px] font-medium mb-[8px]">题目（{{ (detail.data.questions || []).length }}）</div>
                    <div v-for="(q, qi) in detail.data.questions" :key="qi" class="ceping-qa-block">
                        <div class="font-medium">{{ qi + 1 }}. {{ q.stem }}</div>
                        <div class="ceping-options">
                            <span v-for="(opt, oi) in q.options" :key="oi" class="ceping-option">{{ opt.key || optionLetters[oi] }}. {{ opt.text }}</span>
                        </div>
                    </div>

                    <div class="text-[14px] font-medium mt-[16px] mb-[8px]">结果解释（{{ (detail.data.results || []).length }}）</div>
                    <div v-for="(r, ri) in detail.data.results" :key="ri" class="ceping-result-block">
                        <div class="font-medium">{{ r.name }} <span class="text-[12px] text-[var(--el-text-color-secondary)]">（{{ r.key }}）</span></div>
                        <div class="mt-[4px]">{{ r.summary }}</div>
                        <div class="text-[13px] text-[var(--el-text-color-secondary)] mt-[4px]">{{ r.detail }}</div>
                        <div v-if="r.growthTip" class="text-[13px] mt-[4px]"><span class="text-[var(--el-color-primary)]">成长建议：</span>{{ r.growthTip }}</div>
                    </div>
                </template>
            </div>
        </el-dialog>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import type { FormInstance } from 'element-plus'
import { img } from '@/utils/common'
import TablePagination from '@/components/table-pagination/index.vue'
import TableToolbar from '@/components/table-toolbar/index.vue'
import { useTablePageHeight } from '@/hooks/useTablePageHeight'
import { getTestList, getTestInfo, setTestOnline, setTestHot, setTestSort } from '../../../api/test'

defineOptions({ name: 'CepingTestList' })

const { pageRef, pageStyle } = useTablePageHeight()

const categoryOptions = [
    { key: 'persona', name: '🎭 人格认知' },
    { key: 'love', name: '💌 情感关系' },
    { key: 'office', name: '💼 职场成长' },
    { key: 'social', name: '💬 社交人际' },
    { key: 'emotion', name: '🌊 情绪状态' },
    { key: 'life', name: '🍲 生活消费' },
    { key: 'aesthetic', name: '🎨 兴趣审美' },
    { key: 'mystery', name: '🔮 玄学脑洞' }
]
const modelMap: Record<string, string> = {
    category: '分类累加型',
    threshold: '区间阈值型',
    dimension: '维度倾向型',
    partner: '双人选配型'
}
const copyrightMap: Record<string, string> = {
    internet_native: '网络原生',
    public_domain: '公共领域',
    theory: '理论改编',
    commercial: '商业版权改编'
}
const optionLetters = ['A', 'B', 'C', 'D', 'E', 'F']

const categoryName = (key: string) => categoryOptions.find(c => c.key === key)?.name || key || '—'

const searchFormRef = ref<FormInstance>()
const tableData = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: false,
    data: [] as any[],
    searchParam: {
        keyword: '',
        category: '',
        scoring_model: '',
        online_status: '' as '' | 0 | 1
    }
})

const loadList = (page: number = 1) => {
    tableData.loading = true
    tableData.page = page
    getTestList({
        page: tableData.page,
        limit: tableData.limit,
        ...tableData.searchParam
    }).then(res => {
        tableData.data = (res.data.data || []).map((row: any) => ({ ...row, _onlineLoading: false, _hotLoading: false }))
        tableData.total = res.data.total
    }).finally(() => {
        tableData.loading = false
    })
}
loadList()

const resetSearch = () => {
    tableData.searchParam = { keyword: '', category: '', scoring_model: '', online_status: '' }
    loadList(1)
}

const toggleOnline = (row: any, val: boolean) => {
    row._onlineLoading = true
    setTestOnline(row.id, val ? 1 : 0).then(() => {
        row.online_status = val ? 1 : 0
        ElMessage.success(val ? '已上线' : '已下线')
    }).finally(() => {
        row._onlineLoading = false
    })
}

const toggleHot = (row: any, val: boolean) => {
    row._hotLoading = true
    setTestHot(row.id, val ? 1 : 0).then(() => {
        row.is_hot = val ? 1 : 0
        ElMessage.success(val ? '已设为热门' : '已取消热门')
    }).finally(() => {
        row._hotLoading = false
    })
}

const changeSort = (row: any, val: number) => {
    const sort = Number(val) || 0
    setTestSort(row.id, sort).then(() => {
        row.sort = sort
        ElMessage.success('排序已更新')
    })
}

const detail = reactive({ visible: false, loading: false, data: null as any })
const openDetail = (row: any) => {
    detail.visible = true
    detail.loading = true
    detail.data = null
    getTestInfo(row.id).then(res => {
        detail.data = res.data
    }).finally(() => {
        detail.loading = false
    })
}
</script>

<style lang="scss" scoped>
.ceping-qa-block,
.ceping-result-block {
    background: var(--el-fill-color-light);
    border-radius: 6px;
    padding: 10px 12px;
    margin-bottom: 8px;
    font-size: 13px;
    line-height: 1.7;
}
.ceping-options {
    display: flex;
    flex-direction: column;
    margin-top: 4px;
    color: var(--el-text-color-secondary);
}
</style>
