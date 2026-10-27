/**
 * 爱测评 ceping 主题/分类/通用工具
 */
import envConfig from '@/utils/config'

export interface ThemeMeta {
	name: string
	ico: string
	cover: string
	bg: string
	bg2: string
	card: string
	pri: string
	pri2: string
	tx: string
	sub: string
}

export const THEMES: Record<string, ThemeMeta> = {
	'persona-archive': { name: '人格档案馆', ico: '🎖️', cover: '#232c47', bg: '#141826', bg2: '#0d1020', card: '#1e2538', pri: '#d4af6a', pri2: '#8aa0c8', tx: '#f4eede', sub: '#98a2bd' },
	'love-letter':     { name: '恋爱信笺',   ico: '💌', cover: '#ff9db6', bg: '#fff1f5', bg2: '#ffe3ec', card: '#ffffff', pri: '#ef6d8c', pri2: '#ffb2c6', tx: '#5c3845', sub: '#b08293' },
	'office-comic':    { name: '工位漫画',   ico: '💼', cover: '#ffd93d', bg: '#ffd93d', bg2: '#ffc700', card: '#fffdf2', pri: '#222222', pri2: '#ff5d2e', tx: '#222222', sub: '#777777' },
	'chat-bubble':     { name: '气泡聊天',   ico: '💬', cover: '#8fe0b4', bg: '#e8f7ee', bg2: '#d5f1e1', card: '#ffffff', pri: '#17b85f', pri2: '#4fc3f7', tx: '#173326', sub: '#6f8a7b' },
	'wave-healing':    { name: '情绪潮汐',   ico: '🌊', cover: '#9fd3ee', bg: '#e8f5fb', bg2: '#cfeaf6', card: '#ffffff', pri: '#2b86b8', pri2: '#6fc0dd', tx: '#1e3f50', sub: '#6c8fa1' },
	'zine-collage':    { name: '生活志拼贴', ico: '📖', cover: '#f0a06e', bg: '#f5ede1', bg2: '#efe2cf', card: '#fffdf7', pri: '#e04833', pri2: '#1f1f22', tx: '#29251f', sub: '#8b7e6e' },
	'gallery-aesthetic': { name: '审美展厅', ico: '🖼️', cover: '#c2b3e2', bg: '#f1eef5', bg2: '#e3dcef', card: '#ffffff', pri: '#6f5d9c', pri2: '#a99cc6', tx: '#2b2636', sub: '#7e7690' },
	'tarot-night':     { name: '星夜塔罗',   ico: '🔮', cover: '#4a3586', bg: '#171133', bg2: '#0e0a24', card: '#241a49', pri: '#e7c272', pri2: '#9a7ae0', tx: '#f4ecff', sub: '#a99bd2' }
}

/** 分类 -> 默认主题（封面兜底/金刚区配色） */
export const CATEGORY_THEME: Record<string, string> = {
	persona: 'persona-archive',
	love: 'love-letter',
	office: 'office-comic',
	social: 'chat-bubble',
	emotion: 'wave-healing',
	life: 'zine-collage',
	aesthetic: 'gallery-aesthetic',
	mystery: 'tarot-night'
}

export function themeOf(key = ''): ThemeMeta {
	return THEMES[key] || THEMES['wave-healing']
}

/** 分类 key -> 空心 iconfont 类名（字形定义见 styles/cat-iconfont.scss） */
export const CAT_ICON_CLASS: Record<string, string> = {
	persona: 'cp-cat-persona',
	love: 'cp-cat-love',
	office: 'cp-cat-office',
	social: 'cp-cat-social',
	emotion: 'cp-cat-emotion',
	life: 'cp-cat-life',
	aesthetic: 'cp-cat-aesthetic',
	mystery: 'cp-cat-mystery'
}

/** 取分类对应的 iconfont 类名，未命中返回空串 */
export function catIconClass(key = ''): string {
	return CAT_ICON_CLASS[key] || ''
}

/** 分类图标颜色：沿用分类主题主色（小程序端需返回合法字符串样式） */
export function catIconStyle(key = ''): string {
	return `color:${themeOf(CATEGORY_THEME[key] || '').pri};`
}

/** 答题交互变体 */
export const VARIANT_BY_THEME: Record<string, 'flip' | 'swipe' | 'scroll' | 'draw'> = {
	'persona-archive': 'flip',
	'love-letter': 'swipe',
	'office-comic': 'flip',
	'chat-bubble': 'flip',
	'wave-healing': 'scroll',
	'zine-collage': 'flip',
	'gallery-aesthetic': 'swipe',
	'tarot-night': 'draw'
}

export const HOT_GRADIENTS = [
	'linear-gradient(135deg,#ff8a9a,#ff6078)',
	'linear-gradient(135deg,#5b8cff,#8a6bff)',
	'linear-gradient(135deg,#ffb347,#ff7e5f)',
	'linear-gradient(135deg,#36d1a4,#1ab6b6)',
	'linear-gradient(135deg,#b06cf5,#7d5fff)',
	'linear-gradient(135deg,#ffcc33,#ff9d38)',
	'linear-gradient(135deg,#3a4a78,#20263f)',
	'linear-gradient(135deg,#6a5acd,#b16ad1)'
]

/** 相对路径封面转绝对地址（小程序不能用相对路径） */
export function imgUrl(cover = ''): string {
	if (!cover) return ''
	if (/^https?:\/\//.test(cover)) return cover
	const host = (envConfig.VITE_IMG_DOMAIN || '').replace(/\/$/, '')
		|| (envConfig.VITE_APP_BASE_URL || '').replace(/\/api\/?$/, '')
	return host + (cover.charAt(0) === '/' ? cover : '/' + cover)
}

export function greeting(): string {
	const h = new Date().getHours()
	if (h < 6) return '夜深了'
	if (h < 11) return '早上好'
	if (h < 14) return '中午好'
	if (h < 18) return '下午好'
	if (h < 23) return '晚上好'
	return '夜深了'
}

/** 兼容秒级时间戳、毫秒时间戳、"Y-m-d H:i:s" 字符串（iOS 需用 / 分隔） */
function toDate(ts: number | string): Date {
	if (typeof ts === 'string') {
		const trimmed = ts.trim()
		if (/^\d+$/.test(trimmed)) {
			const n = Number(trimmed)
			return new Date(n > 1e12 ? n : n * 1000)
		}
		return new Date(trimmed.replace(/-/g, '/'))
	}
	return new Date(ts > 1e12 ? ts : ts * 1000)
}

export function fmtTime(ts: number | string): string {
	if (!ts) return ''
	const d = toDate(ts)
	const p = (n: number) => (n < 10 ? '0' + n : '' + n)
	return `${d.getFullYear()}.${p(d.getMonth() + 1)}.${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`
}

export function fmtDate(ts: number | string): string {
	if (!ts) return ''
	const d = toDate(ts)
	const p = (n: number) => (n < 10 ? '0' + n : '' + n)
	return `${d.getFullYear()}.${p(d.getMonth() + 1)}.${p(d.getDate())}`
}

/** 状态栏 + 自定义导航尺寸（px） */
export function navMetrics() {
	let statusBarHeight = 20
	let windowWidth = 375
	let navBarHeight = 44
	// 右侧需为微信胶囊按钮避让的宽度（px）；0 表示当前环境无胶囊（如 H5）
	let menuPadRight = 0
	try {
		const info = uni.getSystemInfoSync()
		statusBarHeight = info.statusBarHeight || 20
		windowWidth = info.windowWidth || 375
		// #ifdef MP-WEIXIN
		const menu = uni.getMenuButtonBoundingClientRect()
		if (menu && menu.width > 0) {
			// 胶囊与状态栏的上下间距对称，内容行高 = 胶囊高 + 两侧间距
			const gap = Math.max(menu.top - statusBarHeight, 4)
			navBarHeight = menu.height + gap * 2
			// 右避让 = 胶囊右边距 + 胶囊宽 + 8px 安全间距
			menuPadRight = windowWidth - menu.left + 8
		}
		// #endif
	} catch (e) {}
	return {
		statusBarHeight,
		navBarHeight,
		menuPadRight,
		barPx: statusBarHeight + navBarHeight
	}
}
