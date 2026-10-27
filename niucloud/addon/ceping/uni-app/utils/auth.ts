/**
 * 爱测评 ceping 静默登录
 * 1. 优先微信 wx.login 走框架 weapp/login
 * 2. 失败/无 code 时走开发兜底 ceping/dev/mock-login（自动模拟 openid 建会员）
 */
import useMemberStore from '@/stores/member'
import { setToken } from '@/utils/common'
import request from '@/utils/request'
import { apiMockLogin } from '@/addon/ceping/api/ceping'

function wxLoginCode(): Promise<string> {
	return new Promise((resolve) => {
		// #ifdef MP-WEIXIN
		uni.login({
			provider: 'weixin',
			success: (res) => resolve(res.code || ''),
			fail: () => resolve('')
		})
		// #endif
		// #ifndef MP-WEIXIN
		resolve('')
		// #endif
	})
}

function genMockOpenid(): string {
	let openid = uni.getStorageSync('ceping_mock_openid')
	if (!openid) {
		openid = 'mock_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10)
		uni.setStorageSync('ceping_mock_openid', openid)
	}
	return openid
}

let loginPromise: Promise<string> | null = null

export function ensureLogin(force = false): Promise<string> {
	const member = useMemberStore()
	if (!force && member.token) return Promise.resolve(member.token)
	if (!force && loginPromise) return loginPromise

	loginPromise = (async () => {
		// 1) 微信真实登录
		// #ifdef MP-WEIXIN
		try {
			const code = await wxLoginCode()
			if (code) {
				const res: any = await request.post('weapp/login', { code }, { showErrorMessage: false })
				if (res?.data?.token) {
					member.token = res.data.token
					setToken(res.data.token)
					return res.data.token as string
				}
			}
		} catch (e) {
			// 未配置小程序密钥等情况下走兜底登录
		}
		// #endif

		// 2) 开发兜底：模拟 openid
		const res: any = await apiMockLogin(genMockOpenid())
		member.token = res.data.token
		setToken(res.data.token)
		return res.data.token as string
	})()

	return loginPromise
}
