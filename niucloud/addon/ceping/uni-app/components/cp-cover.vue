<template>
	<view class="cp-cover" :style="boxStyle">
		<image v-if="url && !failed" class="cov-img" :src="url" mode="aspectFill" @error="onError" />
		<view class="cov-shade" :style="shadeStyle"></view>
		<text v-if="showNo" class="cov-no">NO.{{ no }}</text>
		<text v-if="ico" class="cov-emoji">{{ ico }}</text>
		<slot />
	</view>
</template>

<script setup lang="ts">
/**
 * 封面：写实摄影图铺底 + 渐变压暗 + NO.xxx 左上 + emoji 角标
 * 图片失败时露出主题渐变底色
 */
import { computed, ref } from 'vue'
import { imgUrl, themeOf } from '@/addon/ceping/utils/theme'

const props = withDefaults(defineProps<{
	cover?: string
	themeKey?: string
	testId?: number
	ico?: string
	showNo?: boolean
}>(), {
	cover: '',
	themeKey: '',
	testId: 0,
	ico: '',
	showNo: true
})

const failed = ref(false)
const url = computed(() => (failed.value ? '' : imgUrl(props.cover)))
const th = computed(() => themeOf(props.themeKey))
const no = computed(() => String(props.testId).padStart(2, '0'))

const boxStyle = computed(() => `background:linear-gradient(135deg,${th.value.bg2},${th.value.cover});`)
const shadeStyle = computed(() => 'background:linear-gradient(180deg,rgba(10,12,22,.34) 0%,rgba(10,12,22,0) 42%,rgba(10,12,22,.42) 100%);')

function onError() {
	failed.value = true
}
</script>

<style lang="scss" scoped>
.cp-cover {
	position: relative;
	width: 100%;
	height: 100%;
	overflow: hidden;
}
.cov-img {
	position: absolute;
	inset: 0;
	width: 100%;
	height: 100%;
}
.cov-shade {
	position: absolute;
	inset: 0;
}
.cov-no {
	position: absolute;
	top: 14rpx;
	left: 16rpx;
	z-index: 2;
	font-size: 20rpx;
	font-weight: 800;
	letter-spacing: 1rpx;
	color: rgba(255, 255, 255, .92);
}
.cov-emoji {
	position: absolute;
	right: 14rpx;
	bottom: 10rpx;
	z-index: 2;
	font-size: 40rpx;
}
</style>
