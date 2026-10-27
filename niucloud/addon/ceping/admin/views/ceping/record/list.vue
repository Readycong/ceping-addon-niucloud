<template>
    <div ref="pageRef" class="main-container table-fill-page" :style="pageStyle">
        <div class="table-fill-page__card">
            <div class="table-search-wrap table-search-wrap--panel flex justify-between items-center">
                <el-form :inline="true" :model="tableData.searchParam" ref="searchFormRef">
                    <el-form-item label="量表编号">
                        <el-input-number v-model="tableData.searchParam.test_id" :min="1" :max="50" :controls="false" class="input-width" placeholder="如 1" />
                    </el-form-item>
                    <el-form-item label="会员ID">
                        <el-input-number v-model="tableData.searchParam.member_id" :min="0" :controls="false" class="input-width" placeholder="member_id" />
                    </el-form-item>
                    <el-form-item label="模式">
                        <el-select v-model="tableData.searchParam.mode" placeholder="全部模式" clearable class="input-width">
                            <el-option label="单人测评" value="single" />
                            <el-option label="双人-发起方" value="partner_inviter" />
                            <el-option label="双人-受邀方" value="partner_invitee" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="完成时间">
                        <el-date-picker
                            v-model="tableData.searchParam.timeRange"
                            type="datetimerange"
                            range-separator="至"
                            start-placeholder="开始时间"
                            end-placeholder="结束时间"
                            value-format="x"
                            class="!w-[360px]"
                        />
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="loadList(1)">搜索</el-button>
                        <el-button @click="resetSearch">重置</el-button>
                    </el-form-item>
                </el-form>
            </div>

            <div class="table-panel table-fill-page__panel">
                <table-toolbar title="测评记录" :selectable="false" />
                <div class="table-fill-page__table">
                    <el-table height="100%" :data="tableData.data" size="large" v-loading="tableData.loading">
                        <template #empty>
                            <span>{{ !tableData.loading ? '暂无测评记录' : '' }}</span>
                        </template>
                        <el-table-column prop="record_id" label="记录ID" width="90" />
                        <el-table-column prop="member_id" label="会员ID" width="90" />
                        <el-table-column prop="test_id" label="量表" width="80">
                            <template #default="{ row }">#{{ row.test_id }}</template>
                        </el-table-column>
                        <el-table-column label="模式" width="120">
                            <template #default="{ row }">
                                <el-tag :type="modeTag(row.mode)" effect="plain">{{ modeMap[row.mode] || row.mode }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="result_name" label="测评结果" min-width="160" show-overflow-tooltip>
                            <template #default="{ row }">{{ row.result_name || '—' }}</template>
                        </el-table-column>
                        <el-table-column prop="total_score" label="总分" width="80" align="center" />
                        <el-table-column label="状态" width="100" align="center">
                            <template #default="{ row }">
                                <el-tag :type="statusTag(row.status)" effect="plain">{{ statusMap[row.status] || row.status }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="来源" width="90" align="center">
                            <template #default="{ row }">{{ sourceMap[row.source] || row.source }}</template>
                        </el-table-column>
                        <el-table-column label="完成时间" width="170" align="center">
                            <template #default="{ row }">{{ fmtTime(row.create_time) }}</template>
                        </el-table-column>
                        <el-table-column label="操作" width="90" align="right" fixed="right">
                            <template #default="{ row }">
                                <el-button link type="primary" @click="openDetail(row)">详情</el-button>
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

        <el-dialog v-model="detail.visible" title="测评记录详情" width="760px" top="6vh">
            <div v-loading="detail.loading" style="max-height: 66vh; overflow-y: auto;">
                <template v-if="detail.data">
                    <el-descriptions :column="2" border size="small" class="mb-[12px]">
                        <el-descriptions-item label="记录ID">{{ detail.data.record.id }}</el-descriptions-item>
                        <el-descriptions-item label="会员ID">{{ detail.data.record.member_id }}</el-descriptions-item>
                        <el-descriptions-item label="量表">{{ detail.data.test ? `#${detail.data.test.test_id} ${detail.data.test.display_name || ''}` : `#${detail.data.record.test_id}` }}</el-descriptions-item>
                        <el-descriptions-item label="模式">{{ modeMap[detail.data.record.mode] || detail.data.record.mode }}</el-descriptions-item>
                        <el-descriptions-item label="结果">{{ detail.data.record.result_name || '—' }}</el-descriptions-item>
                        <el-descriptions-item label="总分">{{ detail.data.record.total_score }}</el-descriptions-item>
                        <el-descriptions-item label="完成时间" :span="2">{{ fmtTime(detail.data.record.create_time) }}</el-descriptions-item>
                    </el-descriptions>

                    <div v-if="testMap[detail.data.record.test_id]" class="text-[13px] text-[var(--el-text-color-secondary)] mb-[12px]">
                        主题包：{{ detail.data.test?.theme_key || '—' }}
                    </div>

                    <div class="text-[14px] font-medium mb-[8px]">作答明细</div>
                    <div v-for="(a, i) in (detail.data.record.answers || [])" :key="i" class="ceping-line">
                        第{{ a.no }}题：{{ a.optKey }}
                    </div>

                    <template v-if="detail.data.ai_report">
                        <div class="text-[14px] font-medium mt-[14px] mb-[8px]">AI 解读</div>
                        <el-tag :type="detail.data.ai_report.status === 'success' ? 'success' : 'danger'" effect="plain" class="mb-[8px]">
                            {{ detail.data.ai_report.status === 'success' ? '生成成功' : detail.data.ai_report.status }}
                        </el-tag>
                        <div class="ceping-result-block whitespace-pre-wrap">{{ detail.data.ai_report.content || detail.data.ai_report.fail_reason || '—' }}</div>
                    </template>

                    <template v-if="(detail.data.unlock_logs || []).length">
                        <div class="text-[14px] font-medium mt-[14px] mb-[8px]">解锁记录</div>
                        <div v-for="(log, i) in detail.data.unlock_logs" :key="i" class="ceping-line">
                            {{ log.channel }} · {{ log.action }} · {{ fmtTime(log.create_time) }}
                        </div>
                    </template>
                </template>
            </div>
        </el-dialog>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref } from 'vue'
import type { FormInstance } from 'element-plus'
import TablePagination from '@/components/table-pagination/index.vue'
import TableToolbar from '@/components/table-toolbar/index.vue'
import { useTablePageHeight } from '@/hooks/useTablePageHeight'
import { getRecordList, getRecordInfo } from '../../../api/record'

defineOptions({ name: 'CepingRecordList' })

const { pageRef, pageStyle } = useTablePageHeight()

const modeMap: Record<string, string> = {
    single: '单人测评',
    partner_inviter: '双人-发起方',
    partner_invitee: '双人-受邀方'
}
const statusMap: Record<string, string> = {
    completed: '已完成',
    waiting_partner: '等待配对',
    paired: '已配对',
    canceled: '已取消'
}
const sourceMap: Record<string, string> = {
    home: '首页',
    category: '分类',
    share: '分享',
    poster: '海报',
    invite: '双人邀请'
}
const testMap = {} as Record<number, string>

const modeTag = (m: string) => m === 'single' ? '' : 'warning'
const statusTag = (s: string) => s === 'completed' || s === 'paired' ? 'success' : (s === 'canceled' ? 'danger' : 'info')

const pad = (n: number) => String(n).padStart(2, '0')
const fmtTime = (ts: number) => {
    if (!ts) return '—'
    const d = new Date(Number(ts) * 1000)
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
}

const searchFormRef = ref<FormInstance>()
const tableData = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: false,
    data: [] as any[],
    searchParam: {
        test_id: undefined as number | undefined,
        member_id: undefined as number | undefined,
        mode: '',
        timeRange: null as [number, number] | null
    }
})

const buildParams = () => {
    const p: Record<string, any> = {
        page: tableData.page,
        limit: tableData.limit,
        test_id: tableData.searchParam.test_id || 0,
        member_id: tableData.searchParam.member_id || 0,
        mode: tableData.searchParam.mode
    }
    if (tableData.searchParam.timeRange && tableData.searchParam.timeRange.length === 2) {
        p.create_time = [
            Math.floor(Number(tableData.searchParam.timeRange[0]) / 1000),
            Math.floor(Number(tableData.searchParam.timeRange[1]) / 1000)
        ]
    }
    return p
}

const loadList = (page: number = 1) => {
    tableData.loading = true
    tableData.page = page
    getRecordList(buildParams()).then(res => {
        tableData.data = res.data.data || []
        tableData.total = res.data.total
    }).finally(() => {
        tableData.loading = false
    })
}
loadList()

const resetSearch = () => {
    tableData.searchParam = { test_id: undefined, member_id: undefined, mode: '', timeRange: null }
    loadList(1)
}

const detail = reactive({ visible: false, loading: false, data: null as any })
const openDetail = (row: any) => {
    detail.visible = true
    detail.loading = true
    detail.data = null
    getRecordInfo(row.record_id).then(res => {
        detail.data = res.data
    }).finally(() => {
        detail.loading = false
    })
}
</script>

<style lang="scss" scoped>
.ceping-line {
    font-size: 13px;
    line-height: 1.9;
    color: var(--el-text-color-regular);
}
.ceping-result-block {
    background: var(--el-fill-color-light);
    border-radius: 6px;
    padding: 10px 12px;
    font-size: 13px;
    line-height: 1.7;
}
</style>
