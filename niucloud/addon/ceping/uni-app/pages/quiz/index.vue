<template>
	<view :class="['cp-root', 'th-' + themeKey, 'page']">
		<view v-if="loading" class="loading">加载中…</view>

		<template v-else>
			<!-- 顶部进度 -->
			<view :style="{ paddingTop: statusBarHeight + 'px' }" class="quiz-head">
				<view class="head-row">
					<view class="back" @click="exit">‹</view>
					<text class="head-title">{{ answeredCount }}/{{ total }} · {{ mode === 'partner' ? '双人测评' : '答题中' }}</text>
				</view>
				<view class="progress">
					<view class="progress-in" :style="{ width: progressPct + '%' }"></view>
				</view>
			</view>

			<!-- 单题翻页：flip / swipe / draw -->
			<view
				v-if="variant !== 'scroll'"
				class="pager"
				@touchstart="onTouchStart"
				@touchend="onTouchEnd"
			>
				<!-- draw：未翻牌 -->
				<view v-if="variant === 'draw' && !revealed" class="card-back" @click="revealed = true">
					<text class="card-back-emoji">🔮</text>
					<text class="card-back-txt">第 {{ idx + 1 }} 张牌，点击翻开</text>
				</view>

				<view v-else :key="animKey" :class="['qcard', animClass]">
					<text class="q-no">Q{{ cur.no }}</text>
					<text class="q-stem">{{ cur.stem }}</text>
					<view class="opts">
						<view
							v-for="opt in cur.options"
							:key="opt.key"
							class="opt"
							:class="{ on: answers[cur.no] === opt.key }"
							@click="choose(cur.no, opt.key)"
						>
							<text class="opt-key">{{ opt.key }}</text>
							<text class="opt-text">{{ opt.text }}</text>
						</view>
					</view>
				</view>

				<view class="pager-foot">
					<view v-if="idx > 0" class="prev-btn" @click="prev">上一题</view>
					<text v-else></text>
					<text class="pager-hint">{{ variantHint }}</text>
				</view>
			</view>

			<!-- 长卷：scroll -->
			<scroll-view v-else scroll-y class="longlist" :scroll-into-view="scrollInto" scroll-with-animation>
				<view v-for="(q, qi) in paper.questions" :key="q.no" class="long-q" :id="'q' + q.no">
					<view class="long-q-head">
						<text class="q-no">Q{{ q.no }}</text>
						<text class="long-stem">{{ q.stem }}</text>
					</view>
					<view class="opts">
						<view
							v-for="opt in q.options"
							:key="opt.key"
							class="opt"
							:class="{ on: answers[q.no] === opt.key }"
							@click="choose(q.no, opt.key, qi)"
						>
							<text class="opt-key">{{ opt.key }}</text>
							<text class="opt-text">{{ opt.text }}</text>
						</view>
					</view>
				</view>
				<view class="long-end">— 已经到底啦 —</view>
			</scroll-view>

			<!-- 底部提交 -->
			<view class="cta-bar">
				<button class="cp-btn cp-btn-primary" :disabled="!allAnswered || submitting" @click="submit">
					{{ submitting ? '提交中…' : (allAnswered ? (mode === 'partner' ? '生成我的邀请' : '查看结果') : `还有 ${total - answeredCount} 题未作答`) }}
				</button>
			</view>
		</template>
	</view>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { apiPaper, apiSubmit, apiPartnerInvite, apiPartnerSubmit } from '@/addon/ceping/api/ceping'
import { ensureLogin } from '@/addon/ceping/utils/auth'
import { navMetrics, VARIANT_BY_THEME } from '@/addon/ceping/utils/theme'
import { saveDraft, loadDraft, clearDraft } from '@/addon/ceping/utils/draft'

const { statusBarHeight } = navMetrics()

const loading = ref(true)
const submitting = ref(false)
const paper = ref<AnyObject>({ questions: [] })
const themeKey = ref('wave-healing')
const variant = ref<'flip' | 'swipe' | 'scroll' | 'draw'>('flip')
const mode = ref('single')
const token = ref('')
const testId = ref(0)
const draftExpire = ref(24)

const idx = ref(0)
const revealed = ref(false)
const animKey = ref(0)
const animClass = ref('anim-in')
const scrollInto = ref('')
const answers = reactive<Record<number, string>>({})

let advanceTimer: any = null
let touchX = 0

const total = computed(() => paper.value.question_count || paper.value.questions.length || 0)
const cur = computed(() => paper.value.questions[idx.value] || { options: [] })
const answeredCount = computed(() => Object.keys(answers).length)
const allAnswered = computed(() => answeredCount.value >= total.value && total.value > 0)
const progressPct = computed(() => (total.value ? Math.round((answeredCount.value / total.value) * 100) : 0))
const variantHint = computed(() => {
	if (variant.value === 'draw') return '凭第一直觉抽牌'
	if (variant.value === 'swipe') return '左右滑动切换题目'
	return '选好自动进入下一题'
})

function choose(no: number, key: string, listIdx?: number) {
	answers[no] = key
	persist()
	if (variant.value === 'scroll') {
		// 长卷模式：自动滚到下一题
		if (typeof listIdx === 'number' && listIdx + 1 < paper.value.questions.length) {
			const next = paper.value.questions[listIdx + 1]
			scrollInto.value = ''
			setTimeout(() => {
				scrollInto.value = 'q' + next.no
			}, 30)
		}
		return
	}
	if (variant.value === 'draw') {
		// 翻牌模式：选完短暂停留后翻下一张
		scheduleAdvance(420)
	} else {
		scheduleAdvance(220)
	}
}

function scheduleAdvance(delay: number) {
	if (advanceTimer) clearTimeout(advanceTimer)
	advanceTimer = setTimeout(() => {
		if (idx.value < total.value - 1) {
			animClass.value = 'anim-out'
			setTimeout(() => {
				idx.value += 1
				revealed.value = false
				animKey.value += 1
				animClass.value = 'anim-in'
			}, 140)
		}
	}, delay)
}

function prev() {
	if (idx.value === 0) return
	animClass.value = 'anim-out'
	setTimeout(() => {
		idx.value -= 1
		revealed.value = true
		animKey.value += 1
		animClass.value = 'anim-in'
	}, 120)
}

function onTouchStart(e: AnyObject) {
	touchX = e.changedTouches[0].clientX
}
function onTouchEnd(e: AnyObject) {
	if (variant.value !== 'swipe') return
	const dx = e.changedTouches[0].clientX - touchX
	if (Math.abs(dx) < 50) return
	if (dx < 0) {
		// 向左：仅当后面的题已作答才可前进
		if (idx.value < total.value - 1 && answers[cur.value.no]) {
			animClass.value = 'anim-out'
			setTimeout(() => {
				idx.value += 1
				revealed.value = variant.value !== 'draw'
				animKey.value += 1
				animClass.value = 'anim-in'
			}, 120)
		}
	} else {
		prev()
	}
}

function persist() {
	const arr = paper.value.questions
		.filter((q: AnyObject) => answers[q.no])
		.map((q: AnyObject) => ({ no: q.no, optKey: answers[q.no] }))
	saveDraft(testId.value, mode.value, arr, draftExpire.value)
}

function restoreDraft() {
	const draft = loadDraft(testId.value, mode.value, draftExpire.value)
	if (!draft || !draft.length) return
	draft.forEach((a: AnyObject) => {
		answers[a.no] = a.optKey
	})
	uni.showToast({ title: `已恢复 ${draft.length} 题草稿`, icon: 'none' })
}

function exit() {
	uni.showModal({
		title: '确认退出？',
		content: '作答进度已自动保存草稿，24 小时内回来可继续。',
		confirmText: '退出',
		success: (r) => {
			if (r.confirm) uni.navigateBack({ fail: () => uni.reLaunch({ url: '/addon/ceping/pages/home/index' }) })
		}
	})
}

async function submit() {
	if (!allAnswered.value || submitting.value) return
	const answersArr = paper.value.questions.map((q: AnyObject) => ({ no: q.no, optKey: answers[q.no] }))
	submitting.value = true
	uni.showLoading({ title: '统计中…' })
	try {
		if (mode.value === 'partner') {
			const res: any = await apiPartnerInvite(answersArr, 'intro')
			clearDraft(testId.value, mode.value)
			uni.hideLoading()
			uni.redirectTo({ url: `/addon/ceping/pages/invite/index?token=${res.data.token}` })
		} else if (mode.value === 'partner_join') {
			const res: any = await apiPartnerSubmit(token.value, answersArr)
			clearDraft(testId.value, mode.value)
			uni.hideLoading()
			uni.redirectTo({ url: `/addon/ceping/pages/result/index?record_id=${res.data.record_id}` })
		} else {
			const res: any = await apiSubmit(testId.value, answersArr, 'intro')
			clearDraft(testId.value, mode.value)
			uni.hideLoading()
			uni.redirectTo({ url: `/addon/ceping/pages/result/index?record_id=${res.data.record_id}` })
		}
	} catch (e) {
		uni.hideLoading()
	} finally {
		submitting.value = false
	}
}

onLoad(async (q: AnyObject = {}) => {
	testId.value = Number(q.id)
	mode.value = q.mode || 'single'
	token.value = q.token || ''
	await ensureLogin()
	try {
		const res: any = await apiPaper(testId.value)
		paper.value = res.data
		themeKey.value = res.data.theme_key || 'wave-healing'
		variant.value = VARIANT_BY_THEME[themeKey.value] || 'flip'
		restoreDraft()
	} catch (e) {
		uni.showToast({ title: '试卷加载失败', icon: 'none' })
	} finally {
		loading.value = false
	}
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
.loading {
	padding: 200rpx 0;
	text-align: center;
	color: var(--sub2);
	font-size: 28rpx;
}
.quiz-head {
	padding-left: 24rpx;
	padding-right: 40rpx;
	padding-bottom: 16rpx;
}
.head-row {
	display: flex;
	align-items: center;
	height: 88rpx;
}
.back {
	width: 72rpx;
	height: 72rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 52rpx;
	color: var(--tx);
	line-height: 1;
}
.head-title {
	font-size: 28rpx;
	font-weight: 800;
	color: var(--sub2);
}
.progress {
	height: 8rpx;
	background: var(--bdr);
}
.progress-in {
	height: 100%;
	background: var(--pri);
	transition: width .2s;
}

.pager {
	flex: 1;
	display: flex;
	flex-direction: column;
	padding: 24rpx 40rpx 0;
}
.qcard {
	flex: 1;
	display: flex;
	flex-direction: column;
}
.anim-in {
	animation: qIn .26s ease;
}
.anim-out {
	animation: qOut .14s ease forwards;
}
@keyframes qIn {
	from { opacity: 0; transform: translateX(40rpx); }
	to { opacity: 1; transform: translateX(0); }
}
@keyframes qOut {
	from { opacity: 1; transform: translateX(0); }
	to { opacity: 0; transform: translateX(-40rpx); }
}
.card-back {
	flex: 1;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	background: var(--card);
	border: 4rpx solid var(--bdr);
	min-height: 520rpx;
}
.card-back-emoji {
	font-size: 120rpx;
}
.card-back-txt {
	margin-top: 28rpx;
	font-size: 30rpx;
	font-weight: 800;
	color: var(--sub2);
}
.q-no {
	font-size: 26rpx;
	font-weight: 900;
	color: var(--pri);
	letter-spacing: 2rpx;
}
.q-stem {
	display: block;
	margin: 20rpx 0 40rpx;
	font-size: 48rpx;
	font-weight: 800;
	line-height: 1.5;
	color: var(--tx);
}
.opts {
	display: flex;
	flex-direction: column;
	gap: 22rpx;
}
.opt {
	display: flex;
	align-items: center;
	padding: 30rpx 28rpx;
	background: var(--card);
	border: 2rpx solid var(--bdr);
}
.opt.on {
	background: var(--pri);
	border-color: var(--pri);
}
.opt-key {
	width: 52rpx;
	height: 52rpx;
	flex-shrink: 0;
	display: flex;
	align-items: center;
	justify-content: center;
	border: 2rpx solid var(--sub2);
	font-size: 26rpx;
	font-weight: 800;
	color: var(--sub2);
	margin-right: 24rpx;
}
.opt.on .opt-key {
	background: var(--card);
	color: var(--pri);
	border-color: var(--card);
}
.opt-text {
	flex: 1;
	font-size: 30rpx;
	line-height: 1.55;
	color: var(--tx);
}
.opt.on .opt-text {
	color: var(--card);
	font-weight: 700;
}
.th-office-comic .opt {
	border-width: 3rpx;
}
.pager-foot {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 28rpx 0 20rpx;
}
.prev-btn {
	font-size: 26rpx;
	font-weight: 700;
	color: var(--sub2);
	padding: 12rpx 24rpx;
	border: 2rpx solid var(--bdr);
}
.pager-hint {
	font-size: 22rpx;
	color: var(--sub2);
}

.longlist {
	flex: 1;
	padding: 12rpx 40rpx 0;
}
.long-q {
	padding: 32rpx 0 12rpx;
	border-bottom: 2rpx solid var(--bdr);
}
.long-q-head {
	display: flex;
	gap: 16rpx;
	margin-bottom: 28rpx;
}
.long-stem {
	flex: 1;
	font-size: 34rpx;
	font-weight: 800;
	line-height: 1.5;
	color: var(--tx);
}
.long-end {
	text-align: center;
	color: var(--sub2);
	font-size: 24rpx;
	padding: 48rpx 0 32rpx;
}
.cta-bar {
	padding: 20rpx 36rpx calc(24rpx + env(safe-area-inset-bottom));
	background: var(--card);
	border-top: 2rpx solid var(--bdr);
}
</style>
