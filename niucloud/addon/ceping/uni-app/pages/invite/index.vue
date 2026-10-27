<template>
	<view :class="['cp-root', 'th-' + themeKey, 'page']">
		<view :style="{ paddingTop: statusBarHeight + 'px' }" class="bar">
			<view class="back" @click="back">‹</view>
		</view>

		<scroll-view scroll-y class="body" :show-scrollbar="false">
			<view v-if="loading" class="loading">加载中…</view>

			<view v-else-if="!info" class="loading">邀请不存在或已失效</view>

			<!-- 等待作答 -->
			<view v-else-if="info.status === 'waiting'" class="wrap">
				<view class="invite-cover">
					<cp-cover :cover="info.cover" :theme-key="info.theme_key" :test-id="info.test_id" :show-no="false" />
				</view>
				<view class="inviter-row">
					<text class="inviter-emoji">{{ inviterEmoji }}</text>
					<text class="inviter-name">{{ info.inviter.nickname }}</text>
				</view>

				<text v-if="info.is_inviter" class="title">邀请已发出，等 TA 作答</text>
				<text v-else class="title">{{ info.inviter.nickname }} 邀你一起测</text>
				<text class="sub">{{ info.display_name }} · {{ info.question_count }} 题 · 约 3 分钟</text>

				<view class="token-box">
					<text class="token-label">邀请口令（有效期至 {{ expireText }}）</text>
					<text class="token" selectable>{{ info.token }}</text>
				</view>

				<button v-if="!info.is_inviter" class="cp-btn cp-btn-primary main-btn" @click="startAnswer">
					接受邀请，开始作答
				</button>
				<button v-else class="cp-btn cp-btn-primary main-btn" open-type="share">分享给 TA</button>
				<button class="cp-btn cp-btn-ghost main-btn" @click="copyToken">复制邀请口令</button>
				<text class="tip">TA 答完后，你们会同时收到契合度报告</text>
			</view>

			<!-- 已配对 -->
			<view v-else-if="info.status === 'paired'" class="wrap">
				<view class="invite-cover">
					<cp-cover :cover="info.cover" :theme-key="info.theme_key" :test-id="info.test_id" :show-no="false" />
				</view>
				<text class="title">契合度报告已生成</text>
				<view class="paired-score">
					<text class="paired-num">{{ info.pair_score }}</text>
					<text class="paired-unit">分 · {{ info.result?.name }}</text>
				</view>
				<text class="sub">{{ info.result?.summary }}</text>
				<button class="cp-btn cp-btn-primary main-btn" @click="viewReport">查看完整报告</button>
			</view>

			<!-- 已过期 -->
			<view v-else class="wrap">
				<text class="dead-emoji">⏳</text>
				<text class="title">邀请已过期</text>
				<text class="sub">双人邀请有效期已过，可以重新发起一次。</text>
				<button class="cp-btn cp-btn-primary main-btn" @click="reCreate">重新发起邀请</button>
			</view>

			<view class="bottom-space"></view>
		</scroll-view>
	</view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onLoad, onShareAppMessage } from '@dcloudio/uni-app'
import CpCover from '@/addon/ceping/components/cp-cover.vue'
import { apiPartnerJoin } from '@/addon/ceping/api/ceping'
import { ensureLogin } from '@/addon/ceping/utils/auth'
import { navMetrics, fmtTime } from '@/addon/ceping/utils/theme'

const { statusBarHeight } = navMetrics()
const loading = ref(true)
const info = ref<AnyObject | null>(null)
const themeKey = ref('love-letter')
const inviterEmoji = ref('🐻')
let tokenVal = ''

const expireText = computed(() => (info.value ? fmtTime(info.value.expire_time) : ''))

function back() {
	uni.navigateBack({ fail: () => uni.reLaunch({ url: '/addon/ceping/pages/home/index' }) })
}

async function load() {
	try {
		const res: any = await apiPartnerJoin(tokenVal)
		// 部分拦截器在业务 code!=1 时也会 resolve，需显式识别有效载荷，避免把错误响应当成"已过期"
		if (res?.data?.status) {
			info.value = res.data
			themeKey.value = res.data.theme_key || 'love-letter'
			inviterEmoji.value = res.data.inviter?.avatar_emoji || '🐻'
		} else {
			info.value = null
		}
	} catch (e) {
		info.value = null
	} finally {
		loading.value = false
	}
}

function startAnswer() {
	if (!info.value) return
	uni.navigateTo({
		url: `/addon/ceping/pages/quiz/index?id=${info.value.test_id}&mode=partner_join&token=${tokenVal}`
	})
}

function copyToken() {
	if (!info.value) return
	uni.setClipboardData({
		data: `【爱测评】我邀你一起测「${info.value.display_name}」，口令：${tokenVal}`,
		success: () => uni.showToast({ title: '邀请口令已复制', icon: 'none' })
	})
}

function viewReport() {
	if (!info.value) return
	const rid = info.value.is_inviter ? info.value.inviter_record_id : info.value.my_record_id
	if (rid) {
		uni.redirectTo({ url: `/addon/ceping/pages/result/index?record_id=${rid}` })
	} else {
		uni.reLaunch({ url: '/addon/ceping/pages/history/index' })
	}
}

function reCreate() {
	uni.redirectTo({ url: '/addon/ceping/pages/intro/index?id=7' })
}

onShareAppMessage(() => ({
	title: `我邀你一起测「${info.value?.display_name || '双人契合度'}」，答完看我们的契合报告`,
	path: `/addon/ceping/pages/invite/index?token=${tokenVal}`,
	imageUrl: ''
}))

onLoad(async (q: AnyObject = {}) => {
	tokenVal = q.token || ''
	await ensureLogin()
	load()
})
</script>

<style lang="scss" scoped>
@import '@/addon/ceping/styles/theme.scss';

.page {
	height: 100vh;
	background: var(--bg);
	color: var(--tx);
	display: flex;
	flex-direction: column;
	overflow: hidden;
}
.bar {
	flex-shrink: 0;
}
.body {
	flex: 1;
	min-height: 0;
}
.back {
	width: 88rpx;
	height: 88rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 56rpx;
	color: var(--tx);
	line-height: 1;
}
.loading {
	padding: 200rpx 40rpx;
	text-align: center;
	color: var(--sub2);
	font-size: 28rpx;
}
.wrap {
	padding: 40rpx 48rpx 0;
	display: flex;
	flex-direction: column;
	align-items: center;
}
.bottom-space {
	height: 80rpx;
}
.invite-cover {
	width: 420rpx;
	height: 300rpx;
	box-shadow: 0 16rpx 44rpx rgba(25, 35, 70, .18);
}
.inviter-row {
	display: flex;
	align-items: center;
	margin-top: 32rpx;
}
.inviter-emoji {
	font-size: 44rpx;
	margin-right: 14rpx;
}
.inviter-name {
	font-size: 30rpx;
	font-weight: 800;
	color: var(--tx);
}
.title {
	margin-top: 20rpx;
	font-size: 46rpx;
	font-weight: 900;
	color: var(--tx);
	text-align: center;
}
.sub {
	margin-top: 16rpx;
	font-size: 26rpx;
	line-height: 1.7;
	color: var(--sub2);
	text-align: center;
}
.token-box {
	width: 100%;
	margin: 40rpx 0 12rpx;
	padding: 28rpx;
	background: var(--card);
	border: 2rpx dashed var(--bdr);
}
.token-label {
	display: block;
	font-size: 22rpx;
	color: var(--sub2);
	margin-bottom: 12rpx;
}
.token {
	display: block;
	font-size: 26rpx;
	font-weight: 700;
	color: var(--pri);
	word-break: break-all;
	line-height: 1.6;
}
.main-btn {
	margin-top: 28rpx;
}
.tip {
	margin-top: 24rpx;
	font-size: 22rpx;
	color: var(--sub2);
	text-align: center;
}
.paired-score {
	display: flex;
	align-items: baseline;
	margin: 36rpx 0 8rpx;
}
.paired-num {
	font-size: 110rpx;
	font-weight: 900;
	color: var(--pri);
	line-height: 1;
}
.paired-unit {
	font-size: 28rpx;
	color: var(--sub2);
	margin-left: 16rpx;
}
.dead-emoji {
	font-size: 120rpx;
	margin-bottom: 24rpx;
}
</style>
