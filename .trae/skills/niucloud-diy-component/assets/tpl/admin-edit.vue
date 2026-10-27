<template>
    <!-- ===== 内容属性（editTab == 'content'）===== -->
    <div class="content-wrap" v-show="diyStore.editTab == 'content'">
        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">{{TITLE}}</h3>
            <el-form label-width="90px" class="px-[10px]">
                <!-- 业务属性直接绑定 diyStore.editComponent.字段 -->
                <el-form-item label="显示数量">
                    <el-input-number v-model="diyStore.editComponent.count" :min="1" :max="20" />
                </el-form-item>

                <!-- 链接字段用系统 diy-link 组件 -->
                <el-form-item label="跳转链接">
                    <diy-link v-model="diyStore.editComponent.link" />
                </el-form-item>

                <!-- 列表型数据示例（按需保留，无则整段删除） -->
                <p class="text-sm text-gray-400 mb-[10px]">拖动可调整顺序</p>
                <div ref="listBoxRef">
                    <div v-for="(item, index) in diyStore.editComponent.list" :key="item.id"
                         class="item-wrap p-[10px] pb-0 relative border border-dashed border-gray-300 mb-[16px]">
                        <el-form-item label="名称">
                            <el-input v-model.trim="item.title" clearable maxlength="20" />
                        </el-form-item>
                        <el-form-item label="链接">
                            <diy-link v-model="item.link" />
                        </el-form-item>
                        <div class="del absolute cursor-pointer z-[2] top-[-8px] right-[-8px]"
                             v-show="diyStore.editComponent.list.length > 1"
                             @click="diyStore.editComponent.list.splice(index, 1)">
                            <icon name="element CircleCloseFilled" color="#bbb" size="20px" />
                        </div>
                    </div>
                </div>
                <el-button class="w-full" @click="addItem">添加一项</el-button>
            </el-form>
        </div>
    </div>

    <!-- ===== 样式属性（editTab == 'style'）===== -->
    <div class="style-wrap" v-show="diyStore.editTab == 'style'">
        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">文字设置</h3>
            <el-form label-width="80px" class="px-[10px]">
                <el-form-item label="文字颜色">
                    <el-color-picker v-model="diyStore.editComponent.textColor" />
                </el-form-item>
            </el-form>
        </div>
        <!-- 公共样式（边距/背景/圆角等）由框架注入，必须保留 -->
        <slot name="style"></slot>
    </div>
</template>

<script lang="ts" setup>
import useDiyStore from '@/stores/modules/diy'
import { ref, onMounted, nextTick } from 'vue'
import Sortable from 'sortablejs'

const diyStore = useDiyStore()

// 忽略的公共属性（在右侧样式面板中不展示），无则空数组。
// 可选值：pageBgColor componentBgUrl componentBgColor marginTop marginBottom marginBoth topRounded bottomRounded
diyStore.editComponent.ignore = []

// 组件校验（保存/切换页面时执行）：code=false 时阻止并提示
diyStore.editComponent.verify = (index: number) => {
    const res = { code: true, message: '' }
    diyStore.value[index].list.forEach((item: any) => {
        if (item.title == '') {
            res.code = false
            res.message = '名称不能为空'
        }
    })
    return res
}

// 列表项补齐 id
diyStore.editComponent.list.forEach((item: any) => {
    if (!item.id) item.id = diyStore.generateRandom()
})

const addItem = () => {
    diyStore.editComponent.list.push({
        id: diyStore.generateRandom(),
        title: '',
        link: { name: '' }            // 关键：链接初始值必须是 {name:''}，不能写 []
    })
}

// 列表拖拽排序
const listBoxRef = ref()
onMounted(() => {
    nextTick(() => {
        Sortable.create(listBoxRef.value, {
            group: 'item-wrap',
            animation: 200
        })
    })
})
</script>
