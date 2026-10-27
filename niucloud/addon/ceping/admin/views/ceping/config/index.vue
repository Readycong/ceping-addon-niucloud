<template>
    <div class="main-container">
        <div class="table-fill-page__card !block">
            <el-form :model="form" label-width="150px" v-loading="loading" class="ceping-config-form" style="max-width: 760px;">
                <div class="ceping-config-group">
                    <div class="ceping-config-group__title">AI 深度解读</div>
                    <el-form-item label="启用 AI 解读">
                        <el-switch v-model="form.ai_enabled" :active-value="1" :inactive-value="0" />
                        <span class="ceping-tip">关闭后小程序端隐藏 AI 入口</span>
                    </el-form-item>
                    <el-form-item label="API Base URL">
                        <el-input v-model.trim="form.ai_base_url" placeholder="https://api.openai.com/v1，兼容 /chat/completions 的服务均可" />
                    </el-form-item>
                    <el-form-item label="API Key">
                        <el-input v-model.trim="form.ai_api_key" placeholder="sk-..." show-password />
                    </el-form-item>
                    <el-form-item label="模型名称">
                        <el-input v-model.trim="form.ai_model" placeholder="如 gpt-4o-mini / deepseek-chat" />
                    </el-form-item>
                    <el-form-item label="超时时间（秒）">
                        <el-input-number v-model="form.ai_timeout" :min="5" :max="120" />
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" :loading="saving" @click="save">保存配置</el-button>
                    </el-form-item>
                </div>

                <div class="ceping-config-group">
                    <div class="ceping-config-group__title">激励视频广告</div>
                    <el-form-item label="启用广告解锁">
                        <el-switch v-model="form.ad_enabled" :active-value="1" :inactive-value="0" />
                        <span class="ceping-tip">开发环境未配置广告时自动放行，无需看广告</span>
                    </el-form-item>
                    <el-form-item label="激励视频位 ID">
                        <el-input v-model.trim="form.ad_unit_id" placeholder="微信小程序广告位 adUnitId" />
                    </el-form-item>
                </div>

                <div class="ceping-config-group">
                    <div class="ceping-config-group__title">业务参数</div>
                    <el-form-item label="双人邀请有效期（秒）">
                        <el-input-number v-model="form.invite_expire" :min="300" :step="3600" />
                        <span class="ceping-tip">默认 86400（24 小时）</span>
                    </el-form-item>
                    <el-form-item label="草稿保留（小时）">
                        <el-input-number v-model="form.draft_expire_h" :min="1" :max="168" />
                    </el-form-item>
                    <el-form-item label="热门量表编号">
                        <el-select v-model="form.hot_test_ids" multiple filterable allow-create default-first-option placeholder="输入 1-50 编号后回车" class="!w-[360px]">
                            <el-option v-for="n in 50" :key="n" :label="String(n)" :value="n" />
                        </el-select>
                        <span class="ceping-tip">首页热门位，最多 8 个</span>
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" :loading="saving" @click="save">保存配置</el-button>
                    </el-form-item>
                </div>
            </el-form>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getCepingConfig, setCepingConfig } from '../../../api/config'

defineOptions({ name: 'CepingConfig' })

const loading = ref(false)
const saving = ref(false)
const form = reactive<Record<string, any>>({
    ai_enabled: 0,
    ai_base_url: '',
    ai_api_key: '',
    ai_model: '',
    ai_timeout: 30,
    prompt_ver: 'v1',
    ad_enabled: 0,
    ad_unit_id: '',
    invite_expire: 86400,
    draft_expire_h: 24,
    hot_test_ids: []
})

onMounted(() => {
    loading.value = true
    getCepingConfig().then(res => {
        Object.assign(form, res.data)
        if (!Array.isArray(form.hot_test_ids)) form.hot_test_ids = []
    }).finally(() => {
        loading.value = false
    })
})

const save = () => {
    saving.value = true
    const payload = { ...form, hot_test_ids: (form.hot_test_ids || []).slice(0, 8).map(Number) }
    setCepingConfig(payload).then(() => {
        ElMessage.success('配置已保存')
    }).finally(() => {
        saving.value = false
    })
}
</script>

<style lang="scss" scoped>
.ceping-config-group {
    background: var(--el-fill-color-light);
    border-radius: 8px;
    padding: 18px 20px 4px;
    margin-bottom: 18px;
}
.ceping-config-group__title {
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 16px;
    padding-left: 8px;
    border-left: 3px solid var(--el-color-primary);
}
.ceping-tip {
    margin-left: 12px;
    font-size: 12px;
    color: var(--el-text-color-secondary);
}
</style>
