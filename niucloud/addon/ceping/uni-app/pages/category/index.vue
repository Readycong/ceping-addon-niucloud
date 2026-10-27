<template>
	<view class="cp-root page">
		<view :style="{ paddingTop: statusBarHeight + 'px' }" class="nav">
			<view class="nav-back" @click="back">‹</view>
			<text v-if="meta.key" class="cp-caticon nav-cat-ico" :class="catIconClass(meta.key)" :style="catIconStyle(meta.key)"></text>
			<text class="nav-title">{{ meta.name }}</text>
		</view>

		<scroll-view scroll-y class="body" :show-scrollbar="false">
			<view v-if="loading" class="tip">加载中…</view>
			<view v-else-if="!tests.length" class="tip">该分类暂无测评</view>

			<view v-else class="list">
				<view v-for="t in tests" :key="t.test_id" class="row cp-card" @click="openIntro(t.test_id)">
					<view class="row-cover">
						<cp-cover :cover="t.cover" :theme-key="t.theme_key" :test-id="t.test_id" :ico="''" />
					</view>
					<view class="row-body">
						<text class="row-name">{{ t.display_name }}</text>
						<view class="row-tags">
							<text v-for="tag in (t.tags || []).slice(0, 3)" :key="tag" class="mini-tag">{{ tag }}</text>
						</view>
						<text class="row-meta">{{ t.question_count }}题 · {{ t.est_minutes }}分钟 · {{ t.joined_count }}人测过</text>
					</view>
					<text v-if="t.is_partner" class="partner-flag">双人</text>
				</view>
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
import { apiCategory } from '@/addon/ceping/api/ceping'
import { ensureLogin } from '@/addon/ceping/utils/auth'
import { navMetrics, catIconClass, catIconStyle } from '@/addon/ceping/utils/theme'

const { statusBarHeight } = navMetrics()
const meta = ref<AnyObject>({ key: '', name: '' })
const tests = ref<AnyObject[]>([])
const loading = ref(true)

function back() {
	uni.navigateBack({ fail: () => uni.reLaunch({ url: '/addon/ceping/pages/cats/index' }) })
}
function openIntro(id: number) {
	uni.navigateTo({ url: `/addon/ceping/pages/intro/index?id=${id}` })
}

onLoad(async (q: AnyObject = {}) => {
	await ensureLogin()
	try {
		const res: any = await apiCategory(q.category || '')
		meta.value = res.data.meta || { key: '', name: '分类' }
		tests.value = res.data.tests || []
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
	display: flex;
	align-items: center;
	gap: 20rpx;
	padding-bottom: 20rpx;
	padding-left: 24rpx;
	padding-right: 40rpx;
	background: #fff;
}
.body {
	flex: 1;
	min-height: 0;
}
.nav-back {
	width: 72rpx;
	height: 72rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 52rpx;
	color: #3c4256;
	line-height: 1;
}
.nav-title {
	font-size: 40rpx;
	font-weight: 900;
	color: #1c2233;
}
.nav-cat-ico {
	font-size: 38rpx;
}
.tip {
	text-align: center;
	color: #a5abba;
	font-size: 26rpx;
	padding: 100rpx 0;
}
.list {
	padding: 24rpx 28rpx;
}
.row {
	position: relative;
	display: flex;
	margin-bottom: 24rpx;
	padding: 20rpx;
	box-shadow: 0 8rpx 28rpx rgba(25, 35, 70, .06);
}
.row-cover {
	width: 180rpx;
	height: 180rpx;
	flex-shrink: 0;
}
.row-body {
	flex: 1;
	padding-left: 24rpx;
	overflow: hidden;
}
.row-name {
	display: block;
	font-size: 30rpx;
	font-weight: 800;
	color: #1c2233;
	line-height: 1.4;
}
.row-tags {
	margin: 12rpx 0;
}
.mini-tag {
	display: inline-block;
	font-size: 20rpx;
	color: #3b5bdb;
	background: #eef1fe;
	padding: 4rpx 12rpx;
	margin: 0 10rpx 8rpx 0;
}
.row-meta {
	font-size: 21rpx;
	color: #a5abba;
}
.partner-flag {
	position: absolute;
	top: 0;
	right: 0;
	background: #ef6d8c;
	color: #fff;
	font-size: 20rpx;
	font-weight: 800;
	padding: 6rpx 14rpx;
}
.bottom-space {
	height: 200rpx;
}
</style>
