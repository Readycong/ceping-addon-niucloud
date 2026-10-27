<template>
	<view class="poster-page">
		<view :style="{ paddingTop: statusBarHeight + 'px' }" class="bar">
			<view class="back" @click="back">‹</view>
			<text class="bar-title">我的测评海报</text>
		</view>

		<scroll-view scroll-y class="canvas-scroll">
			<!-- canvas 必须常驻挂载，绘制完成前用 loading 遮罩覆盖；v-if 会导致 draw 时节点不存在 -->
			<canvas canvas-id="cpPoster" class="poster-canvas" :style="canvasStyle"></canvas>
			<view v-if="loading" class="loading">海报生成中…</view>
			<view v-else class="actions">
				<button class="cp-btn save-btn" @click="save">保存到相册</button>
				<button class="cp-btn cp-btn-ghost" @click="back">返回结果</button>
			</view>
		</scroll-view>
	</view>
</template>

<script setup lang="ts">
/**
 * 结果海报：Canvas 750×1334
 * 直角设计、主题渐变 + emoji 装饰，不引远程图片避免 CORS
 */
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { apiRecord } from '@/addon/ceping/api/ceping'
import { ensureLogin } from '@/addon/ceping/utils/auth'
import { navMetrics, themeOf, fmtDate } from '@/addon/ceping/utils/theme'

const { statusBarHeight } = navMetrics()
const loading = ref(true)
const canvasStyle = ref('width:690rpx;height:1228rpx;')
let record: AnyObject | null = null
let S = 1 // 设计稿 750 宽 -> 实际 px 缩放

function back() {
	uni.navigateBack({ fail: () => uni.reLaunch({ url: '/addon/ceping/pages/home/index' }) })
}

/** 简易确定性 hash，用于伪二维码 */
function hashStr(s: string): number {
	let h = 2166136261
	for (let i = 0; i < s.length; i++) {
		h ^= s.charCodeAt(i)
		h = Math.imul(h, 16777619)
	}
	return h >>> 0
}

function wrapText(ctx: any, text: string, x: number, y: number, maxW: number, lineH: number, maxLines = 0): number {
	let line = ''
	let lines = 0
	for (let i = 0; i < text.length; i++) {
		const test = line + text[i]
		if (ctx.measureText(test).width > maxW && line) {
			ctx.fillText(line, x, y)
			line = text[i]
			y += lineH
			lines += 1
			if (maxLines && lines >= maxLines) return y
		} else {
			line = test
		}
	}
	if (line) {
		ctx.fillText(line, x, y)
		y += lineH
	}
	return y
}

function roundRect(ctx: any, x: number, y: number, w: number, h: number) {
	// 设计规范要求直角，保留独立函数以便统一调整
	ctx.fillRect(x, y, w, h)
}

function draw() {
	if (!record) return
	const th = themeOf(record.theme_key)
	const ctx = uni.createCanvasContext('cpPoster')
	ctx.scale(S, S)
	const W = 690
	const H = 1228

	// 底色
	ctx.setFillStyle(th.bg)
	ctx.fillRect(0, 0, W, H)

	// 顶部装饰条
	ctx.setFillStyle(th.pri)
	ctx.fillRect(0, 0, W, 12)

	// 大号半透明 emoji 装饰
	ctx.setFillStyle(th.bg2)
	ctx.setFontSize(300)
	ctx.fillText(th.ico ? th.ico : '✨', W - 260, 300)

	// 品牌
	ctx.setFillStyle(th.sub)
	ctx.setFontSize(22)
	ctx.fillText('AI 测评 · 爱测评', 48, 96)
	ctx.setFillStyle(th.pri)
	ctx.fillRect(48, 112, 64, 6)

	// 编号 + 测试名
	ctx.setFillStyle(th.sub)
	ctx.setFontSize(24)
	ctx.fillText(`NO.${String(record.test_id).padStart(2, '0')}  ${record.short_name || ''}`, 48, 168)

	// 结果主标题
	ctx.setFillStyle(th.tx)
	ctx.setFontSize(56)
	let y = 248
	y = wrapText(ctx, record.result_name || '', 48, y, W - 96, 72, 2)

	// 摘要
	ctx.setFillStyle(th.tx)
	ctx.setFontSize(27)
	ctx.globalAlpha = 0.82
	y += 10
	y = wrapText(ctx, record.result?.summary || '', 48, y, W - 96, 42, 3)
	ctx.globalAlpha = 1

	// 卡片：分享语
	const cardY = y + 24
	ctx.setFillStyle(th.card)
	roundRect(ctx, 48, cardY, W - 96, 240)
	ctx.setFillStyle(th.pri)
	ctx.setFontSize(40)
	ctx.fillText('“', 72, cardY + 64)
	ctx.setFillStyle(th.tx)
	ctx.setFontSize(28)
	wrapText(ctx, record.result?.shareCard?.text || record.result?.detail || '', 76, cardY + 104, W - 152, 44, 4)

	// 特质标签
	let tagX = 48
	let tagY = cardY + 268
	ctx.setFontSize(21)
	;(record.result?.traits || []).forEach((tr: string) => {
		const tw = ctx.measureText(tr).width + 40
		if (tagX + tw > W - 48) {
			tagX = 48
			tagY += 58
		}
		ctx.setStrokeStyle(th.pri)
		ctx.setLineWidth(2)
		ctx.strokeRect(tagX, tagY, tw, 44)
		ctx.setFillStyle(th.pri)
		ctx.fillText(tr, tagX + 20, tagY + 30)
		tagX += tw + 16
	})

	// 双人契合分
	if (record.score_detail?.model === 'partner') {
		const py = tagY + 90
		ctx.setFillStyle(th.pri)
		ctx.setFontSize(80)
		ctx.fillText(String(record.score_detail.pairScore), 48, py + 80)
		ctx.setFillStyle(th.sub)
		ctx.setFontSize(24)
		ctx.fillText('契合分 / 100', 48, py + 118)
	}

	// 底部分隔
	const footY = H - 180
	ctx.setStrokeStyle(th.pri)
	ctx.setLineWidth(2)
	ctx.beginPath()
	ctx.moveTo(48, footY)
	ctx.lineTo(W - 48, footY)
	ctx.stroke()

	// 伪二维码
	const qrX = W - 176
	const qrY = footY + 28
	const cell = 7
	ctx.setFillStyle(th.tx)
	const h0 = hashStr('ceping_' + record.record_id)
	for (let r = 0; r < 15; r++) {
		for (let c = 0; c < 15; c++) {
			const v = (hashStr(h0 + '_' + r + '_' + c) % 100) > 52
			// 三个定位角
			const corner = (r < 4 && c < 4) || (r < 4 && c > 10) || (r > 10 && c < 4)
			if (v || corner) ctx.fillRect(qrX + c * cell, qrY + r * cell, cell - 1, cell - 1)
		}
	}
	ctx.setFillStyle(th.tx)
	ctx.setFontSize(26)
	ctx.fillText('扫码测同款', 48, footY + 72)
	ctx.setFillStyle(th.sub)
	ctx.setFontSize(21)
	ctx.fillText('娱乐性质，不代表专业心理评估或医学诊断', 48, footY + 112)
	ctx.fillText(fmtDate(record.create_time), 48, footY + 144)

	ctx.draw(false, () => {
		loading.value = false
	})
}

function save() {
	uni.canvasToTempFilePath({
		canvasId: 'cpPoster',
		destWidth: 690 * S * 2,
		destHeight: 1228 * S * 2,
		success: (res) => {
			uni.saveImageToPhotosAlbum({
				filePath: res.tempFilePath,
				success: () => uni.showToast({ title: '海报已保存', icon: 'none' }),
				fail: (err) => {
					if (/auth|deny/i.test(err.errMsg || '')) {
						uni.showModal({
							title: '需要相册权限',
							content: '请在设置中允许保存到相册',
							confirmText: '去设置',
							success: (m) => m.confirm && uni.openSetting()
						})
					}
				}
			})
		}
	})
}

onLoad(async (q: AnyObject = {}) => {
	await ensureLogin()
	try {
		const sys = uni.getSystemInfoSync()
		S = sys.windowWidth / 750
		canvasStyle.value = `width:${690 * S}px;height:${1228 * S}px;`
		const res: any = await apiRecord(Number(q.record_id))
		record = res.data
		// 等待 canvas 挂载
		setTimeout(draw, 60)
	} catch (e) {
		loading.value = false
		uni.showToast({ title: '海报数据加载失败', icon: 'none' })
	}
})
</script>

<style lang="scss" scoped>
.poster-page {
	height: 100vh;
	background: #10131c;
	display: flex;
	flex-direction: column;
	overflow: hidden;
}
.bar {
	flex-shrink: 0;
	display: flex;
	align-items: center;
	gap: 16rpx;
	padding-bottom: 16rpx;
	padding-left: 24rpx;
}
.back {
	width: 72rpx;
	height: 72rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 52rpx;
	color: #fff;
	line-height: 1;
}
.bar-title {
	font-size: 34rpx;
	font-weight: 800;
	color: #fff;
}
.loading {
	padding: 200rpx 0;
	text-align: center;
	color: rgba(255, 255, 255, .7);
	font-size: 28rpx;
}
.canvas-scroll {
	flex: 1;
	min-height: 0;
}
.poster-canvas {
	display: block;
	width: 690rpx;
	height: 1228rpx;
	margin: 20rpx auto 0;
}
.actions {
	padding: 28rpx 60rpx calc(48rpx + env(safe-area-inset-bottom));
}
.save-btn {
	background: #d4af6a;
	color: #141826;
	margin-bottom: 20rpx;
}
.cp-btn-ghost {
	color: rgba(255, 255, 255, .75);
	border-color: rgba(255, 255, 255, .25);
}
</style>
