<template>
	<view :class="['cp-root', 'th-' + themeKey, 'page']">
		<view v-if="loading" class="loading">加载中…</view>

		<template v-else-if="record">
			<!-- 结果头图 -->
			<view class="hero">
				<cp-cover class="hero-cover" :cover="record.cover" :theme-key="record.theme_key" :test-id="record.test_id" :ico="''" />
				<view class="hero-spacer" :style="{ height: 'calc(' + statusBarHeight + 'px + 88rpx)' }"></view>
				<view class="hero-inner">
					<text class="hero-test">{{ record.short_name }}</text>
					<text v-if="!waiting" class="hero-result">{{ record.result_name }}</text>
					<text v-if="!waiting" class="hero-summary">{{ record.result?.summary }}</text>
					<text v-else class="hero-result">等待 TA 作答中…</text>
				</view>
			</view>

			<!-- 固定悬浮关闭导航 -->
			<view :style="{ paddingTop: statusBarHeight + 'px' }" class="top-nav">
				<view class="back" @click="goHome">×</view>
			</view>

			<view class="content">
				<!-- 等待配对 -->
				<view v-if="waiting" class="wait-card">
					<text class="wait-text">把邀请发给 TA，TA 答完后双方会同时看到契合度报告。</text>
					<button class="cp-btn cp-btn-primary" @click="goInvite">查看 / 分享我的邀请</button>
				</view>

				<template v-else>
					<!-- 分布图 -->
					<view class="panel">
						<text class="panel-title">你的分布图</text>

						<!-- category -->
						<view v-if="detail.model === 'category'">
							<view v-for="row in catRows" :key="row.key" class="bar-row">
								<view class="bar-top">
									<text class="bar-name" :class="{ hit: row.hit }">{{ row.name }}</text>
									<text class="bar-count" :class="{ hit: row.hit }">{{ row.count }} 票</text>
								</view>
								<view class="bar-track">
									<view class="bar-fill" :style="{ width: row.pct + '%', background: row.color || 'var(--pri)' }"></view>
								</view>
							</view>
							<text v-if="detail.gap !== null && detail.gap !== undefined" class="chart-note">
								领先第二名 {{ detail.gap }} 票{{ detail.gap === 0 ? '，并列项按题库顺序判定' : '' }}
							</text>
						</view>

						<!-- threshold -->
						<view v-else-if="detail.model === 'threshold'">
							<view class="gauge-num">
								<text class="gauge-total">{{ detail.total }}</text>
								<text class="gauge-unit">分</text>
							</view>
							<view class="band-row">
								<view
									v-for="b in (chart.bands || [])"
									:key="b.key"
									class="band-cell"
									:class="{ hit: detail.band && b.key === detail.band.key }"
								>
									<text class="band-name">{{ b.name }}</text>
									<text class="band-range">{{ b.min }}-{{ b.max }}</text>
								</view>
							</view>
						</view>

						<!-- dimension -->
						<view v-else-if="detail.model === 'dimension'">
							<view v-for="ax in (chart.axes || [])" :key="ax.key" class="axis-block">
								<text class="axis-name">{{ ax.name }}</text>
								<view class="axis-poles">
									<view
										v-for="p in ax.poles"
										:key="p.key"
										class="pole"
										:class="{ hit: detail.axes[ax.key] === p.key }"
									>
										<text class="pole-label">{{ p.label }}</text>
										<text class="pole-count">{{ (detail.counts[ax.key] || {})[p.key] || 0 }} 票</text>
									</view>
								</view>
							</view>
						</view>

						<!-- partner -->
						<view v-else-if="detail.model === 'partner'">
							<view class="pair-score">
								<text class="pair-num">{{ detail.pairScore }}</text>
								<text class="pair-unit">契合分</text>
							</view>
							<view class="pair-stats">
								<view class="pair-stat">
									<text class="ps-num">{{ detail.sync }}/30</text>
									<text class="ps-label">节奏同步</text>
								</view>
								<view class="pair-stat">
									<text class="ps-num">{{ formatWarmth(detail.warmth) }}/30</text>
									<text class="ps-label">关系温度</text>
								</view>
								<view class="pair-stat">
									<text class="ps-num">{{ detail.sameCount }}/{{ detail.totalCount }}</text>
									<text class="ps-label">选择一致</text>
								</view>
							</view>
							<view v-if="(detail.diffQuestions || []).length" class="diff-box">
								<text class="diff-title">你们最容易不同步的两题</text>
								<text v-for="(q, i) in detail.diffQuestions" :key="i" class="diff-q">· {{ q }}</text>
							</view>
						</view>
					</view>

					<!-- 完整解读 -->
					<view class="panel">
						<text class="panel-title">结果解读</text>
						<text class="detail-text">{{ record.result?.detail }}</text>
						<view class="traits">
							<text v-for="tr in record.result?.traits || []" :key="tr" class="cp-chip">{{ tr }}</text>
						</view>
					</view>

					<!-- 成长建议 -->
					<view v-if="record.result?.growthTip" class="growth">
						<text class="growth-label">🌱 成长小贴士</text>
						<text class="growth-text">{{ record.result.growthTip }}</text>
					</view>

					<!-- AI 深度报告 -->
					<view v-if="record.ai && record.ai.enabled" class="panel ai-panel">
						<text class="panel-title">AI 深度报告 <text class="ai-badge">AI</text></text>

						<view v-if="ai.content" class="ai-content">
							<text v-if="ai.content.title" class="ai-title">{{ ai.content.title }}</text>
							<view v-for="(s, i) in ai.content.sections || []" :key="i" class="ai-section">
								<text class="ai-h">{{ s.heading }}</text>
								<text class="ai-p">{{ s.text }}</text>
							</view>
							<view v-if="(ai.content.advices || []).length" class="ai-advices">
								<text v-for="(a, i) in ai.content.advices" :key="i" class="ai-a">✦ {{ a }}</text>
							</view>
							<text v-if="ai.content.nonDiagnostic" class="cp-disclaimer">{{ ai.content.nonDiagnostic }}</text>
						</view>

						<template v-else>
							<text class="ai-desc">结合你的每一道选择，生成约 1000 字的专属深度分析与行动建议。</text>
							<button v-if="ai.status === 'failed'" class="cp-btn cp-btn-primary" @click="startAi">生成失败，点击重试</button>
							<button v-else-if="aiBusy" class="cp-btn cp-btn-primary" disabled>{{ aiStatusText }}</button>
							<button v-else class="cp-btn cp-btn-primary" @click="startAi">
								{{ record.ai.unlocked ? '生成 AI 深度报告' : '看一段视频，解锁 AI 深度报告' }}
							</button>
						</template>
					</view>

					<!-- 免责声明 -->
					<view class="disclaimer">
						<text class="cp-disclaimer">{{ disclaimer }}</text>
					</view>
				</template>

				<view class="footer-space"></view>
			</view>

			<!-- 底部操作 -->
			<view v-if="!waiting" class="cta-bar">
				<button class="cp-btn cp-btn-primary" @click="goPoster">生成专属海报</button>
				<view class="dual-row">
					<button class="cp-btn cp-btn-ghost dual-btn" @click="retest">再测一次</button>
					<button class="cp-btn cp-btn-ghost dual-btn" @click="goHome">回首页</button>
				</view>
			</view>
		</template>
	</view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import CpCover from '@/addon/ceping/components/cp-cover.vue'
import { apiRecord, apiAdVerify, apiAiRequest, apiAiPoll } from '@/addon/ceping/api/ceping'
import { ensureLogin } from '@/addon/ceping/utils/auth'
import { navMetrics } from '@/addon/ceping/utils/theme'

const { statusBarHeight } = navMetrics()
const loading = ref(true)
const record = ref<AnyObject | null>(null)
const themeKey = ref('wave-healing')
const aiBusy = ref(false)
const aiStatusText = ref('生成中…')
let pollTimer: any = null

const detail = computed<AnyObject>(() => record.value?.score_detail || {})
const chart = computed<AnyObject>(() => record.value?.chart || {})
const waiting = computed(() => record.value?.status === 'waiting_partner')
const ai = computed<AnyObject>(() => record.value?.ai || {})
const disclaimer = computed(() => {
	const c = record.value?.compliance || {}
	return c.note || '本测试为娱乐性质内容，结果不代表专业心理评估或医学诊断，不作为职业或临床依据。'
})

const catRows = computed(() => {
	const counts: Record<string, number> = detail.value.categories || {}
	const cats: AnyObject[] = chart.value.categories || []
	const total = Object.values(counts).reduce((s: number, v) => s + Number(v), 0) || 1
	return cats.map((c) => {
		const count = counts[c.key] || 0
		return {
			key: c.key,
			name: c.name,
			count,
			pct: Math.round((count / total) * 100),
			color: c.color,
			hit: c.key === record.value?.result_key
		}
	})
})

function formatWarmth(v: number): string {
	return String(Math.round(Number(v) || 0))
}

async function load(recordId: number) {
	try {
		const res: any = await apiRecord(recordId)
		record.value = res.data
		themeKey.value = res.data.theme_key || 'wave-healing'
		if (res.data.ai?.status === 'ready') {
			// content 已在详情中
		}
	} catch (e) {
		uni.showToast({ title: '记录不存在', icon: 'none' })
	} finally {
		loading.value = false
	}
}

function startAi() {
	if (aiBusy.value) return
	// 广告门：未解锁先模拟激励视频 5 秒倒计时
	if (!record.value.ai.unlocked) {
		let left = 5
		aiBusy.value = true
		aiStatusText.value = `模拟激励视频 ${left}s`
		const t = setInterval(async () => {
			left -= 1
			if (left > 0) {
				aiStatusText.value = `模拟激励视频 ${left}s`
				return
			}
			clearInterval(t)
			try {
				await apiAdVerify(record.value.record_id)
				record.value.ai.unlocked = true
				await requestAi()
			} catch (e) {
				aiBusy.value = false
			}
		}, 1000)
		return
	}
	aiBusy.value = true
	requestAi()
}

async function requestAi() {
	try {
		aiStatusText.value = 'AI 正在分析你的答案…'
		const res: any = await apiAiRequest(record.value.record_id)
		if (res.data.status === 'ready') {
			applyAi(res.data)
			return
		}
		if (res.data.status === 'failed') {
			uni.showToast({ title: res.data.fail_reason || '生成失败，请稍后再试', icon: 'none' })
			aiBusy.value = false
			return
		}
		startPoll()
	} catch (e: any) {
		aiBusy.value = false
	}
}

function startPoll() {
	let times = 0
	pollTimer = setInterval(async () => {
		times += 1
		try {
			const res: any = await apiAiPoll(record.value.record_id)
			if (res.data.status === 'ready') {
				clearInterval(pollTimer)
				applyAi(res.data)
			} else if (res.data.status === 'failed' || times > 20) {
				clearInterval(pollTimer)
				aiBusy.value = false
				uni.showToast({ title: '生成超时，请稍后重试', icon: 'none' })
			}
		} catch (e) {
			clearInterval(pollTimer)
			aiBusy.value = false
		}
	}, 1500)
}

function applyAi(data: AnyObject) {
	record.value.ai.status = 'ready'
	record.value.ai.content = data.content
	aiBusy.value = false
}

function goPoster() {
	uni.navigateTo({ url: `/addon/ceping/pages/poster/index?record_id=${record.value.record_id}` })
}
function retest() {
	uni.redirectTo({ url: `/addon/ceping/pages/intro/index?id=${record.value.test_id}` })
}
function goHome() {
	uni.reLaunch({ url: '/addon/ceping/pages/home/index' })
}
function goInvite() {
	if (record.value.pair?.token) {
		uni.redirectTo({ url: `/addon/ceping/pages/invite/index?token=${record.value.pair.token}` })
	} else {
		uni.reLaunch({ url: '/addon/ceping/pages/history/index' })
	}
}

onLoad(async (q: AnyObject = {}) => {
	await ensureLogin()
	load(Number(q.record_id))
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
	min-height: 620rpx;
	padding-bottom: 60rpx;
}
.hero-cover {
	position: absolute;
	inset: 0;
	opacity: .9;
}
.hero-spacer {
	flex-shrink: 0;
}
.top-nav {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	z-index: 60;
	display: flex;
	align-items: center;
	pointer-events: none;
}
.back {
	pointer-events: auto;
	width: 88rpx;
	height: 88rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 52rpx;
	color: #fff;
	line-height: 1;
	background: rgba(0, 0, 0, .3);
}
.hero-inner {
	position: relative;
	z-index: 2;
	padding: 80rpx 44rpx 0;
}
.hero-test {
	display: block;
	font-size: 24rpx;
	font-weight: 700;
	color: rgba(255, 255, 255, .85);
	background: rgba(0, 0, 0, .28);
	padding: 8rpx 20rpx;
	align-self: flex-start;
	margin-bottom: 24rpx;
	width: fit-content;
}
.hero-result {
	display: block;
	font-size: 68rpx;
	font-weight: 900;
	line-height: 1.25;
	color: #fff;
	text-shadow: 0 4rpx 16rpx rgba(0, 0, 0, .35);
}
.hero-summary {
	display: block;
	margin-top: 18rpx;
	font-size: 28rpx;
	line-height: 1.6;
	color: rgba(255, 255, 255, .92);
	text-shadow: 0 2rpx 8rpx rgba(0, 0, 0, .3);
}
.content {
	position: relative;
	margin-top: -32rpx;
	background: var(--bg);
	padding: 8rpx 28rpx 0;
}
.panel {
	background: var(--card);
	padding: 36rpx 32rpx;
	margin-top: 24rpx;
}
.panel-title {
	display: block;
	font-size: 34rpx;
	font-weight: 900;
	color: var(--tx);
	margin-bottom: 28rpx;
}
.panel-title::before {
	content: '';
	display: inline-block;
	width: 8rpx;
	height: 30rpx;
	background: var(--pri);
	margin-right: 14rpx;
	vertical-align: -2rpx;
}
.chart-note {
	display: block;
	margin-top: 20rpx;
	font-size: 23rpx;
	color: var(--sub2);
}
.bar-row {
	margin-bottom: 26rpx;
}
.bar-top {
	display: flex;
	justify-content: space-between;
	margin-bottom: 10rpx;
}
.bar-name {
	font-size: 27rpx;
	font-weight: 700;
	color: var(--sub2);
}
.bar-name.hit {
	color: var(--tx);
	font-size: 30rpx;
}
.bar-count {
	font-size: 24rpx;
	color: var(--sub2);
}
.bar-count.hit {
	color: var(--pri);
	font-weight: 900;
}
.bar-track {
	height: 22rpx;
	background: var(--bdr);
}
.bar-fill {
	height: 100%;
	transition: width .4s;
}
.gauge-num {
	display: flex;
	align-items: baseline;
	justify-content: center;
	padding: 20rpx 0 36rpx;
}
.gauge-total {
	font-size: 110rpx;
	font-weight: 900;
	color: var(--pri);
	line-height: 1;
}
.gauge-unit {
	font-size: 30rpx;
	color: var(--sub2);
	margin-left: 12rpx;
}
.band-row {
	display: flex;
}
.band-cell {
	flex: 1;
	display: flex;
	flex-direction: column;
	align-items: center;
	padding: 18rpx 4rpx;
	background: var(--bg);
	border: 2rpx solid var(--bdr);
}
.band-cell.hit {
	background: var(--pri);
	border-color: var(--pri);
}
.band-name {
	font-size: 22rpx;
	font-weight: 800;
	color: var(--sub2);
}
.band-range {
	font-size: 19rpx;
	color: var(--sub2);
	margin-top: 6rpx;
}
.band-cell.hit .band-name,
.band-cell.hit .band-range {
	color: var(--card);
}
.axis-block {
	margin-bottom: 30rpx;
}
.axis-name {
	display: block;
	font-size: 26rpx;
	font-weight: 800;
	color: var(--tx);
	margin-bottom: 14rpx;
}
.axis-poles {
	display: flex;
	gap: 20rpx;
}
.pole {
	flex: 1;
	display: flex;
	justify-content: space-between;
	padding: 20rpx 24rpx;
	background: var(--bg);
	border: 2rpx solid var(--bdr);
}
.pole.hit {
	border-color: var(--pri);
	background: var(--acc);
}
.th-persona-archive .pole.hit,
.th-tarot-night .pole.hit {
	background: rgba(255, 255, 255, .08);
}
.pole-label {
	font-size: 26rpx;
	font-weight: 700;
	color: var(--sub2);
}
.pole.hit .pole-label {
	color: var(--tx);
	font-weight: 900;
}
.pole-count {
	font-size: 24rpx;
	color: var(--sub2);
}
.pole.hit .pole-count {
	color: var(--pri);
	font-weight: 900;
}
.pair-score {
	display: flex;
	align-items: baseline;
	justify-content: center;
	padding: 16rpx 0 30rpx;
}
.pair-num {
	font-size: 130rpx;
	font-weight: 900;
	color: var(--pri);
	line-height: 1;
}
.pair-unit {
	font-size: 28rpx;
	color: var(--sub2);
	margin-left: 14rpx;
}
.pair-stats {
	display: flex;
	margin-bottom: 28rpx;
}
.pair-stat {
	flex: 1;
	display: flex;
	flex-direction: column;
	align-items: center;
}
.ps-num {
	font-size: 34rpx;
	font-weight: 900;
	color: var(--tx);
}
.ps-label {
	font-size: 22rpx;
	color: var(--sub2);
	margin-top: 8rpx;
}
.diff-box {
	background: var(--bg);
	padding: 24rpx;
	border: 2rpx dashed var(--bdr);
}
.diff-title {
	display: block;
	font-size: 25rpx;
	font-weight: 800;
	color: var(--tx);
	margin-bottom: 12rpx;
}
.diff-q {
	display: block;
	font-size: 25rpx;
	line-height: 1.7;
	color: var(--sub2);
}
.detail-text {
	display: block;
	font-size: 28rpx;
	line-height: 1.9;
	color: var(--tx);
	opacity: .9;
}
.traits {
	margin-top: 24rpx;
}
.growth {
	margin-top: 24rpx;
	padding: 32rpx;
	background: var(--acc);
}
.th-persona-archive .growth,
.th-tarot-night .growth {
	background: rgba(255, 255, 255, .06);
}
.growth-label {
	display: block;
	font-size: 27rpx;
	font-weight: 900;
	color: var(--pri);
	margin-bottom: 14rpx;
}
.growth-text {
	display: block;
	font-size: 28rpx;
	line-height: 1.8;
	color: var(--tx);
}
.ai-panel {
	background: var(--card);
}
.ai-badge {
	font-size: 20rpx;
	font-weight: 900;
	color: var(--card);
	background: var(--pri);
	padding: 4rpx 12rpx;
	margin-left: 8rpx;
}
.ai-desc {
	display: block;
	font-size: 25rpx;
	color: var(--sub2);
	line-height: 1.7;
	margin-bottom: 24rpx;
}
.ai-title {
	display: block;
	font-size: 34rpx;
	font-weight: 900;
	color: var(--tx);
	margin-bottom: 20rpx;
}
.ai-section {
	margin-bottom: 24rpx;
}
.ai-h {
	display: block;
	font-size: 28rpx;
	font-weight: 800;
	color: var(--pri);
	margin-bottom: 10rpx;
}
.ai-p {
	display: block;
	font-size: 27rpx;
	line-height: 1.85;
	color: var(--tx);
}
.ai-advices {
	margin-top: 8rpx;
	padding: 24rpx;
	background: var(--acc);
}
.th-persona-archive .ai-advices,
.th-tarot-night .ai-advices {
	background: rgba(255, 255, 255, .06);
}
.ai-a {
	display: block;
	font-size: 26rpx;
	line-height: 1.9;
	color: var(--tx);
}
.disclaimer {
	padding: 32rpx 12rpx;
}
.wait-card {
	background: var(--card);
	padding: 48rpx 32rpx;
	margin-top: 24rpx;
}
.wait-text {
	display: block;
	font-size: 28rpx;
	line-height: 1.8;
	color: var(--tx);
	margin-bottom: 32rpx;
}
.footer-space {
	height: 260rpx;
}
.cta-bar {
	position: fixed;
	left: 0;
	right: 0;
	bottom: 0;
	z-index: 50;
	padding: 18rpx 36rpx calc(20rpx + env(safe-area-inset-bottom));
	background: var(--card);
	border-top: 2rpx solid var(--bdr);
}
.dual-row {
	display: flex;
	gap: 20rpx;
	margin-top: 16rpx;
}
.dual-btn {
	flex: 1;
}
</style>
