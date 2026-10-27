<template>
    <div class="niucloud-container">
        <el-card shadow="never">
            <el-form ref="formRef" :model="form" :rules="rules" label-width="100px" class="max-w-2xl">
                {{FORM_ITEMS}}
                <el-form-item>
                    <el-button type="primary" :loading="state.loading" @click="onSubmit">保存</el-button>
                    <el-button @click="router.back()">返回</el-button>
                </el-form-item>
            </el-form>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import type { FormInstance, FormRules } from 'element-plus'
import { get{{MODULE_PASCAL}}Info, add{{MODULE_PASCAL}}, edit{{MODULE_PASCAL}} } from '@/addon/{{KEY}}/api/{{MODULE}}'

const route = useRoute()
const router = useRouter()
const formRef = ref<FormInstance>()

const state = reactive({
    loading: false,
    id: (route.query.id as string) || ''
})

const form = reactive({
    {{FORM_STATE}}
})

const rules: FormRules = {
    {{FORM_RULES}}
}

const getInfo = async () => {
    if (!state.id) return
    const res = await get{{MODULE_PASCAL}}Info(Number(state.id))
    Object.assign(form, res.data)
}

const onSubmit = async () => {
    await formRef.value?.validate()
    state.loading = true
    try {
        state.id
            ? await edit{{MODULE_PASCAL}}({ ...form, id: Number(state.id) })
            : await add{{MODULE_PASCAL}}(form)
        router.back()
    } finally {
        state.loading = false
    }
}

onMounted(getInfo)
</script>
