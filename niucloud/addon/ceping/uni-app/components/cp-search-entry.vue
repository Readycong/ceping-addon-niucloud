<template>
	<view class="cp-search-entry" @click="goSearch">
		<text class="se-ico">⌕</text>
		<text class="se-ph">{{ placeholder }}</text>
	</view>
</template>

<script setup lang="ts">
/**
 * 顶部搜索入口占位：点击跳转测评搜索页
 */
withDefaults(defineProps<{ placeholder?: string }>(), {
	placeholder: '搜索你想测的测评 / 关键词'
})

function goSearch() {
	uni.navigateTo({ url: '/addon/ceping/pages/search/index' })
}
</script>

<style lang="scss" scoped>
.cp-search-entry {
	display: flex;
	align-items: center;
	height: 76rpx;
	background: #f2f3f8;
	border: 2rpx solid #e6e8f0;
	padding: 0 24rpx;
}
.se-ico {
	font-size: 38rpx;
	color: #9aa1b3;
	margin-right: 14rpx;
	line-height: 1;
}
.se-ph {
	font-size: 27rpx;
	color: #a5abba;
}
</style>
