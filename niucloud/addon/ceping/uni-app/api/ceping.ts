/**
 * 爱测评 ceping 前台接口封装（18 路由）
 */
import request from '@/utils/request'

/* ---------- 浏览类（免登录） ---------- */
export const apiHome = () => request.get('ceping/home')
export const apiCategories = () => request.get('ceping/categories')
export const apiCategory = (category: string) => request.get('ceping/category', { category })
export const apiSearch = (keyword: string) => request.get('ceping/search', { keyword })
export const apiIntro = (test_id: number) => request.get('ceping/intro', { test_id })
export const apiPaper = (test_id: number) => request.get('ceping/paper', { test_id })
export const apiPartnerJoin = (token: string) => request.get('ceping/partner/join', { token })

/* ---------- 开发联调登录 ---------- */
export const apiMockLogin = (openid: string) =>
	request.post('ceping/dev/mock-login', { openid }, { showErrorMessage: false })

/* ---------- 作答与记录 ---------- */
export const apiSubmit = (test_id: number, answers: AnyObject[], source: string) =>
	request.post('ceping/submit', { test_id, answers, source })
export const apiRecords = (test_id = 0, page = 1, limit = 10) =>
	request.get('ceping/records', { test_id, page, limit })
export const apiRecord = (record_id: number) => request.get('ceping/record', { record_id })
export const apiDeleteRecord = (record_id: number, config: AnyObject = {}) =>
	request.post('ceping/record/delete', { record_id }, config)
export const apiClearRecords = () => request.post('ceping/records/clear')

/* ---------- 站内资料 ---------- */
export const apiProfile = () => request.get('ceping/profile')
export const apiProfileUpdate = (data: { nickname?: string; avatar_key?: string }) =>
	request.post('ceping/profile/update', data)

/* ---------- 激励视频解锁 ---------- */
export const apiAdVerify = (record_id: number, ad_type = 'reward') =>
	request.post('ceping/ad/verify', { record_id, ad_type })

/* ---------- AI 深度报告 ---------- */
export const apiAiRequest = (record_id: number) => request.post('ceping/ai/request', { record_id })
export const apiAiPoll = (record_id: number) => request.get('ceping/ai/poll', { record_id })

/* ---------- 双人契合度 ---------- */
export const apiPartnerInvite = (answers: AnyObject[], source: string) =>
	request.post('ceping/partner/invite', { answers, source })
export const apiPartnerSubmit = (token: string, answers: AnyObject[]) =>
	request.post('ceping/partner/submit', { token, answers })
