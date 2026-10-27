<template>
	<view class="cp-root page">
		<view :style="{ paddingTop: statusBarHeight + 'px' }" class="nav">
			<view class="nav-row" :style="{ height: navBarHeight + 'px' }">
				<text class="nav-title">全部分类</text>
			</view>
			<cp-search-entry class="nav-search" />
		</view>

		<scroll-view scroll-y class="body" :show-scrollbar="false">
			<view v-if="loading" class="tip">加载中…</view>

			<view v-for="g in groups" :key="g.key" class="cat-block">
				<view class="cat-head" @click="openCategory(g.key)">
					<text class="cp-caticon cat-emoji" :class="catIconClass(g.key)" :style="catIconStyle(g.key)"></text>
					<text class="cat-name">{{ g.name }}</text>
					<text class="cat-count">{{ g.tests.length }} 个 ›</text>
				</view>
				<scroll-view scroll-x class="cat-scroll" :show-scrollbar="false">
					<view
						v-for="t in g.tests"
						:key="t.test_id"
						class="mini-card cp-card"
						@click="openIntro(t.test_id)"
					>
						<view class="mini-cover">
							<cp-cover :cover="t.cover" :theme-key="t.theme_key" :test-id="t.test_id" :ico="''" />
						</view>
						<text class="mini-name">{{ t.short_name }}</text>
						<text class="mini-meta">{{ t.question_count }}题 · {{ t.joined_count }}人</text>
					</view>
				</scroll-view>
			</view>

			<view class="bottom-space"></view>
		</scroll-view>

		<cp-tabbar active="cats" />
	</view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import CpTabbar from '@/addon/ceping/components/cp-tabbar.vue'
import CpCover from '@/addon/ceping/components/cp-cover.vue'
import CpSearchEntry from '@/addon/ceping/components/cp-search-entry.vue'
import { apiCategories, apiCategory } from '@/addon/ceping/api/ceping'
import { ensureLogin } from '@/addon/ceping/utils/auth'
import { navMetrics, catIconClass, catIconStyle } from '@/addon/ceping/utils/theme'

const { statusBarHeight, navBarHeight } = navMetrics()
const groups = ref<AnyObject[]>([])
const loading = ref(true)

function openCategory(key: string) {
	uni.navigateTo({ url: `/addon/ceping/pages/category/index?category=${key}` })
}
function openIntro(id: number) {
	uni.navigateTo({ url: `/addon/ceping/pages/intro/index?id=${id}` })
}

onLoad(async () => {
	// no query needed
	await ensureLogin()
	try {
		const catRes: any = await apiCategories()
		const cats: AnyObject[] = catRes.data || []
		const lists = await Promise.all(cats.map((c) => apiCategory(c.key).catch(() => null)))
		groups.value = cats.map((c, i) => ({
			...c,
			tests: (lists[i] as any)?.data?.tests || []
		}))
	} catch (e) {
	} finally {
		loading.value = false
	}
})
</script>

<style lang="scss" scoped>
@import '@/addon/ceping/styles/theme.scss';
@import '@/addon/ceping/styles/cat-iconfont.scss';

.page {
	height: 100vh;
	background: var(--app);
	display: flex;
	flex-direction: column;
	overflow: hidden;
}
.nav {
	flex-shrink: 0;
	padding: 0 28rpx 20rpx;
	background: #fff;
}
.nav-row {
	display: flex;
	align-items: center;
}
.nav-search {
	margin-top: 8rpx;
}
.body {
	flex: 1;
	min-height: 0;
}
.nav-title {
	font-size: 44rpx;
	font-weight: 900;
	color: #1c2233;
}
.tip {
	text-align: center;
	color: #a5abba;
	font-size: 26rpx;
	padding: 80rpx 0;
}
.cat-block {
	margin-top: 28rpx;
}
.cat-head {
	display: flex;
	align-items: center;
	padding: 0 40rpx 18rpx;
}
.cat-emoji {
	font-size: 40rpx;
	margin-right: 14rpx;
}
.cat-name {
	font-size: 34rpx;
	font-weight: 900;
	color: #1c2233;
}
.cat-count {
	margin-left: auto;
	font-size: 24rpx;
	color: #8a90a0;
}
.cat-scroll {
	white-space: nowrap;
	padding: 0 28rpx;
}
.mini-card {
	display: inline-block;
	width: 240rpx;
	margin-right: 20rpx;
	overflow: hidden;
	vertical-align: top;
	box-shadow: 0 8rpx 24rpx rgba(25, 35, 70, .07);
}
.mini-cover {
	height: 180rpx;
}
.mini-name {
	display: block;
	padding: 16rpx 18rpx 0;
	font-size: 27rpx;
	font-weight: 800;
	color: #1c2233;
	white-space: normal;
	line-height: 1.35;
}
.mini-meta {
	display: block;
	padding: 8rpx 18rpx 20rpx;
	font-size: 21rpx;
	color: #a5abba;
}
.bottom-space {
	height: 200rpx;
}
</style>
