<template>
	<view class="cp-root page">
		<!-- 固定顶部用户信息卡 -->
		<cp-user-bar ref="userBarRef" />

		<view v-if="loading" class="tip">加载中…</view>
		<view v-else-if="!list.length" class="empty">
			<text class="empty-emoji">🗒️</text>
			<text class="empty-text">还没有测评记录</text>
			<button class="cp-btn cp-btn-primary empty-btn" @click="goHome">去测一个</button>
		</view>

		<scroll-view v-else scroll-y class="list-scroll" :show-scrollbar="false" @scrolltolower="loadMore">
			<view class="list-inner">
				<view class="his-head">
					<text class="his-title">测评历史</text>
					<text class="his-clear" @click="confirmClear">清空全部</text>
				</view>
				<view
					v-for="r in list"
					:key="r.record_id"
					:id="`rec-slot-${r.record_id}`"
					class="rec-slot"
					:style="slotStyle(r)"
				>
					<!-- 左滑露出的删除提示层 -->
					<view class="rec-hint" :class="{ 'hint-on': st(r).hint }">
						<text class="rec-hint-txt">{{ st(r).deleting ? '删除中...' : '拖出删除' }}</text>
					</view>
					<!-- 跟手移动的记录卡片 -->
					<view
						class="rec cp-card rec-fg"
						:style="fgStyle(r)"
						@touchstart="onTouchStart($event, r)"
						@touchmove="onTouchMove($event, r)"
						@touchend="onTouchEnd($event, r)"
						@touchcancel="onTouchEnd($event, r)"
						@click="openRecord(r)"
					>
						<view class="rec-main">
							<view class="rec-cover">
								<cp-cover :cover="r.cover" :theme-key="r.theme_key" :test-id="r.test_id" :show-no="false" />
							</view>
							<view class="rec-body">
								<text class="rec-test">{{ r.display_name }}</text>
								<text class="rec-result">结果：{{ r.result_name }}</text>
								<text class="rec-time">{{ fmtTime(r.create_time) }}</text>
							</view>
							<text class="rec-arrow">›</text>
						</view>
					</view>
				</view>
				<view class="pager-tip">{{ noMore ? '— 没有更多了 —' : '上拉加载更多' }}</view>
				<view class="bottom-space"></view>
			</view>
		</scroll-view>

		<cp-tabbar active="history" />
	</view>
</template>

<script setup lang="ts">
import { ref, reactive, getCurrentInstance } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import CpTabbar from '@/addon/ceping/components/cp-tabbar.vue'
import CpCover from '@/addon/ceping/components/cp-cover.vue'
import CpUserBar from '@/addon/ceping/components/cp-user-bar.vue'
import { apiRecords, apiDeleteRecord, apiClearRecords } from '@/addon/ceping/api/ceping'
import { ensureLogin } from '@/addon/ceping/utils/auth'
import { fmtTime } from '@/addon/ceping/utils/theme'

const list = ref<AnyObject[]>([])
const userBarRef = ref<InstanceType<typeof CpUserBar> | null>(null)
const loading = ref(true)
const page = ref(1)
const noMore = ref(false)
const PAGE_SIZE = 10

/* ---------- 左滑删除：手势状态 ---------- */
const HINT_RATIO = 0.3 // 30% 显示“拖出删除”
const DELETE_RATIO = 0.7 // 70% 直接触发删除
const SLIDE_MS = 220
const RESTORE_MS = 300
const COLLAPSE_MS = 240

// list-inner 左右各 28rpx 内边距，卡片宽度即可视区宽度
const CARD_WIDTH = uni.getSystemInfoSync().windowWidth - uni.upx2px(56)
const instance = getCurrentInstance()

interface SwipeState {
	dx: number // 当前水平位移（<=0）
	startX: number
	startY: number
	startDx: number
	dragging: boolean
	axis: '' | 'x' | 'y'
	hint: boolean // 是否已露出“拖出删除”
	triggered: boolean // 是否已越过 70% 阈值
	deleting: boolean // 删除接口进行中，禁止重复手势
	settled: '' | 'success' | 'fail'
	slideDone: boolean // 滑出动画完成
	animating: boolean
	animDuration: number
	animToken: number
	suppressTap: boolean // 滑动后抑制误触的点击跳转
	collapsing: boolean
	holdHeight: number
}

function createState(): SwipeState {
	return {
		dx: 0,
		startX: 0,
		startY: 0,
		startDx: 0,
		dragging: false,
		axis: '',
		hint: false,
		triggered: false,
		deleting: false,
		settled: '',
		slideDone: false,
		animating: false,
		animDuration: SLIDE_MS,
		animToken: 0,
		suppressTap: false,
		collapsing: false,
		holdHeight: 0
	}
}

const states = reactive<Record<string, SwipeState>>({})
let activeId = ''

function keyOf(r: AnyObject) {
	return String(r.record_id)
}
function st(r: AnyObject): SwipeState {
	const k = keyOf(r)
	if (!states[k]) states[k] = createState()
	return states[k]
}
function animateTo(s: SwipeState, target: number, duration: number) {
	const token = ++s.animToken
	s.animDuration = duration
	s.animating = true
	s.dx = target
	setTimeout(() => {
		if (s.animToken === token) s.animating = false
	}, duration)
}
function fgStyle(r: AnyObject) {
	const s = st(r)
	return `transform: translateX(${s.dx}px); transition: ${s.animating ? `transform ${s.animDuration}ms ease-out` : 'none'};`
}
function slotStyle(r: AnyObject) {
	const s = st(r)
	if (!s.holdHeight) return ''
	return s.collapsing ? 'height:0px;margin-bottom:0px;' : `height:${s.holdHeight}px;`
}

/** 收起其他正在左滑的卡片，同一时间只允许一条处于滑动状态 */
function collapseOthers(currentKey: string) {
	Object.keys(states).forEach((k) => {
		if (k === currentKey) return
		const o = states[k]
		if (o.deleting || o.collapsing) return
		o.dragging = false
		o.axis = ''
		o.hint = false
		o.triggered = false
		o.animating = false
		o.dx = 0
	})
}

function onTouchStart(e: AnyObject, r: AnyObject) {
	const s = st(r)
	if (s.deleting || s.collapsing) return
	const k = keyOf(r)
	if (activeId && activeId !== k) collapseOthers(k)
	activeId = k
	const t = e.touches[0] || e.changedTouches[0]
	s.dragging = true
	s.animating = false
	s.axis = ''
	s.startX = t.clientX
	s.startY = t.clientY
	s.startDx = s.dx
}

function onTouchMove(e: AnyObject, r: AnyObject) {
	const s = st(r)
	if (!s.dragging || s.deleting) return
	const t = e.touches[0]
	const dX = t.clientX - s.startX
	const dY = t.clientY - s.startY
	if (!s.axis) {
		if (Math.abs(dX) < 10 && Math.abs(dY) < 10) return
		s.axis = Math.abs(dX) >= Math.abs(dY) ? 'x' : 'y'
	}
	if (s.axis !== 'x') return
	const dx = Math.max(-CARD_WIDTH, Math.min(0, s.startDx + dX))
	s.dx = dx
	const ratio = -dx / CARD_WIDTH
	s.hint = ratio >= HINT_RATIO
	if (ratio >= DELETE_RATIO && !s.triggered) triggerDelete(r, s)
}

function onTouchEnd(e: AnyObject, r: AnyObject) {
	const s = st(r)
	if (!s.dragging) return
	s.dragging = false
	if (activeId === keyOf(r)) activeId = ''
	// 发生过横向拖动则抑制随后的 click，避免误进详情
	if (s.axis === 'x') {
		s.suppressTap = true
		setTimeout(() => {
			s.suppressTap = false
		}, 120)
	}
	// 已触发删除的卡片由滑出/还原流程接管，未触发则回弹复原
	if (!s.triggered) {
		s.hint = false
		animateTo(s, 0, RESTORE_MS)
	}
}

/** 越过 70%：卡片立即滑出隐藏，进入删除中并调用接口 */
function triggerDelete(r: AnyObject, s: SwipeState) {
	s.triggered = true
	s.deleting = true
	s.hint = true
	animateTo(s, -CARD_WIDTH, SLIDE_MS)
	setTimeout(() => {
		s.slideDone = true
		if (s.settled === 'success') collapseAndRemove(r, s)
	}, SLIDE_MS)

	apiDeleteRecord(r.record_id, { showErrorMessage: false })
		.then(() => {
			s.settled = 'success'
			if (s.slideDone) collapseAndRemove(r, s)
		})
		.catch(() => {
			s.settled = 'fail'
			restoreCard(s)
		})
}

/** 删除成功：折叠行高后销毁卡片，列表自动重排 */
function collapseAndRemove(r: AnyObject, s: SwipeState) {
	if (s.collapsing) return
	const query = uni.createSelectorQuery()
	if (instance?.proxy) query.in(instance.proxy)
	query
		.select(`#rec-slot-${r.record_id}`)
		.boundingClientRect((rect: AnyObject | null) => {
			s.holdHeight = rect ? rect.height : 0
			setTimeout(() => {
				s.collapsing = true
				setTimeout(() => removeRecord(r), COLLAPSE_MS)
			}, 30)
		})
		.exec()
}

function removeRecord(r: AnyObject) {
	const id = r.record_id
	list.value = list.value.filter((x) => x.record_id !== id)
	delete states[String(id)]
	uni.showToast({ title: '删除成功', icon: 'none' })
}

/** 删除失败：还原卡片，保留列表数据 */
function restoreCard(s: SwipeState) {
	uni.showToast({ title: '删除失败，请稍后重试', icon: 'none' })
	s.deleting = false
	s.triggered = false
	s.settled = ''
	s.slideDone = false
	s.hint = false
	animateTo(s, 0, RESTORE_MS)
}

async function load(reset = false) {
	if (reset) {
		page.value = 1
		noMore.value = false
		Object.keys(states).forEach((k) => delete states[k])
	}
	try {
		const res: any = await apiRecords(0, page.value, PAGE_SIZE)
		const data = res.data || {}
		const rows = data.data || []
		list.value = reset ? rows : list.value.concat(rows)
		noMore.value = list.value.length >= (data.total || 0)
		page.value += 1
	} catch (e) {
	} finally {
		loading.value = false
	}
}

function loadMore() {
	if (noMore.value) return
	load(false)
}

function openRecord(r: AnyObject) {
	const s = states[keyOf(r)]
	if (s && (s.deleting || s.suppressTap)) return
	uni.navigateTo({ url: `/addon/ceping/pages/result/index?record_id=${r.record_id}` })
}

function confirmClear() {
	uni.showModal({
		title: '清空全部记录？',
		content: '清空后不可恢复，双人邀请不受影响。',
		confirmText: '全部清空',
		confirmColor: '#e04833',
		success: async (m) => {
			if (!m.confirm) return
			try {
				await apiClearRecords()
				list.value = []
				uni.showToast({ title: '已清空', icon: 'none' })
			} catch (e) {}
		}
	})
}

function goHome() {
	uni.reLaunch({ url: '/addon/ceping/pages/home/index' })
}

onShow(async () => {
	await ensureLogin()
	userBarRef.value?.refresh()
	load(true)
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
.his-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 24rpx 12rpx 20rpx;
}
.his-title {
	font-size: 40rpx;
	font-weight: 900;
	color: #1c2233;
}
.his-clear {
	font-size: 26rpx;
	color: #8a90a0;
}
.tip {
	flex: 1;
	text-align: center;
	color: #a5abba;
	font-size: 26rpx;
	padding: 100rpx 0;
}
.empty {
	flex: 1;
	display: flex;
	flex-direction: column;
	align-items: center;
	padding-top: 200rpx;
}
.empty-emoji {
	font-size: 110rpx;
}
.empty-text {
	margin-top: 24rpx;
	font-size: 28rpx;
	color: #8a90a0;
}
.empty-btn {
	width: 400rpx;
	margin-top: 60rpx;
}
.list-scroll {
	flex: 1;
	min-height: 0;
	width: 100%;
	overflow-x: hidden;
}
/* 边距放在 scroll-view 内层（小程序 scroll-view 自身 padding 会导致右侧溢出） */
.list-inner {
	width: 100%;
	padding: 0 28rpx;
	box-sizing: border-box;
	overflow: hidden;
}
.rec-slot {
	position: relative;
	width: 100%;
	max-width: 100%;
	margin-bottom: 20rpx;
	overflow: hidden;
	box-sizing: border-box;
	transition: height 0.24s ease, margin-bottom 0.24s ease;
}
.rec-hint {
	position: absolute;
	top: 0;
	right: 0;
	bottom: 0;
	left: 0;
	display: flex;
	align-items: center;
	justify-content: flex-end;
	padding-right: 48rpx;
	background: linear-gradient(135deg, #e8523f 0%, #d9362f 100%);
}
.rec-hint-txt {
	font-size: 26rpx;
	font-weight: 700;
	letter-spacing: 2rpx;
	color: #ffffff;
	opacity: 0;
	transition: opacity 0.15s ease;
}
.rec-hint.hint-on .rec-hint-txt {
	opacity: 1;
}
.rec-fg {
	position: relative;
	z-index: 2;
	will-change: transform;
}
.rec {
	width: 100%;
	max-width: 100%;
	display: flex;
	align-items: stretch;
	box-sizing: border-box;
	box-shadow: 0 8rpx 28rpx rgba(25, 35, 70, .06);
}
.rec-main {
	flex: 1;
	display: flex;
	align-items: center;
	padding: 20rpx;
	min-width: 0;
}
.rec-cover {
	width: 120rpx;
	height: 120rpx;
	flex-shrink: 0;
}
.rec-body {
	flex: 1;
	padding: 0 20rpx;
	min-width: 0;
}
.rec-test {
	display: block;
	font-size: 29rpx;
	font-weight: 800;
	color: #1c2233;
	word-break: break-word;
}
.rec-result {
	display: block;
	margin: 10rpx 0;
	font-size: 24rpx;
	color: #3b5bdb;
}
.rec-time {
	display: block;
	font-size: 21rpx;
	color: #a5abba;
}
.rec-arrow {
	font-size: 44rpx;
	color: #c4c9d6;
	padding-right: 8rpx;
}
.pager-tip {
	text-align: center;
	font-size: 22rpx;
	color: #b6bccb;
	padding: 24rpx 0 8rpx;
}
.bottom-space {
	height: 180rpx;
}
</style>
