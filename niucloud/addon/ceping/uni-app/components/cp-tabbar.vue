<template>
	<view class="dock-wrap">
		<view class="dock">
			<view v-for="tab in tabs" :key="tab.key" class="dock-item" @click="go(tab)">
				<view class="dock-ico" :class="{ on: active === tab.key }" :style="iconStyle(tab.key, active === tab.key)"></view>
				<text class="dock-txt" :class="{ on: active === tab.key }">{{ tab.txt }}</text>
			</view>
		</view>
	</view>
</template>

<script setup lang="ts">
/**
 * 通栏底部 Tab：首页 / 分类 / 历史
 */
const PAGES: Record<string, string> = {
	home: '/addon/ceping/pages/home/index',
	cats: '/addon/ceping/pages/cats/index',
	history: '/addon/ceping/pages/history/index'
}

const tabs = [
	{ key: 'home', txt: '首页' },
	{ key: 'cats', txt: '分类' },
	{ key: 'history', txt: '历史' }
]

const props = defineProps<{ active: string }>()

function svg(path: string, color: string): string {
	const s = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="${color}" stroke-width="2" stroke-linecap="square" stroke-linejoin="miter"><path d="${path}"/></svg>`
	return 'data:image/svg+xml,' + encodeURIComponent(s)
}

const ICONS: Record<string, string> = {
	// remix ri-home-5-line 近似
	home: 'M3 10.5 12 3l9 7.5V21h-6v-6h-6v6H3z',
	// 九宫格
	cats: 'M3 3h7.5v7.5H3zM13.5 3H21v7.5h-7.5zM3 13.5h7.5V21H3zM13.5 13.5H21V21h-7.5z',
	// 历史/时钟
	history: 'M12 3a9 9 0 1 0 8.4 12M12 7v5l3.5 2M12 3v3M12 3h3'
}

function iconStyle(key: string, on: boolean) {
	return `background-image:url("${svg(ICONS[key], on ? '#3b5bdb' : '#9aa1b3')}")`
}

function go(tab: AnyObject) {
	if (tab.key === props.active) return
	uni.reLaunch({ url: PAGES[tab.key] })
}
</script>

<style lang="scss" scoped>
.dock-wrap {
	position: fixed;
	left: 0;
	right: 0;
	bottom: 0;
	z-index: 99;
	background: #fff;
	border-top: 2rpx solid rgba(28, 34, 51, .08);
	padding-bottom: env(safe-area-inset-bottom);
}
.dock {
	display: flex;
	align-items: center;
	height: 104rpx;
}
.dock-item {
	flex: 1;
	height: 100%;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 4rpx;
}
.dock-ico {
	width: 42rpx;
	height: 42rpx;
	background-size: contain;
	background-repeat: no-repeat;
	background-position: center;
}
.dock-txt {
	font-size: 21rpx;
	color: #9aa1b3;
	font-weight: 600;
}
.dock-txt.on {
	color: #3b5bdb;
	font-weight: 800;
}
</style>
