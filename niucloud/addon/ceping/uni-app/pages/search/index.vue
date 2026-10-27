<template>
	<view class="cp-root page">
		<!-- 标题栏：返回 + 标题，行高对齐微信胶囊 -->
		<view :style="{ paddingTop: statusBarHeight + 'px' }" class="nav">
			<view class="nav-row" :style="{ height: navBarHeight + 'px' }">
				<view class="nav-back" @click="back">‹</view>
				<text class="nav-title">搜索测评</text>
			</view>
		</view>

		<!-- 主搜索区：标题栏下方独立一行（与首页搜索入口同位） -->
		<view class="search-bar">
			<view class="search-box">
				<text class="search-ico">⌕</text>
				<input
					v-model="keyword"
					class="search-input"
					:focus="autofocus"
					confirm-type="search"
					:maxlength="30"
					placeholder="搜索测评名称 / 关键词"
					placeholder-class="search-ph"
					@input="onInput"
					@confirm="doSearch"
				/>
				<text v-if="keyword" class="search-clear" @click="clearKeyword">✕</text>
			</view>
			<text class="search-cancel" @click="back">取消</text>
		</view>

		<scroll-view scroll-y class="body" :show-scrollbar="false">
			<!-- 加载中 -->
			<view v-if="loading" class="tip">搜索中…</view>

			<template v-else>
				<!-- 默认：热门推荐 -->
				<template v-if="!trimmed">
					<view class="shead">
						<text class="shead-title">🔥 热门推荐</text>
					</view>
					<view v-if="tests.length" class="list">
						<view
							v-for="(t, i) in tests"
							:key="t.test_id"
							class="row cp-card"
							@click="openIntro(t.test_id)"
						>
							<text v-if="i < 3" class="rank" :class="'rank-' + (i + 1)">TOP{{ i + 1 }}</text>
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
				</template>

				<!-- 搜索结果 -->
				<template v-else>
					<view v-if="tests.length" class="result-head">
						<text class="result-count">找到 {{ tests.length }} 个与「{{ trimmed }}」相关的测评</text>
					</view>
					<view v-if="tests.length" class="list">
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
					<view v-else class="empty">
						<text class="empty-emoji">🔍</text>
						<text class="empty-txt">没有找到与「{{ trimmed }}」相关的测评</text>
						<text class="empty-sub">换个关键词试试，如 MBTI / 恋爱 / 人格</text>
					</view>
				</template>
			</template>

			<view class="bottom-space"></view>
		</scroll-view>
	</view>
</template>

<script setup lang="ts">
/**
 * 测评搜索页
 * - 进入默认展示 10 个热门测评
 * - 输入关键字 300ms 防抖后动态检索，服务端按匹配度倒序返回最多 10 条
 */
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import CpCover from '@/addon/ceping/components/cp-cover.vue'
import { apiSearch } from '@/addon/ceping/api/ceping'
import { ensureLogin } from '@/addon/ceping/utils/auth'
import { navMetrics } from '@/addon/ceping/utils/theme'

const { statusBarHeight, navBarHeight } = navMetrics()

const keyword = ref('')
const trimmed = computed(() => keyword.value.trim())
const tests = ref<AnyObject[]>([])
const loading = ref(false)
const autofocus = ref(false)

let timer: ReturnType<typeof setTimeout> | null = null
let reqSeq = 0

function onInput() {
	if (timer) clearTimeout(timer)
	timer = setTimeout(() => {
		doSearch()
	}, 300)
}

async function doSearch() {
	const seq = ++reqSeq
	const kw = trimmed.value
	loading.value = true
	try {
		const res: any = await apiSearch(kw)
		// 只采用最后一次输入的结果，避免慢请求覆盖新结果
		if (seq !== reqSeq) return
		tests.value = res.data.tests || []
	} catch (e) {
		if (seq === reqSeq) tests.value = []
	} finally {
		if (seq === reqSeq) loading.value = false
	}
}

function clearKeyword() {
	keyword.value = ''
	if (timer) clearTimeout(timer)
	doSearch()
}

function back() {
	uni.navigateBack({ fail: () => uni.reLaunch({ url: '/addon/ceping/pages/home/index' }) })
}

function openIntro(id: number) {
	uni.navigateTo({ url: `/addon/ceping/pages/intro/index?id=${id}` })
}

onLoad(async () => {
	await ensureLogin()
	autofocus.value = true
	doSearch()
})
</script>

<style lang="scss" scoped>
@import '@/addon/ceping/styles/theme.scss';

.page {
	height: 100vh;
	background: var(--app);
	display: flex;
	flex-direction: column;
	overflow: hidden;
}

/* 标题栏（直角风格） */
.nav {
	flex-shrink: 0;
	background: #fff;
}
.nav-row {
	display: flex;
	align-items: center;
	padding-left: 16rpx;
}
.nav-back {
	width: 64rpx;
	flex-shrink: 0;
	height: 100%;
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

/* 主搜索区：标题栏下方独立一行，位置与首页搜索入口一致 */
.search-bar {
	flex-shrink: 0;
	display: flex;
	align-items: center;
	gap: 24rpx;
	padding: 4rpx 28rpx 24rpx;
	background: #fff;
	border-bottom: 2rpx solid rgba(28, 34, 51, .05);
}
.search-box {
	flex: 1;
	min-width: 0;
	height: 84rpx;
	display: flex;
	align-items: center;
	background: #f2f3f8;
	border: 2rpx solid #e6e8f0;
	padding: 0 24rpx;
}
.search-ico {
	font-size: 40rpx;
	color: #9aa1b3;
	margin-right: 14rpx;
	line-height: 1;
}
.search-input {
	flex: 1;
	min-width: 0;
	height: 100%;
	font-size: 30rpx;
	color: #1c2233;
}
.search-ph {
	color: #a5abba;
	font-size: 28rpx;
}
.search-clear {
	flex-shrink: 0;
	width: 44rpx;
	height: 44rpx;
	line-height: 44rpx;
	text-align: center;
	font-size: 22rpx;
	color: #fff;
	background: #b9bfce;
	margin-left: 12rpx;
}
.search-cancel {
	flex-shrink: 0;
	font-size: 30rpx;
	color: #3b5bdb;
	font-weight: 700;
}

.body {
	flex: 1;
	min-height: 0;
}

.tip {
	text-align: center;
	color: #a5abba;
	font-size: 26rpx;
	padding: 100rpx 0;
}

.shead {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 28rpx 40rpx 16rpx;
}
.shead-title {
	font-size: 34rpx;
	font-weight: 900;
	color: #1c2233;
}
.shead-title::before {
	content: '';
	display: inline-block;
	width: 8rpx;
	height: 30rpx;
	background: linear-gradient(180deg, #3b5bdb, #8aa4ff);
	margin-right: 14rpx;
	vertical-align: -2rpx;
}

.result-head {
	padding: 24rpx 40rpx 4rpx;
}
.result-count {
	font-size: 24rpx;
	color: #8a90a0;
}

.list {
	padding: 16rpx 28rpx 8rpx;
}
.row {
	position: relative;
	display: flex;
	margin-bottom: 24rpx;
	padding: 20rpx;
	box-shadow: 0 8rpx 28rpx rgba(25, 35, 70, .06);
}
.rank {
	position: absolute;
	top: 0;
	left: 0;
	z-index: 3;
	font-size: 18rpx;
	font-weight: 900;
	color: #fff;
	padding: 6rpx 12rpx;
	background: #9aa1b3;
}
.rank-1 { background: #e8442e; }
.rank-2 { background: #f08a1e; }
.rank-3 { background: #e5b53c; }
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

.empty {
	display: flex;
	flex-direction: column;
	align-items: center;
	padding: 140rpx 60rpx 0;
}
.empty-emoji {
	font-size: 88rpx;
	line-height: 1;
}
.empty-txt {
	margin-top: 28rpx;
	font-size: 28rpx;
	font-weight: 700;
	color: #454c60;
}
.empty-sub {
	margin-top: 14rpx;
	font-size: 23rpx;
	color: #a5abba;
}
.bottom-space {
	height: 80rpx;
}
</style>
