<template>
	<view class="cp-root page">
		<!-- 固定顶部用户信息卡 -->
		<cp-user-bar ref="userBarRef" />

		<!-- 搜索入口 -->
		<view class="search-entry-wrap">
			<cp-search-entry />
		</view>

		<scroll-view scroll-y class="body" :show-scrollbar="false">
			<!-- 金刚区 -->
			<view class="grid cp-card">
				<view v-for="c in categories" :key="c.key" class="gi" @click="openCategory(c.key)">
					<view class="gi-ico" :style="giStyle(c.key)">
						<text class="cp-caticon gi-cat-ico" :class="catIconClass(c.key)"></text>
					</view>
					<text class="gi-name">{{ catShort(c.name) }}</text>
				</view>
			</view>

			<!-- 热门横滑 -->
			<view v-if="hot.length" class="shead">
				<text class="shead-title">🔥 热门测评</text>
			</view>
			<scroll-view scroll-x class="hotscroll" :show-scrollbar="false">
				<view
					v-for="(t, i) in hot"
					:key="t.test_id"
					class="hotcard"
					:style="{ background: hotGradient(i) }"
					@click="openIntro(t.test_id)"
				>
					<cp-cover class="hot-cover" :cover="t.cover" :theme-key="t.theme_key" :test-id="t.test_id" :ico="''" />
					<text class="hot-top">TOP {{ i + 1 }}</text>
					<view class="hot-info">
						<text class="hot-name">{{ t.short_name }}</text>
						<text class="hot-meta">{{ t.question_count }}题 · {{ t.joined_count }}人测过</text>
					</view>
				</view>
			</scroll-view>

			<!-- 信息流 -->
			<view class="shead">
				<text class="shead-title">全部测评</text>
				<text class="shead-more" @click="goCats">全部分类 ›</text>
			</view>
			<view class="feed">
				<view v-for="t in tests" :key="t.test_id" class="tcard cp-card" @click="openIntro(t.test_id)">
					<view class="tcard-cover">
						<cp-cover :cover="t.cover" :theme-key="t.theme_key" :test-id="t.test_id" :ico="''" />
						<text v-if="t.is_partner" class="partner-flag">双人</text>
					</view>
					<view class="tcard-body">
						<text class="tcard-name">{{ t.display_name }}</text>
						<view class="tcard-tags">
							<text v-for="tag in (t.tags || []).slice(0, 2)" :key="tag" class="mini-tag">{{ tag }}</text>
						</view>
						<text class="tcard-meta">{{ t.question_count }}题 · {{ t.est_minutes }}分钟 · {{ t.joined_count }}人测过</text>
					</view>
				</view>
			</view>

			<view class="bottom-space"></view>
		</scroll-view>

		<cp-tabbar active="home" />
	</view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import CpTabbar from '@/addon/ceping/components/cp-tabbar.vue'
import CpCover from '@/addon/ceping/components/cp-cover.vue'
import CpUserBar from '@/addon/ceping/components/cp-user-bar.vue'
import CpSearchEntry from '@/addon/ceping/components/cp-search-entry.vue'
import { apiHome } from '@/addon/ceping/api/ceping'
import { ensureLogin } from '@/addon/ceping/utils/auth'
import { themeOf, HOT_GRADIENTS, catIconClass } from '@/addon/ceping/utils/theme'

const categories = ref<AnyObject[]>([])
const hot = ref<AnyObject[]>([])
const tests = ref<AnyObject[]>([])
const userBarRef = ref<InstanceType<typeof CpUserBar> | null>(null)

function catShort(name: string): string {
	return name.replace('人格认知', '人格').replace('情感关系', '情感').replace('职场成长', '职场').replace('社交人际', '社交').replace('情绪状态', '情绪').replace('生活消费', '生活').replace('兴趣审美', '审美').replace('玄学脑洞', '玄学')
}

function giStyle(key: string): string {
	const th = themeOf(key)
	return `background:${th.cover}22;color:${th.pri};`
}

function hotGradient(i: number): string {
	return HOT_GRADIENTS[i % HOT_GRADIENTS.length]
}

function openCategory(key: string) {
	uni.navigateTo({ url: `/addon/ceping/pages/category/index?category=${key}` })
}
function openIntro(id: number) {
	uni.navigateTo({ url: `/addon/ceping/pages/intro/index?id=${id}` })
}
function goCats() {
	uni.reLaunch({ url: '/addon/ceping/pages/cats/index' })
}

async function loadHome() {
	try {
		const res: any = await apiHome()
		categories.value = res.data.categories || []
		hot.value = res.data.hot || []
		tests.value = res.data.tests || []
	} catch (e) {}
}

onShow(async () => {
	await ensureLogin()
	loadHome()
	userBarRef.value?.refresh()
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
.search-entry-wrap {
	flex-shrink: 0;
	padding: 0 28rpx 20rpx;
	background: linear-gradient(180deg, #f6f7fb 0%, var(--app) 100%);
}
.body {
	flex: 1;
	min-height: 0;
}

.grid {
	display: flex;
	flex-wrap: wrap;
	margin: 24rpx 28rpx;
	padding: 28rpx 8rpx 12rpx;
	box-shadow: 0 6rpx 28rpx rgba(25, 35, 70, .05);
}
.gi {
	width: 25%;
	display: flex;
	flex-direction: column;
	align-items: center;
	margin-bottom: 24rpx;
}
.gi-ico {
	width: 96rpx;
	height: 96rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	margin-bottom: 10rpx;
}
.gi-cat-ico {
	font-size: 48rpx;
}
.gi-name {
	font-size: 25rpx;
	font-weight: 600;
	color: #454c60;
}

.shead {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 24rpx 40rpx 16rpx;
}
.shead-title {
	font-size: 36rpx;
	font-weight: 900;
	color: #1c2233;
}
.shead-title::before {
	content: '';
	display: inline-block;
	width: 8rpx;
	height: 32rpx;
	background: linear-gradient(180deg, #3b5bdb, #8aa4ff);
	margin-right: 14rpx;
	vertical-align: -2rpx;
}
.shead-more {
	font-size: 24rpx;
	color: #8a90a0;
}

.hotscroll {
	white-space: nowrap;
	padding: 4rpx 28rpx 12rpx;
}
.hotcard {
	position: relative;
	display: inline-block;
	width: 280rpx;
	height: 340rpx;
	margin-right: 20rpx;
	overflow: hidden;
	vertical-align: top;
	box-shadow: 0 12rpx 32rpx rgba(25, 35, 70, .16);
}
.hot-cover {
	position: absolute;
	inset: 0;
	opacity: .55;
}
.hot-top {
	position: absolute;
	top: 20rpx;
	left: 20rpx;
	z-index: 2;
	font-size: 22rpx;
	font-weight: 800;
	color: #fff;
	background: rgba(0, 0, 0, .28);
	padding: 6rpx 16rpx;
}
.hot-info {
	position: absolute;
	left: 20rpx;
	right: 20rpx;
	bottom: 20rpx;
	z-index: 2;
}
.hot-name {
	display: block;
	font-size: 32rpx;
	font-weight: 900;
	color: #fff;
	line-height: 1.35;
	white-space: normal;
	text-shadow: 0 2rpx 8rpx rgba(0, 0, 0, .3);
}
.hot-meta {
	display: block;
	margin-top: 8rpx;
	font-size: 21rpx;
	color: rgba(255, 255, 255, .85);
}

.feed {
	display: flex;
	flex-wrap: wrap;
	justify-content: space-between;
	padding: 0 28rpx;
}
.tcard {
	width: 336rpx;
	margin-bottom: 24rpx;
	overflow: hidden;
	box-shadow: 0 8rpx 28rpx rgba(25, 35, 70, .07);
}
.tcard-cover {
	position: relative;
	height: 210rpx;
}
.partner-flag {
	position: absolute;
	top: 0;
	right: 0;
	z-index: 3;
	background: #ef6d8c;
	color: #fff;
	font-size: 20rpx;
	font-weight: 800;
	padding: 6rpx 14rpx;
}
.tcard-body {
	padding: 20rpx 22rpx 24rpx;
}
.tcard-name {
	display: block;
	font-size: 28rpx;
	font-weight: 800;
	color: #1c2233;
	line-height: 1.4;
}
.tcard-tags {
	margin: 12rpx 0 8rpx;
	min-height: 32rpx;
}
.mini-tag {
	display: inline-block;
	font-size: 20rpx;
	color: #3b5bdb;
	background: #eef1fe;
	padding: 4rpx 12rpx;
	margin-right: 10rpx;
}
.tcard-meta {
	font-size: 21rpx;
	color: #a5abba;
}
.bottom-space {
	height: 180rpx;
}
</style>
