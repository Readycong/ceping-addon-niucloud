<template>
	<view :class="['cp-root', 'th-' + themeKey, 'page']">
		<view v-if="loading" class="loading">加载中…</view>

		<template v-else>
			<!-- 头图 -->
			<view class="hero">
				<cp-cover :cover="info.cover" :theme-key="info.theme_key" :test-id="info.test_id" :ico="''" />
			</view>

			<!-- 固定悬浮返回导航 -->
			<view :style="{ paddingTop: statusBarHeight + 'px' }" class="top-nav">
				<view class="back" @click="back">‹</view>
				<text class="hero-cat">{{ info.category_name }}</text>
			</view>

			<!-- 主信息 -->
			<view class="content">
				<text class="title">{{ info.display_name }}</text>
				<view class="tags">
					<text v-for="tag in info.tags || []" :key="tag" class="cp-chip">{{ tag }}</text>
				</view>

				<view class="meta-grid">
					<view class="meta-cell">
						<text class="meta-num">{{ info.question_count }}</text>
						<text class="meta-label">道题</text>
					</view>
					<view class="meta-cell">
						<text class="meta-num">{{ info.est_minutes }}</text>
						<text class="meta-label">分钟</text>
					</view>
					<view class="meta-cell">
						<text class="meta-num">{{ info.joined_count }}</text>
						<text class="meta-label">{{ info.is_partner ? '对配对' : '人测过' }}</text>
					</view>
					<view class="meta-cell">
						<text class="meta-num">{{ modelText }}</text>
						<text class="meta-label">计分方式</text>
					</view>
				</view>

				<view class="block">
					<text class="block-title">这是什么</text>
					<text class="block-text">{{ info.description }}</text>
				</view>

				<view class="block">
					<text class="block-title">作答须知</text>
					<text class="block-text">{{ info.instructions }}</text>
				</view>

				<view class="notice">
					<text class="notice-title">⚠️ 温馨提示</text>
					<text class="cp-disclaimer">{{ disclaimer }}</text>
				</view>

				<view class="footer-space"></view>
			</view>

			<!-- 底部 CTA -->
			<view class="cta-bar">
				<button v-if="info.is_partner" class="cp-btn cp-btn-primary" @click="startQuiz('partner')">
					邀请 TA 一起测
				</button>
				<button v-else class="cp-btn cp-btn-primary" @click="startQuiz('single')">
					开始测评（{{ info.question_count }} 题）
				</button>
			</view>
		</template>
	</view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import CpCover from '@/addon/ceping/components/cp-cover.vue'
import { apiIntro } from '@/addon/ceping/api/ceping'
import { ensureLogin } from '@/addon/ceping/utils/auth'
import { navMetrics } from '@/addon/ceping/utils/theme'

const { statusBarHeight } = navMetrics()
const loading = ref(true)
const info = ref<AnyObject>({})
const themeKey = ref('wave-healing')

const MODEL_MAP: Record<string, string> = {
	category: '类型',
	threshold: '指数',
	dimension: '维度',
	partner: '双人'
}
const modelText = computed(() => MODEL_MAP[info.value.scoring_model] || '测评')
const disclaimer = computed(() => {
	const c = info.value.compliance || {}
	return c.note || '本测试为娱乐性质内容，结果不代表专业心理评估或医学诊断，不作为职业或临床依据。'
})

function back() {
	uni.navigateBack({ fail: () => uni.reLaunch({ url: '/addon/ceping/pages/home/index' }) })
}

function startQuiz(mode: string) {
	uni.navigateTo({ url: `/addon/ceping/pages/quiz/index?id=${info.value.test_id}&mode=${mode}` })
}

onLoad(async (q: AnyObject) => {
	await ensureLogin()
	try {
		const res: any = await apiIntro(Number(q.id))
		info.value = res.data
		themeKey.value = res.data.theme_key || 'wave-healing'
	} catch (e) {
		uni.showToast({ title: '测评不存在或已下线', icon: 'none' })
	} finally {
		loading.value = false
	}
})
</script>

<style lang="scss" scoped>
@import '@/addon/ceping/styles/theme.scss';

.page {
	min-height: 100vh;
	background: var(--bg);
	color: var(--tx);
}
.loading {
	padding: 200rpx 0;
	text-align: center;
	color: var(--sub2);
	font-size: 28rpx;
}
.hero {
	position: relative;
	height: 640rpx;
}
.top-nav {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	z-index: 60;
	display: flex;
	align-items: center;
	padding-right: 32rpx;
	pointer-events: none;
}
.back {
	pointer-events: auto;
	width: 88rpx;
	height: 88rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 56rpx;
	color: #fff;
	line-height: 1;
	background: rgba(0, 0, 0, .3);
}
.hero-cat {
	pointer-events: auto;
	margin-left: 8rpx;
	font-size: 24rpx;
	font-weight: 700;
	color: #fff;
	background: rgba(0, 0, 0, .3);
	padding: 8rpx 22rpx;
}
.content {
	position: relative;
	margin-top: -40rpx;
	background: var(--bg);
	padding: 44rpx 40rpx 0;
}
.title {
	display: block;
	font-size: 62rpx;
	font-weight: 900;
	line-height: 1.25;
	color: var(--tx);
}
.tags {
	margin-top: 24rpx;
}
.meta-grid {
	display: flex;
	margin: 36rpx 0 8rpx;
	background: var(--card);
	border: 2rpx solid var(--bdr);
}
.meta-cell {
	flex: 1;
	display: flex;
	flex-direction: column;
	align-items: center;
	padding: 28rpx 0;
}
.meta-cell + .meta-cell {
	border-left: 2rpx solid var(--bdr);
}
.meta-num {
	font-size: 40rpx;
	font-weight: 900;
	color: var(--pri);
}
.meta-label {
	margin-top: 8rpx;
	font-size: 22rpx;
	color: var(--sub2);
}
.block {
	margin-top: 48rpx;
}
.block-title {
	display: block;
	font-size: 34rpx;
	font-weight: 900;
	color: var(--tx);
	margin-bottom: 18rpx;
}
.block-title::before {
	content: '';
	display: inline-block;
	width: 8rpx;
	height: 30rpx;
	background: var(--pri);
	margin-right: 14rpx;
	vertical-align: -2rpx;
}
.block-text {
	display: block;
	font-size: 28rpx;
	line-height: 1.85;
	color: var(--tx);
	opacity: .88;
}
.notice {
	margin-top: 44rpx;
	padding: 28rpx;
	background: var(--acc);
}
.th-persona-archive .notice,
.th-tarot-night .notice {
	background: rgba(255, 255, 255, .06);
}
.notice-title {
	display: block;
	font-size: 26rpx;
	font-weight: 800;
	color: var(--pri);
	margin-bottom: 12rpx;
}
.footer-space {
	height: 220rpx;
}
.cta-bar {
	position: fixed;
	left: 0;
	right: 0;
	bottom: 0;
	z-index: 50;
	padding: 20rpx 36rpx calc(24rpx + env(safe-area-inset-bottom));
	background: var(--card);
	border-top: 2rpx solid var(--bdr);
}
</style>
