<template>
	<view :style="{ paddingTop: statusBarHeight + 'px' }" class="ubar">
		<view class="ubar-row">
			<!-- 圆形头像：点击气泡选择 -->
			<view class="av-wrap" @click="openAvatar">
				<view class="av">
					<text class="av-emoji">{{ avatarEmoji }}</text>
				</view>

				<!-- 头像气泡层 -->
				<view v-if="avatarOpen" class="pop-mask" @click.stop="avatarOpen = false"></view>
				<view v-if="avatarOpen" class="pop" @click.stop>
					<view class="pop-arrow"></view>
					<text class="pop-title">选择头像</text>
					<view class="pop-grid">
						<view
							v-for="a in presets"
							:key="a.key"
							class="pop-cell"
							:class="{ on: a.key === avatarKey, disabled: avSaving }"
							@click="pickAvatar(a)"
						>
							<text>{{ a.emoji }}</text>
						</view>
					</view>
					<text class="pop-hint">点选即保存 · 仅使用站内预设形象</text>
				</view>
			</view>

			<!-- 昵称：点击弹出设置窗 -->
			<view class="uinfo">
				<view class="nick-row" @click="openNick">
					<text class="nick">{{ nickname }}</text>
				</view>
				<text class="usub">{{ greet }} · 认识自己的 50 种方式</text>
			</view>
		</view>

		<!-- 昵称设置窗 -->
		<view v-if="nickOpen" class="sheet-mask" @click="closeNick">
			<view class="sheet" @click.stop>
				<view class="sheet-head">
					<text class="sheet-title">设置昵称</text>
					<text class="sheet-x" @click="closeNick">✕</text>
				</view>
				<view class="nick-edit">
					<input
						v-model="nickDraft"
						class="nick-input"
						:focus="nickOpen"
						maxlength="10"
						placeholder="2-10 个字"
						placeholder-class="nick-ph"
					/>
					<button class="rand-btn" @click="randomNick">换一个</button>
				</view>
				<text class="sheet-hint">昵称仅在爱测评站内展示，不会获取微信昵称</text>
				<button class="cp-btn cp-btn-primary save-btn" :disabled="nickSaving" @click="saveNick">保存</button>
			</view>
		</view>
	</view>
</template>

<script setup lang="ts">
/**
 * 顶部用户信息卡：圆形头像 + 昵称 + 问候副标题
 * - 点头像：气泡弹出预设头像，点选即保存并关闭
 * - 点昵称：底部弹窗设置昵称
 */
import { ref, onMounted } from 'vue'
import useMemberStore from '@/stores/member'
import { apiProfile, apiProfileUpdate } from '@/addon/ceping/api/ceping'
import { greeting, navMetrics } from '@/addon/ceping/utils/theme'

const { statusBarHeight } = navMetrics()
const greet = greeting()

const nickname = ref('测评爱好者')
const avatarKey = ref('')
const avatarEmoji = ref('🐻')
const presets = ref<AnyObject[]>([])

const avatarOpen = ref(false)
const avSaving = ref(false)
const nickOpen = ref(false)
const nickDraft = ref('')
const nickSaving = ref(false)

function applyProfile(data: AnyObject) {
	nickname.value = data.nickname || '测评爱好者'
	avatarKey.value = data.avatar_key || ''
	presets.value = data.avatar_presets || presets.value
	const hit = (presets.value || []).find((a: AnyObject) => a.key === avatarKey.value)
	avatarEmoji.value = hit ? hit.emoji : '🐻'
}

async function loadProfile() {
	try {
		const res: any = await apiProfile()
		applyProfile(res.data)
	} catch (e) {}
}

function openAvatar() {
	avatarOpen.value = true
}

async function pickAvatar(a: AnyObject) {
	if (avSaving.value) return
	if (a.key === avatarKey.value) {
		avatarOpen.value = false
		return
	}
	avSaving.value = true
	try {
		const res: any = await apiProfileUpdate({ avatar_key: a.key })
		applyProfile(res.data)
		avatarOpen.value = false
		uni.showToast({ title: '头像已更新', icon: 'none' })
	} catch (e) {
	} finally {
		avSaving.value = false
	}
}

function openNick() {
	nickDraft.value = nickname.value
	nickOpen.value = true
}

function closeNick() {
	nickOpen.value = false
}

/** 随机昵称：词库与后端默认昵称规则一致（形容词+的+动物） */
const NICK_ADJ = ['透明', '潮湿', '野生', '半熟', '漂浮', '静音', '微醺', '松弛', '偏蓝', '温热', '反光', '失重', '限量', '慢热', '多云', '匿名']
const NICK_ANIMAL = ['水豚', '橘猫', '章鱼', '树懒', '河豚', '海鸥', '柴犬', '水母', '袋鼠', '企鹅', '浣熊', '刺猬', '海獭', '飞蛾', '柴鱼', '鸵鸟']

function randomNick() {
	let name = nickDraft.value
	for (let i = 0; i < 8 && name === nickDraft.value; i++) {
		const adj = NICK_ADJ[Math.floor(Math.random() * NICK_ADJ.length)]
		const animal = NICK_ANIMAL[Math.floor(Math.random() * NICK_ANIMAL.length)]
		name = `${adj}的${animal}`
	}
	nickDraft.value = name
}

async function saveNick() {
	const name = nickDraft.value.trim()
	if (name.length < 2 || name.length > 10) {
		uni.showToast({ title: '昵称需 2-10 个字', icon: 'none' })
		return
	}
	nickSaving.value = true
	try {
		const res: any = await apiProfileUpdate({ nickname: name })
		applyProfile(res.data)
		nickOpen.value = false
		uni.showToast({ title: '昵称已更新', icon: 'none' })
	} catch (e) {
	} finally {
		nickSaving.value = false
	}
}

onMounted(() => {
	// 首次启动登录未完成时跳过，由页面 onShow 登录后调用 refresh()
	if (useMemberStore().token) loadProfile()
})

defineExpose({ refresh: loadProfile })
</script>

<style lang="scss" scoped>
@import '@/addon/ceping/styles/theme.scss';

.ubar {
	flex-shrink: 0;
	background: linear-gradient(180deg, #ffffff 0%, #f6f7fb 100%);
	padding-right: 40rpx;
	padding-bottom: 20rpx;
	padding-left: 40rpx;
}
.ubar-row {
	display: flex;
	align-items: center;
}

/* 圆形头像 */
.av-wrap {
	position: relative;
	flex-shrink: 0;
	margin-right: 24rpx;
}
.av {
	width: 96rpx;
	height: 96rpx;
	border-radius: 50%;
	background: linear-gradient(135deg, #ffe7cd, #ffcfa6);
	box-shadow: 0 6rpx 18rpx rgba(255, 160, 90, .32);
	display: flex;
	align-items: center;
	justify-content: center;
}
.av-emoji {
	font-size: 48rpx;
	line-height: 1;
}

.uinfo {
	flex: 1;
	min-width: 0;
}
.nick-row {
	display: flex;
	align-items: center;
}
.nick {
	font-size: 36rpx;
	font-weight: 900;
	color: #1c2233;
	max-width: 480rpx;
	overflow: hidden;
	white-space: nowrap;
	text-overflow: ellipsis;
}
.usub {
	display: block;
	margin-top: 8rpx;
	font-size: 23rpx;
	color: #8a90a0;
}

/* 头像气泡 */
.pop-mask {
	position: fixed;
	inset: 0;
	z-index: 300;
	background: transparent;
}
.pop {
	position: absolute;
	top: calc(100% + 18rpx);
	left: 0;
	z-index: 301;
	width: 560rpx;
	background: #fff;
	box-shadow: 0 16rpx 48rpx rgba(25, 35, 70, .18);
	border: 2rpx solid rgba(28, 34, 51, .06);
	padding: 24rpx 24rpx 20rpx;
}
.pop-arrow {
	position: absolute;
	top: -10rpx;
	left: 36rpx;
	width: 20rpx;
	height: 20rpx;
	background: #fff;
	border-left: 2rpx solid rgba(28, 34, 51, .06);
	border-top: 2rpx solid rgba(28, 34, 51, .06);
	transform: rotate(45deg);
}
.pop-title {
	display: block;
	font-size: 26rpx;
	font-weight: 800;
	color: #1c2233;
	margin-bottom: 18rpx;
}
.pop-grid {
	display: flex;
	flex-wrap: wrap;
}
.pop-cell {
	width: 80rpx;
	height: 80rpx;
	margin: 0 8rpx 12rpx 0;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 40rpx;
	background: #f6f7fb;
	border: 2rpx solid transparent;
}
.pop-cell:nth-child(6n) {
	margin-right: 0;
}
.pop-cell.on {
	border-color: #3b5bdb;
	background: #eef1fe;
}
.pop-cell.disabled {
	opacity: .5;
}
.pop-hint {
	display: block;
	font-size: 20rpx;
	color: #a5abba;
	margin-top: 4rpx;
}

/* 昵称设置窗 */
.sheet-mask {
	position: fixed;
	inset: 0;
	z-index: 310;
	background: rgba(12, 15, 26, .5);
	display: flex;
	align-items: flex-end;
}
.sheet {
	width: 100%;
	background: #fff;
	padding: 36rpx 32rpx calc(40rpx + env(safe-area-inset-bottom));
}
.sheet-head {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 28rpx;
}
.sheet-title {
	font-size: 36rpx;
	font-weight: 900;
	color: #1c2233;
}
.sheet-x {
	font-size: 32rpx;
	color: #9aa1b3;
	padding: 8rpx 12rpx;
}
.nick-edit {
	display: flex;
	gap: 16rpx;
}
.nick-input {
	flex: 1;
	min-width: 0;
	height: 88rpx;
	border: 2rpx solid #e8eaf2;
	padding: 0 24rpx;
	font-size: 30rpx;
	color: #1c2233;
}
.nick-ph {
	color: #b6bccb;
}
.rand-btn {
	flex-shrink: 0;
	height: 88rpx;
	line-height: 88rpx;
	padding: 0 28rpx;
	margin: 0;
	border: 2rpx solid #3b5bdb;
	background: #fff;
	color: #3b5bdb;
	font-size: 28rpx;
	font-weight: 700;
}
.rand-btn::after {
	border: 0;
}
.sheet-hint {
	display: block;
	font-size: 22rpx;
	color: #a5abba;
	margin: 16rpx 0 8rpx;
}
.save-btn {
	margin-top: 20rpx;
}
</style>
