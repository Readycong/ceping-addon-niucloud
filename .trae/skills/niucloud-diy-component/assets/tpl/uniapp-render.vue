<template>
    <!-- 根节点 style 只放组件容器自身样式（背景/圆角由公共样式驱动） -->
    <view :style="wrapCss">
        <!-- 业务内容：统一用 diyComponent（已处理装修态/渲染态） -->
        <view class="flex flex-wrap">
            <view v-for="(item, index) in diyComponent.list" :key="index"
                  class="w-1/2 p-[10rpx]" @click="onItemClick(item)">
                <text :style="{ color: diyComponent.textColor }">{{ item.title }}</text>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, watch, onMounted } from 'vue'
import { img } from '@/utils/common'
import useDiyStore from '@/app/stores/diy'

// 固定 props：component 为渲染态数据，index 为装修态组件下标
const props = defineProps(['component', 'index'])
const diyStore = useDiyStore()

// 装修态取下标数据，渲染态取 props 数据
const diyComponent = computed(() => {
    if (diyStore.mode == 'decorate') {
        return diyStore.value[props.index]
    } else {
        return props.component
    }
})

// 公共样式：背景色/渐变/背景图/圆角（与模板 template 字段对应）
const wrapCss = computed(() => {
    let style = 'position:relative;'
    const c = diyComponent.value
    if (c.componentStartBgColor) {
        if (c.componentEndBgColor) {
            style += `background:linear-gradient(${c.componentGradientAngle},${c.componentStartBgColor},${c.componentEndBgColor});`
        } else {
            style += 'background-color:' + c.componentStartBgColor + ';'
        }
    }
    if (c.componentBgUrl) {
        style += `background-image:url('${img(c.componentBgUrl)}');background-size:cover;background-repeat:no-repeat;`
    }
    if (c.topRounded) style += `border-top-left-radius:${c.topRounded * 2}rpx;border-top-right-radius:${c.topRounded * 2}rpx;`
    if (c.bottomRounded) style += `border-bottom-left-radius:${c.bottomRounded * 2}rpx;border-bottom-right-radius:${c.bottomRounded * 2}rpx;`
    if (c.marginTop) style += `margin-top:${c.marginTop * 2}rpx;`
    if (c.marginBottom) style += `margin-bottom:${c.marginBottom * 2}rpx;`
    return style
})

// 点击跳转：装修态不响应
const onItemClick = (item: any) => {
    if (diyStore.mode == 'decorate') return
    diyStore.toRedirect(item.link)
}

// 装修态下属性变化时的刷新（按需）
onMounted(() => {
    if (diyStore.mode == 'decorate') {
        watch(() => diyComponent.value, () => { /* 重算依赖尺寸的内容 */ })
    }
})
</script>

<style lang="scss" scoped></style>
